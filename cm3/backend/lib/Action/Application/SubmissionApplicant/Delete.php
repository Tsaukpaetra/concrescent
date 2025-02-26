<?php

namespace CM3_Lib\Action\Application\SubmissionApplicant;

use CM3_Lib\models\application\submissionapplicant;
use CM3_Lib\models\application\submission;
use CM3_Lib\models\application\badgetype;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;

/**
 * Action.
 */
final class Delete
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(private Responder $responder, private submissionapplicant $submissionapplicant, private submission $submission, private badgetype $badgetype)
    {
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

        //Confirm permission to delete this submission applicant
        $submissioninfo = $this->submission->GetByID($params['application_id'], new View(
            array(
                'applicant_count'
            ),
            array(
                new Join($this->badgetype, array('id'=>'badge_type_id', new SearchTerm('group_id', $params['group_id'])))
            )
        ));

        if ($submissioninfo === false) {
            throw new HttpBadRequestException($request, 'Invalid submission specified');
        }

        // Invoke the Domain with inputs and retain the result
        $data = $this->submissionapplicant->Delete(array('id'=>$params['id']));

        //And update the submission so it reflects the new count
        $this->submission->Update(array('id'=>$params['application_id'], 'applicant_count' => $submissioninfo['applicant_count'] - 1));


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
