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
final class SimCreateBatch
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     */
    public function __construct(
        private Responder $responder,
        private PaymentBuilder $PaymentBuilder,
        private CurrentUserInfo $CurrentUserInfo
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
        $data = (array) $request->getParsedBody();

        if ($this->CurrentUserInfo->HasEventPerm(PermEvent::GlobalAdmin)
        || $this->CurrentUserInfo->HasEventPerm(checkPerm: PermEvent::Payment_CreateCancel)
        )
        {
            $this->PaymentBuilder->SetIgnoreBadgeTypeAvailability(true);
        }

        $result = array_map(function ($item) {
            $this->PaymentBuilder->resetCart($item['contact_id'] ?? null,$this->CurrentUserInfo->GetContactName());

            $this->PaymentBuilder->setCartItem(0, $item);
            $this->PaymentBuilder->refreshCartMeta();
            //Return with some of the seed data and some mods
            return array_merge(
                $this->PaymentBuilder->getCartExpandedState(),
                [
                    'contact_id' => $item['contact_id'] ?? null,
                    'contact_email_address' => $item['contact_email_address'] ?? '',
                    'uuid' => $this->uuidv4(),
                    'canEdit' => false,
                ]
            );
        }, $data);



        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
    function uuidv4()
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
