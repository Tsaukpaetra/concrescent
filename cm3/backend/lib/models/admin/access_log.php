<?php

namespace CM3_Lib\models\admin;

use CM3_Lib\database\Column as cm_Column;
use CM3_Lib\database\ColumnIndex as cm_ColumnIndex;

class access_log extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Admin_Access_Log';
        $this->ColumnDefs = array(
            'id'		 	=> new cm_Column('BIGINT', null, false, true, false, true, null, true),
            'timestamp'		=> new cm_Column('TIMESTAMP', null, false, defaultValue:'CURRENT_TIMESTAMP'),
            'event_id' 	=> new cm_Column('INT', null, false),
            'contact_id' 	=> new cm_Column('BIGINT', null, false),
            'remote_addr'	=> new cm_Column('VARCHAR', 255, false),
            'request_uri'	=> new cm_Column('VARCHAR', 255, false),
            'http_referrer'	=> new cm_Column('VARCHAR', 255, false),
            'http_user_agent'	=> new cm_Column('VARCHAR', 255, false),
            'action'	=> new cm_Column('VARCHAR', 255, false),
            'status_code' => new cm_Column('SMALLINT UNSIGNED', null, false),
            'server_duration' => new cm_Column('DECIMAL(8, 2) UNSIGNED', null, false),
            'message'	=> new cm_Column('VARCHAR', 500, false),
            'data'	=> new cm_Column('TEXT', null, true),

        );
        $this->IndexDefs = array(
            'cid' => new cm_ColumnIndex(array(
                'contact_id' => false,
                'remote_addr' => false
            )));
        $this->PrimaryKeys = array('id'=>false);
        $this->DefaultSearchColumns = array('id');
    }
}
