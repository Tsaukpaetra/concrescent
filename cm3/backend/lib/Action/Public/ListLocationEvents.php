<?php

namespace CM3_Lib\Action\Public;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\models\eventinfo;
use CM3_Lib\models\application\location;
use CM3_Lib\models\application\assignment;
use CM3_Lib\models\application\submission;

use CM3_Lib\util\CurrentUserInfo;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class ListLocationEvents
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private eventinfo $eventinfo,
        private location $location,
        private assignment $assignment,
        private submission $submission,
        private CurrentUserInfo $CurrentUserInfo
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
        $qp = $request->getQueryParams();
        $override = $qp['override'] ?? null;

        //TODO: Implement caching response for if-modified-since

        $order = array('short_code' => false);


        // Invoke the Domain with inputs and retain the result
        $data =  $this->assignment->Search(new View([
            'category_id','start_time','end_time',
            new SelectColumn('real_name', JoinedTableAlias:'s'),
            new SelectColumn('fandom_name', JoinedTableAlias:'s'),
            new SelectColumn('name_on_badge', JoinedTableAlias:'s'),
            new SelectColumn('application_status', JoinedTableAlias:'s'),
            new SelectColumn('short_code', JoinedTableAlias:'l'),
        ],[
            new Join($this->location,['id'=>'location_id'],alias:'l', subQSelectColumns:[
                'id','short_code'
            ], subQSearchTerms:[
                new SearchTerm('event_id', $params['event_id']),
                new SearchTerm('active', 1),
            ]),
            new Join($this->submission,['id'=>'application_id'],alias:'s')
        ]),[
        ], $order);

        //Add the display_name and blank the not-displayed parts
        foreach ($data as &$value) {            
            switch ($value['name_on_badge']) {
                case 'Fandom Name Large, Real Name Small':
                    $value['display_name'] = trim(($value['fandom_name'] ??'') .' (' . $value['real_name'] . ')');
                    break;
                case 'Real Name Large, Fandom Name Small':
                    $value['display_name'] = trim($value['real_name'] .' (' . ($value['fandom_name']??'') . ')');
                    break;
                case 'Fandom Name Only':
                    $value['display_name'] = $value['fandom_name']??'';
                    $value['real_name'] = '';
                    break;
                case 'Real Name Only':
                    $value['display_name'] = $value['real_name'];
                    $value['fandom_name'] = '';
                break;
            }
        }


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
