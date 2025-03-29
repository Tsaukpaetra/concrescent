<?php

namespace CM3_Lib\models\application;

use CM3_Lib\database\Column as cm_Column;
use CM3_Lib\database\ColumnIndex;

class submission extends \CM3_Lib\database\Table
{
    protected function setupTableDefinitions(): void
    {
        $this->TableName = 'Application_Submissions';
        $this->ColumnDefs = array(
            'id' 			=> new cm_Column('INT', null, false, true, false, true, null, true),
            'badge_type_id'		=> new cm_Column('INT', null, false),
            'contact_id'	=> new cm_Column('BIGINT', null, false, false, false, false),
            'uuid_raw'		=> new cm_Column('BINARY', 16, false, false, true, false, '(UNHEX(REPLACE(UUID(),\'-\',\'\')))'),
            'uuid'			=> new cm_Column('CHAR', 36, null, false, false, false, null, false, 'GENERATED ALWAYS as (LOWER(CONCAT_WS(\'-\',SUBSTR(HEX(`uuid_raw`), 1, 8),SUBSTR(HEX(`uuid_raw`), 9, 4),SUBSTR(HEX(`uuid_raw`), 13, 4),SUBSTR(HEX(`uuid_raw`), 17, 4),SUBSTR(HEX(`uuid_raw`), 21)))) VIRTUAL'),
            'display_id'	=> new cm_Column('INT', null, true),
            'hidden'        => new cm_Column('BOOLEAN', null, false, defaultValue: 'false'),

            'real_name'		=> new cm_Column('VARCHAR', '500', false),
            'fandom_name'	=> new cm_Column('VARCHAR', '255', true),
            'name_on_badge'	=> new cm_Column(
                'ENUM',
                array(
                    'Fandom Name Large, Real Name Small',
                    'Real Name Large, Fandom Name Small',
                    'Fandom Name Only',
                    'Real Name Only'
                ),
                false,
                defaultValue: "'Real Name Only'"
            ),
            'applicant_count' => new cm_Column('INT', null, false, defaultValue: '0'),
            'assignment_count' => new cm_Column('INT', null, false, defaultValue: '0'),
            'application_status'		=> new cm_Column(
                'ENUM',
                array(
                    'InProgress',
                    'Submitted',
                    'Cancelled',
                    'Rejected',
                    'PendingAcceptance',
                    'Accepted',
                    'Waitlisted',
                ),
                false
            ),

            /* Payment Info */
        'payment_badge_price'	=> new cm_Column('DECIMAL', '7,2', false),
        'payment_promo_code' 	=> new cm_Column('VARCHAR', '255', true),
        'payment_promo_price'	=> new cm_Column('DECIMAL', '7,2', true),
        'payment_id'		=> new cm_Column('BIGINT', null, true),
        'payment_id_hist'	=> new cm_Column('VARCHAR', 740, true),
        'payment_status'		=> new cm_Column(
            'ENUM',
            array(
                'NotStarted',
                'Incomplete',
                'Cancelled',
                'Rejected',
                'Completed',
                'Refunded',
                'RefundedInPart',
            ),
            false,
            defaultValue: '\'NotStarted\''
        ),

            'date_created'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP'),
            'date_modified'	=> new cm_Column('TIMESTAMP', null, false, false, false, false, 'CURRENT_TIMESTAMP', false, 'ON UPDATE CURRENT_TIMESTAMP'),
            'notes'			=> new cm_Column('TEXT', null, true),
            //Generated columns

        );
        $this->IndexDefs = array('ft_name' => new ColumnIndex(array(
            'real_name' =>false,
            'fandom_name'=>false
        ), 'fulltext'));
        $this->PrimaryKeys = array('id'=>false);
        $this->DefaultSearchColumns = array('id','application_name1','application_name2','display_id','application_status');
    }
}
