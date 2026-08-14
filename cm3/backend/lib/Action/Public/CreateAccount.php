<?php

namespace CM3_Lib\Action\Public;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\TableValidator;
use CM3_Lib\util\TokenGenerator;
use CM3_Lib\models\contact;
use CM3_Lib\models\eventinfo;
use CM3_Lib\models\admin\user;

use CM3_Lib\util\UserPermissions;
use Respect\Validation\Validator as v;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

class CreateAccount
{
    /**
     * The constructor.
     *
     * @param Responder $responder The responder
     * @param eventinfo $eventinfo The service
     */
    public function __construct(private Responder $responder, private contact $contact, private TokenGenerator $TokenGenerator,
    private eventinfo $eventinfo, private user $user
    )
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
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $params): ResponseInterface
    {
        // Extract the form data from the request body
        $data = (array)$request->getParsedBody();

        //Validate that the contact information is somewhat sane
        $v = new TableValidator($this->contact);
        $v->addColumnValidator('event_id', v::Digit(), true);
        $v->addColumnValidator('email_address', v::Email(), true);
        if (!$v->Validate($data)) {
            throw new HttpBadRequestException($request, 'Profile information error.');
            //throw new HttpBadRequestException($request, implode($v->GetErrors()));
        }

        //Check if there's an account already
        $existing = $this->contact->Search(null, array(new SearchTerm('email_address', $data['email_address'])), limit:1);
        if (count($existing) > 0) {
            throw new HttpBadRequestException($request, 'Contact already exists with that email.');
        }

        $data['event_id'] = $data['event_id'] ?? null;

        $result = $this->contact->Create($data);

        //If this is the first contact ever, promote them to global Admin and create the first e vent
        if($result['id'] == 1) {
            $perms = new UserPermissions();
            $perms->IsPermanentGlobalAdmin = true;
            $this->user->Create([
                'contact_id' => 1,
                'username' => $data['email_address'],
                'active' => 1,
                'permissions' => $this->TokenGenerator->packPermissions($perms)
            ]);
            $this->eventinfo->Create([
                'shortcode' => '1',
                'active' => 0,
                'display_name' => 'Default Event',
                'date_start' => date('Y/m/d'),
                'date_end' => date('Y/m/d'),
                'staff_start' => date('Y/m/d'),
                'staff_end' => date('Y/m/d'),
                'notes' => 'Default event'
            ]);
            //Get their newly-minted admin token
            $result = $this->TokenGenerator->forUser(1,1)['token'];
        } else {
            //Since they're new, make a bare token
            $result = $this->TokenGenerator->forLoginOnly(
                $result['id'],
                $data['event_id']
            );
        }


        // Build the HTTP response
        return $this->responder
            ->withJson($response, $result);
    }
}
