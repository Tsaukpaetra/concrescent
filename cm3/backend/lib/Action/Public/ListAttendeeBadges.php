<?php

namespace CM3_Lib\Action\Public;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\models\eventinfo;
use CM3_Lib\models\attendee\badgetype;
use CM3_Lib\models\attendee\badge;

use CM3_Lib\util\CurrentUserInfo;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class ListAttendeeBadges
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
        private badgetype $badgetype,
        private badge $badge,
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
        $viewData = new View(
            array(
              'id',
              'display_order',
              'name',
              'description',
              'rewards',
              'price',
              'payable_onsite',
              new SelectColumn('payment_deferred',EncapsulationFunction:'0', Alias: 'payment_deferred'),
              'quantity',
              'start_date',
              'end_date',
              'min_age',
              'max_age',
              new SelectColumn('date_start', EncapsulationFunction: 'date_sub(?, INTERVAL `min_age` YEAR)', Alias: 'max_birthdate', JoinedTableAlias: 'event'),
              new SelectColumn('date_start', EncapsulationFunction: 'date_sub(?+1, INTERVAL `max_age`+1 YEAR)', Alias: 'min_birthdate', JoinedTableAlias: 'event'),
              'dates_available',
              new SelectColumn('quantity_sold', EncapsulationFunction: 'ifnull(?,0)', Alias: 'quantity_sold', JoinedTableAlias: 'q'),
              new SelectColumn('quantity_sold', EncapsulationFunction: 'quantity - ifnull(?,0)', Alias: 'quantity_remaining', JoinedTableAlias: 'q')
          ),
            array(
            new Join(
                $this->badge,
                array(
                  'badge_type_id'=>'id',
                ),
                'LEFT',
                'q',
                array(
                  new SelectColumn('badge_type_id', true),
                  new SelectColumn('id', false, 'count(?)', 'quantity_sold')
              ),
                array(
                 new SearchTerm('payment_status', 'Completed'),
               )
            ),
           new Join(
               $this->eventinfo,
               array(
                   'id' => 'event_id',
               ),
               'INNER',
               'event',
               array(
                   new SelectColumn('id'),
                   new SelectColumn('date_start')
               ),
               array(
                   new SearchTerm('id', $params['event_id'])
               )
           )
          )
        );

        $whereParts = array(
          new SearchTerm('event_id', $params['event_id']),
          new SearchTerm('', '', subSearch:array(
              new SearchTerm('active', 1),
              new SearchTerm('active_override_code', $override, TermType:'OR'),
          ))
        );

        $order = array('display_order' => false);


        // Invoke the Domain with inputs and retain the result
        $data = $this->badgetype->Search($viewData, $whereParts, $order);

        // $now = new \DateTime();
        foreach ($data as &$value) {
            //Ensure quantity remaining is a value if quantity available is set
            if (!is_null($value['quantity'])) {
                $value['quantity_remaining'] = $value['quantity_remaining'] ?? $value['quantity'];
            }
        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
