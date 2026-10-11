<?php

namespace CM3_Lib\models\application;

use CM3_Lib\database\Column as cm_Column;

class assignment extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Application_Assignments';
        $this->ColumnDefs = array(
            'id' 			=> new cm_Column('INT', null, false, true, false, true, null, true),
            'application_id'		=> new cm_Column('INT', null, false),
            'location_id'		=> new cm_Column('INT', null, false),
            'category_id'		=> new cm_Column('INT', null, false),
            'start_time'    => new cm_Column('DATETIME', null, true),
            'end_time'      => new cm_Column('DATETIME', null, true),
            'formatted_time_range' => new cm_Column('VARCHAR', '100', null, customPostfix: "GENERATED ALWAYS AS (CASE WHEN DATE(start_time) = DATE(end_time) THEN CONCAT(DATE_FORMAT(start_time, '%b %e, '), DATE_FORMAT(start_time, '%l:%i%p'), ' to ', DATE_FORMAT(end_time, '%l:%i%p')) ELSE CONCAT(DATE_FORMAT(start_time, '%b %e, %l:%i%p'), ' to ', DATE_FORMAT(end_time, '%b %e, %l:%i%p')) END) VIRTUAL"),
            'date_created'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP'),
            'date_modified'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP', false, 'ON UPDATE CURRENT_TIMESTAMP'),
            'notes'			=> new cm_Column('TEXT', null, true),

        );
        $this->IndexDefs = array();
        $this->PrimaryKeys = array('id'=>false);
        $this->DefaultSearchColumns = array('id','application_id','location_id','start_time','end_time');
    }
}
