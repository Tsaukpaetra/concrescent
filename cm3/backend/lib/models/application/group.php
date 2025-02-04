<?php

namespace CM3_Lib\models\application;

use CM3_Lib\database\Column as cm_Column;

class group extends \CM3_Lib\database\Table
{
    use \CM3_Lib\database\orderableTrait;
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Application_Groups';
        $this->ColumnDefs = array(
            'id' 			=> new cm_Column('INT', null, false, true, false, true, null, true),
            'event_id'		=> new cm_Column('INT', null, false),
            'context_code'	=> new cm_Column('VARCHAR', '3', false),
            'active'        => new cm_Column('BOOLEAN', null, false, defaultValue: 'true'),
            //Whether applications in the group can be assigned a location/time slot
            'can_assign_slot'    => new cm_Column('BOOLEAN', null, false, defaultValue: 'true'),
            'display_order'					=> new cm_Column('TINYINT', null, false),
            'name'          => new cm_Column('VARCHAR', '255', false),
            'menu_icon'     => new cm_Column('VARCHAR', '255', true),
            'description'   => new cm_Column('TEXT', null, true),
            'application_name1'          => new cm_Column('VARCHAR', '255', false),
            'application_name2'          => new cm_Column('VARCHAR', '255', true),

            'date_created'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP'),
            'date_modified'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP', false, 'ON UPDATE CURRENT_TIMESTAMP'),
            'notes'			=> new cm_Column('TEXT', null, true),

        );
        $this->IndexDefs = array();
        $this->PrimaryKeys = array('id'=>false);
        $this->DefaultSearchColumns = array('id','context_code','name','menu_icon','active','display_order');
        
        //OrderableTrait defs
        $this->orderColumn = 'display_order';
        $this->orderGroupColumns = ['event_id'];
    }
}
