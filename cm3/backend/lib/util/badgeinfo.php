<?php

namespace CM3_Lib\util;

use CM3_Lib\database\SearchDefinition;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;

use CM3_Lib\models\attendee\badgetype as a_badge_type;
use CM3_Lib\models\application\badgetype as g_badge_type;
use CM3_Lib\models\staff\badgetype as s_badge_type;
use CM3_Lib\models\attendee\badge as a_badge;
use CM3_Lib\models\attendee\addon as a_addon;
use CM3_Lib\models\attendee\addonmap as a_addonmap;
use CM3_Lib\models\attendee\addonpurchase as a_addonpurchase;
use CM3_Lib\models\application\submission as g_badge_submission;
use CM3_Lib\models\application\submissionapplicant as g_badge;
use CM3_Lib\models\application\group as g_group;
use CM3_Lib\models\application\addon as g_addon;
use CM3_Lib\models\application\addonmap as g_addonmap;
use CM3_Lib\models\application\addonpurchase as g_addonpurchase;
use CM3_Lib\models\application\assignment as g_assignment;
use CM3_Lib\models\application\location as g_location;
use CM3_Lib\models\staff\badge as s_badge;
use CM3_Lib\models\forms\question as f_question;
use CM3_Lib\models\forms\response as f_response;
use CM3_Lib\models\contact as contact;
use CM3_Lib\models\eventinfo as eventinfo;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\util\barcode;
use CM3_Lib\util\FrontendUrlTranslator;
use CM3_Lib\util\PermEvent;

use CM3_Lib\models\staff\department as s_department;
use CM3_Lib\models\staff\position as s_position;
use CM3_Lib\models\staff\assignedposition as s_assignedposition;

/**
 * Action.
 */
final class badgeinfo
{
    public function __construct(
        private a_badge_type $a_badge_type,
        private g_badge_type $g_badge_type,
        private s_badge_type $s_badge_type,
        private a_badge $a_badge,
        private a_addon $a_addon,
        private a_addonmap $a_addonmap,
        private a_addonpurchase $a_addonpurchase,
        private g_badge $g_badge,
        private s_badge $s_badge,
        private g_group $g_group,
        private g_badge_submission $g_badge_submission,
        private g_addon $g_addon,
        private g_addonmap $g_addonmap,
        private g_addonpurchase $g_addonpurchase,
        private g_assignment $g_assignment,
        private g_location $g_location,
        private f_question $f_question,
        private f_response $f_response,
        private CurrentUserInfo $CurrentUserInfo,
        private FrontendUrlTranslator $FrontendUrlTranslator,
        private s_assignedposition $s_assignedposition,
        private s_department $s_department,
        private s_position $s_position,
        private contact $contact,
        private eventinfo $eventinfo,
    ) {


        //If user has ICE permissions, add that in
        if ($this->CurrentUserInfo->hasEventPerm(PermEvent::Badge_Ice)) {
            $this->selectColumns =array_merge(
                $this->selectColumns,
                [
                'ice_name',
                'ice_relationship',
                'ice_email_address',
                'ice_phone_number',
                ]
            );
        }
    }


    private $selectColumns = array(
      'id',
      'uuid',
      'display_id',
      'contact_id',
      'real_name',
      'fandom_name',
      'name_on_badge',
      'date_of_birth',
      'notify_email',
      'time_printed',
      'time_checked_in'
      //View will add:
      //context_code
      //application_status
      //badge_type_id
      //payment_status
      //badge_type_name

      //Full view will add:
      //payment_id
      //notes
      //large_name
      //small_name
      //application_name
      //assigned_slot_name (work in progress name)
      //assigned_department
      //assigned_position
      //assigned_department_and_position_concat
      //assigned_department_and_position_hyphen
      //badge_id_display
      //uuid
      //qr_data

    );

    public function CreateSpecificBadgeUnchecked(&$data, $allowedColumns = null)
    {
        $result = false;
        //Filter in the allowed columns to update
        if ($allowedColumns != null) {
            $data = array_intersect_key($data, array_flip($allowedColumns));
        }
        //Actually don't accept updates to ID and uuid
        unset($data['id']);
        unset($data['uuid']);

        switch ($data['context_code']) {
            case 'A':
                $result =  $this->a_badge->Create($data);
                break;
            case 'S':
                $result =  $this->s_badge->Create($data);
                break;
            default:
                $result =  $this->g_badge->Create($data);
        }
        //Also set supplementary data
        $data['id'] = $result['id'];
        $this->updateSupplementaryBadgeData($data);
        return $result;
    }

    public function CreateSpecificGroupApplicationUnchecked(&$data, $allowedColumns = null)
    {
        $result = false;
        //Filter in the allowed columns to update
        if ($allowedColumns != null) {
            $data = array_intersect_key($data, array_flip($allowedColumns));
        }
        //Actually don't accept updates to ID and uuid
        unset($data['id']);
        unset($data['uuid']);

        $result =  $this->g_badge_submission->Create($data);
        if ($result!==false) {
            //Also set supplementary data
            $data['id'] = $result['id'];
            //Also create supplementary data if provided
            $this->updateSupplementaryBadgeData($data);
        }
        return $result;
    }

    public function GetSpecificBadge($id, $context_code, $full = false)
    {
        $result = false;
        switch ($context_code) {
            case 'A':
                $result =  $this->getASpecificBadge($id, $this->a_badge, $this->a_badge_type, 'A', $full ? $this->badgeViewFullAddAttendee() : null);
                if ($result !== false) {
                    $result['addons'] = [];
                    $a_addons = $this->a_addonpurchase->Search(array(
                    'addon_id',
                    'payment_status'
                ), array(
                    new SearchTerm('attendee_id', $id)
                ));
                    foreach ($a_addons as $addon) {
                        $result['addons'][] = array(
                        'addon_id' => $addon['addon_id'],
                        'addon_payment_status' => $addon['payment_status']
                    );
                    }
                }

                break;
            case 'S':
                $result =  $this->getASpecificBadge($id, $this->s_badge, $this->s_badge_type, 'S', $full ? $this->badgeViewFullAddStaff() : null);
                break;
            default:
                $result =  $this->getASpecificGroupBadge($id, $full ? $this->badgeViewFullAddGroup() : null);
                if ($result !== false) {
                    $result['addons'] = [];
                    $g_addons = $this->g_addonpurchase->Search(array(
                    'addon_id',
                    'payment_status'
                ), array(

                    new SearchTerm('application_id', $id)
                ));
                    foreach ($g_addons as $addon) {
                        $result['addons'][] = array(
                        'addon_id' => $addon['addon_id'],
                        'addon_payment_status' => $addon['payment_status']
                    );
                    }
                }
        }

        if ($result === false || !$full) {
            return $result;
        }
        //add badge type info
        $result['badge_info'] = $this->getBadgetType($context_code, $result['badge_type_id']);
        //Add in form responses
        $result['form_responses'] = $this->GetFormResponses($id, $context_code);
        //Add in supplementary
        $this->addSupplementaryBadgeData($result);
        return $this->addComputedColumns($result, true);
    }

    public function UpdateSpecificBadgeUnchecked($id, $context_code, &$data, $allowedColumns = null)
    {
        $result = false;
        //Filter in the allowed columns to update
        if ($allowedColumns != null) {
            $data = array_intersect_key($data, array_flip($allowedColumns));
        }
        //Actually don't accept updates to uuid
        unset($data['uuid']);
        //Slide in the ID
        $data['id'] = $id;
        switch ($context_code) {
            case 'A':
                $result =  $this->a_badge->Update($data);
                break;
            case 'S':
                $result =  $this->s_badge->Update($data);
                break;
            default:
                $result =  $this->g_badge->Update($data);
        }
        //Also update supplementary data if provided
        $this->updateSupplementaryBadgeData($data);
        return $result;
    }

    public function UpdateSpecificGroupApplicationUnchecked($id, $context_code, &$data, $allowedColumns = null)
    {
        $result = false;
        //Filter in the allowed columns to update
        if ($allowedColumns != null) {
            $data = array_intersect_key($data, array_flip($allowedColumns));
        }
        //Actually don't accept updates to uuid
        unset($data['uuid']);
        //TODO: Make sure the badge belongs to the context specified?
        //Slide in the ID
        $data['id'] = $id;
        $result =  $this->g_badge_submission->Update($data);
        //Also update supplementary data if provided
        $this->updateSupplementaryBadgeData($data);
        return $result;
    }

