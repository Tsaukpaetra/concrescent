<?php

namespace CM3_Lib\Middleware;

use CM3_Lib\util\PermEvent;
use CM3_Lib\util\PermGroup;
use CM3_Lib\util\EventPermissions;
use CM3_Lib\util\CurrentUserInfo;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\application\group as g_group;

use CM3_Lib\Responder\Responder;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Exception\HttpInternalServerErrorException;

class PermCheckGroupByContextCode
{
    public array $AllowedPerms = array();
    public ?string $AttributeName = null;
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(
        private g_group $g_group,
        private CurrentUserInfo $CurrentUserInfo
    ) {
    }
    /**
     * Example middleware invokable class
     *
     * @param  ServerRequest  $request PSR-7 request
     * @param  RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): \Nyholm\Psr7\Response
    {
        $routeArguments = \Slim\Routing\RouteContext::fromRequest($request)->getRoute()->getArguments();
        $perms = $request->getAttribute('perms');
        $hasPerm = false;


        //If we have an AttributeName, check it
        if (is_null($this->AttributeName)) {
            throw new HttpInternalServerErrorException($request, "PermCheckGroupByContextCode called but  was not provided  with  AttributeName to check against?");
        }
        if (!isset($routeArguments[$this->AttributeName])) {
            throw new HttpInternalServerErrorException($request, "PermCheckGroupByContextCode called but no argument <$this->AttributeName> to check against?");
        } else {

            $context_code = $routeArguments[$this->AttributeName];
            //Fetch group ID from context code
            $matchedGroups = $this->g_group->Search(['id'], [
                $this->CurrentUserInfo->EventIdSearchTerm(),
                new SearchTerm('context_code', $context_code),
            ]);

            $desiredGroupID = $matchedGroups[0]['id'] ?? 0;

            //Short circuit if we're global admin
            if ($perms->EventPerms->isGlobalAdmin() || $perms->EventPerms->isEventAdmin()) {
                //Instant thumbs up
                return $handler->handle($request->withAttribute('group_id', $desiredGroupID));
            }
            //Does a group with this context code exist?
            if (count($matchedGroups) < 1 && !($context_code == 'A' || $context_code == 'S')) {
                throw new HttpUnauthorizedException($request, 'Context Code not accessible with current permissions or does not exist');
            }


            //Do they have permissions for the specified group at all?
            if (isset($perms->GroupPerms[$desiredGroupID])) {
                //Are we checking for more than the group id at this time?
                if (count($this->AllowedPerms) > 0) {
                    $gperms = $perms->GroupPerms[$desiredGroupID];
                    foreach ($this->AllowedPerms as $value) {
                        if ($value instanceof PermGroup) {
                            $hasPerm |= $gperms->getValue() & $value->getValue();
                        }
                    }
                } else {
                    //We don't have any specific perms to check, pass this round
                    $hasPerm = true;
                }
            } elseif ($context_code == 'A' || $context_code == 'S') {
                $gperms = $perms->GroupPerms[$desiredGroupID];
                $eventPermsValue = $perms->EventPerms->getValue();

                // Define the mapping configuration
                // Key: PermGroup constant
                // Value: Array of PermEvent constants it maps to
                $mappings = [
                    'A' => [
                        PermGroup::Badge_View    => [PermEvent::Attendee_View],
                        PermGroup::Badge_Edit    => [PermEvent::Attendee_Edit],
                        PermGroup::Badge_Manage  => [PermEvent::Attendee_Manage],
                        // Add View/Export/Refund mappings as defined in your business logic
                    ],
                    'S' => [
                        PermGroup::Badge_View    => [PermEvent::Staff_View],
                        PermGroup::Badge_Edit    => [PermEvent::Staff_Edit],
                        PermGroup::Badge_Manage  => [PermEvent::Staff_Manage],
                        // Add Staff-specific mappings here
                    ],
                ];

                $currentMapping = $mappings[$desiredGroupID] ?? [];

                foreach ($currentMapping as $groupBit => $eventBits) {
                    // Check if the user has the specific Group permission
                    if ($gperms->getValue() & $groupBit) {
                        // Check if the user has ANY of the mapped Event permissions
                        foreach ($eventBits as $eventBit) {
                            if ($eventPermsValue & $eventBit) {
                                $hasPerm = true;
                                break 2; // Found a match, exit both loops
                            }
                        }
                    }
                }

            
            } else {
                throw new HttpUnauthorizedException($request, 'Context Code ' . $context_code . ' not accessible with missing permissions');
            }
        }

        if (!$hasPerm) {
            throw new HttpUnauthorizedException($request, 'Context Code ' . $context_code . ' not accessible with current permissions');
        }
        return  $handler->handle($request->withAttribute('group_id', $desiredGroupID));
    }

    public function withAttributeName(string $inAttributeName)
    {
        $new = clone $this;
        $new->AttributeName = $inAttributeName;
        return $new;
    }
    public function withAllowedPerms(array $perms)
    {
        $new = clone $this;
        $new->AllowedPerms = $perms;
        return $new;
    }
    public function withAllowedPerm(PermGroup $permToAdd)
    {
        $new = clone $this;
        $new->AllowedPerms[] = $permToAdd;
        return $new;
    }
}
