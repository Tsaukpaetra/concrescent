<?php

namespace CM3_Lib\Action\Public;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\models\eventinfo;
use CM3_Lib\models\application\location;
use CM3_Lib\models\application\assignment;

use CM3_Lib\util\CurrentUserInfo;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class ListLocations
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
        $excludeTimed = ($qp['excludeTimed'] ?? 'false') == 'true';
        $excludeUntimed = ($qp['excludeUntimed'] ?? 'false') == 'true';
        $transformToConventionMaster = ($qp['transformToConventionMaster'] ?? 'false') == 'true';

        //TODO: Implement caching response for if-modified-since
        $terms = [
            $excludeTimed ? new SearchTerm('start_time',null,'IS') : null,
            $excludeUntimed ? new SearchTerm('start_time',null,'IS NOT') : null,
        ];

        //Get modified date
        $mod = $this->location->Search(new View([
            'date_modified'
        ],[
            new Join($this->assignment,['location_id'=>'id'],alias:'l', subQSelectColumns:[
                new SelectColumn('location_id',true), new SelectColumn('id', EncapsulationFunction:'min(?)')
            ], subQSearchTerms:$terms)
        ]),[
            new SearchTerm('event_id', $params['event_id']),
            new SearchTerm('active', 1),
        ], ['date_modified'=>true],1);

        if($mod === false) {
            //Skip everything, we have no events with the current query!
            return $this->responder
                ->withJson($response, []);
        }
        
        //Provide the HTTP-compliant modified time format
        $dateTime = new \DateTime($mod[0]['date_modified']);
        $dateTime->setTimezone(new \DateTimeZone('GMT'));
        $mod[0]['date_modified'] = $dateTime->format('D, d M Y H:i:s \G\M\T');

        $response = $response
        ->withHeader('Last-Modified', $mod[0]['date_modified']);
        
        //Check if we were asked about the modified time
        if ($request->hasHeader('If-Modified-Since')) {
            if (new \DateTime($request->getHeaderLine('If-Modified-Since'), new \DateTimeZone('UTC')) >= $dateTime) {
                return $response
                ->withStatus(304);
            }
        }
        
        
        //TODO: Implement caching response for if-modified-since

        $whereParts = array(
          new SearchTerm('event_id', $params['event_id']),
          new SearchTerm('active', 1),
        );

        $order = array('short_code' => false);


        // Invoke the Domain with inputs and retain the result
        $data = $this->location->Search(new View([
            'short_code', 'name','description'
        ],[
            new Join($this->assignment,['location_id'=>'id'],alias:'l', subQSelectColumns:[
                new SelectColumn('location_id',true), new SelectColumn('id', EncapsulationFunction:'min(?)')
            ], subQSearchTerms:$terms)
        ]), $whereParts, $order);

        if($transformToConventionMaster){
            $data = array_map(function($location){
                return [
                    'id' => $location['short_code'],
                    'resourceType' => 'Room',
                    'roomName' => $location['name'],
                    'title' => $location['name'],
                    'description' => $location['description'],
                ];
            }, $data);
        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
