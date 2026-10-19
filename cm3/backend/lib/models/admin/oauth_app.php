<?php

namespace CM3_Lib\models\admin;

use CM3_Lib\database\Column as cm_Column;
use CM3_Lib\database\ColumnIndex as cm_ColumnIndex;

class oauth_app extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Admin_Oauth_Apps';
        $this->ColumnDefs = array(
            'client_id' => new cm_Column('int', null, false, true, false, true, null, true),
            'contact_id' => new cm_Column('BIGINT', defaultValue: '0'),
            'active' => new cm_Column('BOOLEAN', null, false, defaultValue: 'false'),
            'app_name' => new cm_Column('VARCHAR', 255, false),
            'logo_url' => new cm_Column('VARCHAR', 255, true),
            'developer_name' => new cm_Column('VARCHAR', 255, true),
            'developer_email' => new cm_Column('VARCHAR', 255, true),
            'developer_url' => new cm_Column('VARCHAR', 255, true),
            'redirect_uri' => new cm_Column('VARCHAR', 4096, true),
            'date_created' => new cm_Column('TIMESTAMP', null, false, defaultValue: 'CURRENT_TIMESTAMP'),
            'date_token' => new cm_Column('TIMESTAMP', null, false, defaultValue: 'CURRENT_TIMESTAMP'),
            'date_modified' => new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP', false, 'ON UPDATE CURRENT_TIMESTAMP'),
        );
        $this->IndexDefs = array(
            'client_id' => new cm_ColumnIndex(array(
                'client_id' => false
            )),
            'app_name' => new cm_ColumnIndex(array(
                'app_name' => false
            ))
        );
        $this->PrimaryKeys = array('client_id' => false);
        $this->DefaultSearchColumns = array('client_id', 'active', 'app_name', 'developer_name');
    }
}
