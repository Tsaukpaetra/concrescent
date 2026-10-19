<?php

namespace CM3_Lib\Action\Public;

use CM3_Lib\database\SearchTerm;

use CM3_Lib\models\admin\user;
use CM3_Lib\models\eventinfo;
use CM3_Lib\models\application\group;
use CM3_Lib\util\TokenGenerator;
use CM3_Lib\database\DbConnection;


use CM3_Lib\models\admin\oauth_app;
use CM3_Lib\util\BaseIntEncoder;
use Branca\Branca;
use MessagePack\Packer;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

class redeemOauth
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private user $user,
        private TokenGenerator $TokenGenerator,
        private Branca $Branca,
        private oauth_app $oauth_app,
        private DbConnection $DbConnection
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

        if (!isset($data['client_id'], $data['client_secret'], $data['code'])) {
            throw new HttpBadRequestException($request, 'Missing required OAuth parameters');
        }

        //Decode the token
        try {
            $decoded_client_id = BaseIntEncoder::decode($this->Branca->Decode($data['client_secret']));
        } catch (\Exception $e) {
            throw new HttpBadRequestException($request, 'Invalid client secret format');
        }

        //Ensure the client_id matches the token's client ID
        if(BaseIntEncoder::decode($data['client_id']) != $decoded_client_id){
            throw new HttpBadRequestException($request, 'Invalid client ID');
        }

        //Okay, let's lock us in a transaction guard in case a smarty is trying to get two sessions with one code
        $result = $this->DbConnection->transactional(function () use ($decoded_client_id, $data, $request) {
            //Fetch the oauth app (if it exists)
            $oauthApp = $this->oauth_app->GetByID($decoded_client_id, '*', true);
            $checks = [
            'client_id mismatch'     => $data['client_id'] != $decoded_client_id,
            'client_secret rotated' => $oauthApp !== false && $this->Branca->timestamp('') == strtotime($oauthApp['date_token']),
            'client_id inactive' => ($oauthApp !== false && $oauthApp['active']),
            'code already redeemed' => $this->TokenGenerator->checkOathCode($decoded_client_id, $data['code'], $data['code_verifier'] ??  '')
            ];

            if (in_array(false, $checks, true)) {
                $failedChecks = 'Redeem failed due to the following: ' . implode(', ', array_keys($checks, false, true));
                throw new HttpBadRequestException($request, $failedChecks);
            }

            //Redeem the token
            return $this->TokenGenerator->redeemOauth($decoded_client_id, $data['code']);

        });
        // Build the HTTP response
        return $this->responder
            ->withJson($response
            ->withHeader('Cache-Control', 'no-store'), $result);
    }
}
