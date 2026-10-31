<?php

namespace CM3_Lib\Action\System\EventInfo;

use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
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
use CM3_Lib\Responder\Responder;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action: Import event configuration data from a JSON blob.
 * Validates structure, matches groups by context_code, and creates/updates records.
 *
 * Expects a hierarchical structure where:
 * - groups contain nested badgetypes[] and addons[]
 * - addons contain applied_to_badge_types[] (badge type names)
 * - promos contain valid_badge_type_references[] and valid_addon_references[]
 * - questions contain required_badge_types[]
 * - format_maps contain format_name and badge_type_reference{}
 *
 * The import resolves all references by context_code + name and remaps
 * foreign keys to the target event's IDs.
 */
final class Import
{
    /** @var array<string, array<int, int>> Mapping of [source_id => new_id] per entity */
    private array $idMap = [];

    /** @var array<string, int> Mapping of [context_code => group_id] for current target event */
    private array $contextToGroupId = [];

    /** @var array<string, array<string, int>> Flat index: [context_code => [badge_type_name => id]] */
    private array $flatBadgeTypeIndex = [];

    /** @var array<string, array<string, int>> Flat index: [context_code => [addon_name => id]] */
    private array $flatAddonIndex = [];

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

        if (empty($data)) {
            return $this->responder->withError(
                $response,
                StatusCodeInterface::STATUS_BAD_REQUEST,
                'No import data provided'
            );
        }

        $options = $data['options'] ?? [];
        $eventId = $this->currentUser->GetEventId();
        $dryRun = ($options['dry_run'] ?? false) === true || ($options['dry_run'] ?? '') === 'true';
        $mode = $options['mode'] ?? 'create'; // 'create' or 'upsert'
        $contextCodes = $options['context_codes'] ?? [];

        // Validate structure
        $errors = $this->validateImportStructure($data);
        if (!empty($errors)) {
            return $this->responder->withError(
                $response,
                StatusCodeInterface::STATUS_BAD_REQUEST,
                json_encode(['validation_errors' => $errors])
            );
        }

        $results = [
            'dry_run' => $dryRun,
            'target_event_id' => $eventId,
            'mode' => $mode,
            'summary' => [],
            'details' => [],
        ];

        $this->idMap = [];
        $this->contextToGroupId = [];
        $this->flatBadgeTypeIndex = [];
        $this->flatAddonIndex = [];

        // Step 1: Import groups with nested badgetypes and addons
        if (!empty($data['groups'])) {
            $groupResults = $this->importGroups($data['groups'], $eventId, $mode, $dryRun);
            $results['summary']['groups'] = $groupResults;
            $results['details']['groups'] = [];

            // After importing groups, index badgetypes and addons for later reference
            $this->buildFlatIndices($eventId, $contextCodes);
        }

        // Step 2: Event-scoped entities that reference badgetypes/addons by context_code + name
        if (!empty($data['promos'])) {
            $promoResults = $this->importPromos($data['promos'], $eventId, $mode, $dryRun);
            $results['summary']['promos'] = $promoResults;
            $results['details']['promos'] = [];
        }

        if (!empty($data['questions'])) {
            $qResults = $this->importQuestions($data['questions'], $eventId, $contextCodes, $mode, $dryRun);
            $results['summary']['questions'] = $qResults;
            $results['details']['questions'] = [];
        }

        if (!empty($data['templates'])) {
            $tResults = $this->importTemplates($data['templates'], $eventId, $contextCodes, $mode, $dryRun);
            $results['summary']['templates'] = $tResults;
            $results['details']['templates'] = [];
        }

        if (!empty($data['location_categories'])) {
            $lcResults = $this->importLocationCategories($data['location_categories'], $eventId, $mode, $dryRun);
            $results['summary']['location_categories'] = $lcResults;
            $results['details']['location_categories'] = [];
        }

        if (!empty($data['locations'])) {
            $locResults = $this->importLocations($data['locations'], $eventId, $mode, $dryRun);
            $results['summary']['locations'] = $locResults;
            $results['details']['locations'] = [];
        }

