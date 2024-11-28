<?php

namespace CM3_Lib\Action\Payment;

use CM3_Lib\database\SearchTerm;

use CM3_Lib\util\PaymentBuilder;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\util\PermEvent;
use CM3_Lib\Responder\Responder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpNotFoundException;

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
    public function __construct(private Responder $responder, 
    private PaymentBuilder $PaymentBuilder,
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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
      $data = (array)$request->getQueryParams();

      //Check if we have specified a cart
      $cart_id = $data['id'] ?? $params['id'] ?? 0;
      $cart_uuid = $data['uuid'] ?? '';

      if($this->CurrentUserInfo->HasEventPerm(PermEvent::GlobalAdmin) ){
          $this->PaymentBuilder->SetIgnoreBadgeTypeAvailability(true);
      }

      $cart_loaded = $this->PaymentBuilder->loadCart(
          $cart_id,
          $cart_uuid,
          $this->CurrentUserInfo->GetEventId(),
          null 
      );

      if (!$cart_loaded) {
          throw new HttpNotFoundException($request, $cart_id);
      }

      // Build the HTTP response
      return $this->responder
          ->withJson($response, $this->PaymentBuilder->getCartExpandedState());
    }
}
