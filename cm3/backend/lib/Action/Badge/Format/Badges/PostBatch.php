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
final class PostBatch
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
        $overridePaymentRequirement = $data['overridePaymentRequirement'] == 'true';

        $result = [
            'enqueued' => [],
            'failed' => []
        ];

        foreach ($data['badges'] as $badge) {

            $current = $this->badgeinfo->GetSpecificBadge($badge['id'], $badge['context_code'], true);
            //Simple checks
            if ($current === false)
            {
                $result['failed'][$badge['uuid']]='Not found';
                continue;
            }
            if ($current['event_id'] !=  $request->getAttribute('event_id'))
            {
                $result['failed'][$badge['uuid']]='Does not belong to current event';
                continue;
            }
            if ($current['payment_status'] != 'Completed' && ! $overridePaymentRequirement)
            {
                $result['failed'][$badge['uuid']]='Payment not complete';
                continue;
            }

            //Spit tests Ok, create the job
            $printJob = $this->printjob->Create([
                'event_id' => $current['event_id'],
                'format_id' => $params['format_id'],
                'state' => 'Queued',
                'meta' => json_encode($data['meta']),
                'data' => json_encode($current),
                'result' => ''
            ]);

            if($printJob !== false) {
                $current['printjob_id'] = $printJob['id'];
                $result['enqueued'][$badge['uuid']] = $current;
            }
        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
