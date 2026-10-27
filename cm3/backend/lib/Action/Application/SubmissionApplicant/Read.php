<?php

namespace CM3_Lib\Action\Application\SubmissionApplicant;

use CM3_Lib\util\badgeinfo;

use CM3_Lib\Responder\Responder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 * Action.
 */
final class Read
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(private Responder $responder, 
        private badgeinfo $badgeinfo)
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

        // Invoke the Domain with inputs and retain the result
        $data = $this->badgeinfo->GetSpecificBadge($params['id'],$params['context_code']);

        if ($data === false) {
            throw new HttpNotFoundException($request);
        }

        //If application_id was given, confirm it's a match
        if(isset($params['application_id'])){
            if ($data['application_id'] != $params['application_id']) {
                throw new HttpBadRequestException($request, 'Invalid submission specified');
            }
        } else {
            //TODO: Verify it's in the right event and  context and that the user has permission
        }


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
