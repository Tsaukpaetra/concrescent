<?php

namespace CM3_Lib\Action\Public;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\models\eventinfo;
use CM3_Lib\models\application\location;
use CM3_Lib\models\application\locationcategory;
use CM3_Lib\models\application\assignment;
use CM3_Lib\models\application\submission;
use CM3_Lib\models\forms\question;
use CM3_Lib\models\forms\response;

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
        private locationcategory $locationcategory,
        private assignment $assignment,
        private submission $submission,
        private question $question,
        private response $response,
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

        $terms = [
            $excludeTimed ? new SearchTerm('start_time', null, 'IS') : null,
            $excludeUntimed ? new SearchTerm('start_time', null, 'IS NOT') : null,
        ];

        //Get modified date
        $mod = $this->assignment->Search(new View([
            'date_modified'
        ], [
            new Join($this->location, ['id' => 'location_id'], alias: 'l', subQSelectColumns: [
                'id'
            ], subQSearchTerms: [
                new SearchTerm('event_id', $params['event_id']),
                new SearchTerm('active', 1),
            ])
        ]), $terms, ['date_modified' => true], 1);

        if ($mod === false)
        {
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
        if ($request->hasHeader('If-Modified-Since'))
        {
            if (new \DateTime($request->getHeaderLine('If-Modified-Since'), new \DateTimeZone('UTC')) >= $dateTime)
            {
                return $response
                    ->withStatus(304);
            }
        }


        $order = array('short_code' => false);

        //Fetch the question IDs that say Description for each context code and make searchterms out of them
        $questionsearch = array_map(function($question){
            return new SearchTerm('','',TermType:'OR',subSearch: [
                new SearchTerm('question_id',$question['question_id']),
                new SearchTerm('context_code',['A','S'], 'NOT IN')
            ]);
        }, $this->question->Search(new View(
            ['event_id', new SelectColumn('id', Alias: 'question_id')],
            [
                new Join(
                    $this->question,
                    ['min_order' => 'order', 'context_code' => 'context_code'],
                    'INNER',
                    'q2',
                    [
                        new SelectColumn('context_code', true),
                        new SelectColumn('order', false, 'MIN(?)', 'min_order')
                    ],
                    [
                        new SearchTerm('event_id', $params['event_id']),
                        new SearchTerm('context_code',['A','S'], 'NOT IN'),
                        new SearchTerm('active', 1),
                        new SearchTerm('title', '%descri%', 'LIKE'),
                        new SearchTerm('type', 6,'>'),
                    ]
                    ),
            ]
        )));


        // Invoke the Domain with inputs and retain the result
        $data = $this->assignment->Search(new View([
            new SelectColumn('id', JoinedTableAlias: 's', Alias:'appl_id'),
            new SelectColumn('id', Alias:'assn_id'),
            'category_id',
            'start_time',
            'end_time',
            new SelectColumn('real_name', JoinedTableAlias: 's'),
            new SelectColumn('fandom_name', JoinedTableAlias: 's'),
            new SelectColumn('name_on_badge', JoinedTableAlias: 's'),
            new SelectColumn('application_status', JoinedTableAlias: 's'),
            new SelectColumn('short_code', JoinedTableAlias: 'l'),
            new SelectColumn('description',JoinedTableAlias:'desc')
        ], [
            new Join($this->location, ['id' => 'location_id'], alias: 'l', subQSelectColumns: [
                'id',
                'short_code'
            ], subQSearchTerms: [
                new SearchTerm('event_id', $params['event_id']),
                new SearchTerm('active', 1),
            ]),
            new Join($this->submission, ['id' => 'application_id'], alias: 's'),
            new Join($this->response, [
                'context_id' => new SearchTerm('id','',JoinedTableAlias:'s')
            ],'left',alias:'desc',subQSelectColumns:[
                new SelectColumn('response', GroupBy:true, Alias:'description', EncapsulationFunction: 'max(?)'),
                'context_id','context_code'
                
            ], subQSearchTerms:$questionsearch),
        ]), $terms, $order);
        
        //Add the display_name and blank the not-displayed parts
        foreach ($data as &$value)
        {
            switch ($value['name_on_badge'])
            {
                case 'Fandom Name Large, Real Name Small':
                    $value['display_name'] = trim(($value['fandom_name'] ?? '') . ' (' . $value['real_name'] . ')');
                    break;
                case 'Real Name Large, Fandom Name Small':
                    $value['display_name'] = trim($value['real_name'] . ' (' . ($value['fandom_name'] ?? '') . ')');
                    break;
                case 'Fandom Name Only':
                    $value['display_name'] = $value['fandom_name'] ?? '';
                    $value['real_name'] = '';
                    break;
                case 'Real Name Only':
                    $value['display_name'] = $value['real_name'];
                    $value['fandom_name'] = '';
                    break;
            }
        }


        if ($transformToConventionMaster)
        {
            //Fetch categories now too
            $categories = $this->locationcategory->Search([
                'id',
                'color',
                'name',
                'description'
            ], [
                new SearchTerm('event_id', $params['event_id']),
                new SearchTerm('active', 1),
            ]);

            $data = array_map(function ($location) use ($categories) {
                //Get the associated category
                $cat = array_values(array_filter($categories, function ($category) use ($location) {
                    return $category['id'] == $location['category_id'];
                }))[0] ?? [
                    //Default unknown
                    'id' => 0,
                    'color' => '#2196F3',
                    'name' => 'Unknown',
                    'description' => 'Unknown'
                ];

                return [
                    'handle' => $location['assn_id'],
                    'activityID' => $location['appl_id'],
                    'title' => $location['display_name'],
                    'start' => date("Y-m-d\\TH:i:s", strtotime($location['start_time'])),
                    'end' => date("Y-m-d\\TH:i:s", strtotime($location['end_time'])),
                    'resourceId' => $location['short_code'],

                    'activityTypeTitle' => $cat['name'],
                    'activityColour' => $cat['color'],
                    'color' => $cat['color'],
                    'activityType' => $cat['name'],


                    'description' => $location['description'] ?? 'Description here',
                    'roomName' => $location['name'],
                ];
            }, $data);
        }


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
