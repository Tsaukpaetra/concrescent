<?php

namespace CM3_Lib\Action\Notification\Mail\Template;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\Modules\Notification\Mail;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Search
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param Mail $Mail The mail notification helper
     */
    public function __construct(private Responder $responder, private Mail $Mail)
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
        
        $qp = $request->getQueryParams();
        $context = $request->getAttribute('context');
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();
        //TODO: Actually do something with submitted data. Also, provide some sane defaults

        // Invoke the Domain with inputs and retain the result
        $results = $this->Mail->ListTemplates($context);

        //Do the sort
        $results = $this->doSort($results,$this->getOrder($qp['sort']));

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $results);
    }

    function getOrder($sortBy, $sortDesc = false, $defaultSortDesc = false)
    {

        //Ripped from badgeInfo
        $defaultSortColumn = 'name';
        //Interpret order parameters
        $sortBy = explode(',', $sortBy ??'');
        $idAdded = false;
        //Add the ID
        if (empty($sortBy[0])) {
            $idAdded = true;
            $sortBy[0] = $defaultSortColumn;
        } else {
            //$idAdded = true;
            $sortBy[] = $defaultSortColumn;
            $sortDesc .=','.$defaultSortDesc;
        }
        $sortDesc = array_map(function ($v) {
            return $v == 'true' ? 1 : 0;
        }, explode(',', $qp['sortDesc']??''));
        //Ensure the ID sort is descending
        if ($idAdded) {
            $sortDesc[count($sortDesc) - 1] = $defaultSortDesc;
        } else {
            //If we actually had the ID specified, un-add the ID column we forced
            if (array_count_values($sortBy)[$defaultSortColumn] > 1) {
                array_pop($sortBy);
                array_pop($sortDesc);
            }
        }

        //ensure we do not have mismatched number of elements
        $sortDesc = array_slice($sortDesc,0,count($sortBy));

        return array_combine(
            $sortBy,
            $sortDesc
        );
    }
    function doSort($data, $sortOrder)
    {
        usort($data, function ($a, $b) use ($sortOrder) {
            foreach ($sortOrder as $field => $isDescending) {
                // If the values are identical for this field, move to the next field in $order
                if ($a[$field] === $b[$field]) {
                    continue;
                }
                
                // Spaceship operator handles string and numeric comparisons seamlessly
                $comparison = $a[$field] <=> $b[$field];
                
                // If descending is requested (true), invert the comparison result
                return $isDescending ? -$comparison : $comparison;
            }
            return 0; // Everything configured in $order matches exactly
        });
        return $data;
    }
}

