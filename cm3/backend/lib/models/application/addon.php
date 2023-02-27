<?php

namespace CM3_Lib\models\application;

use CM3_Lib\database\Column as cm_Column;

class addon extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Application_Addons';
        $this->ColumnDefs = array(
            'id' 			=> new cm_Column('INT', null, false, true, false, true, null, true),
            'group_id'      => new cm_Column('INT', null, false),
            'active'        => new cm_Column('BOOLEAN', null, false, defaultValue: 'false'),
            'display_order' => new cm_Column('INT', null, false),
            'name'          => new cm_Column('VARCHAR', '255', false),
            'description'   => new cm_Column('TEXT', null, true),
            'rewards'       => new cm_Column('TEXT', null, true),
            'price'         => new cm_Column('DECIMAL', '7,2', false),
            'payable_onsite'=> new cm_Column('BOOLEAN', null, false, defaultValue: 'false'),
            'quantity'      => new cm_Column('INT', null, true),
            'start_date'	=> new cm_Column('DATE', null, true),
            'end_date'  	=> new cm_Column('DATE', null, true),
            'min_age'   	=> new cm_Column('INT', null, true),
            'max_age'     	=> new cm_Column('INT', null, true),
            'active_override_code' => new cm_Column('VARCHAR', '255', true),
            'date_created'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP'),
            'date_modified'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP', false, 'ON UPDATE CURRENT_TIMESTAMP'),
            'notes'			=> new cm_Column('TEXT', null, true),
            //Generated columns
            'dates_available' => new cm_Column('VARCHAR', '50', null, customPostfix: 'GENERATED ALWAYS as (concat(case `start_date` is null when true then \'forever\' else `start_date` end,\' to \', case end_date is null when true then \'forever\' else `end_date` end)) VIRTUAL'),

        );
        $this->IndexDefs = array();
        $this->PrimaryKeys = array('id'=>false);
        $this->DefaultSearchColumns = array('id','name','price','quantity','dates_available','active');
    }

    public function verifyAddonBelongsToGroup(int $id, int $group_id)
    {
        $bt = $this->GetByID($id, array('group_id'));
        if ($bt === false) {
            return false;
        }
        if ($bt['group_id'] != $group_id) {
            return false;
        }
        return true;
    }
}
