<?php

namespace CM3_Lib\models;

use CM3_Lib\database\Column as cm_Column;
use CM3_Lib\database\ColumnIndex;
use CM3_Lib\database\SelectColumn as cm_SelectColumn;
use CM3_Lib\database\View as cm_View;

class contact_session extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'ContactSessions';
        $this->ColumnDefs = array(
            'contact_id'		=> new cm_Column('BIGINT', null, false, false, false, true),
            'token_timestamp' => new cm_Column('int', null, false, false, false, true),
            'description'	=> new cm_Column('VARCHAR', '500', true),
            'user_agent'		=> new cm_Column('VARCHAR', '255', true),
            'ip_address'		=> new cm_Column('VARCHAR', '45', true),
            'session_type'	=> new cm_Column('VARCHAR', '50', true, defaultValue: '"standard"'),
            'metadata'		=> new cm_Column('JSON', null, true),
            'date_created'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP'),
            'date_modified'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP', false, 'ON UPDATE CURRENT_TIMESTAMP')
        );
        $this->IndexDefs = array(new ColumnIndex(['contact_id','token_timestamp'],'PRIMARY KEY'));
        $this->PrimaryKeys = array('contact_id'=>false, 'token_timestamp' => false);
        $this->DefaultSearchColumns = array('contact_id','token_timestamp','description','ip_address','session_type','date_modified');
        $this->Views = array(
            'main' => new cm_View(
                array(
                    new cm_SelectColumn('id'),
                    new cm_SelectColumn('contact_id'),
                    new cm_SelectColumn('description'),
                    new cm_SelectColumn('expiration_timestamp'),
                    new cm_SelectColumn('session_type'),
                    new cm_SelectColumn('user_agent'),
                    new cm_SelectColumn('ip_address')
                )
            ));
    }

    public function terminateSession($contact_id, $timestamp): bool
    {
        return $this->Delete(array(
            'contact_id' => $contact_id,
            'token_timestamp' => $timestamp
        ));

    }

    public function updateSession($contact_id, $timestamp, $newTimestamp): bool
    {
        $result = $this->Update(array(
            'id' => $contact_id,
            'token_timestamp' => [$timestamp, $newTimestamp]
        ));
        
        return $result !== false;
    }
}
