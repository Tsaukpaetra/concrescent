<?php

namespace CM3_Lib\Action\Attendee\Badge;

use CM3_Lib\models\attendee\badge;
use CM3_Lib\models\attendee\badgetype;
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
    public function __construct(
        private Responder $responder,
        private badge $badge,
        private badgetype $badgetype
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
        //Confirm badge belongs to a badgetype in this event
        $current = $this->badge->GetByID($params['id'], array('id','badge_type_id'));
        if ($current === false) {
            throw new HttpNotFoundException($request);
        }

        if (!$this->badgetype->verifyBadgeTypeBelongsToEvent($current['badge_type_id'], $request->getAttribute('event_id'))) {
            throw new HttpBadRequestException($request, 'Badge does not belong to current event');
        }

        // Invoke the Domain with inputs and retain the result
        $current['payment_status'] = 'Cancelled';
        $data = $this->badge->Update($current);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
