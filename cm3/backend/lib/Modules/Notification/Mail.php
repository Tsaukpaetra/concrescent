<?php

namespace CM3_Lib\Modules\Notification;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\models\eventinfo;
use CM3_Lib\models\mail\log;
use CM3_Lib\models\mail\template;
use CM3_Lib\models\mail\templatemap;

use CM3_Lib\util\CurrentUserInfo;

use PHPMailer\PHPMailer\PHPMailer;
use League\CommonMark\MarkdownConverter;

class Mail
{
    public function __construct(
        private CurrentUserInfo $CurrentUserInfo,
        private log $log,
        private template $template,
        private templatemap $templatemap,
        private eventinfo $eventinfo,
        private PHPMailer $PHPMailer,
        private MarkdownConverter $MarkdownConverter
    ) {
        $PHPMailer->CharSet = PHPMailer::CHARSET_UTF8;
        //Default connection timeout to something quick
        $PHPMailer->Timeout = 20;
    }

    public function getMailerErrorInfo()
    {
        return $this->PHPMailer->ErrorInfo;
    }

    public function SendTemplate(string $to, string|array|int $template, array $entity, ?string $cc = null)
    {
        //Start prepping the message
        $loadedtemplate = $this->GetTemplate($template);
        $this->PrepareMessage($loadedtemplate, $entity);

        //Set main recipient(s)
        $this->addAddress('Address', $to);

        //Set CCs
        if (!empty($cc)) {
            $this->addAddress('CC', $cc);
        }

        //Remove raw stuff
        unset($entity['uuid_raw']);
        unset($entity['qr_data_uri']);

        $meta = array(
            'template' => $template
        );

        //Send it
        if ($this->PHPMailer->send()) {
            //Log the success
            $this->log->Create(array(
                'template_id' => $loadedtemplate['id'],
                'success' => 1,
                'meta' => json_encode($meta),
                'data' => json_encode($entity),
                'result' => 'sent'
            ));
            return true;
        } else {
            //Log the failure
            $this->log->Create(array(
                'template_id' => $loadedtemplate['id'],
                'success' => 0,
                'meta' => json_encode($meta),
                'data' => json_encode($entity),
                'result' => 'Failed:' . $this->PHPMailer->ErrorInfo
            ));
            return false;
        }
    }

    public function RenderTemplate(string|array|int $template, array $entity)
    {
        //Start prepping the message
        $template = $this->GetTemplate($template);
        $this->PrepareMessage($this->GetTemplate($template), $entity);

        //Prepare to send it (but don't actually do so)
        $this->PHPMailer->preSend();
        //Get what we would have sent
        return $this->GetLastMessage();
    }

    public function GetTemplate(string|array|int $template)
    {
        if (is_string($template) || is_int($template)) {
            $templatedata = $this->template->Search(
                array(
                    'id',
                    'name',
                    'reply_to',
                    'from',
                    'cc',
                    'bcc',
                    'subject',
                    'format',
                    'body',
                    'attachments'
                ),
                array(
                    $this->CurrentUserInfo->EventIdSearchTerm(),
                    new SearchTerm('active', 1),
                    new SearchTerm('', '', subSearch: array(
                        new SearchTerm('name', $template),
                        new SearchTerm('id', $template, 'OR')
                    ))

                ),
                limit: 1
            );
            if (count($templatedata) > 0) {
                $template = $templatedata[0];
            } else {
                //Search the on-disk templates
                $templatefile = __DIR__ . '/../../../config/templates/Mail/' . $template . '.json';
                if (file_exists($templatefile)) {
                    //Load it up!
                    $template = json_decode(file_get_contents($templatefile), true, flags: JSON_INVALID_UTF8_SUBSTITUTE);

                    if (json_last_error() != JSON_ERROR_NONE) {
                        switch (json_last_error()) {
                            case JSON_ERROR_DEPTH:
                                $msg = 'Maximum stack depth exceeded';
                                break;
                            case JSON_ERROR_STATE_MISMATCH:
                                $msg = 'Underflow or the modes mismatch';
                                break;
                            case JSON_ERROR_CTRL_CHAR:
                                $msg = 'Unexpected control character found';
                                break;
                            case JSON_ERROR_UTF8:
                                $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                                break;
                            default:
                                $msg = 'Unknown error';
                        }

                        throw new \Exception('Unable to load mail template because JSON decoding failed: ' . $msg);
                    }
                    //Make sure it knows its name
                    $template['name'] = basename($templatefile, '.json');
                    $template['id'] = 0;
                }
                if (is_null($template) || is_string($template)) {
                    throw new \Exception('Unable to load mail template "' . $template . '"');
                }
                //Check template is minimally valid...
                $missingKeys = array_flip(array_diff_key(array_flip(['subject', 'format', 'body']), $template));
                if (count($missingKeys)) {
                    throw new \Exception('Missing keys from on-disk template "' . basename($templatefile) . '": ' . implode(',', $missingKeys));
                }
            }
        }
        return $template;
    }


