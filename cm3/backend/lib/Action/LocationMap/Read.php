<?php

namespace CM3_Lib\Action\LocationMap;

use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\models\application\locationcoord;
use CM3_Lib\models\application\locationmap;
use CM3_Lib\models\filestore;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
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
     private locationmap $locationmap,
     private filestore $filestore,
     private locationcoord $locationcoord,
     )
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
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        $result = $this->locationmap->GetByID($params['id'], new View([
            'id','name','active','bgImageID','description','notes','event_id',
            new SelectColumn('name',Alias:'bgFileName', JoinedTableAlias:'fs')
        
        ],[
            new Join($this->filestore, ['id'=>'bgImageID'],alias:'fs')
        ]));
        if ($result === false) {
            throw new HttpNotFoundException($request);
        }
        if ($result['event_id'] != $request->getAttribute('event_id')) {
            throw new HttpBadRequestException($request, 'Location Map does not belong to the current event!');
        }

        //Add in the coordinates for this map
        $result['coords'] = $this->locationcoord->Search([
            'id','map_id','location_id','coords','notes'
        ],[
            new SearchTerm('map_id',$params['id'] )
        ]);

        // Build the HTTP response
        return $this->responder
                     ->withJson($response, $result);
    }
}
