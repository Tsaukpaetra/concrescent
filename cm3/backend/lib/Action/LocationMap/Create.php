<?php

namespace CM3_Lib\Action\LocationMap;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\application\locationmap;
use CM3_Lib\models\application\locationcoord;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Create
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private locationmap $locationmap,
        private locationcoord $locationcoord,
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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Extract the form data from the request body
        $data = (array) $request->getParsedBody();

        //Ensure we're only attempting to create a location for the current Event
        $data['event_id'] = $request->getAttribute('event_id');

        // Invoke the Domain with inputs and retain the result
        $data = $this->locationmap->Create($data);


        if (isset($data['coords']))
        {
            $result['CoordsResults'] = [];
            $setCoords = $data['coords'];
            
            //Process adds
            foreach ($setCoords as $newCoord)
            {
                $newCoord['map_id'] = $result['id'];
                unset($newCoord['id']);
                $result['CoordsResults']['Added'][] =
                    array_merge($newCoord, $this->locationcoord->Create($newCoord));
            }
        }


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
