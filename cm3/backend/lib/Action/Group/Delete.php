<?php

namespace CM3_Lib\Action\Group;

use CM3_Lib\models\application\group;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

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
    public function __construct(private Responder $responder, private group $group)
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
        //Confirm group belongs to event
        $current = $this->group->GetByID($params['id'], array('id'));
        if ($current === false) {
            throw new HttpNotFoundException($request);
        }
        if ($current['event_id'] != $request->getAttribute('event_id')) {
            throw new HttpBadRequestException($request, 'Group does not belong to the current event!');
        }

        $data = $this->group->Update(array('id'=>$params['id'],'active'=>0));

        // We don't delete, just deactivate
        //$data = $this->group->Delete(array('id'=>$params['id']));

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
