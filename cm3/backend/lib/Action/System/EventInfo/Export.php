<?php

namespace CM3_Lib\Action\System\EventInfo;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\database\SelectColumn;
use CM3_Lib\models\eventinfo;
use CM3_Lib\models\application\group;
use CM3_Lib\models\application\badgetype;
use CM3_Lib\models\application\addon;
use CM3_Lib\models\application\addonmap;
use CM3_Lib\models\application\promocode;
use CM3_Lib\models\forms\question;
use CM3_Lib\models\forms\questionmap;
use CM3_Lib\models\mail\template;
use CM3_Lib\models\application\location;
use CM3_Lib\models\application\locationcategory;
use CM3_Lib\models\badge\format;
use CM3_Lib\models\badge\formatmap;
use CM3_Lib\models\attendee\badgetype as AttendeeBadgeType;
use CM3_Lib\models\staff\badgetype as StaffBadgeType;
use CM3_Lib\util\CurrentUserInfo;
use CM3_Lib\util\ExportHelpers;
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action: Export full event data for import wizard.
 * Returns a hierarchical JSON blob scoped to an event.
 *
 * Uses context_code as the primary stable identifier for cross-event matching.
 *
 * Column selection:
 * - Uses "*" wildcard column selection to export ALL columns from each table.
 * - Auto-generated columns (date_created, date_modified) stripped at export time.
 * - Auto-incrementing primary keys (id) stripped from child entities — the
 *   hierarchical nesting encodes FK ownership; the import assigns fresh IDs.
 * - Computed columns (GENERATED ALWAYS) stripped as they cannot be inserted.
 *
 * Hierarchy:
 * - event: top-level metadata
 * - groups: each context_code (incl. S/A dummy groups)
 *   - badgetypes[]: nested, keyed by context_code + name + display_order
 *   - addons[]: nested, with applied_to_badge_types[] references
 *   - promos[]: group-scoped (match by valid_badge_type_ids / valid_addon_ids via context_code + name + display_order)
 *   - questions[]: group-scoped (match by context_code + question context_code + required_badge_types via name + display_order)
 *   - templates[]: group-scoped (match by context_code + name + event_id)
 * - location_categories[]: event-scoped
 * - locations[]: event-scoped
 * - formats[]: event-scoped
 * - format_maps[]: format-to-badgetype mappings (match by format_name + badge_type context_code + name + display_order)
 *
 * Mapping tables (addon_maps, question_maps, format_maps, template_maps) are
 * eliminated — the hierarchy encodes these relationships implicitly.
 */
final class Export
{
    public function __construct(
        private CurrentUserInfo $currentUser,
        private Responder $responder,
        private eventinfo $eventinfo,
        private group $group,
        private badgetype $badgetType,
        private addon $addon,
        private addonmap $addonMap,
        private promocode $promoCode,
        private question $question,
        private questionmap $questionMap,
        private template $template,
        private location $location,
        private locationcategory $locationCategory,
        private format $format,
        private formatmap $formatMap,
        private AttendeeBadgeType $attendeeBadgeType,
        private StaffBadgeType $staffBadgeType
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array)$request->getParsedBody();
        $include = $data['include'] ?? [];
        $contextCodes = $data['context_codes'] ?? [];
        $eventId = $this->currentUser->GetEventId();

        $export = [
            'event' => [],
            'metadata' => [
                'exported_at' => date('Y-m-d H:i:s'),
                'event_id' => $eventId,
                'includes' => $include,
            ],
        ];

        // Event info
        $rows = $this->eventinfo->Search(
            ['id', 'shortcode', 'active', 'display_name', 'date_start', 'date_end', 'staff_start', 'staff_end', 'notes'],
            [new SearchTerm('id', $eventId)]
        );
        $export['event'] = $rows ?: [];

        // Groups with nested badgetypes, addons, promos, questions, and templates
        if (in_array('groups', $include, true)) {
            $export['groups'] = $this->exportGroups($eventId, $contextCodes);
        }

