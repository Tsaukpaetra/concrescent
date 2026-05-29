<?php

namespace CM3_Lib\Action\Notification\Mail\Template;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\Modules\Notification\Mail;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use CM3_Lib\util\CurrentUserInfo;
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
     * @param Mail $Mail The mail notification helper
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
                $qp = $request->getQueryParams();
        $context = $request->getAttribute('context');
        $name    = $request->getAttribute('name');
        
        // Invoke the Domain with inputs and retain the result
        // TODO: Doesn't actually protect against context-less retrieval ?
        // (i.e. putting context as empty string and then just adding the context manually as part of the name)
        // It would only work for the on-disk templates.
        $data = $this->Mail->GetTemplate($context, $name);


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
