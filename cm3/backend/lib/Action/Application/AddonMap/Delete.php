<?php

namespace CM3_Lib\Action\Application\AddonMap;

use CM3_Lib\models\application\addonmap;
use CM3_Lib\models\application\addon;
use CM3_Lib\models\application\badgetype;

use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Exception\HttpBadRequestException;
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
    public function __construct(
        private Responder $responder,
        private addonmap $addonmap,
        private addon $addon,
        private badgetype $badgetype,
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
        $event_id = $request->getAttribute('event_id');
        $group_id = $request->getAttribute('group_id');
        // Extract the form data from the request
        $data = array(
            'badge_type_id' => $params['badge_type_id'],
            'addon_id'     => $params['addon_id']
        );

        //Confirm the given addon_id belongs to the given event_id
        if (!$this->addon->verifyAddonBelongsToGroup($data['addon_id'], $group_id)) {
            throw new HttpBadRequestException($request, 'Invalid addon_id specified');
        }

        //Also confirm the specified badge_type_id belongs to the event id
        if (!($this->badgetype->verifyBadgeTypeBelongsToEvent($data['badge_type_id'], $event_id))) {
            throw new HttpBadRequestException($request, 'Invalid badge id specified');
        }

        $data = $this->addonmap->Delete($data);

        if ($data == 1) {
            // Build the HTTP response
            return $response;
        }
        throw new HttpNotFoundException($request);
    }
}
