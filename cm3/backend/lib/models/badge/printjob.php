<?php

namespace CM3_Lib\models\badge;

use CM3_Lib\database\Column as cm_Column;
use CM3_Lib\database\ColumnIndex;

class printjob extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Badge_PrintJob';
        $this->ColumnDefs = array(
            'id' 			=> new cm_Column('BIGINT', null, false, true, false, true, null, true),
            'event_id'		=> new cm_Column('INT', null, false, false, false, true),
            'format_id'		=> new cm_Column('INT', null, false, false, false, true),
            'state'        => new cm_Column('ENUM', array(
                'Queued',
                'Held',
                'Reserved',
                'InProgress',
                'Completed',
                'Batch',
                'Cancelling',
                'Cancelled'
            ), false, defaultValue: "'Queued'"),
            'meta'			=> new cm_Column('VARCHAR', 255, true),
            'data'			=> new cm_Column('TEXT', null, true),
            'result'			=> new cm_Column('VARCHAR', 255, false, defaultValue: "''"),
            'date_created'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP'),
            'date_modified'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP', false, 'ON UPDATE CURRENT_TIMESTAMP'),

        );
        $this->IndexDefs = array(new ColumnIndex(['format_id'=>false,'state'=>false,'meta'=>false]));
        $this->PrimaryKeys = array('id'=>true);
        $this->DefaultSearchColumns = array('format_id','meta','state');
    }
}
