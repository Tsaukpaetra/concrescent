<?php

namespace CM3_Lib\Action\LocationMap\Coord;

use CM3_Lib\models\application\locationmap;
use CM3_Lib\models\application\locationcoord;
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
    public function __construct(private Responder $responder, private locationcoord $locationcoord)
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

        $current = $this->locationcoord->GetByID($params['id'], array('map_id'));
        if ($current === false) {
            throw new HttpNotFoundException($request);
        }
        if ($current['map_id'] == $params['map_id'] && $this->locationmap->verifyMapBelongsToEvent($current['map_id'], $request->getAttribute('event_id'))) {
            throw new HttpBadRequestException($request, 'Location Map does not belong to the current event!');
        }

        $data['id'] = $params['id'];
        $data['map_id'] = $params['map_id'];

        // Invoke the Domain with inputs and retain the result
        $data = $this->locationcoord->Update($data);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
