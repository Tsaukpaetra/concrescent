<?php

namespace CM3_Lib\models\application;

use CM3_Lib\database\Column as cm_Column;

class locationcategory extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Application_Location_Category';
        $this->ColumnDefs = array(
            'id' 			=> new cm_Column('INT', null, false, true, false, true, null, true),
            'event_id'		=> new cm_Column('INT', null, false),
            'active'        => new cm_Column('BOOLEAN', null, false, defaultValue: 'true'),
            'name'          => new cm_Column('VARCHAR', '255', false),
            'color'          => new cm_Column('VARCHAR', '10', false),
            'description'   => new cm_Column('TEXT', null, true),

            'date_created'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP'),
            'date_modified'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP', false, 'ON UPDATE CURRENT_TIMESTAMP'),
            'notes'			=> new cm_Column('TEXT', null, true),

        );
        $this->IndexDefs = array();
        $this->PrimaryKeys = array('id'=>false);
        $this->DefaultSearchColumns = array('id','name','color', 'active');
    }
    public function verifyCategoryBelongsToEvent(int $id, int $event_id)
    {
        $bt = $this->GetByIDorUUID($id, array('event_id'));
        if ($bt === false) {
            return false;
        }
        if ($bt['event_id'] != $event_id) {
            return false;
        }
        return true;
    }
}
