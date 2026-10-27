<?php

namespace CM3_Lib\Action\Application\SubmissionApplicant;

use CM3_Lib\models\application\submissionapplicant;
use CM3_Lib\models\application\submission;
use CM3_Lib\models\application\badgetype;

use CM3_Lib\util\badgeinfo;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\Modules\Notification\Mail;

use CM3_Lib\Responder\Responder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

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
        private submissionapplicant $submissionapplicant,
        private submission $submission,
        private badgetype $badgetype,
        private badgeinfo $badgeinfo,
        private Mail $Mail,
        private CurrentUserInfo $CurrentUserInfo,
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

        //Confirm permission to read this submission applicant
        $current = $this->badgeinfo->GetSpecificBadge($params['id'],$params['context_code']);

        if ($current === false) {
            throw new HttpNotFoundException($request);
        }

        //If application_id was given, confirm it's a match
        if(isset($params['application_id'])){
            if ($current['application_id'] != $params['application_id']) {
                throw new HttpBadRequestException($request, 'Invalid submission specified');
            }
        } else {
            //TODO: Verify it's in the right event and  context and that the user has permission
        }

        $this->badgeinfo->UpdateSpecificBadgeUnchecked($params['id'], $params['context_code'], $data);

        // Invoke the Domain with inputs and retain the result
        $this->submissionapplicant->Update($data);
        // Optionally send an email to this sub-badge (controlled by notify query param)
        if (isset($qp['sendupdate']) && $qp['sendupdate'] == 'true') {
            //Fetch it again
            $updated = $this->badgeinfo->GetSpecificBadge($params['id'],$params['context_code'],true);
            
            $template =  'subbadgeapplication-' .$updated['application_status'];

            // Send to notify_email if present, contact email as CC
            if (!empty($subbadge['notify_email'])) {
                $this->Mail->SendTemplate(
                    $updated['notify_email'],
                    $updated['context_code'],
                    $template,
                    $updated,
                    $updated['contact_email_address'],
                    $updated['contact_id'] ?? 0,
                    $this->CurrentUserInfo->GetContactId()
                );
            } else {
                $this->Mail->SendTemplate(
                    $updated['contact_email_address'],
                    $updated['context_code'],
                    $template,
                    $updated,
                    null,
                    $updated['contact_id'] ?? 0,
                    $this->CurrentUserInfo->GetContactId()
                );
            }


        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
