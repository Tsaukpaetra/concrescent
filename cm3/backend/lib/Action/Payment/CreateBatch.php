<?php

namespace CM3_Lib\Action\Payment;

use CM3_Lib\database\SearchTerm;

use CM3_Lib\util\badgeinfo;
use CM3_Lib\util\PaymentBuilder;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\util\PermEvent;
use CM3_Lib\modules\Notification\Mail;
use CM3_Lib\Responder\Responder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpNotFoundException;

/**
 * Action.
 */
final class CreateBatch
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     */
    public function __construct(
        private Responder $responder,
        private PaymentBuilder $PaymentBuilder,
        private CurrentUserInfo $CurrentUserInfo,
        private badgeinfo $badgeinfo,
        private Mail $Mail
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
        $qp = (array) $request->getQueryParams();
        $data = (array) $request->getParsedBody();

        $immediateApprove = ($qp['immediateApprove'] ?? "false") == 'true';
        $sendEmail = ($qp['sendEmail'] ?? "false") == 'true';

        if (
            $this->CurrentUserInfo->HasEventPerm(checkPerm: PermEvent::GlobalAdmin)
            || $this->CurrentUserInfo->HasEventPerm(checkPerm: PermEvent::Payment_CreateCancel)
        )
        {
            $this->PaymentBuilder->SetIgnoreBadgeTypeAvailability(true);
        }

        $result = array_map(function ($item) use ($immediateApprove, $sendEmail) {
            $approvalData = ['application_status' => 'PendingAcceptance'];
            //Fixup some things
            $result = [];
            $this->PaymentBuilder->createCart($item['contact_id'], $this->CurrentUserInfo->GetContactName());

            $this->PaymentBuilder->setCartItems($item['items']);
            //Immediately initiate submission
            $this->PaymentBuilder->prepPayment();
            //Good to go, are we immediately approving them?
            if ($immediateApprove)
            {
                $bicart = $this->PaymentBuilder->getCartItemByIx(0);

                $this->badgeinfo->UpdateSpecificGroupApplicationUnchecked($bicart['id'], $bicart['context_code'], $approvalData);
                if ($sendEmail)
                {

                    //TODO: Use the notification framework for this...
                    $badge = $this->badgeinfo->getASpecificGroupApplication($bicart['id'] ?? 0, $bicart['context_code'], true);
                    $to = $this->CurrentUserInfo->GetContactEmail($badge['contact_id']);
                    $template = $bicart['context_code'] . '-application-' . $badge['application_status'];
                    try
                    {
                        //Attempt to send mail
                        $result['sentEmail'] = $this->Mail->SendTemplate($to, $template, $badge, null);
                    } catch (\Exception $e)
                    {
                        //Oops, couldn't send. Oh well?
                        $result['sentEmail'] = false;
                    }
                }
            } else
            {
                if ($this->PaymentBuilder->confirmPrep())
                {
                    if ($sendEmail)
                        $result['sentEmail'] = $this->PaymentBuilder->SendStatusEmail();
                } else
                {
                    //Failed to prep (probably because it needs approval)
                    
                    if ($sendEmail)
                        $result['sentEmail'] = $this->PaymentBuilder->SendStatusEmail();
                }
            }


            //Return with some of the seed data and some mods
            return array_merge(
                $this->PaymentBuilder->getCartExpandedState(),
                $result
            );
        }, $data);



        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
