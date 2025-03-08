<?php

namespace CM3_Lib\Action\LocationMap;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\application\locationmap;
use CM3_Lib\models\application\locationcoord;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        // Extract the form data from the request body
        $data = (array) $request->getParsedBody();
        $data['id'] = $params['id'];

        $result = $this->locationmap->GetByID($params['id'], array('event_id'));
        if ($result === false)
        {
            throw new HttpNotFoundException($request);
        }
        if ($result['event_id'] != $request->getAttribute('event_id'))
        {
            throw new HttpBadRequestException($request, 'Location Map does not belong to the current event!');
        }

        // Invoke the Domain with inputs and retain the result
        $result = $this->locationmap->Update($data);


        if (isset($data['coords']))
        {
            $result['CoordsResults'] = [];
            $setCoords = $data['coords'];
            $currentCoords = $this->locationcoord->Search(
                array(
                    'id'
                ),
                array(
                    new SearchTerm('map_id', $result['id'])
                )
            );
            //Process adds
            foreach (array_udiff($setCoords, $currentCoords, array($this, 'compareCoordID')) as $newCoord)
            {
                $newCoord['map_id'] = $result['id'];
                unset($newCoord['id']);
                $result['CoordsResults']['Added'][] =
                    array_merge($newCoord, $this->locationcoord->Create($newCoord));
            }
            //Process removes
            foreach (array_udiff($currentCoords, $setCoords, array($this, 'compareCoordID')) as $deletedCoord)
            {
                $deletedCoord['map_id'] = $result['id'];
                if ($this->locationcoord->Delete($deletedCoord))
                    $result['CoordsResults']['Deleted'][] = $deletedCoord['id'];

            }
            //Process modifications
            foreach (array_uintersect($setCoords, $currentCoords, array($this, 'compareCoordID')) as $modifiedCoord)
            {
                $modifiedCoord['map_id'] = $result['id'];
                $result['CoordsResults']['Updated'][] =
                    $this->locationcoord->Update($modifiedCoord)['id'];
            }
        }


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
    public function compareCoordID($left, $right)
    {
        //Spaceship!
        return $left['id'] <=> $right['id'];
    }
}
