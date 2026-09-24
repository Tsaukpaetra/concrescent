<?php

namespace CM3_Lib\util;

use CM3_Lib\database\Column as cm_Column;
use CM3_Lib\database\SelectColumn as cm_SelectColumn;
use CM3_Lib\database\View as cm_View;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\models\admin\user;
use CM3_Lib\models\eventinfo;
use CM3_Lib\models\application\group;
use CM3_Lib\util\Permissions;
use CM3_Lib\util\EventPermissions;
use CM3_Lib\AppConfig;
use CM3_Lib\models\contact_session;

use Branca\Branca;
use MessagePack\Packer;
use MessagePack\BufferUnpacker;

class TokenGenerator
{
    public function __construct(private user $user, private eventinfo $eventinfo, private group $group, private Branca $Branca, private AppConfig $config, private contact_session $contactSessionModel)
    {
    }

    public function forLoginOnly($contact_id, $event_id)
    {
        $event_id = $this->checkEventID($event_id, $contact_id);

        // Generate the token proper
        $packer = (new Packer())
            ->extendWith(new EventPermissions());
        // Initialize payload
        $tokenPayload = $packer->pack($contact_id)
          . $packer->pack($event_id);
        $token = $this->Branca->encode($tokenPayload);

        // Insert a new session record
        $this->Branca->decode($token);
        $timestamp = $this->Branca->timestamp('');
        $this->createSessionRecord($contact_id, $event_id, 'ephemeral_login_only', $timestamp);

        return $token;
    }

    public function forUser($contact_id, $event_id, $existingSessionTimestamp = null)
    {
        $username = '';
        $preferences = '';
        // Decode and load their Permissions
        $eperms = $this->loadPermissionsAndPreferences($contact_id, $username, $preferences);

        // Fetch the permissions for the selected event
        if (isset($eperms->EventPerms[$event_id])) {
            $perms = $eperms->EventPerms[$event_id];
        } else {
            $perms = new EventPermissions();
            // Check if they're global admin anywhere
            if ($eperms->IsGlobalAdmin()) {
                $perms->EventPerms->setGlobalAdmin(true);
            }
        }

        if ($eperms->IsGlobalAdmin()) {
            // Flag them as GlobalAdmin
            $perms->EventPerms->setGlobalAdmin(true);
            // Load groups for the selected event
            $eventgroups = array_column($this->group->Search(array('id'), array(
                new SearchTerm('event_id', $event_id)
            )), 'id');
            // Ensure they have all groups
            foreach ($eventgroups as $group) {
                if (!isset($perms->GroupPerms[$group])) {
                    $perms->GroupPerms[$group] = new PermGroup(0);
                }
            }
        }
        if ($perms->EventPerms->isNoPermission() && empty($eperms->EventPerms)) {
            // They don't have permissions elsewhere either
            $perms = null;
        }
        
        // Don't check the event if they have permission for the event requested
        if ($perms == null || ($perms != null && $perms->EventPerms->isNoPermission()) && empty($eperms->EventPerms)) {
            $event_id = $this->checkEventID($event_id, 0);
        }

        // Generate the token proper
        $packer = (new Packer())
            ->extendWith(new EventPermissions());
        // Initialize payload
        $tokenPayload = $packer->pack($contact_id)
          . $packer->pack($event_id)
          . ($perms != null ? $packer->pack($perms) : '');

        $result = array();
        $result['event_id'] = $event_id;
        $result['token'] = $this->Branca->encode($tokenPayload);

        if ($perms != null) {
            $result['username'] = $username;
            $result['preferences'] = $preferences;
            $result['permissions'] = $perms->getPermEnumeration();
        }
        $this->Branca->decode($result['token']);
        $timestamp = $this->Branca->timestamp('');
        if($timestamp == $existingSessionTimestamp) {
            throw new \Exception('Created a token with the same timestamp as the current one? ' . $timestamp);
        }

        // Insert a new session record
        $this->createSessionRecord($contact_id, $event_id, 'user', $timestamp,[],$existingSessionTimestamp);

        return $result;
    }

    public function forOAuth($contact_id, $event_id, $oauthPerm, $ttl = 0)
    {
        $event_id = $this->checkEventID($event_id, $contact_id);

        // Generate the token proper
        $packer = (new Packer())
            ->extendWith(new OAuthPermissions());
        // Initialize payload
        $tokenPayload = $packer->pack($contact_id)
          . $packer->pack($event_id)
          . $packer->pack($oauthPerm);

        $result = array();
        $result['event_id'] = $event_id;

        $conf_ttl = $ttl ? time() - \intval($this->config->get('environment')['token_life']) + $ttl : 0;

        $result['token'] = $this->Branca->encode($tokenPayload, $conf_ttl );

        $result['permissions'] = $oauthPerm->getKey();

        // Insert a new session record
        $this->Branca->decode($result['token']);
        $timestamp = $this->Branca->timestamp('');
        $this->createSessionRecord($contact_id, $event_id, 'oauth', $timestamp);

        return $result;
    }