    public function GetTemplateByBadge(string $reason, array $entity)
    {
        $template = null;
        $templatedata = $this->template->Search(
            new View(
                array(
                    'id',
                    'name',
                    'reply_to',
                    'from',
                    'cc',
                    'bcc',
                    'subject',
                    'format',
                    'body',
                    'attachments'
                ),
                array(
                    new Join(
                        $this->templatemap,
                        array(
                            'id' => 'template_id',
                            new SearchTerm('context_code', $entity['context_code'] ?? 'A'),
                            new SearchTerm('badge_type_id', $entity['badge_type_id'] ?? 0),
                            new SearchTerm('reason', $reason),
                        )
                    )
                )
            ),
            array(
                $this->CurrentUserInfo->EventIdSearchTerm(),
                new SearchTerm('active', 1),
                new SearchTerm('name', $templatename)
            ),
            limit: 1
        );
        if (count($templatedata) > 0) {
            $template = $templatedata[0];
        } else {
            //Search the on-disk templates
            $templatefile = __DIR__ . '/../../../config/templates/Mail/' . ($entity['context_code'] ?? 'A') . '-' . $reason . '.json';
            if (file_exists($templatefile)) {
                //Load it up!
                $template = json_decode(file_get_contents($templatefile), true);
                //Make sure it knows its name
                $template['name'] = basename($templatefile, '.json');
                $template['id'] = 0;
            }
            if (is_null($template) || is_string($template)) {
                throw new \Exception('Unable to load mail template "' . $templatename . '"');
            }
        }
    }

    public function GetLastMessage(bool $includeAttachements = false)
    {
        $result = array(
            'to' => $this->PHPMailer->getToAddresses(),
            'cc' => $this->PHPMailer->getCcAddresses(),
            'bcc' => $this->PHPMailer->getBccAddresses(),
            'reply_to' => $this->PHPMailer->getReplyToAddresses(),
            'subject' => $this->PHPMailer->Subject,
            'body' => $this->PHPMailer->Body,

        );
        if ($includeAttachements) {
            $result['attachments'] = $this->PHPMailer->getAttachments();
            //Base64 encode the attachements
            foreach ($result['attachments'] as $key => &$value) {
                $value[0] = base64_encode($value[0]);
            }
        }

        return $result;
    }
    private function PrepareMessage(array $template, array $entity)
    {
        $this->PHPMailer->clearAllRecipients();
        $this->PHPMailer->clearReplyTos();
        $this->PHPMailer->Body = '';
        $this->PHPMailer->AltBody = '';
        $this->PHPMailer->clearAttachments();
        $this->PHPMailer->clearCustomHeaders();

        //Add some headers
        $this->PHPMailer->XMailer = 'CONcrescent/3.0 PHP/' . phpversion() . ' PHPMailer' . $this->PHPMailer::VERSION;
        //Generate the message ID
        $msgId = '<ei' . $this->CurrentUserInfo->GetEventId() . '-';
        if (isset($template['name'])) {
            $msgId .= ('tn' . $template['name'] . '-');
        }
        $msgId .= 'ci' . $this->CurrentUserInfo->GetContactId() . '-';
        if (isset($entity['badge_type_id'])) {
            $msgId .= 'bt' . $entity['badge_type_id'] . '-';
        }
        if (isset($entity['context_code'])) {
            $msgId .= 'cx' . $entity['context_code'] . '-';
        }
        if (isset($entity['id'])) {
            $msgId .= 'id' . $entity['id'] . '-';
        }
        $msgId .= md5(serialize($entity));
        //TODO: Is there a better way to do this?
        $msgId .= '@' . strtolower($_SERVER['SERVER_NAME']) . '>';
        //$this->PHPMailer->addCustomHeader('Message-ID', $msgId);
        $this->PHPMailer->MessageID = $msgId;

        //Add addresses
        if (!empty($template['from'])) {
            $address = PHPMailer::parseAddresses($template['from'])[0];
            $this->PHPMailer->setFrom($address['address'], $address['name'], false);
        }
        if (!empty($template['reply_to'])) {
            $this->addAddress('ReplyTo', $template['reply_to']);
        }
        if (!empty($template['cc'])) {
            $this->addAddress('CC', $template['cc']);
        }
        if (!empty($template['bcc'])) {
            $this->addAddress('BCC', $template['bcc']);
        }

        //Merge in some global merge fields
        $mergeFields = $this->wrap_entity($entity);

        //Do the marge-able fields next
        $this->PHPMailer->Subject = $this->compileTemplate($template['subject'], $mergeFields);
        $body = $this->compileTemplate($template['body'], $mergeFields);

        switch ($template['format']) {
            case 'Text Only':
                $this->PHPMailer->Body = $body;
                break;
            case 'Markdown':
                //parse into HTML
                $this->PHPMailer->msgHTML((string) $this->MarkdownConverter->convert($body));
                break;
            case 'Full HTML':
                $this->PHPMailer->msgHTML($body);
                break;
        }
    }
    private function addAddress(string $type, $addressLine)
    {
        foreach (PHPMailer::parseAddresses($addressLine) as $address) {
            $this->PHPMailer->{'add' . $type}($address['address'], $address['name']);
        }
    }
    private function wrap_entity($entity)
    {
        $result = array_merge(
            $entity,
            array(
                'event' => $this->eventinfo->GetByID($this->CurrentUserInfo->GetEventId(), array())
            )
        );

        //Clear out raw stuff
        return array_diff_key($result, array_flip(array(
            'uuid_raw'
        )));
    }