        if (!empty($data['formats'])) {
            $fmtResults = $this->importFormats($data['formats'], $eventId, $mode, $dryRun);
            $results['summary']['formats'] = $fmtResults;
            $results['details']['formats'] = [];
        }

        if (!empty($data['format_maps'])) {
            $fmResults = $this->importFormatMaps($data['format_maps'], $mode, $dryRun);
            $results['summary']['format_maps'] = $fmResults;
            $results['details']['format_maps'] = [];
        }

        return $this->responder->withJson($response, $results);
    }

    /**
     * Validate the overall structure of the import data.
     */
    private function validateImportStructure(array $data): array
    {
        $errors = [];

        if (isset($data['metadata'])) {
            if (!isset($data['metadata']['exported_at'])) {
                $errors[] = 'Missing metadata.exported_at';
            }
        }

        $validKeys = [
            'event', 'groups', 'promos', 'questions', 'templates',
            'locations', 'location_categories', 'format_maps', 'formats',
            'metadata', 'options',
        ];
        $extra = array_diff(array_keys($data), $validKeys);
        if (!empty($extra)) {
            $errors[] = 'Unknown top-level keys: ' . implode(', ', array_keys($extra));
        }

        return $errors;
    }

    /**
     * Load existing groups in target event to build context_code → group_id map.
     */
    private function loadContextToGroupId(int $eventId): void
    {
        $groups = $this->group->Search(
            ['id', 'context_code'],
            [new SearchTerm('event_id', $eventId)]
        );
        foreach ($groups as $g) {
            if (isset($g['context_code']) && isset($g['id'])) {
                $this->contextToGroupId[$g['context_code']] = $g['id'];
            }
        }
    }

    /**
     * Get the group_id for a context_code in the target event.
     * Creates a new group if it doesn't exist and mode is 'upsert'.
     */
    private function getGroupIdForContext(int $eventId, string $contextCode, string $mode, bool $dryRun): ?int
    {
        if (isset($this->contextToGroupId[$contextCode])) {
            return $this->contextToGroupId[$contextCode];
        }

        if ($mode === 'upsert' && !$dryRun) {
            $groupData = [
                'event_id' => $eventId,
                'context_code' => $contextCode,
                'active' => true,
                'display_order' => 0,
                'name' => 'Imported ' . $contextCode,
            ];
            $this->group->Create($groupData);
            $newId = $this->group->getLastInsertId();
            $this->contextToGroupId[$contextCode] = $newId;
            return $newId;
        }

        return null;
    }

    /**
     * Import groups with nested badgetypes and addons.
     */
    private function importGroups(array $groups, int $eventId, string $mode, bool $dryRun): array
    {
        $count = 0;
        $skipped = 0;
        $errors = [];

        foreach ($groups as $groupData) {
            $contextCode = $groupData['context_code'] ?? null;
            if (!$contextCode) continue;

            $this->contextToGroupId[$contextCode] = null; // mark as pending

            try {
                $groupId = $this->getGroupIdForContext($eventId, $contextCode, $mode, $dryRun);

                if ($groupId !== null) {
                    $this->contextToGroupId[$contextCode] = $groupId;

                    if ($mode === 'upsert') {
                        $groupData['id'] = $groupId;
                        unset($groupData['context_code']); // can't update context_code
                        $this->group->Update($groupData, [new SearchTerm('id', $groupId)]);
                    }

                    // Import nested badgetypes
                    if (!empty($groupData['badgetypes']) && !$dryRun) {
                        $btResults = $this->importBadgeTypesForGroup(
                            $groupData['badgetypes'], $groupId, $eventId, $contextCode, $mode
                        );
                        $count += $btResults['imported'];
                        $errors = array_merge($errors, $btResults['errors']);
                    }

                    // Import nested addons
                    if (!empty($groupData['addons']) && !$dryRun) {
                        $addonResults = $this->importAddonsForGroup(
                            $groupData['addons'], $groupId, $eventId, $contextCode, $mode
                        );
                        $count += $addonResults['imported'];
                        $errors = array_merge($errors, $addonResults['errors']);

                        // Import addon maps from applied_to_badge_types
                        $amResults = $this->importAddonMapsForGroup(
                            $groupData['addons'], $groupId, $contextCode
                        );
                        $errors = array_merge($errors, $amResults['errors']);
                    }

                    $count++;
                } else {
                    // Group doesn't exist in create mode — skip (context_code must match)
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors[] = "Group $contextCode: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import badgetypes nested under a specific group.
     */
    private function importBadgeTypesForGroup(array $badgetypes, int $groupId, int $eventId, string $contextCode, string $mode): array
    {
        $count = 0;
        $errors = [];

        foreach ($badgetypes as $btData) {
            $name = $btData['name'] ?? null;
            if (!$name) continue;

            if ($mode === 'create') {
                unset($btData['id']);
            }

            try {
                $btData['group_id'] = $groupId;
                $this->badgetType->Create($btData);
                $newId = $this->badgetType->getLastInsertId();
                $this->flatBadgeTypeIndex[$contextCode][$name] = $newId;
                $count++;
            } catch (\Exception $e) {
                $errors[] = "BadgeType $name: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Import addons nested under a specific group.
     */
    private function importAddonsForGroup(array $addons, int $groupId, int $eventId, string $contextCode, string $mode): array
    {
        $count = 0;
        $errors = [];

        foreach ($addons as $addonData) {
            $name = $addonData['name'] ?? null;
            if (!$name) continue;

            if ($mode === 'create') {
                unset($addonData['id']);
            }

            // Remove applied_to_badge_types before creating addon (not a column)
            unset($addonData['applied_to_badge_types']);

            try {
                $addonData['group_id'] = $groupId;
                $this->addon->Create($addonData);
                $newId = $this->addon->getLastInsertId();
                $this->flatAddonIndex[$contextCode][$name] = $newId;
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Addon $name: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Import addon-to-badgetype mappings from applied_to_badge_types arrays.
     */
    private function importAddonMapsForGroup(array $addons, int $groupId, string $contextCode): array
    {
        $count = 0;
        $errors = [];

        foreach ($addons as $addonData) {
            $addonName = $addonData['name'] ?? null;
            if (!$addonName || empty($addonData['applied_to_badge_types'])) continue;

            if (!isset($this->flatAddonIndex[$contextCode][$addonName])) continue;
            $addonId = $this->flatAddonIndex[$contextCode][$addonName];

            foreach ($addonData['applied_to_badge_types'] as $btRef) {
                $btId = null;

                if (is_string($btRef)) {
                    // Simple name reference (legacy format)
                    $btId = $this->flatBadgeTypeIndex[$contextCode][$btRef] ?? null;
                } elseif (is_array($btRef) && isset($btRef['name'])) {
                    // Named reference with context_code
                    $refContext = $btRef['context_code'] ?? $contextCode;
                    $btId = $this->flatBadgeTypeIndex[$refContext][$btRef['name']] ?? null;
                }

                if ($btId !== null) {
                    try {
                        $this->addonMap->Create([
                            'addon_id' => $addonId,
                            'badge_type_id' => $btId,
                        ]);
                        $count++;
                    } catch (\Exception $e) {
                        $errors[] = "AddonMap ($addonName → {$btRef}): " . $e->getMessage();
                    }
                }
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Build flat indices of badgetypes and addons by context_code + name
     * for resolving named references from promos, questions, and format_maps.
     */
    private function buildFlatIndices(int $eventId, array $contextCodes): void
    {
        // Application badge types (from groups)
        $groups = $this->group->Search(
            ['id', 'context_code'],
            [new SearchTerm('event_id', $eventId)]
        );
        foreach ($groups as $g) {
            $ctx = $g['context_code'];
            if (!empty($contextCodes) && !in_array($ctx, $contextCodes, true)) continue;

            $btRows = $this->badgetType->Search(
                ['id', 'name', 'group_id'],
                [new SearchTerm('group_id', $g['id'])]
            ) ?: [];
            foreach ($btRows as $bt) {
                $this->flatBadgeTypeIndex[$ctx][$bt['name']] = $bt['id'];
            }

            $addonRows = $this->addon->Search(
                ['id', 'name', 'group_id'],
                [new SearchTerm('group_id', $g['id'])]
            ) ?: [];
            foreach ($addonRows as $a) {
                $this->flatAddonIndex[$ctx][$a['name']] = $a['id'];
            }
        }

        // Staff badge types
        $staffRows = $this->staffBadgeType->Search(['id', 'name'], [new SearchTerm('event_id', $eventId)]) ?: [];
        foreach ($staffRows as $bt) {
            $this->flatBadgeTypeIndex['S'][$bt['name']] = $bt['id'];
        }

        // Attendee badge types
        $attendeeRows = $this->attendeeBadgeType->Search(['id', 'name'], [new SearchTerm('event_id', $eventId)]) ?: [];
        foreach ($attendeeRows as $bt) {
            $this->flatBadgeTypeIndex['A'][$bt['name']] = $bt['id'];
        }
    }

    /**
     * Import promo codes with badge type/addon references resolved by context_code + name.
     */
    private function importPromos(array $promos, int $eventId, string $mode, bool $dryRun): array
    {
        $count = 0;
        $errors = [];

        foreach ($promos as $promoData) {
            if ($mode === 'create') {
                unset($promoData['id']);
            }

            $promoData['event_id'] = $eventId;

            // Resolve valid_badge_type_references to comma-separated IDs
            $btIds = [];
            if (!empty($promoData['valid_badge_type_references'])) {
                foreach ($promoData['valid_badge_type_references'] as $ref) {
                    $refContext = $ref['context_code'] ?? '?';
                    $refName = $ref['name'] ?? null;
                    if ($refName && isset($this->flatBadgeTypeIndex[$refContext][$refName])) {
                        $btIds[] = $this->flatBadgeTypeIndex[$refContext][$refName];
                    }
                }
            }
            $promoData['valid_badge_type_ids'] = implode(',', $btIds);

            // Resolve valid_addon_references to comma-separated IDs
            $addonIds = [];
            if (!empty($promoData['valid_addon_references'])) {
                foreach ($promoData['valid_addon_references'] as $ref) {
                    $refContext = $ref['context_code'] ?? '?';
                    $refName = $ref['name'] ?? null;
                    if ($refName && isset($this->flatAddonIndex[$refContext][$refName])) {
                        $addonIds[] = $this->flatAddonIndex[$refContext][$refName];
                    }
                }
            }
            $promoData['valid_addon_ids'] = implode(',', $addonIds);

            // Remove reference arrays (not columns)
            unset($promoData['valid_badge_type_references']);
            unset($promoData['valid_addon_references']);

            if ($dryRun) {
                $count++;
                continue;
            }

            try {
                $this->promoCode->Create($promoData);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Promo: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Import form questions with badge type references resolved by context_code + name.
     */
    private function importQuestions(array $questions, int $eventId, array $contextCodes, string $mode, bool $dryRun): array
    {
        $count = 0;
        $errors = [];

        foreach ($questions as $qData) {
            $qData['event_id'] = $eventId;

            if ($mode === 'create') {
                unset($qData['id']);
            }

            if (!empty($contextCodes) && isset($qData['context_code'])) {
                if (!in_array($qData['context_code'], $contextCodes, true)) {
                    continue;
                }
            }

            // Resolve required_badge_types to questionmap records
            $requiredBt = [];
            if (!empty($qData['required_badge_types'])) {
                foreach ($qData['required_badge_types'] as $ref) {
                    $requiredBt[] = $ref;
                }
            }
            unset($qData['required_badge_types']);

            if ($dryRun) {
                $count++;
                continue;
            }

            try {
                $this->question->Create($qData);
                $qId = $this->question->getLastInsertId();

                // Create questionmap records for each badge type reference
                foreach ($requiredBt as $ref) {
                    $refContext = $ref['context_code'] ?? ($qData['context_code'] ?? null);
                    $refName = $ref['name'] ?? null;
                    if (!$refName || !$refContext) continue;

                    $btId = $this->flatBadgeTypeIndex[$refContext][$refName] ?? null;
                    if ($btId === null) continue;

                    $this->questionMap->Create([
                        'context_code' => $qData['context_code'],
                        'badge_type_id' => $btId,
                        'question_id' => $qId,
                        'required' => $ref['required'] ?? false,
                    ]);
                }

                $count++;
            } catch (\Exception $e) {
                $errors[] = "Question: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Import mail templates (event + context scoped).
     */
    private function importTemplates(array $templates, int $eventId, array $contextCodes, string $mode, bool $dryRun): array
    {
        $count = 0;
        $errors = [];

        foreach ($templates as $record) {
            $origName = $record['name'] ?? null;
            if (!$origName) continue;

            $origContextCode = $record['context_code'] ?? null;

            if (!empty($contextCodes) && isset($record['context_code'])) {
                if (!in_array($record['context_code'], $contextCodes, true)) {
                    continue;
                }
            }

            $record['event_id'] = $eventId;

            if ($dryRun) {
                $count++;
                continue;
            }

            try {
                if ($mode === 'upsert') {
                    $this->template->Update([], [
                        new SearchTerm('event_id', $eventId),
                        new SearchTerm('context_code', $origContextCode),
                        new SearchTerm('name', $origName),
                    ]);
                }
                unset($record['id']);
                $this->template->Create($record);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Template $origName: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Import location categories.
     */
    private function importLocationCategories(array $categories, int $eventId, string $mode, bool $dryRun): array
    {
        $count = 0;
        $errors = [];

        foreach ($categories as $record) {
            $record['event_id'] = $eventId;

            if ($mode === 'create') {
                unset($record['id']);
            }

            if ($dryRun) {
                $count++;
                continue;
            }

            try {
                $this->locationCategory->Create($record);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "LocationCategory: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Import locations.
     */
    private function importLocations(array $locations, int $eventId, string $mode, bool $dryRun): array
    {
        $count = 0;
        $errors = [];

        foreach ($locations as $record) {
            $record['event_id'] = $eventId;

            if ($mode === 'create') {
                unset($record['id']);
            }

            if ($dryRun) {
                $count++;
                continue;
            }

            try {
                $this->location->Create($record);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Location: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Import badge formats.
     */
    private function importFormats(array $formats, int $eventId, string $mode, bool $dryRun): array
    {
        $count = 0;
        $errors = [];

        foreach ($formats as $record) {
            $record['event_id'] = $eventId;

            if ($mode === 'create') {
                unset($record['id']);
            }

            if ($dryRun) {
                $count++;
                continue;
            }

            try {
                $this->format->Create($record);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Format: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }

    /**
     * Import format-to-badgetype mappings with named references resolved.
     */
    private function importFormatMaps(array $maps, string $mode, bool $dryRun): array
    {
        $count = 0;
        $errors = [];

        foreach ($maps as $map) {
            $formatName = $map['format_name'] ?? null;
            $btRef = $map['badge_type_reference'] ?? null;

            if (!$formatName || !$btRef) continue;

            // Get format ID by name
            $formatRows = $this->format->Search(
                ['id', 'name'],
                [new SearchTerm('name', $formatName)]
            ) ?: [];
            if (empty($formatRows)) continue;
            $formatId = $formatRows[0]['id'];

            // Get badge type ID by reference
            $refContext = $btRef['context_code'] ?? '?';
            $refName = $btRef['name'] ?? null;
            if (!$refName) continue;

            $btId = $this->flatBadgeTypeIndex[$refContext][$refName] ?? null;
            if ($btId === null) continue;

            if ($dryRun) {
                $count++;
                continue;
            }

            try {
                $this->formatMap->Create([
                    'context_code' => $map['context_code'] ?? '',
                    'format_id' => $formatId,
                    'badge_type_id' => $btId,
                ]);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "FormatMap: " . $e->getMessage();
            }
        }

        return ['imported' => $count, 'errors' => $errors];
    }
}
