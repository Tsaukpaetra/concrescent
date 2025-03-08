<?php

namespace CM3_Lib\Action\Location;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\models\application\location;
use CM3_Lib\models\application\assignment;
use CM3_Lib\models\application\submission;
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
     private location $location,
     private assignment $assignment,
     private submission $submission,
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

        $result = $this->location->GetByID($params['id'], '*');
        if ($result === false) {
            throw new HttpNotFoundException($request);
        }
        if ($result['event_id'] != $request->getAttribute('event_id')) {
            throw new HttpBadRequestException($request, 'Location does not belong to the current event!');
        }
        //Add in the assignments
        $result['Assignments'] = $this->assignment->Search(new View([
            'id','application_id','location_id','category_id','start_time','end_time',
            new SelectColumn('real_name', JoinedTableAlias:'s'),
            new SelectColumn('fandom_name', JoinedTableAlias:'s'),
            new SelectColumn('name_on_badge', JoinedTableAlias:'s'),
            new SelectColumn('application_status', JoinedTableAlias:'s'),
        ],[
            new Join($this->submission,['id'=>'application_id'],alias:'s')
        ]),[
            new SearchTerm('location_id',$result['id'])
        ]);

        //Add the display_name
        foreach ($result['Assignments'] as &$value) {            
            switch ($value['name_on_badge']) {
                case 'Fandom Name Large, Real Name Small':
                    $value['display_name'] = trim(($value['fandom_name'] ??'') .' (' . $value['real_name'] . ')');
                    break;
                case 'Real Name Large, Fandom Name Small':
                    $value['display_name'] = trim($value['real_name'] .' (' . ($value['fandom_name']??'') . ')');
                    break;
                case 'Fandom Name Only':
                    $value['display_name'] = $value['fandom_name']??'';
                    break;
                case 'Real Name Only':
                    $value['display_name'] = $value['real_name'];
                    break;
            }
        }


        // Build the HTTP response
        return $this->responder
             ->withJson($response, $result);
    }
}
