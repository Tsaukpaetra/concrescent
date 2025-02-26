<?php

namespace CM3_Lib\Action\Location;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\application\location;
use CM3_Lib\models\application\assignment;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class Create
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(private Responder $responder, private location $location, private assignment $assignment)
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
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();

        //Ensure we're only attempting to create a location for the current Event
        $data['event_id'] = $request->getAttribute('event_id');

        // Invoke the Domain with inputs and retain the result
        $result = $this->location->Create($data);


        
        if (isset($data['Assignments'])) {
            $result['AssnResults'] = [];
            $setAssignments = $data['Assignments'];
            $currentAssignments = $this->assignment->Search(
                array(
                    'id'
                ),
                array(
                    new SearchTerm('location_id', $result['id'])
                )
            );
            //Process adds
            foreach (array_udiff($setAssignments, $currentAssignments, array($this,'compareAssignmentID')) as $newAssignment) {
                $newAssignment['location_id'] = $result['id'];
                unset($newAssignment['id']);
                $result['AssnResults']['Added'] = 
                $this->assignment->Create($newAssignment);
            }
            //Process removes
            foreach (array_udiff($currentAssignments, $setAssignments, array($this,'compareAssignmentID')) as $deletedAssignment) {
                $deletedAssignment['location_id'] = $result['id'];
                $result['AssnResults']['Deleted'] = 
                $this->assignment->Delete($deletedAssignment);
            }
            //Process modifications
            foreach (array_uintersect($setAssignments, $currentAssignments, array($this,'compareAssignmentID')) as $modifiedAssignment) {
                $modifiedAssignment['location_id'] = $result['id'];
                $result['AssnResults']['Updated'] = 
                $this->assignment->Update($modifiedAssignment);
            }
        }

        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
    
    public function compareAssignmentID($left, $right)
    {
        //Spaceship!
        return $left['id'] <=> $right['id'];
    }
}
