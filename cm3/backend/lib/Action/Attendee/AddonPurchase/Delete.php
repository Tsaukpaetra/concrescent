<?php

namespace CM3_Lib\Action\Attendee\AddonPurchase;

use CM3_Lib\models\attendee\addonpurchase;
use CM3_Lib\models\attendee\badge;
use CM3_Lib\models\attendee\badgetype;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;

/**
 * Action.
 */
final class Delete
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(private Responder $responder, private addonpurchase $addonpurchase, private badge $badge, private badgetype $badgetype)
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

        //Confirm permission to delete this badge applicant
        $badgeinfo = $this->badge->GetByID($params['attendee_id'], new View(
            array('id'),
            array(
                new Join($this->badgetype, array('id'=>'badge_type_id', new SearchTerm('event_id', $params['event_id'])))
            )
        ));

        if ($badgeinfo === false) {
            throw new HttpBadRequestException($request, 'Invalid badge specified');
        }

        //TODO: Maybe this should just cancel the addon instead?
        $data = $this->addonpurchase->Update([
            'id'=>$params['id'],
            'payment_status' => 'Cancelled'
        ]);

        // Invoke the Domain with inputs and retain the result
        // $data = $this->addonpurchase->Delete(array('id'=>$params['id']));

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
