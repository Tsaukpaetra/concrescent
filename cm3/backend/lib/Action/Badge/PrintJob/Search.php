<?php

namespace CM3_Lib\Action\Badge\PrintJob;

use CM3_Lib\util\badgeinfo;
use CM3_Lib\models\badge\printjob;
use CM3_Lib\models\badge\format;

use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\SelectColumn;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 * Action.
 */
final class Search
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private Responder $responder,
        private printjob $printjob,
        private format $format,
        private badgeinfo $badgeinfo
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
        $whereParts = array(
            new SearchTerm('event_id', $request->getAttribute('event_id'))
          //new SearchTerm('active', 1)
        );
        $qp = $request->getQueryParams();
        $state = $qp['state'] ?? false;
        $stationName = $qp['stationName'] ?? false;
        $full = $qp['full'] ?? 'false';
        $expandMeta = $qp['expandMeta'] ?? 'false';

        if ($state) {
            $whereParts[] = new SearchTerm('state', $state);
        }
        if ($stationName) {
            $whereParts[] = new SearchTerm('meta', $stationName, EncapsulationFunction: 'JSON_EXTRACT(?, "$.stationName")', EncapsulationColumnOnly: true);
        }

        //Build the view based on options
        $view = new View(['id','format_id','state'], []);
        if ($full == 'true') {
            $view->Columns[] = 'meta';
            $view->Columns[] = 'data';
        }
        if ($expandMeta == 'true') {
            $view->Joins[] = new Join($this->format, ['id' => 'format_id'], 'LEFT', alias:'f');
            $view->Columns[] = new SelectColumn('name', JoinedTableAlias:'f');
            $view->Columns[] = new SelectColumn('meta', EncapsulationFunction: 'JSON_UNQUOTE(JSON_EXTRACT(?, "$.stationName"))', Alias:'stationName');
            $view->Columns[] = 'date_created';
            $view->Columns[] = 'date_modified';
        }

        // Invoke the Domain with inputs and retain the result
        $pg = $this->badgeinfo->parseQueryParamsPagination($qp, defaultSortDesc:true);
        $totalRows = 0;
        $data = $this->printjob->Search($view, $whereParts, $pg['order'], $pg['limit'], $pg['offset'], $totalRows);

        foreach ($data as &$value) {
            if (isset($value['data'])) {
                //Move into raw for safe-keeping
                $value['data_raw'] = $value['data'];
                $value['data'] = json_decode($value['data']);
                if (0==json_last_error()) {
                    unset($value['data_raw']);
                }
            }
            //Move into raw for safe-keeping
            if (isset($value['meta'])) {
                $value['meta_raw'] = $value['meta'];
                $value['meta'] = json_decode($value['meta']);
                if (0==json_last_error()) {
                    unset($value['meta_raw']);
                }
            }
        }

        $response = $response->withHeader('X-Total-Rows', (string)$totalRows);
        // Build the HTTP response
        return $this->responder
            ->withJson($response, $data);
    }
}
