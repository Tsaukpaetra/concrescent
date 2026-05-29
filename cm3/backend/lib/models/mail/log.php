<?php

namespace CM3_Lib\models\mail;

use CM3_Lib\database\Column as cm_Column;
use CM3_Lib\database\ColumnIndex;

class log extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Mail_Log';
        $this->ColumnDefs = array(
            'id' 			=> new cm_Column('BIGINT', null, false, true, false, true, null, true),
            'event_id'		=> new cm_Column('INT', null, false),            
            'context_code'	=> new cm_Column('VARCHAR', '3', false),
            'template'			=> new cm_Column('VARCHAR', 255, false),
            'success'        => new cm_Column('BOOLEAN', null, false, defaultValue: 'false'),
            'contact_id'	=> new cm_Column('BIGINT', null, false, false, false, false),
            'sender_id'	    => new cm_Column('BIGINT', null, false, false, false, false),
            'meta'			=> new cm_Column('VARCHAR', 255, true),
            'data'			=> new cm_Column('TEXT', null, true),
            'result'			=> new cm_Column('VARCHAR', 255, false)
        );
        $this->IndexDefs = array(new ColumnIndex(['contact_id']));
        $this->PrimaryKeys = array('id'=>false);
        $this->DefaultSearchColumns = array('contact_id','event_id','context_code','name','meta','result');
    }
}
