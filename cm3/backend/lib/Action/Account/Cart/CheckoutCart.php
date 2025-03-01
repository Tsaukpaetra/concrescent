<?php

namespace CM3_Lib\Action\Account\Cart;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\Factory\PaymentModuleFactory;
use CM3_Lib\util\PermEvent;
use CM3_Lib\util\PaymentBuilder;
use CM3_Lib\util\CurrentUserInfo;

use CM3_Lib\models\payment;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

class CheckoutCart
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private PaymentBuilder $PaymentBuilder,
        private payment $payment,
        private CurrentUserInfo $CurrentUserInfo,
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
        $data = (array)$request->getParsedBody();
        //TODO: Validate ownership/permissions?
        
        if($this->CurrentUserInfo->HasEventPerm(PermEvent::GlobalAdmin) ){
            $this->PaymentBuilder->SetIgnoreBadgeTypeAvailability(true);
        }

        //Check if we have specified a cart
        $cart_id = $data['id'] ?? $params['id'] ?? 0;
        $cart_uuid = $data['uuid'] ?? null;

        $data['handler_id'] = $data['handler_id'] ?? $this->CurrentUserInfo->GetContactId();

        if (!$this->PaymentBuilder->loadCart($cart_id, $cart_uuid)) {
            throw new HttpNotFoundException($request);
        }


        //If the cart is in progress, we cannot adjust it until cancelled or completed...
        if (!$this->PaymentBuilder->canCheckout()
        ) {
            if ($this->PaymentBuilder->getCartStatus() == 'Completed') {
                //Weird, they're already completed. Let them know about that...
                return $this->responder
                ->withJson($response, array(
                    'state' => $this->PaymentBuilder->getCartStatus()
                ));
            }
            throw new HttpBadRequestException($request, 'Cart not in correct state to checkout: ' .$this->PaymentBuilder->getCartStatus());
        }

        if ($this->PaymentBuilder->getCartStatus() == 'Incomplete') {
            //Hrm, they've already initiated the payment request. Check if it's completed

            if ($this->PaymentBuilder->CompletePayment($data)) {
                $this->PaymentBuilder->SendStatusEmail();
                // Build the HTTP response
                return $this->responder
                ->withJson($response, array(
                    'state' => $this->PaymentBuilder->getCartStatus()
                ));
            } else {
                //Attempting to complete the payment and failing should have set us back to NotStarted
                //Finish the prep
                if ($this->PaymentBuilder->confirmPrep()) {
                    return $this->responder
                        ->withJson($response, array(
                            'paymentURL' => $this->PaymentBuilder->getPayProcessor()->RetrievePaymentRedirectURL(),
                            'state' => $this->PaymentBuilder->getCartStatus()
                        ));
                }
            }
        } elseif ($this->PaymentBuilder->getCartStatus() == 'Cancelled') {
            //They want to try paying again after cancelling
            $this->PaymentBuilder->CancelPayment();
        }

        if (isset($data['payment_system'])) {
            $this->PaymentBuilder->setPayProcessor($data['payment_system']);
        } elseif (empty($this->PaymentBuilder->getPayProcessorName())) {
            throw new \Exception('payment_system not specified!');
        }

        //Build the payment
        $errors = $this->PaymentBuilder->prepPayment();

        if (count($errors) > 0) {
            throw new \Exception('Errors! ' . print_r($errors, true));
        }
        //Finish the prep
        if ($this->PaymentBuilder->confirmPrep()) {
            if ($this->PaymentBuilder->isFreeride() && $this->PaymentBuilder->CompletePayment($data)) {
                $this->PaymentBuilder->SendStatusEmail();
                // Build the HTTP response
                return $this->responder
                    ->withJson($response, array(
                        'state' => $this->PaymentBuilder->getCartStatus()
                    ));
            } else {
                return $this->responder
                ->withJson($response, array(
                    'paymentURL' => $this->PaymentBuilder->getPayProcessor()->RetrievePaymentRedirectURL(),
                    'state' => $this->PaymentBuilder->getCartStatus()
                ));
            }
        }

        //If it can't be paid, send them the status email too
        if (!$this->PaymentBuilder->getCanPay() && !$this->PaymentBuilder->canEdit()) {
            $this->PaymentBuilder->SendStatusEmail();
        }
        // Build the HTTP response
        return $this->responder
            ->withJson($response, array(
                'state' => $this->PaymentBuilder->getCartStatus()
            ));
    }
}
