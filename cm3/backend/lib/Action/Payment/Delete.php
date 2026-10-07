<?php

namespace CM3_Lib\Action\Payment;

use CM3_Lib\util\PaymentBuilder;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

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
    public function __construct(private Responder $responder, private PaymentBuilder $PaymentBuilder,
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

        $cart_loaded = $this->PaymentBuilder->loadCart(
            $cart_id,
            $cart_uuid,
            $this->CurrentUserInfo->GetEventId(),
            null 
        );

        if (!$cart_loaded) {
            throw new HttpNotFoundException($request, $cart_id);
        }
        //Check that it can be refunded and if so process the refund
        if (isset($data['refund_amount'])) {
            $amount = (float)$data['refund_amount'];
            $reason = $data['reason'] ?? null;

            if ($amount <= 0) {
                throw new HttpBadRequestException($request, "Refund amount must be greater than zero.");
            }

            try {
                $this->PaymentBuilder->ProcessRefund($amount, $reason);
                
                return $this->responder->withJson($response, [
                    'status' => 'success',
                    'message' => 'Refund processed successfully',
                    'new_total' => $this->PaymentBuilder->getCartExpandedState()['payment_txn_amt']
                ]);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