    public function loadPermissions($contact_id): UserPermissions
    {
        //Fetch their permissions (if they have any)
        $founduser = $this->user->GetByIDorUUID($contact_id, null, array('permissions'));

        if ($founduser !== false) {
            return $this->decodePermissionsString($founduser['permissions']);
        } else {
            return new UserPermissions();
        }
    }
    public function loadPermissionsAndPreferences($contact_id, string &$username, string &$preferences): UserPermissions
    {
        //Fetch their permissions (if they have any)
        $founduser = $this->user->GetByIDorUUID($contact_id, null, array('permissions','username','preferences'));

        if ($founduser !== false) {
            $username = $founduser['username'];
            $preferences = $founduser['preferences'];
            return $this->decodePermissionsString($founduser['permissions']);
        } else {
            return new UserPermissions();
        }
    }
    public function decodePermissionsString(?string $perms): UserPermissions
    {
        if (empty($perms)) {
            return new UserPermissions();
        }

        $unpacker = (new BufferUnpacker())
                ->extendWith(new UserPermissions())
                ->extendWith(new EventPermissions());

        $unpacker->reset($perms);
        return $unpacker->unpack();
    }

    public function packPermissions(UserPermissions $Perms)
    {
        $packer = (new Packer())
                ->extendWith(new UserPermissions())
                ->extendWith(new EventPermissions());
        //TODO:  Should probably implement a demotion mechanic if none of the events has GlobalAdmin permission...
        return $packer->pack($Perms);
    }

    public function mergePermsFromArray(UserPermissions $initialPerms, int $event_id, array $EventPerms)
    {
        //Create EventPermissions
        $newEventPerms = new EventPermissions();
        //Loop all the permissions and set them
        foreach ($EventPerms['EventPerms'] as $perm) {
            $newEventPerms->EventPerms->{'set' . $perm}(true);
        }
        //Loop all the groups and create them
        if (isset($EventPerms['GroupPerms'])) {
            foreach ($EventPerms['GroupPerms'] as $groupId => $gpermdata) {
                $gPerm = new PermGroup(0);
                //Loop all the permissions in the group and set them
                foreach ($gpermdata as $perm) {
                    $gPerm->{'set' . $perm}(true);
                }
                //Set the group
                $newEventPerms->GroupPerms[$groupId] = $gPerm;
            }
        }
        //Did we end up with permissions at all?
        if($newEventPerms->hasAnyPerms()){
            //Replace the event perms with this one!
            $initialPerms->EventPerms[$event_id] = $newEventPerms;
        } else {
            //Remove it if it was there before.
            unset($initialPerms->EventPerms[$event_id]);
        }
        return $initialPerms;
    }

    public function setPermissions($contact_id, UserPermissions $newPerms)
    {
        //Fetch their permissions (if they have any)
        $founduser = $this->user->GetByIDorUUID($contact_id, null, array('contact_id'));

        if ($founduser !== false) {
            $founduser['permissions'] = $this->packPermissions($newPerms);
            $this->user->Update($founduser);
        } else {
            throw new \Exception('User does not exist');
        }
    }

    private function createSessionRecord($contact_id, $event_id, $session_type, $timestamp, $meta =[], $existing_timestamp = null)
    {
        //If a session with the current timestamp is already created, just set up the action as an update
        if (
            false !== $this->contactSessionModel->GetByID([
                'contact_id' => $contact_id,
                'token_timestamp' => $timestamp
            ], ['contact_id'])
        ) {
            $existing_timestamp = $timestamp;
        }
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $new_timestamp = $timestamp;

        $data = [
            'contact_id' => $contact_id,
            'token_timestamp' => is_null($existing_timestamp) ? $new_timestamp: [$existing_timestamp, $new_timestamp] ,
            'description' => "Session for event $event_id",
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $ip_address,
            'session_type' => $session_type,
            'metadata' => json_encode($meta)
        ];

        if(!$timestamp) throw new \Exception('Attempting to create session with zero timestamp?');
        $this->contactSessionModel->{is_null($existing_timestamp) ? 'Create' : 'Update'}($data);
    }
    public function checkEventID($event_id, $contact_id)
    {
        //Determine the event ID if not provided
        $thedate = date("Y/m/d");
        $eventresult = $this->eventinfo->Search(
            array('id','active'),
            terms: array(
                //This probably doesn't work like we think?
                new SearchTerm('id', $event_id, EncapsulationFunction: 'ifnull(?,0)', EncapsulationColumnOnly:false),
                new SearchTerm('', null, TermType: 'OR', subSearch:array(
                new SearchTerm('date_end', $thedate, ">="),
                new SearchTerm('', CompareValue: $event_id, Raw: '? IS NULL')
              ))
        ),
            order: array(
            'date_start'=> false
        ),
            limit: 1
        );

        if (count($eventresult) == 0) {
            throw new \Exception("Invalid event_id or event not available");
        }

        //If the event is not active, do some extra checks to see if the contact supplied has permissions
        if ($eventresult[0]['active'] == 0) {
            $permissions = $this->loadPermissions($contact_id);
            
            //Check that the event is active and if not, check permissions
            if (!($permissions->IsGlobalAdmin()
            || (isset($permissions->EventPerms[$event_id]) && !$permissions->EventPerms[$event_id]->isNoPermission())
            )) {
                throw new \Exception("No permission to this event");
            }
        }

        return $eventresult[0]['id'];
    }
}
