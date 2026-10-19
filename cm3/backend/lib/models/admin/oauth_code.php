<?php

namespace CM3_Lib\models\admin;

use CM3_Lib\database\Column as cm_Column;

class oauth_code extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Admin_Oauth_Codes';
        $this->ColumnDefs = array(
            'code_hash' => new cm_Column('CHAR', 64, false, true, false, true, null), // SHA256 of the code
            'client_id' => new cm_Column('int', null, false, false, false, true, null),
            'contact_id' => new cm_Column('int', null, false, false, false, true, null),
            'event_id' => new cm_Column('int', null, false, false, false, true, null),
            'scopes'    => new cm_Column('INT', null, false, defaultValue: 0),
            'expires_at'=> new cm_Column('TIMESTAMP', null, false),
            'pkce_data' => new cm_Column('VARCHAR',1024),
            'date_created' => new cm_Column('TIMESTAMP', null, false, defaultValue: 'CURRENT_TIMESTAMP'),
        );
        $this->PrimaryKeys = array('code_hash' => true);
    }
}