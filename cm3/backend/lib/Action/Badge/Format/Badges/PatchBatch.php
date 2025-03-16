<?php

namespace CM3_Lib\Action\Badge\Format\Badges;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\badge\printjob;

use CM3_Lib\util\badgeinfo;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 * Action.
 */
final class PatchBatch
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private badgeinfo $badgeinfo,
        private printjob $printjob
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
        // Extract the form data from the request body
        $data = (array) $request->getParsedBody();

        $result = [];

        foreach ($data['badges'] as $badge) {

            //Spit tests Ok, create the job
            $printJob = $this->printjob->update([
                'id' => $badge['printjob_id'],
                'state' => $badge['printjob_state'],
                'result' => $badge['printjob_result'] ?? ''
            ]);

            if($badge['printjob_state'] == 'Completed') {
                //Tell the badge it's been printed
                $bDat = [
                    'time_printed'=> date('Y-m-d H:i:s')
                ];
                $bUp = $this->badgeinfo->UpdateSpecificBadgeUnchecked(
                    $badge['id'],
                    $badge['context_code'],
                    $bDat
                );
            }
            $result[$badge['uuid']] = $printJob !== false ? 'updated' : 'failed';
        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
