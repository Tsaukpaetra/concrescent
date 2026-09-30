<?php

namespace CM3_Lib\Action\Public;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\models\attendee\badge as a_badge;
use CM3_Lib\models\attendee\badgetype as a_badge_type;
use CM3_Lib\models\application\submissionapplicant as g_badge;
use CM3_Lib\models\application\submission as g_badge_submission;
use CM3_Lib\models\application\badgetype as g_badge_type;
use CM3_Lib\models\application\group as g_group;
use CM3_Lib\models\application\assignment as g_assignment;
use CM3_Lib\models\eventinfo;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\util\CurrentUserInfo;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

class GetSpecificApplication
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private badgeinfo $badgeinfo,
        private g_assignment $g_assignment,
        private g_badge $g_badge,
        private g_badge_submission $g_badge_submission,
        private g_badge_type $g_badge_type,
        private g_group $g_group,
        private eventinfo $eventinfo,
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
        $data = $request->getQueryParams();
        $searchTerms = array(
          new SearchTerm('id', $data['id']),
          new SearchTerm('uuid', $data['uuid']),
        );
        
        //And group application badges, ripped and modified from badgeinfo
        $result = $this->g_badge_submission->Search(
            new View(
            array(
                'id',
                'uuid',
                'display_id',
                'contact_id',
                'real_name',
                'fandom_name',
                'name_on_badge',
               new SelectColumn('context_code', JoinedTableAlias:'grp'),
               new SelectColumn('AssignmentCount',EncapsulationFunction:'ifnull(?,0)',Alias:'assignments',JoinedTableAlias:'ac'),
               new SelectColumn('LocationIDs',EncapsulationFunction:'ifnull(?,\'\')',Alias:'assignments_locations',JoinedTableAlias:'ac'),
               new SelectColumn('CategoryIDs',EncapsulationFunction:'ifnull(?,\'\')',Alias:'assignments_categories',JoinedTableAlias:'ac'),
               new SelectColumn('application_status'),
               new SelectColumn('badge_type_id'),
               new SelectColumn('payment_status'),
               new SelectColumn('payment_id'),
               new SelectColumn('name', Alias:'badge_type_name', JoinedTableAlias:'typ'),

            ),
            array(
                   new Join(
                       $this->g_badge_type,
                       array(
                         'id' =>new SearchTerm('badge_type_id', null),
                       ),
                       alias:'typ'
                   ),
                  new Join(
                      $this->g_group,
                      array(
                        'id' => new SearchTerm('group_id', null, JoinedTableAlias: 'typ')
                      ),
                      alias:'grp'
                  ),
                 new Join($this->g_assignment,['application_id'=>'id'],'LEFT',
                 'ac',[
                     new SelectColumn('application_id',true),
                     new SelectColumn('location_id',false,'GROUP_CONCAT(? SEPARATOR \',\')','LocationIDs'),
                     new SelectColumn('category_id',false,'GROUP_CONCAT(? SEPARATOR \',\')','CategoryIDs'),
                     new SelectColumn('id',false,'count(?)','AssignmentCount')
                 ]),
                 )
        ),
            $searchTerms
        );

        if (count($result) == 0) {
            throw new HttpNotFoundException($request);
        }
        
        //Munge in some extras
        array_walk($result, function (&$badge) {
            $badge['qr_data'] = 'CM*' . $badge['context_code'] . $badge['display_id'] . '*' . $badge['uuid'];
        });


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result[0]);
    }
}
