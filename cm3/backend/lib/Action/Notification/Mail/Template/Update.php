<?php

namespace CM3_Lib\Action\Notification\Mail\Template;

use CM3_Lib\Modules\Notification\Mail;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Update
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     */
    public function __construct(private Responder $responder, private CurrentUserInfo $CurrentUserInfo, private Mail $Mail)
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

        $context = $request->getAttribute('context');
        $name    = $request->getAttribute('name');

        //Special case: Are we literally only setting the Active state?
        if(count($data) == 1 && array_keys($data)[0] == 'active') {    
            $data = $this->Mail->SetTemplateActive($context, $name, $data['active']);
        } else {
            // Invoke the Domain with inputs and retain the result
            $data = $this->Mail->SetTemplate($context, $name, $data);
        }
        
        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