    public function setNextDisplayIDSpecificBadge($id, $context_code)
    {
        //Note that this is way unsafe in terms of concurrency.
        //Hopefully not a lot of people will be simultaneously creating IDs...
        $result = false;
        //Slide in the ID
        $data = array('id' => $id);
        switch ($context_code) {
            case 'A':
                $next = $this->a_badge->Search(
                    new View(
                        array(
                       'display_id',
                    ),
                        array(
                       new Join(
                           $this->a_badge_type,
                           array(
                             'id' => 'badge_type_id',
                             $this->CurrentUserInfo->EventIdSearchTerm()
                           ),
                           alias:'typ'
                       )
                     )
                    ),
                    array(
                        new SearchTerm('display_id', '', 'IS NOT')
                    ),
                    array('display_id'=>true),
                    1
                );
                $data['display_id'] = (count($next) > 0) ? $next[0]['display_id'] + 1 : 1;
                $result =  $this->a_badge->Update($data);
                break;
            case 'S':
                $next = $this->s_badge->Search(
                    new View(
                        array(
                       'display_id',
                    ),
                        array(
                       new Join(
                           $this->s_badge_type,
                           array(
                             'id' => 'badge_type_id',
                             new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
                           ),
                           alias:'typ'
                       )
                     )
                    ),
                    array(
                        new SearchTerm('display_id', '', 'IS NOT')
                    ),
                    array('display_id'=>true),
                    1
                );
                $data['display_id'] = (count($next) > 0) ? $next[0]['display_id'] + 1 : 1;
                $result =  $this->s_badge->Update($data);
                break;
            default:
            //TODO: Fix
                $next = $this->g_badge_submission->Search(
                    new View(
                        array(
                            'display_id'
                         ),
                        array(
                           new Join(
                               $this->g_badge_type,
                               array(
                                 'id' =>new SearchTerm('badge_type_id', null),
                               ),
                               alias:'typ'
                           ),
                          new Join(
                              $this->g_group,
                              array(
                                'id' => new SearchTerm('group_id', null, JoinedTableAlias: 'typ'),
                                new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId()),
                                new SearchTerm('context_code', $context_code)
                              ),
                              alias:'grp'
                          )
                         )
                    ),
                    array(
                        new SearchTerm('display_id', '', 'IS NOT')
                    ),
                    array('display_id'=>true),
                    1
                );
                $data['display_id'] = (count($next) > 0) ? $next[0]['display_id'] + 1 : 1;

                $result =  $this->g_badge_submission->Update($data);
        }
        return $data['display_id'];
    }

    public function setNextDisplayIDSpecificSubBadge($id, $context_code)
    {
        //Note that this is way unsafe in terms of concurrency.
        //Hopefully not a lot of people will be simultaneously creating IDs...
        $result = false;
        //Slide in the ID
        $data = array('id' => $id);
        $next = $this->g_badge->Search(
            new View(
                array(
                    'display_id'
                 ),
                array(
                   new Join(
                       $this->g_badge_submission,
                       array(
                         'id' => 'application_id',
                       ),
                       alias:'bs'
                   ),

                   new Join(
                       $this->g_badge_type,
                       array(
                         'id' =>new SearchTerm('badge_type_id', null, JoinedTableAlias: 'bs'),
                       ),
                       alias:'typ'
                   ),
                  new Join(
                      $this->g_group,
                      array(
                        'id' => new SearchTerm('group_id', null, JoinedTableAlias: 'typ'),
                        new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId()),
                        new SearchTerm('context_code', $context_code)
                      ),
                      alias:'grp'
                  )
                 )
            ),
            array(
                        new SearchTerm('display_id', '', 'IS NOT')
                    ),
            array('display_id'=>true),
            1
        );

        $data['display_id'] = (count($next) > 0) ? $next[0]['display_id'] + 1 : 1;

        $result =  $this->g_badge->Update($data);
        return $data['display_id'];
    }
    public function GetFormResponses($id, $context_code, $question_ids = null): array
    {
        $data = $this->f_response->Search(
            array('question_id','response',is_array($id) ? 'context_id' : null),
            array(
                new SearchTerm('context_code', $context_code),
                new SearchTerm('context_id', $id, is_array($id) ? 'IN' : '='),
                is_null($question_ids) ? null : new SearchTerm('question_id', $question_ids, 'IN')
            )
        );
        //Zip together the responses, if single context supplied
        if (!is_array($id)) {
            return array_combine(array_column($data, 'question_id'), array_column($data, 'response'));
        } else {
            $result = array_flip($id);
            foreach ($result as $key => &$value) {
                $data2 = array_filter($data, function ($item) use ($key) {
                    return $item['context_id'] == $key;
                });
                $value = array_combine(array_column($data2, 'question_id'), array_column($data2, 'response'));
            }
            return $result;
        }
    }

    public function SetFormResponses($id, $context_code, $responses)
    {
        //First fetch any that might exist already
        $existing =array_flip(array_column($this->f_response->Search(
            array('question_id'),
            array(
                new SearchTerm('context_code', $context_code),
                new SearchTerm('context_id', $id),
            )
        ), 'question_id'));
        //Create/Update
        foreach ($responses as $question_id => $response) {
            $item = array(
                'context_code' => $context_code,
                'context_id' => $id,
                'question_id' => $question_id,
                'response' => $response
            );
            if (isset($existing[$question_id])) {
                $this->f_response->Update($item);
            } else {
                $this->f_response->Create($item);
            }
        }
        //Delete the missing ones
        foreach (array_diff_key($existing, $responses) as $question_id => $response) {
            $item = array(
                'context_code' => $context_code,
                'context_id' => $id,
                'question_id' => $question_id
            );
            $this->f_response->Delete($item);
        }
    }

    public function GetValidContexts(bool $only_active = true) 
    {
        return array_merge( ['A'],
        array_column($this->g_group->Search(['context_code'],[
            $this->CurrentUserInfo->EventIdSearchTerm(),
            $only_active ? new SearchTerm('active', 1) : null
        ]),'context_code')
        ,['S']);
    }

    public function GetValidTypeIdsForContext(string $context_code, bool $only_active = true)
    {
        switch ($context_code) {
            case 'A':
                return array_column($this->a_badge_type->Search(
                    array('id'),
                    array(
                        $this->CurrentUserInfo->EventIdSearchTerm(),
                        $only_active ? new SearchTerm('active', 1) : null
                    )
                ), 'id');
            case 'S':
                return array_column($this->s_badge_type->Search(
                    array('id'),
                    array(
                        $this->CurrentUserInfo->EventIdSearchTerm(),
                        $only_active ? new SearchTerm('active', 1) : null
                    )
                ), 'id');
            default:
                return array_column($this->g_badge_type->Search(
                    new View(
                        array('id'),
                        array(new Join(
                            $this->g_group,
                            array(
                              'id' => 'group_id',
                            ),
                            alias:'grp'
                        ))
                    ),
                    array(
                        $only_active ? new SearchTerm('active', 1) : null,
                        new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId(), JoinedTableAlias: 'grp'),
                        new SearchTerm('context_code', $context_code, JoinedTableAlias: 'grp')
                    )
                ), 'id');
        }
    }


    public function GetAddonsAvailable($badge_type_id, $context_code)
    {
        switch ($context_code) {
            case 'A':
                return $this->GetAttendeeAddonsAvailable($badge_type_id);
            case 'S':
                return [];
            default:
                return $this->GetApplicationAddonsAvailable($badge_type_id);
        }
    }
    public function GetAttendeeAddonsAvailable($badge_type_id)
    {
        return $this->a_addon->Search(
            new View(
                array(
                'id',
                'active',
                'name',
                'description',
                'price',
                'payable_onsite',
            ),
                array(

               new Join(
                   $this->a_addonmap,
                   array(
                     'addon_id' => 'id',
                     new SearchTerm('badge_type_id', $badge_type_id)
                   ),
                   alias:'map'
               )
            )
            ),
            array(
                $this->CurrentUserInfo->EventIdSearchTerm()
            )
        );
    }
    public function GetApplicationAddonsAvailable($badge_type_id)
    {
        return $this->g_addon->Search(
            new View(
                array(
                'id',
                'active',
                'name',
                'description',
                'price',
                'payable_onsite',
            ),
                array(

               new Join(
                   $this->g_addonmap,
                   array(
                     'addon_id' => 'id',
                     new SearchTerm('badge_type_id', $badge_type_id)
                   ),
                   alias:'map'
               )
            )
            ),
            array(
            )
        );
    }
    public function GetAddons($id, $context_code)
    {
        switch ($context_code) {
            case 'A':
                return $this->GetAttendeeAddons($id);
            case 'S':
                return [];
            default:
                return $this->GetApplicationAddons($id);
        }
    }
    public function GetAttendeeAddons($attendee_id)
    {
        return $this->a_addonpurchase->Search(
            array(
                'addon_id',
                'payment_id',
                'payment_status'
            ),
            array(
                new SearchTerm('attendee_id', $attendee_id)
            )
        );
    }
    public function GetApplicationAddons($attendee_id)
    {
        return $this->g_addonpurchase->Search(
            array(
                'addon_id',
                'payment_id',
                'payment_status'
            ),
            array(
                new SearchTerm('application_id', $attendee_id)
            )
        );
    }
    public function AddUpdateBadgeAddonUnchecked($data)
    {
        switch ($data['context_code'] ??'A') {
            case 'A':
                return $this->AddUpdateABadgeAddonUnchecked($data);
            case 'S':
                return [];
            default:
                return $this->AddUpdateGBadgeAddonUnchecked($data);
        }
    }
    public function AddUpdateABadgeAddonUnchecked(&$data)
    {
        $current = $this->a_addonpurchase->Exists(
            array(
                'attendee_id'=> $data['attendee_id'] ?? 0,
                'addon_id'=> $data['addon_id'] ?? 0,
            )
        );
        if ($current > 0) {
            return $this->a_addonpurchase->Update($data);
        } else {
            return $this->a_addonpurchase->Create($data);
        }
    }

    public function AddUpdateGBadgeAddonUnchecked(&$data)
    {
        $current = $this->g_addonpurchase->Exists(
            array(
                'application_id'=> $data['application_id'] ?? 0,
                'addon_id'=> $data['addon_id'] ?? 0,
            )
        );
        if ($current > 0) {
            return $this->g_addonpurchase->Update($data);
        } else {
            return $this->g_addonpurchase->Create($data);
        }
    }

    public function SearchSpecificBadge($uuid, $context_code, $full)
    {
        $result = false;
        switch ($context_code) {
            case 'A':
                $result =  $this->searchASpecificBadge($uuid, $this->a_badge, $this->a_badge_type, 'A', $full ? $this->badgeViewFullAddAttendee() : null);

                break;
            case 'S':
                $result =  $this->searchASpecificBadge($uuid, $this->s_badge, $this->s_badge_type, 'S', $full ? $this->badgeViewFullAddStaff() : null);
                break;
            default:
                $result =  $this->searchASpecificGroupBadge($uuid, $full ? $this->badgeViewFullAddGroup() : null);
        }
        if ($result === false || !$full) {
            return $result;
        }
        return $this->addComputedColumns($result);
    }

    public function SearchBadgesFromGroup($uuid, $full, &$totalRows = null,) {

        $g_data = $this->g_badge_submission->GetByIDorUUID(null,  $uuid,['id']);
        if($g_data === false){
            return [];
        }
        $gid = $g_data['id'];
        $g_bv = $this->groupBadgeView();
        if ($full) {
            $this->MergeView($g_bv, $this->badgeViewFullAddGroup());
        }
        $g_data = $this->g_badge->Search($g_bv, [
            new SearchTerm('application_id',$gid)
        ], resultTotal: $totalRows, initialTableAlias: 'b');

        foreach ($g_data as &$badge) {
            $badge = $this->addComputedColumns($badge, false);
        }

        return $g_data;

    }

    public function SearchBadgesText($context, string $searchText, $order, $limit, $offset, &$totalRows, $includeFormQuestions = null,  string|array $filter = [])
    {
        $whereParts =
        empty($searchText) ? [] :
        array(
            new SearchTerm('real_name', $searchText, Raw: 'MATCH(`b`.`real_name`, `b`.`fandom_name`, `b`.`notify_email`, `ice_name`, `ice_email_address`) AGAINST (? IN NATURAL LANGUAGE MODE) ')
        );
        $wherePartsSimpler = array(
            new SearchTerm(
                '',
                '',
                subSearch: array(
                    new SearchTerm('real_name', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('fandom_name', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('notify_email', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('ice_name', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('ice_email_address', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('email_address', '%' . $searchText . '%', 'LIKE', 'OR', JoinedTableAlias:'con'),
                )
            ));
        //If it looks like they specified a badge ID, add that to the search term
        preg_match_all('/(?\'context_code\'[a-zA-Z]{0,3})(?\'display_id\'\d+)/m', $searchText, $badgeMatches, PREG_SET_ORDER, 0);
        foreach ($badgeMatches as $exactSearch) {
            $whereParts[]= new SearchTerm('', '', TermType:'OR', subSearch: array(
                    new SearchTerm('context_code', $exactSearch['context_code'], JoinedTableAlias:'grp'),
                    new SearchTerm('display_id', $exactSearch['display_id'], )
                ));
        }
        
        //If it looks like an internal ID, add  it to the search term
        preg_match_all('/^(?\'id\'\d+)$/m', $searchText, $badgeMatches, PREG_SET_ORDER, 0);
        foreach ($badgeMatches as $exactSearch) {
            $whereParts[]= new SearchTerm('', '', TermType:'OR', subSearch: array(
                    new SearchTerm('id', $exactSearch['id'], ),
                    //TODO: This isn't working for some reason
                    //new SearchTerm('payment_id', $exactSearch['id'])
                ));
        }
        if(gettype($filter) == 'string')
        {
            $filter = array_filter(explode(chr(28),$filter ?? ''));
        }
        if(count($filter))
        {
            $whereParts[] = new SearchTerm('', '', subSearch: $filter);
            $wherePartsSimpler[] = new SearchTerm('', '', subSearch: $filter);
        }

        $result = $this->SearchBadges($context, $whereParts, $order, $limit, $offset, $totalRows, false, $includeFormQuestions);
        //If we got nothing, switch to a simpler search
        if (count($result) == 0) {
            $result =  $this->SearchBadges($context, $wherePartsSimpler, $order, $limit, $offset, $totalRows, false, $includeFormQuestions);
        }
        return $result;
    }
    public function SearchGroupApplicationsText($context, string $searchText, $order, $limit, $offset, &$totalRows, $includeFormQuestions = null,  string|array $filter = [])
    {
        $whereParts =
        empty($searchText) ? [] :
        array(
            new SearchTerm('real_name', $searchText, Raw: 'MATCH('.$this->g_badge_submission->dbTableName().'.`real_name`,'.$this->g_badge_submission->dbTableName().'.`fandom_name`) AGAINST (? IN NATURAL LANGUAGE MODE) ')
        );
        $wherePartsSimpler = array(
            new SearchTerm(
                '',
                '',
                subSearch: array(
                    new SearchTerm('real_name', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('fandom_name', '%' . $searchText . '%', 'LIKE', 'OR'),
                    new SearchTerm('email_address', '%' . $searchText . '%', 'LIKE', 'OR', JoinedTableAlias:'con'),
                )
            ));
            
        //If it looks like they specified a badge ID, add that to the search term
        preg_match_all('/(?\'context_code\'[a-zA-Z]{0,3})(?\'display_id\'\d+)/m', $searchText, $badgeMatches, PREG_SET_ORDER, 0);
        foreach ($badgeMatches as $exactSearch) {
            $whereParts[]= new SearchTerm('', '', TermType:'OR', subSearch: array(
                    new SearchTerm('context_code', $exactSearch['context_code'], JoinedTableAlias:'grp'),
                    new SearchTerm('display_id', $exactSearch['display_id'], )
                ));
        }
        
        //If it looks like an internal ID, add  it to the search term
        preg_match_all('/^(?\'id\'\d+)$/m', $searchText, $badgeMatches, PREG_SET_ORDER, 0);
        foreach ($badgeMatches as $exactSearch) {
            $whereParts[]= new SearchTerm('', '', TermType:'OR', subSearch: array(
                    new SearchTerm('id', $exactSearch['id'], ),
                    //TODO: This isn't working for some reason
                    //new SearchTerm('payment_id', $exactSearch['id'])
                ));
        }
        if(gettype($filter) == 'string')
        {
            $filter = array_filter(explode(chr(28),$filter ?? ''));
        }
        if(count($filter))
        {
            $whereParts[] = new SearchTerm('', '', subSearch: $filter);
            $wherePartsSimpler[] = new SearchTerm('', '', subSearch: $filter);
        }

        $result = $this->SearchGroupApplications($context, $whereParts, $order, $limit, $offset, $totalRows, false, $includeFormQuestions);
        //If we got nothing, switch to a simpler search
        if (count($result) == 0) {
            $result =  $this->SearchGroupApplications($context, $wherePartsSimpler, $order, $limit, $offset, $totalRows, false, $includeFormQuestions);
        }
        return $result;
    }

    public function SearchBadges($context, $terms, ?array $order = null, int $limit = -1, int $offset = 0, &$totalRows = null, $full = false, $includeFormQuestions = null)
    {
        // Invoke the Domain with inputs and retain the result
        $trA = 0;
        $trS = 0;
        $trG = 0;
        $a_bv = $this->badgeView($this->a_badge_type, 'A', $includeFormQuestions);
        $s_bv = $this->staffBadgeView($this->s_badge_type, 'S', $includeFormQuestions);
        $g_bv = $this->groupBadgeView();
        if ($full) {
            $this->MergeView($a_bv, $this->badgeViewFullAddAttendee());
            $this->MergeView($s_bv, $this->badgeViewFullAddStaff());
            $this->MergeView($g_bv, $this->badgeViewFullAddGroup());
        }
        $a_terms = $this->AdjustSearchTerms($terms, $a_bv);
        $s_terms = $this->AdjustSearchTerms($terms, $s_bv);
        $g_terms = $this->AdjustSearchTerms($terms, $g_bv);
        //Add to the group search if context specified
        if ($context !== false) {
            $g_terms[] = new SearchTerm('context_code', $context, is_null($context) ? 'IS' : '=', JoinedTableAlias:'grp');
            //At present the only time the $context is specified is if they're admin and can read applications
            //So, add in the notes for use in the table
            $g_bv->Columns[] = 'notes';
        }

        $a_data = ($context === false || ($context ?? 'A') == 'A') ? $this->a_badge->Search($a_bv, $a_terms, $order, $limit, $offset, $trA, 'b') : array();
        $s_data = ($context === false || ($context ?? 'S') == 'S') ? $this->s_badge->Search($s_bv, $s_terms, $order, $limit, $offset, $trS, 'b') : array();
        //$this->g_badge->debugThrowBeforeSelect = true;
        //Sub badges do not have queestion responses, so filder out possible form responses
        $orderNoFormResponses = is_array($order) ? array_filter($order, function ($colname) {
            return !str_starts_with($colname, 'form_responses');
        }, ARRAY_FILTER_USE_KEY) : null;
        if(is_array($g_terms))  array_walk($g_terms, function (SearchTerm &$col) {
            //Only the subSearch terms might contain column names, skip any that aren't
            if(is_null($col->subSearch)) return;
            $col->subSearch = array_filter($col->subSearch, function ($search) {
                if(gettype($search) == 'string') return !str_starts_with($search, 'form_responses');
                return true;
            });
        });
        $g_data = $this->g_badge->Search($g_bv, $g_terms, $orderNoFormResponses, $limit, $offset, $trG, 'b');
        $totalRows =  $trA + $trS + $trG;


        //Add in any addons
        $attendeeIds = array_column($a_data, 'id');
        if (count($attendeeIds) == 0) {
            $attendeeIds[] = 0;
        }
        $a_addons = $this->a_addonpurchase->Search(array(
            'attendee_id',
            'addon_id'
        ), array(
            new SearchTerm('attendee_id', $attendeeIds, 'IN'),
            new SearchTerm('payment_status', 'Completed')
        ));
        $applicantIds = array_column($g_data, 'id');
        if (count($applicantIds) == 0) {
            $applicantIds[] = 0;
        }
        $g_addons = $this->g_addonpurchase->Search(array(
            'application_id',
            'addon_id'
        ), array(
            new SearchTerm('application_id', $applicantIds, 'IN'),
            new SearchTerm('payment_status', 'Completed')
        ));

        $result = array_merge($a_data, $s_data, $g_data);

        //Loop the badges to add their addons, if any addons were returned for it
        foreach ($result as &$badge) {
            $badge['addons'] = array();
            if ($badge['context_code'] == 'A') {
                foreach ($a_addons as $addon) {
                    if ($addon['attendee_id'] == $badge['id']) {
                        $badge['addons'][] = array(
                        'addon_id' => $addon['addon_id']
                    );
                    }
                }
            } elseif ($badge['context_code'] != 'S') {
                foreach ($g_addons as $addon) {
                    if ($addon['application_id'] == $badge['id']) {
                        $badge['addons'][] = array(
                        'addon_id' => $addon['addon_id']
                    );
                    }
                }
            }

            //While we're here, add the computed columns too
            $badge = $this->addComputedColumns($badge, false);
        }

        //Fetch associated form responses if full == true
        if ($full) {
            //Generate searchTerms for each of the found badges
            $f_searchTerms = [];
            foreach ($result as &$badge) {
                $f_searchTerms[] = new SearchTerm(
                    '',
                    '',
                    TermType: 'OR',
                    subSearch: [
                            new SearchTerm('context_code', $badge['context_code']),
                            new SearchTerm('context_id', $badge['id']),
                    ]
                );
            }
            $f_responsedata = $this->f_response->Search(
                array('context_id','context_code','question_id','response'),
                $f_searchTerms
            );

            foreach ($result as &$badge) {
                $f_filteredbadgedata = array_filter($f_responsedata, function ($f_response) use ($badge) {
                    return $f_response['context_id'] == $badge['id']
                    &&  $f_response['context_code'] == $badge['context_code'] ;
                });
                //Zip together the responses
                $badge['form_responses'] = array_combine(array_column($f_filteredbadgedata, 'question_id'), array_column($f_filteredbadgedata, 'response'));
            }
        }

        return $result;
    }
    public function SearchGroupApplications($context, $terms, ?array $order = null, int $limit = -1, int $offset = 0, &$totalRows = null, $full = false, $includeFormQuestions = null)
    {
        // Invoke the Domain with inputs and retain the result
        $g_bv = $this->groupApplicationBadgeView($includeFormQuestions);
        if ($full) {
            $this->MergeView($g_bv, $this->badgeViewFullAddGroup());
        }
        $g_terms = $this->AdjustSearchTerms($terms, $g_bv);
        //Add to the group search if context specified
        if (!empty($context)) {
            $g_terms[] = new SearchTerm('context_code', $context, is_null($context) ? 'IS' : '=', JoinedTableAlias:'grp');
            //At present the only time the $context is specified is if they're admin and can read applications
            //So, add in the notes for use in the table
            $g_bv->Columns[] = 'notes';
        }

        // $this->g_badge_submission->debugThrowBeforeSelect = true;
        $g_data = $this->g_badge_submission->Search($g_bv, $g_terms, $order, $limit, $offset, $totalRows);


        //Add in any addons
        $applicantIds = array_column($g_data, 'id');
        if (count($applicantIds) == 0) {
            $applicantIds[] = 0;
        }
        $g_addons = $this->g_addonpurchase->Search(new View(array(
            'application_id',
            'addon_id',
            new SelectColumn('name', JoinedTableAlias:'a'),
        ), array(
            new Join($this->g_addon, array('id'=>'addon_id'), 'inner', 'a')
        )), array(
            new SearchTerm('application_id', $applicantIds, 'IN'),
            new SearchTerm('payment_status', 'Completed')
        ));

        $result = $g_data;

        //Loop the badges to add their addons, if any addons were returned for it
        foreach ($result as &$badge) {
            $badge['addons'] = array();
            foreach ($g_addons as $addon) {
                if ($addon['application_id'] == $badge['id']) {
                    $badge['addons'][] = array(
                    'addon_id' => $addon['addon_id'],
                    'name' => $addon['name']
                );
                }
            }

            //While we're here, add the computed columns too
            $badge = $this->addComputedColumns($badge, false);
        }

        //Fetch associated form responses if full == true
        if ($full) {
            //Generate searchTerms for each of the found badges
            $f_searchTerms = [];
            foreach ($result as &$badge) {
                $f_searchTerms[] = new SearchTerm(
                    '',
                    '',
                    TermType: 'OR',
                    subSearch: [
                            new SearchTerm('context_code', $badge['context_code']),
                            new SearchTerm('context_id', $badge['id']),
                    ]
                );
            }
            $f_responsedata = $this->f_response->Search(
                array('context_id','context_code','question_id','response'),
                $f_searchTerms
            );

            foreach ($result as &$badge) {
                $f_filteredbadgedata = array_filter($f_responsedata, function ($f_response) use ($badge) {
                    return $f_response['context_id'] == $badge['id']
                    &&  $f_response['context_code'] == $badge['context_code'] ;
                });
                //Zip together the responses
                $badge['form_responses'] = array_combine(array_column($f_filteredbadgedata, 'question_id'), array_column($f_filteredbadgedata, 'response'));
                //Retrieve subbadges

                $badge['subbadges'] = $this->g_badge->Search(array(
                    'id',
                    'display_id',
                    'real_name',
                    'fandom_name',
                    'name_on_badge',
                    'date_of_birth',
                    'notify_email',
                    'can_transfer',
                    'ice_name',
                    'ice_relationship',
                    'ice_email_address',
                    'ice_phone_number',
                ), array(
                    new SearchTerm('application_id', $badge['id'])
                ));
            }
        }

        return $result;
    }

    public function AdjustSearchTerms($terms, $badgeView)
    {
        if (is_null($terms) || is_null($badgeView)) {
            return $terms;
        }
        $result = array();
        foreach ($terms as $sterm) {
            //Search the view
            if($sterm instanceof SearchTerm)
            {
                $term = clone $sterm;
                $found = false;
                foreach ($badgeView->Columns as $selCol) {
                    if ($selCol instanceof SelectColumn) {
                        //Does this match a column with an alias?
                        if (($selCol->ColumnName == $term->ColumnName || $selCol->Alias == $term->ColumnName)) {
                            if(is_null($selCol->EncapsulationFunction))
                            {
                                $term->JoinedTableAlias = $selCol->JoinedTableAlias ?? $term->JoinedTableAlias;
                            } else {
                                //Not a direct column, must use the whole thing
                                $term->EncapsulationFunction = $selCol->EncapsulationFunction;
                            }
                            $found = true;
                            break;
                        }
                    } elseif ($selCol == $term->ColumnName) {
                        //Assume  it's the default table, which we don't actually have the alias for in this function
                        $found = true;
                        break;
                    }
                }
                if(!$found)
                {
                    //Might be a generated column. Do a hack I suppose
                    throw new \Exception("Could not find term->ColumnName " . var_export($term,true) ." In: \r\n"  . var_export($badgeView->Columns,true));
                }
                //Search the joins' columns
                foreach ($badgeView->Joins as $join) {
                    //TODO: Implement?
                }
                //If there is subSearch, iterate into that
                if($sterm->subSearch != null) {                    
                    $term->subSearch = $this->AdjustSearchTerms($sterm->subSearch, $badgeView);
                }
                $result[] = $term;
            } else {
                //Dunno, just save as-is
                $result[] = $sterm;
            }
        }
        return $result;
    }

    private function MergeView(View &$view, ?View $addView)
    {
        if (is_null($addView)) {
            return;
        }
        if (!is_null($addView->Columns)) {
            $view->Columns = array_merge($view->Columns, $addView->Columns);
        }
        if (!is_null($addView->Joins)) {
            $view->Joins = array_merge($view->Joins, $addView->Joins);
        }
    }

    public function getASpecificBadge($id, $badge, $badgetype, $contextCode, $addView)
    {
        $view = $contextCode == 'A' ? $this->badgeView($badgetype, $contextCode)
        : $this->staffBadgeView($badgetype, $contextCode);
        if ($addView instanceof View) {
            $this->MergeView($view, $addView);
        }
        return $badge->GetByIDorUUID($id, null, $view);
    }
    public function getASpecificGroupBadge($id, $addView)
    {
        $view = $this->groupBadgeView();
        if ($addView instanceof View) {
            $this->MergeView($view, $addView);
        }
        return $this->g_badge->GetByIDorUUID($id, null, $view);
    }
    public function getASpecificGroupApplication($id, $context_code, $full = false)
    {
        $view = $this->groupApplicationBadgeView();
        if ($full) {
            $this->MergeView($view, $this->badgeViewFullAddGroupApplication());
        }
        $result = $this->g_badge_submission->GetByIDorUUID($id, null, $view);

        if ($result !== false) {
            $result['addons'] = [];
            $g_addons = $this->g_addonpurchase->Search(array(
            'addon_id',
            'payment_status'
            ), array(

                new SearchTerm('application_id', $id)
            ));
            foreach ($g_addons as $addon) {
                $result['addons'][] = array(
                'addon_id' => $addon['addon_id'],
                'addon_payment_status' => $addon['payment_status']
            );
            }

            $result['subbadges'] = $this->g_badge->Search(array(
                'id',
                'display_id',
                'real_name',
                'fandom_name',
                'name_on_badge',
                'date_of_birth',
                'notify_email',
                'can_transfer',
                'ice_name',
                'ice_relationship',
                'ice_email_address',
                'ice_phone_number',
            ), array(
                new SearchTerm('application_id', $id)
            ));

            $result['assignments'] = $this->g_assignment->Search(new View([
                'id','application_id','location_id','category_id','start_time','end_time',
                new SelectColumn('short_code', JoinedTableAlias:'l'),
                new SelectColumn('name', JoinedTableAlias:'l'),
            ],[
                new Join($this->g_location,['id'=>'location_id'],alias:'l')
            ]),[
                new SearchTerm('application_id',$result['id'])
            ]);
        }


        if ($result === false || !$full) {
            return $result;
        }
        //Add in form responses
        $result['form_responses'] = $this->GetFormResponses($id, $context_code);
        //Add in supplementary
        $this->addSupplementaryBadgeData($result);
        return $this->addComputedColumns($result, true);
    }
    public function checkBadgeTypeBelongsToEvent($context_code, $badge_type_id)
    {
        $result = false;
        switch ($context_code) {
                    case 'A':
                        $found = $this->a_badge_type->Search(
                            array('id'),
                            array(
                            new SearchTerm('id', $badge_type_id),
                            new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
                        )
                        );
                        $result = count($found) > 0;
                        break;
                    case 'S':
                        $found = $this->s_badge_type->Search(
                            array('id'),
                            array(
                            new SearchTerm('id', $badge_type_id),
                            new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
                        )
                        );
                        $result = count($found) > 0;
                        break;
                    default:
                        $found =  $this->g_badge_type->Search(new View(
                            array('id'),
                            array(
                                  new Join(
                                      $this->g_group,
                                      array(
                                        'id' => 'group_id',
                                        new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId()),
                                        new SearchTerm('context_code', $context_code)
                                      ),
                                      alias:'grp'
                                  )
                                 )
                        ), array(
                                 new SearchTerm('id', $badge_type_id),
                             ));
                             $result = count($found) > 0;
                }
        return $result;
    }
    public function getBadgetType($context_code, $badge_type_id)
    {
        $result = false;
        switch ($context_code) {
                case 'A':
                    $result =  $this->a_badge_type->GetByID($badge_type_id, $this->badgeTypeColumns());
                    break;
                case 'S':
                    $result =  $this->s_badge_type->GetByID($badge_type_id, $this->badgeTypeColumns(true));
                    break;
                default:
                    $result =  $this->g_badge_type->GetByID($badge_type_id, $this->badgeTypeColumns(true, true));
            }
        return $result;
    }


    public function searchASpecificBadge($uuid, $badge, $badgetype, $contextCode, $addView)
    {
        $view = $contextCode == 'A' ? $this->badgeView($badgetype, $contextCode)
        : $this->staffBadgeView($badgetype, $contextCode);
        if ($addView instanceof View) {
            $this->MergeView($view, $addView);
        }
        return $badge->GetByIDorUUID(null, $uuid, $view);
    }
    public function searchASpecificGroupBadge($uuid, $addView)
    {
        $view = $this->groupBadgeView();
        if ($addView instanceof View) {
            $this->MergeView($view, $addView);
        }
        return $this->g_badge->GetByIDorUUID(null, $uuid, $view);
    }

    public function badgeView($badgetype, $contextCode, $includeFormQuestions = null)
    {
        $result = new View(
            array_merge(
                $this->selectColumns,
                array(
                'display_id',
               //new SelectColumn('context_code', EncapsulationFunction: "'".$contextCode."'", Alias:'context_code'),
               new SelectColumn('context_code', JoinedTableAlias:'grp'),
               new SelectColumn('application_id', EncapsulationFunction: "null", Alias:'application_id'),
               new SelectColumn('application_status', EncapsulationFunction: "''", Alias:'application_status'),
               'badge_type_id',
               'payment_status',
               'payment_id',
               'payment_promo_price',
               'payment_badge_price',
               new SelectColumn('name', Alias:'badge_type_name', JoinedTableAlias:'typ'),
               new SelectColumn('payable_onsite', Alias:'badge_type_payable_onsite', JoinedTableAlias:'typ'),
               new SelectColumn('email_address', Alias:'contact_email_address', JoinedTableAlias:'con'),
               'notes',
             )
            ),
            array(

               new Join(
                   $badgetype,
                   array(
                     'id' => 'badge_type_id',
                     new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
                   ),
                   alias:'typ'
               ),
               new Join(
                   $this->eventinfo,
                   array(
                     'context_code' => new SearchTerm('', '', Raw: '1=1 '),
                   ),
                   'LEFT',
                   'grp',
                   array(new SelectColumn('context_code', EncapsulationFunction: "'".$contextCode."'", Alias:'context_code'),),
                   array(
                   new SearchTerm('id', $this->CurrentUserInfo->GetEventId())),
               ),
              new Join(
                  $this->contact,
                  array(
                    'id' => 'contact_id'
                  ),
                  alias:'con'
              ),
             )
        );

        if (!empty($includeFormQuestions)) {
            //Add join for the question results table
            $result->Joins[] = new Join(
                $this->f_response,
                array(
                new SearchTerm('context_code', $contextCode),
                'context_id' => 'id'
            ),
                'LEFT',
                alias:'FR'
            );
            //Make all the other columns group_by
            foreach ($result->Columns as &$col) {
                if ($col instanceof SelectColumn) {
                    //Except if we have an EncapsulationFunction that doesn't have a ?
                    if (is_null($col->EncapsulationFunction)
                    || (!is_null($col->EncapsulationFunction) && strpos($col->EncapsulationFunction, '?') !== false)) {
                        $col->GroupBy = true;
                    }
                } else {
                    $col = new SelectColumn($col, true);
                }
            }

            //Add in the extra questions asked for
            foreach ($includeFormQuestions as $questionId) {
                $result->Columns[] = new SelectColumn('response', false, 'MAX(CASE WHEN `FR`.`question_id` = ' . $questionId . ' THEN ? END)', 'form_responses[' . $questionId . ']', 'FR');
            }
        }
        return $result;
    }

    public function staffBadgeView($badgetype, $contextCode, $includeFormQuestions = null)
    {
        $result = new View(
            array_merge(
                $this->selectColumns,
                array(
               //new SelectColumn('context_code', EncapsulationFunction: "'".$contextCode."'", Alias:'context_code'),
               new SelectColumn('context_code', JoinedTableAlias:'grp'),
               new SelectColumn('id', Alias:'application_id'),
               'application_status',
               'badge_type_id',
               'payment_status',
               'payment_id',
               'payment_promo_price',
               'payment_badge_price',
               new SelectColumn('name', Alias:'badge_type_name', JoinedTableAlias:'typ'),
               new SelectColumn('payable_onsite', Alias:'badge_type_payable_onsite', JoinedTableAlias:'typ'),
               new SelectColumn('email_address', Alias:'contact_email_address', JoinedTableAlias:'con'),
               'notes',
             )
            ),
            array(

               new Join(
                   $badgetype,
                   array(
                     'id' => 'badge_type_id',
                     new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
                   ),
                   alias:'typ'
               ),
               new Join(
                   $this->eventinfo,
                   array(
                     'context_code' => new SearchTerm('', '', Raw: '1=1 '),
                   ),
                   'LEFT',
                   'grp',
                   array(new SelectColumn('context_code', EncapsulationFunction: "'".$contextCode."'", Alias:'context_code'),),
                   array(
                   new SearchTerm('id', $this->CurrentUserInfo->GetEventId())),
               ),
              new Join(
                  $this->contact,
                  array(
                    'id' => 'contact_id'
                  ),
                  alias:'con'
              ),
             )
        );

        if (!empty($includeFormQuestions)) {
            //Add join for the question results table
            $result->Joins[] = new Join(
                $this->f_response,
                array(
                new SearchTerm('context_code', $contextCode),
                'context_id' => 'id'
            ),
                'LEFT',
                alias:'FR'
            );
            //Make all the other columns group_by
            foreach ($result->Columns as &$col) {
                if ($col instanceof SelectColumn) {
                    //Except if we have an EncapsulationFunction that doesn't have a ?
                    if (is_null($col->EncapsulationFunction)
                    || (!is_null($col->EncapsulationFunction) && strpos($col->EncapsulationFunction, '?') !== false)) {
                        $col->GroupBy = true;
                    }
                } else {
                    $col = new SelectColumn($col, true);
                }
            }

            //Add in the extra questions asked for
            foreach ($includeFormQuestions as $questionId) {
                $result->Columns[] = new SelectColumn('response', false, 'MAX(CASE WHEN `FR`.`question_id` = ' . $questionId . ' THEN ? END)', 'form_responses[' . $questionId . ']', 'FR');
            }
        }
        return $result;
    }
    public function badgeViewFullAddAttendee()
    {
        return new View(
            array(
                 $this->CurrentUserInfo->GetPerms()->EventPerms->getValue() > 0 ? 'notes' : null,
                'uuid',
                'payment_id'
            ),
            array(

            ),
        );
    }
    public function badgeViewFullAddStaff()
    {
        return new View(
            array(
                 $this->CurrentUserInfo->GetPerms()->EventPerms->getValue() > 0 ? 'notes' : null,
                'uuid',
                'payment_id'
            )
        );
    }

    public function groupBadgeView() : View
    {
        return new View(
            array_merge(
                $this->selectColumns,
                array(
                   new SelectColumn('context_code', JoinedTableAlias:'grp'),
                   new SelectColumn('application_id'),
                   new SelectColumn('application_status', JoinedTableAlias:'bs'),
                   new SelectColumn('badge_type_id', JoinedTableAlias:'bs'),
                   new SelectColumn('payment_status', JoinedTableAlias:'bs'),
                   new SelectColumn('payment_id', JoinedTableAlias:'bs'),
                   new SelectColumn('name', Alias:'badge_type_name', JoinedTableAlias:'typ'),
                   new SelectColumn('payable_onsite', Alias:'badge_type_payable_onsite', JoinedTableAlias:'typ'),
                   new SelectColumn('payment_deferred', Alias:'badge_type_payment_deferred', JoinedTableAlias:'typ'),
                   new SelectColumn('email_address', Alias:'contact_email_address', JoinedTableAlias:'con'),
                 )
            ),
            array(
                   new Join(
                       $this->g_badge_submission,
                       array(
                         'id' => 'application_id',
                       ),
                       alias:'bs'
                   ),

                   new Join(
                       $this->g_badge_type,
                       array(
                         'id' =>new SearchTerm('badge_type_id', null, JoinedTableAlias: 'bs'),
                       ),
                       alias:'typ'
                   ),
                  new Join(
                      $this->g_group,
                      array(
                        'id' => new SearchTerm('group_id', null, JoinedTableAlias: 'typ'),
                        new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
                      ),
                      alias:'grp'
                  ),
                 new Join(
                     $this->contact,
                     array(
                       'id' => 'contact_id'
                     ),
                     alias:'con'
                 ),
                 )
        );
    }
    public function badgeViewFullAddGroup()
    {
        return new View(
            array(
                 $this->CurrentUserInfo->GetPerms()->EventPerms->getValue() > 0 ? 'notes' : null,
                'uuid'
            ),
            array()
        );
    }

    public function groupApplicationBadgeView($includeFormQuestions = null)
    {
        $result =  new View(
            array(
                'id',
                'uuid',
                'display_id',
                'contact_id',
                'real_name',
                'fandom_name',
                'name_on_badge',
               new SelectColumn('context_code', JoinedTableAlias:'grp'),
               new SelectColumn('assignment_count'),
               new SelectColumn('AssignmentCount',EncapsulationFunction:'ifnull(?,0)',Alias:'assignments',JoinedTableAlias:'ac'),
               new SelectColumn('LocationIDs',EncapsulationFunction:'ifnull(?,\'\')',Alias:'assignments_locations',JoinedTableAlias:'ac'),
               new SelectColumn('CategoryIDs',EncapsulationFunction:'ifnull(?,\'\')',Alias:'assignments_categories',JoinedTableAlias:'ac'),
               new SelectColumn('application_status'),
               new SelectColumn('badge_type_id'),
               new SelectColumn('payment_status'),
               new SelectColumn('payment_promo_price'),
               new SelectColumn('payment_badge_price'),
               new SelectColumn('payment_id'),
               new SelectColumn('name', Alias:'badge_type_name', JoinedTableAlias:'typ'),
               new SelectColumn('payable_onsite', Alias:'badge_type_payable_onsite', JoinedTableAlias:'typ'),
               new SelectColumn('payment_deferred', Alias:'badge_type_payment_deferred', JoinedTableAlias:'typ'),
               new SelectColumn('email_address', Alias:'contact_email_address', JoinedTableAlias:'con'),

            ),
            array(
                   new Join(
                       $this->g_badge_type,
                       array(
                         'id' =>new SearchTerm('badge_type_id', null),
                       ),
                       alias:'typ'
                   ),
                  new Join(
                      $this->g_group,
                      array(
                        'id' => new SearchTerm('group_id', null, JoinedTableAlias: 'typ'),
                        new SearchTerm('event_id', $this->CurrentUserInfo->GetEventId())
                      ),
                      alias:'grp'
                  ),
                 new Join(
                     $this->contact,
                     array(
                       'id' => 'contact_id'
                     ),
                     alias:'con'
                 ),
                 new Join($this->g_assignment,['application_id'=>'id'],'LEFT',
                 'ac',[
                     new SelectColumn('application_id',true),
                     new SelectColumn('location_id',false,'GROUP_CONCAT(? SEPARATOR \',\')','LocationIDs'),
                     new SelectColumn('category_id',false,'GROUP_CONCAT(? SEPARATOR \',\')','CategoryIDs'),
                     new SelectColumn('id',false,'count(?)','AssignmentCount')
                 ]),
                 )
        );

        if (!empty($includeFormQuestions)) {
            //Add join for the question results table
            $result->Joins[] = new Join(
                $this->f_response,
                array(
                'context_code' => new SearchTerm('context_code', '', JoinedTableAlias:'grp'),
                'context_id' => 'id'
            ),
                'LEFT',
                alias:'FR'
            );
            //Make all the other columns group_by
            foreach ($result->Columns as &$col) {
                if ($col instanceof SelectColumn) {
                    $col->GroupBy = true;
                } else {
                    $col = new SelectColumn($col, true);
                }
            }

            //Add in the extra questions asked for
            foreach ($includeFormQuestions as $questionId) {
                $result->Columns[] = new SelectColumn('response', false, 'MAX(CASE WHEN `FR`.`question_id` = ' . $questionId . ' THEN ? END)', 'form_responses[' . $questionId . ']', 'FR');
            }
        }
        return $result;
    }
    public function badgeViewFullAddGroupApplication()
    {
        return new View(
            array(
                 $this->CurrentUserInfo->GetPerms() ? 'notes' : null,
                'uuid'
            ),
            array()
        );
    }

    public function badgeTypeColumns($include_defferred_pay = false, $include_group_app = false)
    {
        return new View(
            array_merge(
                [
                    'active',
                    'name',
                    'description',
                    'rewards',
                    'price',
                    'payable_onsite'
                ],
                !$include_defferred_pay ? [] : [ 'payment_deferred'],
                !$include_group_app ? [] : [
                    'max_applicant_count',
                    'max_assignment_count',
                    'base_applicant_count',
                    'base_assignment_count',
                    'price_per_applicant',
                    'price_per_assignment'
                ],
            ),
            array()
        );
    }
    public function addSupplementaryBadgeData(&$result)
    {
        switch ($result['context_code']) {
            case 'A':
                //$result =  $this->a_badge->Create($data);
                break;
            case 'S':
                $result['assigned_positions'] = $this->s_assignedposition->Search(
                    new View(
                        array(
                            'position_id','onboard_completed','onboard_meta','date_created','date_modified',
                            new SelectColumn('is_exec', JoinedTableAlias:'p'),
                            new SelectColumn('name', Alias:'position_text', JoinedTableAlias:'p'),
                            new SelectColumn('department_id', JoinedTableAlias:'p'),
                            new SelectColumn('name', Alias:'department_text', JoinedTableAlias:'d'),
                        ),
                        array(
                            new Join(
                                $this->s_position,
                                array('id'=>'position_id'),
                                alias:'p'
                            ),
                            new Join(
                                $this->s_department,
                                array('id'=>new SearchTerm('department_id', null, JoinedTableAlias:'p')),
                                alias:'d'
                            ),
                        )
                    ),
                    array(
                        new SearchTerm('staff_id', $result['id'])
                    )
                );
                break;
            default:
                $result['subbadges'] = $this->g_badge->Search(array(
                    'id',
                    'display_id',
                    'real_name',
                    'fandom_name',
                    'name_on_badge',
                    'date_of_birth',
                    'notify_email',
                    'can_transfer',
                    'ice_name',
                    'ice_relationship',
                    'ice_email_address',
                    'ice_phone_number',
                ), array(
                    new SearchTerm('application_id', $result['id'])
                ));
        }
        //If user has contact permissions, add that in
        if ($this->CurrentUserInfo->hasEventPerm(PermEvent::Contact_Full)) {
            $result['contact'] = $this->contact->GetById(
                $result['contact_id'],
                [
                'allow_marketing',
                'email_address',
                'real_name',
                'phone_number',
                'address_1',
                'address_2',
                'city',
                'state',
                'zip_code',
                'country',
                'notes'
                ]
            );
        }
    }
    public function updateSupplementaryBadgeData(&$result)
    {
        //If we're accepted or otherwise complete but still do not have a display ID, fix that
        if (array_key_exists('display_id', $result) && $result['display_id']==null && (in_array(
            $result['application_status']??'',
            ['PendingAcceptance','Accepted','Onboarding','Active']
        ) || ($result['payment_status']??'') == 'Completed')) {
            $this->setNextDisplayIDSpecificBadge($result['id'], $result['context_code']);
        }
        switch ($result['context_code']??'A') {
            case 'A':
                //$result =  $this->a_badge->Create($data);
                break;
            case 'S':
            if (isset($result['assigned_positions'])) {
                $setPositions = $result['assigned_positions'];
                $currentPositions = $this->s_assignedposition->Search(
                    array(
                        'position_id'
                    ),
                    array(
                        new SearchTerm('staff_id', $result['id'])
                    )
                );
                //Process adds
                foreach (array_udiff($setPositions, $currentPositions, array($this,'comparePositionID')) as $newPosition) {
                    $newPosition['staff_id'] = $result['id'];
                    $this->s_assignedposition->Create($newPosition);
                }
                //Process removes
                foreach (array_udiff($currentPositions, $setPositions, array($this,'comparePositionID')) as $deletedPosition) {
                    $deletedPosition['staff_id'] = $result['id'];
                    $this->s_assignedposition->Delete($deletedPosition);
                }
                //Process modifications
                foreach (array_uintersect($setPositions, $currentPositions, array($this,'comparePositionID')) as $modifiedPosition) {
                    $modifiedPosition['staff_id'] = $result['id'];
                    $this->s_assignedposition->Update($modifiedPosition);
                }
            }

                break;
            default:

            if (isset($result['subbadges'])) {
                $setSubbadges = &$result['subbadges'];
                $currentSubbadges = $this->g_badge->Search(
                    array(
                            'id','display_id'
                    ),
                    array(
                        new SearchTerm('application_id', $result['id'])
                    )
                );
                //Process adds
                foreach (array_udiff($setSubbadges, $currentSubbadges, array($this,'compareID')) as &$newAssignment) {
                    $curIx = array_search($newAssignment, $setSubbadges, true);

                    unset($newAssignment['id']);//Just in case
                    $newAssignment['application_id'] = $result['id'];
                    $newAssignment['contact_id'] = $result['contact_id'];
                    $newAssignment['date_of_birth'] = empty($newAssignment['date_of_birth']) ? '1000-01-01' : $newAssignment['date_of_birth'] ;

                    $newAssignment['id'] = $this->g_badge->Create($newAssignment)['id'];
                    //Tag this badge as new...?
                    $newAssignment['created'] = true;

                    //If completed payment and accepted add display ID
                    if ($newAssignment['display_id'] ??null==null && in_array(
                        $result['application_status']??'',
                        ['PendingAcceptance','Accepted','Onboarding','Active']
                    )) {
                        $newAssignment['display_id'] = $this->setNextDisplayIDSpecificSubBadge($newAssignment['id'], $result['context_code']);
                    }
                    //Save back to the subbadges
                    $setSubbadges[$curIx] = $newAssignment;
                }
                //Process removes
                foreach (array_udiff($currentSubbadges, $setSubbadges, array($this,'compareID')) as $deletedAssignment) {
                    $deletedAssignment['application_id'] = $result['id'];
                    $this->g_badge->Delete($deletedAssignment);
                }
                //Process modifications
                foreach (array_uintersect($setSubbadges, $currentSubbadges, array($this,'compareID')) as $existingAssignment) {
                    $curIx = array_search($existingAssignment, $setSubbadges, true);
                    $existingAssignment['application_id'] = $result['id'];
                    $this->g_badge->Update($existingAssignment);

                    //If completed payment and accepted add display ID
                    if (($existingAssignment['display_id'] ??null)==null && in_array(
                        $result['application_status']??'',
                        ['PendingAcceptance','Accepted','Onboarding','Active']
                    )) {
                        $existingAssignment['display_id'] = $this->setNextDisplayIDSpecificSubBadge($existingAssignment['id'], $result['context_code']);
                    }
                    //Save back to the subbadges
                    $setSubbadges[$curIx] = $existingAssignment;
                }
            }

            //Now do the assignments

            if (isset($result['assignments'])) {
                $setAssignments = &$result['assignments'];
                $currentAssignments = $this->g_assignment->Search(
                    array(
                            'id'
                    ),
                    array(
                        new SearchTerm('application_id', $result['id'])
                    )
                );
                //Process adds
                foreach (array_udiff($setAssignments, $currentAssignments, array($this,'compareID')) as &$newAssignment) {
                    $curIx = array_search($newAssignment, $setAssignments, true);

                    unset($newAssignment['id']);//Just in case
                    $newAssignment['application_id'] = $result['id'];
                    $newAssignment['start_time'] = empty($newAssignment['start_time']) ? null : $newAssignment['start_time'] ;
                    $newAssignment['end_time'] = empty($newAssignment['end_time']) ? null : $newAssignment['end_time'] ;

                    $newAssignment['id'] = $this->g_assignment->Create($newAssignment)['id'];
                    //Tag this badge as new...?
                    $newAssignment['action'] = 'created';

                    //Save back to the Assignments
                    $setAssignments[$curIx] = $newAssignment;
                }
                //Process removes
                foreach (array_udiff($currentAssignments, $setAssignments, array($this,'compareID')) as $deletedAssignment) {
                    $deletedAssignment['application_id'] = $result['id'];
                    $this->g_assignment->Delete($deletedAssignment);
                    $deletedAssignment['action'] = 'deleted';
                    //Save back to the Assignments
                    $setAssignments[$curIx] = $deletedAssignment;
                }
                //Process modifications
                foreach (array_uintersect($setAssignments, $currentAssignments, array($this,'compareID')) as $existingAssignment) {
                    $curIx = array_search($existingAssignment, $setAssignments, true);
                    $existingAssignment['application_id'] = $result['id'];
                    $existingAssignment['start_time'] = empty($existingAssignment['start_time']) ? null : $existingAssignment['start_time'] ;
                    $existingAssignment['end_time'] = empty($existingAssignment['end_time']) ? null : $existingAssignment['end_time'] ;
                    $this->g_assignment->Update($existingAssignment);
                    $existingAssignment['action'] = 'updated';

                    //Save back to the Assignments
                    $setAssignments[$curIx] = $existingAssignment;
                }
            }
        }
        
        //Save the form responses
        if (isset($result['form_responses'])) {
            $this->SetFormResponses($result['id'], $result['context_code'], $result['form_responses']);
        }
    }

    public function addComputedColumns(array $result, $includeImageData = false): array
    {
        //Add some computed helper columns
        switch ($result['name_on_badge']) {
            case 'Fandom Name Large, Real Name Small':
                $result['only_name'] = '';
                $result['large_name'] = $result['fandom_name'];
                $result['small_name'] = $result['real_name'];
                $result['display_name'] = trim(($result['fandom_name'] ??'') .' (' . $result['real_name'] . ')');
                break;
            case 'Real Name Large, Fandom Name Small':
                $result['only_name'] = '';
                $result['large_name'] = $result['real_name'];
                $result['small_name'] = $result['fandom_name'];
                $result['display_name'] = trim($result['real_name'] .' (' . ($result['fandom_name']??'') . ')');
                break;
            case 'Fandom Name Only':
                $result['only_name'] = $result['fandom_name']??'';
                $result['display_name'] = $result['fandom_name']??'';
                break;
            case 'Real Name Only':
                $result['only_name'] = $result['real_name'];
                $result['display_name'] = $result['real_name'];
                break;
        }
        $result['badge_id_display'] = $result['context_code'] . $result['display_id'];
        $result['qr_data'] = 'CM*' . $result['context_code'] . $result['display_id'] . '*' . $result['uuid'];
        $result['event_id'] = $this->CurrentUserInfo->GetEventId();
        //Only do this if the user is a event admin or this is their own badge
        if($result['contact_id'] == $this->CurrentUserInfo->GetContactId() 
        || $this->CurrentUserInfo->HasEventPerm(PermEvent::EventAdmin)){
            //Add in the retrieve url
            $result['retrieve_url'] = $this->FrontendUrlTranslator->GetBadgeLoad($result);
            //Add in the cart url
            $result['cart_url'] = $this->FrontendUrlTranslator->GetCartLoad($result);
        } else {
            $result['retrieve_url'] = $this->FrontendUrlTranslator->routedURL('mybadges?obfusctated');
            $result['cart_url'] = $this->FrontendUrlTranslator->routedURL('cart?id='. $result['payment_id']);
        }

        if ($includeImageData) {
            $bc = new barcode_generator();

            $image = $bc->render_image('qr', $result['qr_data'], array(
                'w'=>150,
                'h'=>150
            ));
            //Open a stream to buffer the output
            $stream = fopen("php://memory", "w+");
            //write it out with max compression
            imagepng($image, $stream, 9);
            //Release resource for the image since we're done with it
            imagedestroy($image);
            //Set the cursor to the beginning
            rewind($stream);
            //Stuff it into a string
            $png = stream_get_contents($stream);
            $result['qr_data_uri'] = 'data:image/png;base64,' . base64_encode($png);
        }
        return $result;
    }

    //Utility
    public function parseQueryParamsPagination(array $qp, $defaultSortColumn = 'id', $defaultSortDesc = false)
    {
        //Interpret order parameters
        $sortBy = explode(',', $qp['sortBy'] ??'');
        $idAdded = false;
        //Add the ID
        if (empty($sortBy[0])) {
            $idAdded = true;
            $sortBy[0] = $defaultSortColumn;
        } else {
            //$idAdded = true;
            $sortBy[] = $defaultSortColumn;
            $qp['sortDesc'] .=','.$defaultSortDesc;
        }
        $sortDesc = array_map(function ($v) {
            return $v == 'true' ? 1 : 0;
        }, explode(',', $qp['sortDesc']??''));
        //Ensure the ID sort is descending
        if ($idAdded) {
            $sortDesc[count($sortDesc) - 1] = $defaultSortDesc;
        } else {
            //If we actually had the ID specified, un-add the ID column we forced
            if (array_count_values($sortBy)[$defaultSortColumn] > 1) {
                array_pop($sortBy);
                array_pop($sortDesc);
            }
        }

        //ensure we do not have mismatched number of elements
        $sortDesc = array_slice($sortDesc,0,count($sortBy));

        $order =array_combine(
            $sortBy,
            $sortDesc
        );
        //die(print_r($order, true));

        $page      = ($qp['page']?? 0 > 0) ? $qp['page'] : 1;
        $limit     = $qp['itemsPerPage']?? -1; // Number of posts on one page
        $offset      = ($page - 1) * $limit;
        if ($offset < 0) {
            $offset = 0;
        }
        return array(
            'order'=>$order,
            'limit'=>$limit,
            'offset'=>$offset,
        );
    }
    public function compareID($left, $right, $idName = 'id')
    {
        //Handle not defineds
        if (!isset($left[$idName])) {
            return 1;
        } else {
            if (!isset($right[$idName])) {
                return -1;
            } else {
                //Spaceship!
                return $left[$idName] <=> $right[$idName];
            }
        }
    }
    public function comparePositionID($left, $right)
    {
        //Spaceship!
        return $left['position_id'] <=> $right['position_id'];
    }
}
