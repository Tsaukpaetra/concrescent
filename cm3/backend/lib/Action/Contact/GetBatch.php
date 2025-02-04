<?php

namespace CM3_Lib\Action\Contact;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\contact;
use CM3_Lib\util\badgeinfo;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class GetBatch
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(private Responder $responder, private contact $contact,
    private badgeinfo $badgeinfo)
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
        $data = array_unique(array_filter($request->getParsedBody()));

        //Short circuit if nothing is provided
        if(count($data)< 1){
            return $this->responder
                ->withJson($response, new \stdClass());
        }
        
        $whereParts = array(
          new SearchTerm('email_address', $data, 'IN'),
        );
        //Find what's there already
        $existing = array_column($this->contact->Search(['id','email_address'], $whereParts),'id','email_address');

        //Prepare our result
        $result = array_fill_keys($data,null);
        //Zip the result
        array_walk($result, function(&$id, $email_address) use($existing){
            if(isset($existing[$email_address])){
                $id = $existing[$email_address];
            }
        });

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
