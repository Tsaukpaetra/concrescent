<?php

namespace CM3_Lib\Action\Account;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\models\attendee\badge as a_badge;
use CM3_Lib\models\attendee\addonpurchase as a_addon;
use CM3_Lib\models\application\submissionapplicant as g_badge;
use CM3_Lib\models\application\submission as g_badge_submission;
use CM3_Lib\models\application\badgetype as g_badge_type;
use CM3_Lib\models\application\group as g_group;
use CM3_Lib\models\eventinfo;


use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetMyBadges
{
    private $selectColumns = array(
      'id',
      'display_id',
      'hidden',
      'uuid',
      'real_name',
      'fandom_name',
      'name_on_badge',
      'date_of_birth',
      'notify_email',
      'can_transfer',
      'ice_name',
      'ice_relationship',
      'ice_email_address',
      'ice_phone_number',
    );
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private a_badge $a_badge,
        private g_badge $g_badge,
        private g_badge_submission $g_badge_submission,
        private g_badge_type $g_badge_type,
        private g_group $g_group,
        private eventinfo $eventinfo
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
        //Fetch the authenticated user's info
        $c_id = $request->getAttribute('contact_id');
        $searchTerms = array(
          new SearchTerm('contact_id', $c_id)
        );

        //First, attendee badges
        $a_badges = $this->a_badge->Search(
            array_merge(
                $this->selectColumns,
                array(
              new SelectColumn('', EncapsulationFunction: "'A'", Alias: 'Context'),
              'badge_type_id'
           )
            ),
            $searchTerms
        );

        //And group application badges
        $g_badges = $this->g_badge->Search(
            new View(
                array_merge(
                    $this->selectColumns,
                    array(
                new SelectColumn('context_code', JoinedTableAlias:'grp', Alias: 'Context'),
                new SelectColumn('badge_type_id', JoinedTableAlias:'sub')
             )
                ),
             //And the join so we can get the badge_type_id
             array(
               new Join(
                   $this->g_badge_submission,
                   array('id' => 'application_id'),
                   alias:'sub'
               ),
                 new Join(
                     $this->g_badge_type,
                     array('id' => new SearchTerm('badge_type_id', null, JoinedTableAlias:'sub') ),
                     alias:'typ'
                 ),
               new Join(
                   $this->g_group,
                   array('id' =>  new SearchTerm('group_id', null, JoinedTableAlias:'typ')),
                   alias:'grp'
               )
             )
            ),
            $searchTerms
        );

        $result = array_merge($a_badges, $g_badges);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
