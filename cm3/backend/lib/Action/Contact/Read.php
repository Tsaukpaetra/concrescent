<?php

namespace CM3_Lib\Action\Contact;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\contact;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
    public function __construct(private Responder $responder, private contact $contact)
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

        $whereParts = array(
          new SearchTerm('id', $params['id'])
        );

        // Invoke the Domain with inputs and retain the result
        $data = $this->contact->GetByID($params['id'], ['id',
            'id',
            'date_created',
            'date_modified',
            'allow_marketing',
            'email_address',
            'real_name',
            'phone_number',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip_code',
            'country',
            'notes']);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
