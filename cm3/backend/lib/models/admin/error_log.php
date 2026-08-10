<?php

namespace CM3_Lib\models\admin;

use CM3_Lib\database\Column as cm_Column;
use CM3_Lib\database\ColumnIndex as cm_ColumnIndex;

class error_log extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Admin_Error_Log';
        $this->ColumnDefs = array(
            'id'		 	=> new cm_Column('BIGINT', null, false, true, false, true, null, true),
            'timestamp'		=> new cm_Column('TIMESTAMP', null, false, defaultValue:'CURRENT_TIMESTAMP'),
            'event_id' 	=> new cm_Column('INT', null, true),
            'contact_id' 	=> new cm_Column('BIGINT', null, true),
            'remote_addr'	=> new cm_Column('VARCHAR', 255, false),
            'request_uri'	=> new cm_Column('VARCHAR', 255, false),
            'message'	=> new cm_Column('VARCHAR', 500, false),
            'level'	=> new cm_Column('VARCHAR', 10, false),
            'channel'	=> new cm_Column('VARCHAR', 40, false),
            'data'	=> new cm_Column('TEXT', null, true),

        );
        $this->IndexDefs = array(
            'cid' => new cm_ColumnIndex(array(
                'contact_id' => false,
                'remote_addr' => false
            )));
        $this->PrimaryKeys = array('id'=>false);
        $this->DefaultSearchColumns = array('id','timestamp','remote_addr','request_uri','message');
    }

}
