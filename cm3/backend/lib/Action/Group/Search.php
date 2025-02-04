<?php

namespace CM3_Lib\Action\Group;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\application\group;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Search
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(private Responder $responder, private group $group, private badgeinfo $badgeinfo)
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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();
        $qp = $request->getQueryParams();
        $find = $qp['find'] ?? '';

        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, defaultSortColumn:'display_order', defaultSortDesc:false);
        $totalRows = 0;

        $whereParts = array(
            new SearchTerm('event_id', $request->getAttribute('event_id')),
          empty($find) ? null : new SearchTerm('', '',subSearch:[
            new SearchTerm('name','%'.$find.'%','LIKE'),
            new SearchTerm('description','%'.$find.'%','LIKE','OR'),
            new SearchTerm('notes','%'.$find.'%','LIKE','OR'),
          ])
        );

        
        $columns = [];
        $includeDefault = false;
        if($request->getQueryParams()['includeDefault']??'false' == 'true'){
            $columns = [
                'id','context_code','name','menu_icon','active','application_name1','application_name2'
            ];
            $includeDefault = true;
        }

        // Invoke the Domain with inputs and retain the result
        $data = $this->group->Search($columns, $whereParts, $pg['order'], $pg['limit'], $pg['offset'], $totalRows);

        if($includeDefault){
            //Append the hard-coded contexts
            array_unshift($data, array('id'=>-1,'context_code'=>'A', 'name'=>'Attendee',
                'menu_icon' => 'badge-account-horizontal', 'active' => 1));
            $data[] = array('id'=>0,'context_code'=>'S', 'name'=>'Staff',
                'menu_icon' => 'account-hard-hat', 'active' => 1);
    

        }
        $response = $response->withHeader('X-Total-Rows', (string)$totalRows);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