    function isObject($o)
    {
        return is_array($o) || is_object($o);
    }

    function getValueByPath($input, $s)
    {
        if (!$s)
            return null; // Using PHP null as equivalent to JS undefined here

        $fallbackString = null;

        // Check if a fallback pipeline exists: path || 'fallback'
        if (strpos($s, '||') !== false) {
            $segments = explode('||', $s);
            $s = trim($segments[0]); // Extract the true path (e.g., "profile.firstName")

            // Clean up the fallback string by stripping spaces and wrapping quotes (' or ")
            $fallbackString = trim($segments[1]);
            $fallbackString = preg_replace('/^[\'"]|[\'"]$/', '', $fallbackString);
        }

        $s = preg_replace('/\[(\w+)\]/', '.$1', $s);
        $s = ltrim($s, '.');
        $a = explode('.', $s);
        $current = $input;

        foreach ($a as $k) {
            if (\is_object($current) && isset($current->$k)) {
                $current = $current->$k;
            } elseif (\is_array($current) && \array_key_exists($k, $current)) {
                $current = $current[$k];
            } else {
                // If the property path fails but a fallback string exists, return the fallback
                return $fallbackString !== null ? $fallbackString : null;
            }
        }

        // If the path evaluates to a falsy/blank value, honor the fallback string if it exists
        if ($current === null || $current === '') {
            return $fallbackString !== null ? $fallbackString : $current;
        }

        return $current;
    }

    function isTruthy($value)
    {
        if (!$value)
            return false;
        if (\is_array($value) && \count($value) === 0)
            return false;
        return true;
    }

