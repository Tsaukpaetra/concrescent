<?php

namespace CM3_Lib\Action\Notification\Mail\Template;

use CM3_Lib\models\mail\template;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Create
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param mail\template $mail\template The service
     */
    public function __construct(private Responder $responder, private mail\template $mail\template)
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
        $data = (array)$request->getParsedBody();

        // Invoke the Domain with inputs and retain the result
        $data = $this->mail\template->Create($data);

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
