<?php

namespace CM3_Lib\Action\Payment;

use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\models\payment;
use CM3_Lib\models\contact;
use CM3_Lib\Responder\Responder;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\util\CurrentUserInfo;
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
    public function __construct(private Responder $responder, private payment $payment,
    private contact $contact,
    private badgeinfo $badgeinfo,
    private CurrentUserInfo $CurrentUserInfo)
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
        $qp = $request->getQueryParams();
        $find = $qp['find'] ?? '';
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        $whereParts = array(
          new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
        );

        //Interpret order parameters
        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, defaultSortDesc:true);
        $totalRows = 0;
        $data = $this->payment->Search(new View([
          'id','contact_id', 'requested_by','payment_system','payment_status','payment_txn_amt','payment_date',
          new SelectColumn('email_address',Alias:'contact_email_address',JoinedTableAlias:'con')
        ],[
          
          new Join(
            $this->contact,
            array(
              'id' => 'contact_id'
            ),
            alias:'con'
        ),
        ]), $whereParts, $pg['order'], $pg['limit'], $pg['offset'], $totalRows);

        $response = $response->withHeader('X-Total-Rows', (string)$totalRows);


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
