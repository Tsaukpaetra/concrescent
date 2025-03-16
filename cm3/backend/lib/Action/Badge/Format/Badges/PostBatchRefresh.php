<?php

namespace CM3_Lib\Action\Badge\Format\Badges;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\badge\printjob;

use CM3_Lib\util\badgeinfo;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 * Action.
 */
final class PostBatchRefresh
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private badgeinfo $badgeinfo,
        private printjob $printjob
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
        $data = (array) $request->getParsedBody();

        //Quick sanity check, we should only have been passed job IDs as a bare array
        if(!is_array($data) || count($data) < 1){
            throw new HttpBadRequestException($request, 'Need an array of job IDs!');
        
        }
        
        $result = $this->printjob->Search(['id','state','result'], [
            new SearchTerm('event_id',$request->getAttribute('event_id')),
            new SearchTerm('id',$data,'IN')
        ]);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
