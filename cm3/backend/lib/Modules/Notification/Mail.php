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
    
    //Define the fields we must have:
    private $defaultListSchema = [
        'active' => 1,
        'name' => '',
        'from' => '',
        'cc' => '',
        'subject' => ''
    ];
    private $defaultSchema = [
        'event_id' => 0,
        'context_code' => '',
        'name' => '',
        'active' => 1,
        'reply_to' => '',
        'from' => '',
        'cc' => '',
        'subject' => '',
        'format' => 'Markdown',
        'body' => '',
        'attachments' => ''
    ];
    private $schemaSizes = [
        'name' => 255,
        'from' => 300,
        'cc' => 2000,
        'subject' => 1000,
        'body' => 65535,
        'attachments' => 300
    ];

    public function getMailerErrorInfo()
    {
        return $this->PHPMailer->ErrorInfo;
    }

    public function SendTemplate(string $to, string $context, string $template, array $entity, ?string $cc = null, int $contact_id = 0, int $sender_id = 0, bool $overrideActive = false, bool $renderMissingVariables = false)
    {
        //Start prepping the message
        $loadedtemplate = $this->GetTemplate($context, $template);
        //Short circuit if the template is not active
        if(!$loadedtemplate['active'] && !$overrideActive){
            return ['sent'=>false,'result'=>'Template [' . $context .'-'. $template . '] not active'];
        }
        $this->PrepareMessage($loadedtemplate, $entity, $renderMissingVariables);

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
            'dataMD5' => md5(serialize($entity)),
            'To' => $to,
            'CC' => $cc
        );

        //Send it
        $success = $this->PHPMailer->send();
        //Log the result
        $this->log->Create(array(
            'event_id' => $this->CurrentUserInfo->GetEventId(),
            'context_code' => $context,
            'template' => $template,
            'success' => $success,
            'contact_id' => $contact_id,
            'sender_id' => $sender_id,
            'meta' => json_encode($meta),
            'data' => json_encode($entity),
            'result' => $success ? 'Sent' : 'Failed:' . $this->PHPMailer->ErrorInfo
        ));
            return ['sent'=> $success,'result'=> $success ? 'Sent' : 'Failed:' . $this->PHPMailer->ErrorInfo];
    
    }

    public function RenderTemplate(string $context, string $template, array $entity, bool $includeAttachements = false)
    {
        //Start prepping the message
        $this->PrepareMessage($this->GetTemplate($context, $template, true), $entity, true);

        //Prepare to send it (but don't actually do so)
        $this->PHPMailer->preSend();
        //Get what we would have sent
        return $this->GetLastMessage($includeAttachements);
    }

    public function CheckTemplateActive(string $context, string $name, bool $throwOnMissing = false){
         $templatedata = $this->template->Search(
                array(
                    'active',
                ),
                array(
                    $this->CurrentUserInfo->EventIdSearchTerm(),
                    new SearchTerm('context_code', $context),
                    new SearchTerm('name', $name)
                ),
                limit: 1 //Normally should only return one, but just be safe
            );
            if (count($templatedata) > 0) {
                $template = $templatedata[0];
            } else {
                //Search the on-disk templates
                $templatefile = __DIR__ . '/../../../config/templates/Mail/' . ($context??'') . '-' . $name . '.json';
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
                    // Merge with schema: fills missing fields with defaults, injects 'id' => null, removes the other fields
                    // and forces the filename into the 'name' field (since that's how it will be found on disk when not in the database)
                    if(is_array($template)){
                        $template['name'] = $name;
                        $template = array_merge($this->defaultSchema, array_intersect_key($template, $this->defaultSchema));
                    }
                }
            }
            if (is_null($template) || is_string($template)) {
                if($throwOnMissing) {
                    throw new \Exception('Unable to load mail template "' . $template . '"');
                } else {
                    //Just return a blank template that's inactive
                    $template = array_merge($this->defaultSchema, [
                        'active' => 0
                    ]);
                }
            }
        return $template['active'];
    }

    public function GetTemplate(string $context, string $name, bool $throwOnMissing = false)
    {
            $templatedata = $this->template->Search(
                array(
                    'name',
                    'active',
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
                    new SearchTerm('context_code', $context),
                    new SearchTerm('name', $name)
                ),
                limit: 1 //Normally should only return one, but just be safe
            );
            if (count($templatedata) > 0) {
                $template = $templatedata[0];
            } else {
                //Search the on-disk templates
                $templatefile = __DIR__ . '/../../../config/templates/Mail/' . ($context??'') . '-' . $name . '.json';
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
                    // Merge with schema: fills missing fields with defaults, injects 'id' => null, removes the other fields
                    // and forces the filename into the 'name' field (since that's how it will be found on disk when not in the database)
                    if(is_array($template)){
                        $template['name'] = $name;
                        $template = array_merge($this->defaultSchema, array_intersect_key($template, $this->defaultSchema));
                    }
                }
            }
            if (is_null($template) || is_string($template)) {
                if($throwOnMissing) {
                    throw new \Exception('Unable to load mail template "' . $template . '"');
                } else {
                    //Just return a blank template that's inactive
                    $template = array_merge($this->defaultSchema, [
                        'event_id' => $this->CurrentUserInfo->GetEventId(),
                        'context_code' => $context,
                        'name' => $name,
                        'active' => 0
                    ]);
                }
            }
        return $template;
    }

    public function SetTemplate(string $context, string $name, array $templateData)
    {
        //Ensure some things aren't sneaky
        $templateData = array_replace($this->defaultSchema, $templateData,
        array_map(
            function($value, $key) {
                if(array_key_exists($key, $this->schemaSizes))
                    return substr((string)$value, 0, $this->schemaSizes[$key]);
                else
                    switch ($key) {
                        case 'active':
                            return $value ? 1 :0;
                        case 'format':
                            //Default to Markdown if not right
                            return in_array($value, ['Text Only','Markdown','Full HTML']) ? $value : 'Markdown';
                        default:
                            //Eh, whatever. If it's something else it will be filtered out anyways
                            return $value;
                    }
            },
            $templateData,
            array_keys($templateData) // Passes keys to the map callback
        ),
        [
            //Force these values, no sideloading through the post data!
            'event_id' => $this->CurrentUserInfo->GetEventId(),
            'context_code' => $context,
            'name' => $name
        ]);
        $action = $this->template->Exists($templateData) ? 'Update' :'Create';
        //Execute the action
        return call_user_func_array(array($this->template,$action), [$templateData ]);

    }
    public function ResetTemplate(string $context, string $name)
    {
        $templateData = [
            'event_id' => $this->CurrentUserInfo->GetEventId(),
            'context_code' => $context,
            'name' => $name
        ];
        if($this->template->Exists($templateData)){
            return $this->template->Delete($templateData);
        }
        return true;
    }
    
    public function SetTemplateActive(string $context, string $name, bool $active)
    {
        $templateData = [
            'event_id' => $this->CurrentUserInfo->GetEventId(),
            'context_code' => $context,
            'name' => $name,
            'active' => $active ? 1 : 0
        ];
        $action = $this->template->Exists($templateData) ? 'Update' :'Create';
        if($action == 'Create') {
            //Oh, it doesn't exist. Slice in a default form
            $templateData = array_replace($this->GetTemplate($context, $name),$templateData);
        }
        //Execute the action
        return call_user_func_array(array($this->template,$action), [$templateData ]);

    }

    /// Get context-specific templates
    public function ListTemplates(string $context){
        
        $results = [];
        //First grab on-disk templates, file name only
        $templateglob = __DIR__ . '/../../../config/templates/Mail/' . ($context??'') . '-*.json';
        foreach (\glob($templateglob) ?: [] as $file) {
            
            // Extract the name without path or .json extension to use as the key
            $nameKey =  substr(pathinfo($file, PATHINFO_FILENAME), strlen($context)+1);
            
            $templateData = json_decode(file_get_contents($file), true, flags: JSON_INVALID_UTF8_SUBSTITUTE);
            // Merge with schema: fills missing fields with defaults, injects 'id' => null, removes the other fields
            // and forces the filename into the 'name' field (since that's how it will be found on disk when not in the database)
            $normalizedTemplate = array_merge($this->defaultListSchema, array_intersect_key($templateData, $this->defaultListSchema));
            $normalizedTemplate['name'] = $nameKey;
            $results[$nameKey] = $normalizedTemplate;
        }


        //Fetch the db templates and merge replace the disk templates
        foreach($this->template->Search(
                array(
                    'active',
                    'name',
                    'from',
                    'cc',
                    'subject',
                ),
                array(
                    $this->CurrentUserInfo->EventIdSearchTerm(),
                    new SearchTerm('context_code', $context),

                )
        ) as $template) {
            $results[$template['name']] = $template;
        }

        return array_values($results);
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
    private function PrepareMessage(array $template, array $entity, bool $renderMissingVariables = false)
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
        $this->PHPMailer->Subject = $this->compileTemplate($template['subject'], $mergeFields, $renderMissingVariables);
        $body = $this->compileTemplate($template['body'], $mergeFields, $renderMissingVariables);

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

    function compileTemplate($template, $templateData, bool $renderMissingVariables = false)
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

        $parseBlock = function ($context) use (&$parseBlock, $state, $swallowBlock, $renderMissingVariables) {
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
                    if($renderMissingVariables) $result .= "[[?{$innerContent}]]";
                } else {
                    $result .= (string) $value;
                }
            }

            return $result;
        };

        return $parseBlock($templateData);
    }
}