        // Event-scoped entities (no FK to badgetypes)
        if (in_array('location_categories', $include, true)) {
            $where = [new SearchTerm('event_id', $eventId)];
            $rows = $this->locationCategory->Search('*', $where);
            $export['location_categories'] = $this->stripAndClean($rows ?: [], $this->locationCategory) ?: [];
            // Strip id and event_id — these are resolved by the target event context
            foreach ($export['location_categories'] as &$lc) {
                unset($lc['id']);
                unset($lc['event_id']);
            }
            unset($lc);
        }

        if (in_array('locations', $include, true)) {
            $where = [new SearchTerm('event_id', $eventId)];
            $rows = $this->location->Search('*', $where);
            $export['locations'] = $this->stripAndClean($rows ?: [], $this->location) ?: [];
            foreach ($export['locations'] as &$loc) {
                unset($loc['id']);
                unset($loc['event_id']);
            }
            unset($loc);
        }

        // Formats with nested format maps (badge_type_reference objects only)
        if (in_array('formats', $include, true)) {
            $export['formats'] = $this->exportFormats($eventId, $contextCodes);
        } elseif (in_array('format_maps', $include, true)) {
            $export['format_maps'] = $this->exportFormatMaps($eventId, $contextCodes);
        }

        return $this->responder->withJson($response, $export);
    }

    /**
     * Export groups with nested badgetypes, addons, promos, questions, and templates.
     * Each group is a self-contained object with its child entities.
     * Staff (S) and Attendee (A) get dummy group entries.
     *
     * @return array Each group contains: context_code, name, display_order, active,
     *               can_assign_slot, application_name1, menu_icon, description, notes
     *               plus nested arrays: badgetypes[], addons[], promos[], questions[], templates[]
     *               (id and event_id are stripped from all entities — context_code is the stable
     *               identifier used for cross-event matching during import)
     */
    private function exportGroups(int $eventId, array $contextCodes): array
    {
        // 1. Fetch application groups (context_code groups)
        $where = [new SearchTerm('event_id', $eventId)];
        if (!empty($contextCodes)) {
            $where[] = new SearchTerm('context_code', $contextCodes, 'IN');
        }
        $rows = $this->group->Search('*', $where, ['display_order' => false]);
        $groups = $this->stripAndClean($rows ?: [], $this->group);

        // Save group IDs before stripping them — they're needed for FK matching
        foreach ($groups as &$group) {
            $group['_gid'] = $group['id'];
            unset($group['id'], $group['event_id']);
        }
        unset($group);

        // 2. Build per-group badge type, addon, promo, question, and template data
        foreach ($groups as &$group) {
            $contextCode = $group['context_code'];
            $groupId = $group['_gid'];
            unset($group['_gid']);

            // Export badgetypes for this group
            $btWhere = [
                new SearchTerm('event_id', $eventId, JoinedTableAlias: 'grp'),
                new SearchTerm('context_code', $contextCode, '=', JoinedTableAlias: 'grp'),
            ];
            $btView = new View(
                [
                    'name', 'description', 'rewards', 'price', 'display_order',
                    'max_applicant_count', 'max_assignment_count',
                    'base_applicant_count', 'base_assignment_count',
                    'price_per_applicant', 'price_per_assignment',
                    'max_prereg_discount', 'payable_onsite', 'quantity',
                    'payment_deferred', 'max_total_applications', 'max_total_applicants', 'max_total_assignments',
                    'start_date', 'end_date', 'min_age', 'max_age', 'active_override_code', 'active', 'notes',
                ],
                [new Join($this->group, ['id' => 'group_id'], 'LEFT', 'grp')]
            );
            $btRows = $this->badgetType->Search($btView, $btWhere, ['display_order' => false]) ?: [];
            $group['badgetypes'] = array_map(function ($row) {
                $clean = [];
                foreach ($row as $k => $v) {
                    $clean[str_replace('grp_', '', $k)] = $v;
                }
                return $clean;
            }, $btRows);

            // Export addons for this group
            $addonWhere = [
                new SearchTerm('event_id', $eventId, JoinedTableAlias: 'grp'),
                new SearchTerm('context_code', $contextCode, '=', JoinedTableAlias: 'grp'),
            ];
            $addonView = new View(
                [
                    'name', 'description', 'price', 'display_order',
                    'quantity', 'start_date', 'end_date', 'active', 'notes',
                ],
                [new Join($this->group, ['id' => 'group_id'], 'LEFT', 'grp')]
            );
            $addonRows = $this->addon->Search($addonView, $addonWhere, ['display_order' => false]) ?: [];
            $group['addons'] = array_map(function ($row) {
                $clean = [];
                foreach ($row as $k => $v) {
                    $clean[str_replace('grp_', '', $k)] = $v;
                }
                return $clean;
            }, $addonRows);

            // Attach addon-to-badgetype mappings (from addonmap)
            if (!empty($group['addons'])) {
                $addonNames = [];
                foreach ($group['addons'] as $i => $addon) {
                    $addonNames[] = $addon['name'];
                }

                // Build addon name → IDs map from already-fetched addons
                $idToName = array_column($group['addons'], 'name', 'id');

                $validAddonIds = array_keys($idToName);
                $addonMaps = $this->addonMap->Search(
                    ['addon_id', 'badge_type_id'],
                    [new SearchTerm('addon_id', $validAddonIds, 'IN')]
                ) ?: [];

                // Build addon name → badge type names map
                $addonBtMap = [];
                foreach ($addonMaps as $map) {
                    $addonName = $idToName[$map['addon_id']] ?? null;
                    if ($addonName === null) continue;
                    if (!isset($addonBtMap[$addonName])) {
                        $addonBtMap[$addonName] = [];
                    }
                    $addonBtMap[$addonName][] = $map['badge_type_id'];
                }

                // Fetch badge type IDs → names
                $btIds = [];
                foreach ($addonBtMap as $btIdsArr) {
                    foreach ($btIdsArr as $btId) {
                        $btIds[$btId] = true;
                    }
                }
                $btNameMap = [];
                if (!empty($btIds)) {
                    $btRows2 = $this->badgetType->Search(
                        ['id', 'name'],
                        [new SearchTerm('id', array_keys($btIds), 'IN')]
                    ) ?: [];
                    foreach ($btRows2 as $bt) {
                        $btNameMap[$bt['id']] = $bt['name'];
                    }
                }

                // Attach applied_to_badge_types to each addon
                foreach ($group['addons'] as &$addon) {
                    $addon['applied_to_badge_types'] = [];
                    if (isset($addonBtMap[$addon['name']])) {
                        foreach ($addonBtMap[$addon['name']] as $btId) {
                            $btNameMap[$btId] && $addon['applied_to_badge_types'][] = $btNameMap[$btId];
                        }
                    }
                    unset($addon['description']); // already on parent addon or less critical
                }
                unset($addon);
            }

            // Nest promos that reference this group's badge types or addons
            $promoWhere = [new SearchTerm('event_id', $eventId)];
            $promoRows = $this->promoCode->Search(
                ['code', 'description', 'discount', 'is_percentage', 'active', 'quantity',
                 'start_date', 'end_date', 'limit_per_customer', 'notes'],
                $promoWhere
            ) ?: [];

            $group['promos'] = [];
            foreach ($promoRows as $row) {
                $promo = [
                    'code' => $row['code'],
                    'description' => $row['description'],
                    'discount' => $row['discount'],
                    'is_percentage' => (bool)$row['is_percentage'],
                    'active' => (bool)$row['active'],
                    'quantity' => $row['quantity'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'limit_per_customer' => $row['limit_per_customer'],
                    'notes' => $row['notes'],
                    'valid_badge_type_references' => [],
                    'valid_addon_references' => [],
                ];

                // Decode valid_badge_type_ids and match by context_code + name
                if (!empty($row['valid_badge_type_ids'])) {
                    $btIds = array_filter(array_map('intval', explode(',', $row['valid_badge_type_ids'])));
                    if (!empty($btIds)) {
                        $btRows = $this->badgetType->Search(
                            ['id', 'name', 'group_id'],
                            [new SearchTerm('id', $btIds, 'IN')]
                        ) ?: [];
                        foreach ($btRows as $bt) {
                            if ($bt['group_id'] == $groupId) {
                                $promo['valid_badge_type_references'][] = [
                                    'context_code' => $contextCode,
                                    'name' => $bt['name'],
                                ];
                            }
                        }
                    }
                }

                // Decode valid_addon_ids and match by context_code + name
                if (!empty($row['valid_addon_ids'])) {
                    $addonIds = array_filter(array_map('intval', explode(',', $row['valid_addon_ids'])));
                    if (!empty($addonIds)) {
                        $addonRows = $this->addon->Search(
                            ['id', 'name', 'group_id'],
                            [new SearchTerm('id', $addonIds, 'IN')]
                        ) ?: [];
                        foreach ($addonRows as $addon) {
                            if ($addon['group_id'] == $groupId) {
                                $promo['valid_addon_references'][] = [
                                    'context_code' => $contextCode,
                                    'name' => $addon['name'],
                                ];
                            }
                        }
                    }
                }

                // Only include promos that reference this group
                if (!empty($promo['valid_badge_type_references']) || !empty($promo['valid_addon_references'])) {
                    $group['promos'][] = $promo;
                }
            }

            // Nest questions scoped to this context_code
            $qWhere = [
                new SearchTerm('event_id', $eventId),
                new SearchTerm('context_code', $contextCode),
            ];
            $qRows = $this->question->Search('*', $qWhere) ?: [];
            $group['questions'] = $this->stripAndClean($qRows ?: [], $this->question);

            // Save question IDs for FK matching, then strip them
            foreach ($group['questions'] as &$qq) {
                $qq['_q'] = $qq['id'];
                unset($qq['id']);
                unset($qq['event_id']);
            }
            unset($qq);

            // Attach badge type references from questionmap
            foreach ($group['questions'] as &$q) {
                $q['required_badge_types'] = [];
                $maps = $this->questionMap->Search(
                    ['context_code', 'badge_type_id', 'required'],
                    [new SearchTerm('context_code', $contextCode), new SearchTerm('question_id', $q['_q'])]
                ) ?: [];
                $btIds = [];
                foreach ($maps as $m) {
                    $btIds[$m['badge_type_id']] = $m['required'];
                }
                if (empty($btIds)) continue;
                $btRows = $this->badgetType->Search(
                    ['id', 'name', 'group_id'],
                    [new SearchTerm('id', array_keys($btIds), 'IN')]
                ) ?: [];
                foreach ($btRows as $bt) {
                    if ($bt['group_id'] == $groupId) {
                        $q['required_badge_types'][] = [
                            'context_code' => $contextCode,
                            'name' => $bt['name'],
                            'required' => $btIds[$bt['id']],
                        ];
                    }
                }
                unset($q['_q']);
            }
            unset($q);

            // Nest templates scoped to this context_code
            $tWhere = [
                new SearchTerm('event_id', $eventId),
                new SearchTerm('context_code', $contextCode),
            ];
            $tRows = $this->template->Search('*', $tWhere);
            $group['templates'] = $this->stripAndClean($tRows ?: [], $this->template);
            // Strip id and event_id — resolved by context on import
            foreach ($group['templates'] as &$tt) {
                unset($tt['id']);
                unset($tt['event_id']);
            }
            unset($tt);
            
        }
        unset($group);

        // 3. Create dummy group entries for Staff (S) and Attendee (A) badge types
        foreach ([
            'S' => 'Staff',
            'A' => 'Attendee',
        ] as $contextCode => $displayName) {
            // Staff badge types
            if ($contextCode === 'S') {
                $btRows = $this->staffBadgeType->Search(
                    [
                        'name', 'description', 'rewards', 'price', 'display_order',
                        'payable_onsite', 'quantity',
                        'payment_deferred',
                        'start_date', 'end_date', 'min_age', 'max_age', 'active_override_code', 'active', 'notes',
                    ],
                    [new SearchTerm('event_id', $eventId)]
                ) ?: [];
            } else {
                // Attendee
                $btRows = $this->attendeeBadgeType->Search(
                    [
                        'name', 'description', 'rewards', 'price', 'display_order',
                        'payable_onsite', 'quantity',
                        'start_date', 'end_date', 'min_age', 'max_age', 'active_override_code', 'active', 'notes',
                    ],
                    [new SearchTerm('event_id', $eventId)]
                ) ?: [];
            }

            $btRows = array_map(function ($row) {
                $clean = [];
                foreach ($row as $k => $v) {
                    $clean[$k] = $v;
                }
                return $clean;
            }, $btRows);

            // Sort by display_order
            usort($btRows, function ($a, $b) {
                $oa = $a['display_order'] ?? 9999;
                $ob = $b['display_order'] ?? 9999;
                return $oa <=> $ob ?: strcmp($a['name'] ?? '', $b['name'] ?? '');
            });

            $groups[] = [
                'context_code' => $contextCode,
                'name' => $displayName,
                'active' => true,
                'display_order' => $contextCode === 'S' ? 1 : 2,
                'can_assign_slot' => false,
                'application_name1' => $displayName,
                'menu_icon' => null,
                'description' => null,
                'notes' => 'Auto-created for ' . $contextCode . ' badge types',
                'badgetypes' => $btRows,
                'addons' => [],
                'promos' => [],
                'questions' => [],
                'templates' => [],
            ];
        }

        // Sort groups by display_order then context_code
        usort($groups, function ($a, $b) {
            $oa = $a['display_order'] ?? 9999;
            $ob = $b['display_order'] ?? 9999;
            if ($oa !== $ob) return $oa <=> $ob;
            return strcmp($a['context_code'] ?? '', $b['context_code'] ?? '');
        });

        // Strip id and event_id from groups — context_code is the stable identifier
        foreach ($groups as &$group) {
            unset($group['id']);
            unset($group['event_id']);
        }
        unset($group);
        return $groups;
    }

    /**
     * Export format-to-badgetype mappings with named references.
     * Badges formats reference badge types by ID; we convert to context_code + name.
     *
     * @return array Each entry contains: context_code, format_name,
     *               badge_type_reference { context_code, name }
     */
    private function exportFormatMaps(int $eventId, array $contextCodes): array
    {
        $where = !empty($contextCodes)
            ? [new SearchTerm('context_code', $contextCodes, 'IN')]
            : null;

        $maps = $this->formatMap->Search(
            ['context_code', 'format_id', 'badge_type_id'],
            $where
        ) ?: [];

        $results = [];
        foreach ($maps as $map) {
            // Get format name
            $formatRows = $this->format->Search(
                ['id', 'name'],
                [new SearchTerm('id', $map['format_id'])]
            );
            $formatName = $formatRows[0]['name'] ?? null;

            // Get badge type info
            $btRows = $this->badgetType->Search(
                ['id', 'name', 'group_id'],
                [new SearchTerm('id', $map['badge_type_id'])]
            );

            if (empty($btRows) || empty($formatName)) continue;

            $bt = $btRows[0];
            $groupRow = $this->group->Search(
                ['context_code'],
                [new SearchTerm('id', $bt['group_id'])]
            );
            $contextCode = $groupRow[0]['context_code'] ?? null;

            if ($contextCode) {
                $results[] = [
                    'context_code' => $map['context_code'],
                    'format_name' => $formatName,
                    'badge_type_reference' => [
                        'context_code' => $contextCode,
                        'name' => $bt['name'],
                    ],
                ];
            }
        }

        return $results;
    }

    /**
     * Export formats with nested format maps.
     * Each format contains a 'maps' array of badge_type_reference objects.
     * Format maps are matched by format_id, and each map includes the
     * badge type context_code + name for cross-event matching.
     *
     * @return array Each format contains: name, width, height, orientation, notes, plus nested 'maps'[]
     */
    private function exportFormats(int $eventId, array $contextCodes): array
    {
        $where = [new SearchTerm('event_id', $eventId)];
        $formats = $this->format->Search('*', $where) ?: [];

        $mapWhere = !empty($contextCodes)
            ? [new SearchTerm('context_code', $contextCodes, 'IN')]
            : null;

        $allMaps = $this->formatMap->Search(
            ['format_id', 'context_code', 'badge_type_id'],
            $mapWhere
        ) ?: [];

        // Build format_id -> badge_type lookup for maps
        $badgeTypeCache = [];
        foreach ($allMaps as $map) {
            $btId = $map['badge_type_id'];
            if (isset($badgeTypeCache[$btId])) continue;

            $btRows = $this->badgetType->Search(
                ['id', 'name', 'group_id'],
                [new SearchTerm('id', $btId)]
            );

            if (!empty($btRows)) {
                $bt = $btRows[0];
                $groupRow = $this->group->Search(
                    ['context_code'],
                    [new SearchTerm('id', $bt['group_id'])]
                );
                $contextCode = $groupRow[0]['context_code'] ?? null;

                if ($contextCode) {
                    $badgeTypeCache[$btId] = [
                        'context_code' => $contextCode,
                        'name' => $bt['name'],
                    ];
                }
            }
        }

        // Group maps by format_id
        $mapsByFormat = [];
        foreach ($allMaps as $map) {
            $fid = $map['format_id'];
            if (isset($badgeTypeCache[$map['badge_type_id']])) {
                $mapsByFormat[$fid][] = $badgeTypeCache[$map['badge_type_id']];
            }
        }

        // Build result with nested maps
        $results = [];
        foreach ($formats as &$fmt) {
            $cleaned = $this->stripAndClean([$fmt], $this->format)[0];
            unset($cleaned['id'], $cleaned['event_id']);

            $fid = $fmt['id'];
            if (isset($mapsByFormat[$fid]) && !empty($mapsByFormat[$fid])) {
                $cleaned['maps'] = $mapsByFormat[$fid];
            } else {
                $cleaned['maps'] = [];
            }

            $results[] = $cleaned;
        }

        return $results;
    }

    /**
     * Strip auto-generated, computed, and auto-incrementing columns from search results,
     * and remove join alias prefixes from column names.
     *
     * Uses "*" wildcard column selection via ExportHelpers to ensure
     * all non-computed, non-auto-generated columns are included.
     *
     * @param array<array<string, mixed>> $rows The rows returned from Search().
     * @param object $tableModel The Table model instance (provides ColumnDefs).
     * @param string|null $aliasPrefix Optional alias prefix to strip (e.g. "grp_").
     * @param bool $isTopLevel Whether this entity is top-level (groups). If false,
     *                         auto-incrementing IDs are also excluded.
     * @return array<array<string, mixed>> Cleaned rows ready for export.
     */
    private function stripAndClean(array $rows, object $tableModel, ?string $aliasPrefix = null, bool $isTopLevel = true): array
    {
        if (empty($rows)) {
            return $rows;
        }

        // Strip auto-generated, computed, and (for children) auto-incrementing columns
        $cleaned = ExportHelpers::stripExportColumns($rows, $tableModel->ColumnDefs, $isTopLevel);

        // Remove alias prefixes if this was a joined query result
        if ($aliasPrefix !== null) {
            $cleaned = array_map(function ($row) use ($aliasPrefix) {
                $newRow = [];
                foreach ($row as $key => $value) {
                    $newKey = $key;
                    if (str_starts_with($key, $aliasPrefix)) {
                        $newKey = substr($key, strlen($aliasPrefix));
                    }
                    $newRow[$newKey] = $value;
                }
                return $newRow;
            }, $cleaned);
        }

        return $cleaned;
    }
}
