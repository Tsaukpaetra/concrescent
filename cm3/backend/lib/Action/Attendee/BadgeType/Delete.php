<?php

namespace CM3_Lib\Action\Attendee\BadgeType;

use CM3_Lib\models\attendee\badgetype;
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
    public function __construct(private Responder $responder, private badgetype $badgetype)
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
        $data =array(
            'id' => $params['id'],
            'active' => 0
        );

        if (!$this->badgetype->verifyBadgeTypeBelongsToEvent($params['id'], $request->getAttribute('event_id'))) {
            throw new HttpBadRequestException($request, 'Badge does not belong to current event');
        }

        // Invoke the Domain with inputs and retain the result
        $data = $this->badgetype->Update($data);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