    function compileTemplate($template, $templateData)
    {
        // Tokenize the string by splitting on all [[ tags ]]
        $tagRegex = '/(\[\[[\s\S]*?\]\])/';
        $parts = preg_split($tagRegex, $template, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Using an object instance variable state to track tracking mechanics uniformly in PHP 7.3
        $state = new \stdClass();
        $state->index = 0;
        $state->parts = $parts;

        // Global helper closure to skip sections until reaching matching boundaries
        $swallowBlock = function () use ($state) {
            $depth = 1;
            while ($state->index < \count($state->parts) && $depth > 0) {
                $p = $state->parts[$state->index++];
                if (strpos($p, '[[') === 0) {
                    $tag = trim(substr($p, 2, -2));
                    if (strpos($tag, 'for ') === 0 || strpos($tag, 'if ') === 0)
                        $depth++;
                    if ($tag === 'end')
                        $depth--;
                }
            }
        };

        $parseBlock = function ($context) use (&$parseBlock, $state, $swallowBlock) {
            $result = '';

            while ($state->index < \count($state->parts)) {
                $part = $state->parts[$state->index++];

                // Handle normal markdown and text syntax fragments
                if (strpos($part, '[[') !== 0) {
                    $result .= $part;
                    continue;
                }

                $innerContent = trim(substr($part, 2, -2));

                // Handle structural closing loops
                if ($innerContent === 'end') {
                    return $result;
                }

                // If we see a raw [[else]], it means the main branch finished executing successfully.
                if ($innerContent === 'else') {
                    $swallowBlock();
                    return $result;
                }

                // Handle structural conditions: [[if path]]
                if (strpos($innerContent, 'if ') === 0) {
                    $path = trim(substr($innerContent, 3));
                    $value = $this->getValueByPath($context, $path);

                    if ($value === null) {
                        //trigger_error("[Template Debug] Property not found along condition path: \"{$path}\"", E_USER_WARNING);
                        $result .= "[[?{$path}]]";
                        $swallowBlock();
                        continue;
                    }

                    if ($this->isTruthy($value)) {
                        $result .= $parseBlock($context);
                    } else {
                        // Search loop sequentially for a structural fallback branch
                        $depth = 1;
                        $foundElse = false;
                        while ($state->index < \count($state->parts) && $depth > 0) {
                            $p = $state->parts[$state->index++];
                            if (strpos($p, '[[') === 0) {
                                $tag = trim(substr($p, 2, -2));
                                if (strpos($tag, 'for ') === 0 || strpos($tag, 'if ') === 0)
                                    $depth++;
                                if ($tag === 'end')
                                    $depth--;
                                if ($tag === 'else' && $depth === 1) {
                                    $foundElse = true;
                                    break;
                                }
                            }
                        }
                        if ($foundElse) {
                            $result .= $parseBlock($context); // Run the else branch block
                        }
                    }
                    continue;
                }

                // Handle iteration loop syntax block: [[for path]]
                if (strpos($innerContent, 'for ') === 0) {
                    $path = trim(substr($innerContent, 4));
                    $list = $this->getValueByPath($context, $path);

                    if ($list === null) {
                        //trigger_error("[Template Debug] Loop array target not found at path: \"{$path}\"", E_USER_WARNING);
                        $result .= "[[?{$path}]]";
                        $swallowBlock();
                        continue;
                    }

                    $loopBodyStartIndex = $state->index;

                    if (is_array($list) && count($list) > 0) {
                        foreach ($list as $item) {
                            $state->index = $loopBodyStartIndex; // Snap text reading pointer back to start of loop content
                            $scopedContext = (is_array($item) || is_object($item)) ? (array) $item : ['value' => $item];
                            $result .= $parseBlock($scopedContext);
                        }

                        // Once items execute, skip over the accompanying trailing [[else]] block container
                        $state->index = $loopBodyStartIndex;
                        $swallowBlock();
                    } else {
                        // Empty or falsy lists navigate straight down to the [[else]] branch
                        $depth = 1;
                        $foundElse = false;
                        while ($state->index < count($state->parts) && $depth > 0) {
                            $p = $state->parts[$state->index++];
                            if (strpos($p, '[[') === 0) {
                                $tag = trim(substr($p, 2, -2));
                                if (strpos($tag, 'for ') === 0 || strpos($tag, 'if ') === 0)
                                    $depth++;
                                if ($tag === 'end')
                                    $depth--;
                                if ($tag === 'else' && $depth === 1) {
                                    $foundElse = true;
                                    break;
                                }
                            }
                        }
                        if ($foundElse) {
                            $result .= $parseBlock($context);
                        }
                    }
                    continue;
                }

                // Handle standalone string output fields: [[variable]]
                $value = $this->getValueByPath($context, $innerContent);
                if ($value === null) {
                    //trigger_error("[Template Debug] Variable target not found: \"[[{$innerContent}]]\"", E_USER_WARNING);
                    $result .= "[[?{$innerContent}]]";
                } else {
                    $result .= (string) $value;
                }
            }

            return $result;
        };

        return $parseBlock($templateData);
    }
}
