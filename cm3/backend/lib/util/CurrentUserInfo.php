<?php

namespace CM3_Lib\util;
use MessagePack\BufferUnpacker;

use CM3_Lib\util\EventPermissions;
use CM3_Lib\util\OAuthPermissions;
use CM3_Lib\util\PermOAuth;

use CM3_Lib\database\SearchTerm;

class CurrentUserInfo
{
    public function __construct(
        private \CM3_Lib\models\contact $contact,
    ) {
        $this->perms = new EventPermissions();
        $this->oauth_perms = new PermOAuth(0);
    }

    public function fromToken(string $token){

            //Load up the unpacker
            $unpacker = (new BufferUnpacker())
                ->extendWith(new EventPermissions())
                ->extendWith(new OAuthPermissions());
            $unpacker->reset($token);

            //Get the Contact ID first
            $this->contact_id = $unpacker->unpack();
            //And their selected event ID
            $this->event_id = $unpacker->unpack();

            $this->perms = new EventPermissions();
            $this->oauth_perms = new PermOAuth(0);
            
            //Does this token have permissions?
            while ($unpacker->hasRemaining()) {
                $nextObj = $unpacker->unpack();
                match (get_class($nextObj)) {
                    EventPermissions::class => $this->perms = $nextObj,
                    PermOAuth::class => $this->oauth_perms = $nextObj,
                    default => throw new \Exception("Unexpected object type: " . get_class($nextObj))
                };
            }
    }

    private $event_id = 0;
    public function SetEventId($event_id)
    {
        $this->event_id = $event_id;
    }
    public function GetEventId()
    {
        return $this->event_id;
    }
    public function EventIdSearchTerm(string $event_id_name = 'event_id')
    {
        return new SearchTerm($event_id_name, $this->event_id);
    }
    private $contact_id = 0;
    public function SetContactId($contact_id)
    {
        $this->contact_id = $contact_id;
    }
    public function GetContactId()
    {
        return $this->contact_id;
    }
    public function GetContactEmail(?int $contact_id = null): string
    {
        if ($contact_id == null) {
            $contact_id = $this->contact_id;
        }
        $result = $this->contact->GetByID($contact_id, array('email_address'));
        if ($result === false) {
            return '';
        }
        return $result['email_address'];
    }
    public function GetContactName(?int $contact_id = null): string
    {
        if ($contact_id == null) {
            $contact_id = $this->contact_id;
        }
        $result = $this->contact->GetByID($contact_id, array('real_name'));
        if ($result === false) {
            return '';
        }
        return $result['real_name'];
    }

    private EventPermissions $perms;
    public function SetPerms($perms)
    {
        $this->perms = $perms;
    }
    public function GetPerms()
    {
        return $this->perms;
    }

    private PermOAuth $oauth_perms;
    public function SetOAuthPerms($oauth_perms)
    {
        $this->oauth_perms = $oauth_perms;
    }
    public function GetOAuthPerms()
    {
        return $this->oauth_perms;
    }

    public function HasEventPerm(int $checkPerm)
    {
        if ($this->perms->EventPerms->isGlobalAdmin() || $this->perms->EventPerms->isEventAdmin()) {
            return true;
        }
        return ($this->perms->EventPerms->getValue() & $checkPerm) == $checkPerm;
    }
    public function HasGroupPerm(int $groupId, int $checkPerm)
    {
        if ($this->perms->EventPerms->isGlobalAdmin() || $this->perms->EventPerms->isEventAdmin()) {
            return true;
        }
        if (!isset($this->perms->GroupPerms[$groupId])) {
            return false;
        }
        return ($this->perms->GroupPerms[$groupId]->getValue() & $checkPerm) == $checkPerm;
    }
    public function HasOAuthPermissions(int $checkPerm)
    {
        if ($this->oauth_perms->OAuthPerms->isGlobalAdmin() || $this->oauth_perms->OAuthPerms->isOAuthAdmin()) {
            return true;
        }
        return ($this->oauth_perms->OAuthPerms->getValue() & $checkPerm) == $checkPerm;
    }
}
