<?php

namespace CM3_Lib\Action\Application\Submission;

use CM3_Lib\models\application\submission;
use CM3_Lib\models\application\badgetype;

use CM3_Lib\util\badgeinfo;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\Modules\Notification\Mail;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Update
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private CurrentUserInfo $CurrentUserInfo,
        private badgeinfo $badgeinfo,
        private Mail $Mail,
        private \Psr\Container\ContainerInterface $container
    ) {
    }

    /**
     * Action.
     *
     * @param ServerRequestInterface $request The request
     * @param ResponseInterface $response The response
     *
     * @return ResponseInterface The response
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();
        $qp = $request->getQueryParams();
        $data['id'] = $params['id'];

        // Invoke the Domain with inputs and retain the result
        $this->badgeinfo->UpdateSpecificGroupApplicationUnchecked($params['id'], $params['context_code'], $data);
        if (isset($qp['sendupdate']) && $qp['sendupdate'] == 'true') {

            //TODO: Use the notification framework for this...
            $badge = $this->badgeinfo->getASpecificGroupApplication($data['id'] ?? 0, $params['context_code'], true);
            $to = $this->CurrentUserInfo->GetContactEmail($badge['contact_id']);
            $template =  'application-' .$badge['application_status'];
            try {
                //Attempt to send mail
                $sendResult =  $this->Mail->SendTemplate($to, $params['context_code'], $template, $badge,
                    null,$badge['contact_id'], $this->CurrentUserInfo->GetContactId());
                $data['sentUpdate'] = $sendResult['sent'];
                $data['sentResult'] = $sendResult['result'];

                // Send application-status emails to each sub-badge
                if (isset($data['subbadges']) && is_array($data['subbadges'])) {
                    $template =  'subbadgeapplication-' .$badge['application_status'];
                    foreach ($data['subbadges'] as &$subbadge) {
                        // Build context_code and payment_id for template rendering
                        $subbadge = array_merge($subbadge,array_intersect_key($badge,array_flip([
                            'context_code',
                            'application_status',
                            'badge_type_id',
                            'badge_type_name',
                            'badge_type_payable_onsite',
                            'badge_type_payment_deferred',
                            'contact_id'                            
                        ])));
                        $subbadge['payment_id'] = $badge['payment_id'] ?? null;
                        $subbadge['payment_status'] = $badge['payment_status'];
                        $subbadge['contact_id'] = $to;
                        $subbadge['context_code'] = $params['context_code'];
                        $subbadge = $this->badgeinfo->addComputedColumns($subbadge,true,false);
                        // Send to notify_email if present, contact email as CC
                        if (!empty($subbadge['notify_email'])) {
                            $this->Mail->SendTemplate(
                                $subbadge['notify_email'],
                                $params['context_code'],
                                $template,
                                $subbadge,
                                $to,
                                $badge['contact_id'],
                                $this->CurrentUserInfo->GetContactId()
                            );
                        } else {
                            $this->Mail->SendTemplate(
                                $to,
                                $params['context_code'],
                                $template,
                                $subbadge,
                                null,
                                $badge['contact_id'],
                                $this->CurrentUserInfo->GetContactId()
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                //Oops, couldn't send. Oh well?
                $data['sentUpdate'] = false;
                $data['sentResult'] = $e->getMessage();
            }
        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
