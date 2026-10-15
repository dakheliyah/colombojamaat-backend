<?php

namespace App\Services;

use App\Enums\SharafStatus;
use App\Exceptions\DuplicateSharafAssignmentException;
use App\Models\Census;
use App\Models\ForeignCensus;
use App\Models\Sharaf;
use App\Models\SharafDefinition;
use App\Models\SharafMember;
use App\Models\SharafPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class NikahImportService
{
    private const MAX_ROWS = 5000;

    /** @var array<string, int> */
    private const ROLES = [
        'Dulha' => 1,
        'Dulhan' => 1,
        'Wali' => 1,
        'Shahid 1' => 1,
        'Shahid 2' => 1,
        'Dulha Maa' => 1,
        'Dulhan Maa' => 1,
        'Extra Member' => 5,
    ];

    private const REQUIRED_HEADERS = [
        'Token',
        'Bethak_Date',
        'Group_ID',
        'Mumin_ID',
        'FullName',
        'Mumin_Type',
        'Mobile_No',
        'Age',
        'Gender',
        'Jamaat',
        'Jamiaat',
    ];

    /** @var list<string> */
    private const CENSUS_FIELDS = ['name', 'arabic_name', 'age', 'gender', 'jamaat', 'jamiat', 'mobile', 'email'];

    /** @var array<string, string> */
    private const DEFAULT_CENSUS_COLUMNS = [
        'name' => 'FullName',
        'arabic_name' => 'Arabic_FullName',
        'age' => 'Age',
        'gender' => 'Gender',
        'jamaat' => 'Jamaat',
        'jamiat' => 'Jamiaat',
        'mobile' => 'Mobile_No',
        'email' => 'Email',
    ];

    /** @var array<string, int> */
    private const MISAQ_ROLES = [
        'Group incharge' => 1,
        'Member' => 0,
    ];

    /** Sheet roles that stay on the row and are not given a sharaf position. */
    private const UNMAPPED_ROLES = ['Wakil'];

    /** @var list<string> */
    private const STRUCTURAL_HEADERS = ['Token', 'Bethak_Date', 'Group_ID', 'Mumin_ID', 'Mumin_Type'];

    public function __construct(
        protected NikahSheetReader $reader,
        protected SharafAllocationService $allocationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, int>  $roleMap  Sheet role name to sharaf position id
     * @param  array<string, string>|null  $censusMap  Sheet column to census field. Null keeps the default map.
     * @param  list<string>  $skipIts
     */
    public function preview(SharafDefinition $definition, string $path, array $roleMap = [], ?array $censusMap = null, array $skipIts = [], string $module = 'nikah'): array
    {
        return $this->analyze($definition, $path, $roleMap, $censusMap, $skipIts, $module)['public'];
    }

    /**
     * @param  array<string, int>  $roleMap
     * @param  array<string, string>|null  $censusMap
     * @param  list<string>  $skipIts
     * @return array{ok: bool, message: string, result: array<string, int>, preview: array<string, mixed>}
     */
    public function applyCensus(SharafDefinition $definition, string $path, array $roleMap = [], ?array $censusMap = null, array $skipIts = [], string $module = 'nikah'): array
    {
        $analyzed = $this->analyze($definition, $path, $roleMap, $censusMap, $skipIts, $module);
        if (!$analyzed['public']['can_apply_census']) {
            return [
                'ok' => false,
                'message' => 'The file has errors and census was not updated.',
                'result' => ['created' => 0, 'updated' => 0, 'unchanged' => 0],
                'preview' => $analyzed['public'],
            ];
        }

        $created = 0;
        $updated = 0;
        $sharafCounts = ['sharaf_names' => 0, 'member_names' => 0, 'member_phones' => 0];
        DB::transaction(function () use ($definition, $analyzed, &$created, &$updated, &$sharafCounts) {
            foreach ($analyzed['census'] as $person) {
                $this->writeCensusPerson($person);
                if ($person['action'] === 'create') {
                    $created++;
                } elseif ($person['action'] === 'update') {
                    $updated++;
                }
            }
            $sharafCounts = $this->syncSharafIdentity($definition, $analyzed['identity'] ?? []);
        });

        return [
            'ok' => true,
            'message' => 'Census updated.',
            'result' => [
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $analyzed['public']['census']['unchanged'],
                'sharaf_names' => $sharafCounts['sharaf_names'],
                'member_names' => $sharafCounts['member_names'],
                'member_phones' => $sharafCounts['member_phones'],
            ],
            'preview' => $analyzed['public'],
        ];
    }

    /**
     * @param  list<string>  $confirmRemovals
     * @param  list<string>  $confirmHofChanges
     * @return array{ok: bool, message: string, result: array<string, mixed>, preview: array<string, mixed>}
     */
    /**
     * @param  array<string, int>  $roleMap
     * @param  array<string, string>|null  $censusMap
     * @param  list<string>  $skipIts
     * @param  list<string>  $skipTokens  Parties left as they are on the sharaf
     */
    public function applySharafs(
        SharafDefinition $definition,
        string $path,
        array $confirmRemovals,
        array $confirmHofChanges,
        array $roleMap = [],
        ?array $censusMap = null,
        array $skipIts = [],
        array $skipTokens = [],
        string $module = 'nikah',
    ): array {
        $analyzed = $this->analyze($definition, $path, $roleMap, $censusMap, $skipIts, $module);
        $skipLookup = [];
        foreach ($skipTokens as $token) {
            $skipLookup[trim((string) $token)] = true;
        }
        $blocking = [];
        foreach ($analyzed['public']['parties'] as $party) {
            if ($party['errors'] === [] || isset($skipLookup[(string) $party['token']])) {
                continue;
            }
            foreach ($party['errors'] as $message) {
                $blocking[] = $message;
            }
        }
        if ($analyzed['public']['file_errors'] !== [] || $analyzed['public']['definition_errors'] !== [] || $blocking !== []) {
            return [
                'ok' => false,
                'message' => 'The file has errors and sharafs were not changed.',
                'result' => $this->emptySharafResult(),
                'preview' => $analyzed['public'],
            ];
        }

        $confirmRemovals = array_fill_keys($confirmRemovals, true);
        $confirmHofChanges = array_fill_keys($confirmHofChanges, true);
        $skipTokens = $skipLookup;
        $result = $this->emptySharafResult();

        try {
            DB::transaction(function () use ($definition, $analyzed, $confirmRemovals, $confirmHofChanges, $skipTokens, &$result) {
                SharafDefinition::query()->whereKey($definition->id)->lockForUpdate()->first();
                $rank = (int) Sharaf::query()->where('sharaf_definition_id', $definition->id)->max('rank');

                foreach ($analyzed['public']['parties'] as $party) {
                    $token = (string) $party['token'];
                    if ($party['errors'] !== [] && isset($skipTokens[$token])) {
                        $result['skipped_parties'][] = $token;
                    }
                }

                foreach ($analyzed['parties'] as $party) {
                    $token = (string) $party['token'];
                    if (isset($skipTokens[$token])) {
                        $result['skipped_parties'][] = $token;
                        continue;
                    }
                    if ($party['sharaf'] === null) {
                        $rank++;
                        $this->createParty($definition, $party, $rank, $result);
                        continue;
                    }
                    $this->updateParty($party, $confirmRemovals, $confirmHofChanges, $result);
                }
            });
        } catch (DuplicateSharafAssignmentException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        return [
            'ok' => true,
            'message' => 'Sharafs synced.',
            'result' => $result,
            'preview' => $analyzed['public'],
        ];
    }

    /**
     * @return array{public: array<string, mixed>, census: list<array<string, mixed>>, parties: list<array<string, mixed>>}
     */
    /**
     * @param  array<string, int>  $roleMap
     * @param  array<string, string>|null  $censusMap
     * @param  list<string>  $skipIts
     */
    private function analyze(SharafDefinition $definition, string $path, array $roleMap = [], ?array $censusMap = null, array $skipIts = [], string $module = 'nikah'): array
    {
        $definition->loadMissing(['event', 'sharafPositions']);
        $table = $this->reader->read($path);
        $fileErrors = $table['errors'];
        $warnings = [];

        if ($fileErrors !== []) {
            return $this->emptyAnalysis($fileErrors, [], $warnings);
        }

        $headerMap = [];
        foreach ($table['headers'] as $header) {
            $headerMap[mb_strtolower($header)] = $header;
        }
        foreach ($this->requiredHeaders($module) as $required) {
            if (!isset($headerMap[mb_strtolower($required)])) {
                $fileErrors[] = "Missing column \"{$required}\".";
            }
        }
        if ($fileErrors !== []) {
            return $this->emptyAnalysis($fileErrors, [], $warnings);
        }

        if (count($table['records']) > self::MAX_ROWS) {
            $fileErrors[] = 'Upload at most '.self::MAX_ROWS.' people at a time.';

            return $this->emptyAnalysis($fileErrors, [], $warnings);
        }
        if ($table['records'] === []) {
            $fileErrors[] = 'The file has no people to import.';

            return $this->emptyAnalysis($fileErrors, [], $warnings);
        }

        $rows = [];
        $seenIts = [];
        $bethakDates = [];
        foreach ($table['records'] as $record) {
            $cells = $record['cells'];
            $line = $record['line'];
            $value = function (string $header) use ($cells, $headerMap): string {
                $actual = $headerMap[mb_strtolower($header)] ?? $header;

                return trim((string) ($cells[$actual] ?? ''));
            };

            $groupId = $this->digits($value('Group_ID'));
            $token = $module === 'misaq' ? $groupId : $value('Token');
            $its = $this->digits($value('Mumin_ID'));
            $name = $value('FullName');
            $bethak = $value('Bethak_Date');
            $rowErrors = [];
            if ($token === '') {
                $rowErrors[] = $module === 'misaq'
                    ? "Row {$line}: Group_ID is empty."
                    : "Row {$line}: Token is empty.";
            }
            if ($bethak === '') {
                $rowErrors[] = "Row {$line}: Bethak_Date is empty.";
            } else {
                $bethakDates[$bethak] = true;
            }
            if ($its === '' || strlen($its) > 20) {
                $rowErrors[] = "Row {$line}: Mumin_ID must be digits, up to 20 characters.";
            } elseif (isset($seenIts[$its])) {
                $rowErrors[] = "Row {$line}: ITS {$its} is already on row {$seenIts[$its]}.";
            } else {
                $seenIts[$its] = $line;
            }
            if ($name === '') {
                $rowErrors[] = "Row {$line}: FullName is empty.";
            }

            foreach ($rowErrors as $error) {
                $fileErrors[] = $error;
            }
            if ($rowErrors !== []) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'token' => $token,
                'bethak' => $bethak,
                'group_id' => $groupId,
                'its' => $its,
                'name' => $name,
                'role' => $module === 'misaq'
                    ? ($its !== '' && $its === $groupId ? 'Group incharge' : 'Member')
                    : $value('Mumin_Type'),
                'mobile' => $value('Mobile_No'),
                'columns' => $cells,
            ];
        }

        if (count($bethakDates) > 1) {
            $fileErrors[] = 'This file has more than one Bethak_Date. Import one date at a time.';
        }
        if ($fileErrors !== []) {
            return $this->emptyAnalysis($fileErrors, [], $warnings);
        }

        $bethak = (string) array_key_first($bethakDates);
        $definitionErrors = [];
        $bethakCheck = $this->bethakCheck($bethak, $definition);
        if ($bethakCheck['error'] !== null) {
            $definitionErrors[] = $bethakCheck['error'];
        }
        if ($bethakCheck['warning'] !== null) {
            $warnings[] = $bethakCheck['warning'];
        }

        $roleCounts = [];
        foreach ($rows as $row) {
            $role = (string) $row['role'];
            if ($this->isUnmappedRole($role)) {
                continue;
            }
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
        }
        $positionPlan = $this->positionPlan($definition, $roleMap, $roleCounts, $module);
        $definitionErrors = array_merge($definitionErrors, $positionPlan['errors']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['token']][] = $row;
        }

        $tokens = array_keys($grouped);
        $foreignTokens = Sharaf::query()
            ->whereIn('token', $tokens)
            ->where('sharaf_definition_id', '!=', $definition->id)
            ->pluck('token')
            ->all();
        $foreignLookup = array_fill_keys($foreignTokens, true);

        $onDefinition = Sharaf::query()
            ->where('sharaf_definition_id', $definition->id)
            ->with(['sharafMembers.sharafPosition'])
            ->get();
        $existing = $onDefinition
            ->filter(fn (Sharaf $sharaf) => $sharaf->token !== null && (string) $sharaf->token !== '')
            ->keyBy(fn (Sharaf $sharaf) => (string) $sharaf->token);
        $existingByHead = $module === 'misaq'
            ? $onDefinition
                ->filter(fn (Sharaf $sharaf) => $sharaf->hof_its !== null && (string) $sharaf->hof_its !== '')
                ->keyBy(fn (Sharaf $sharaf) => (string) $sharaf->hof_its)
            : collect();

        if (Schema::hasTable('sharaf_clearances')) {
            $existing->load('sharafClearances');
        }

        $skipSet = $this->skipSet($skipIts);
        $censusMapping = $this->censusMapping($censusMap, $headerMap, $table['headers']);
        $census = $this->censusPlan($rows, (string) $positionPlan['hof_role'], $censusMapping['map'], $skipSet);
        $censusErrors = array_merge($censusMapping['errors'], $census['errors']);
        $parties = [];
        $internalParties = [];
        $partyErrors = [];

        foreach ($grouped as $token => $people) {
            $token = (string) $token;
            $active = [];
            foreach ($people as $person) {
                if (!isset($skipSet[(string) $person['its']])) {
                    $active[] = $person;
                }
            }
            $sharaf = $existing->get($token);
            if ($sharaf === null && $module === 'misaq') {
                $sharaf = $existingByHead->get($token);
                if ($sharaf !== null) {
                    $existingByHead->forget($token);
                }
            }
            if ($active === []) {
                $hof = $this->headPerson($people, (string) $positionPlan['hof_role']);
                $parties[] = [
                    'token' => $token,
                    'action' => 'skipped',
                    'sharaf_id' => $sharaf?->id,
                    'status' => $sharaf ? $this->statusValue($sharaf) : null,
                    'dulha_its' => (string) ($hof['its'] ?? ''),
                    'dulha_name' => (string) ($hof['name'] ?? ''),
                    'headcount' => count($people),
                    'needs_removal_confirm' => false,
                    'needs_hof_confirm' => false,
                    'hof_change_blocked' => false,
                    'hof_change_reason' => null,
                    'changes' => [],
                    'people' => $this->listedPeople($people, [], $skipSet, $positionPlan['positions']),
                    'members' => $this->publicMembers($sharaf),
                    'errors' => [],
                ];

                continue;
            }

            $built = $this->buildParty($token, $active, $positionPlan['positions'], $positionPlan['hof_role'], $positionPlan['explicit'], isset($foreignLookup[$token]));
            $skippedHof = false;
            $activeHof = 0;
            foreach ($people as $person) {
                if ((string) $person['role'] !== (string) $positionPlan['hof_role']) {
                    continue;
                }
                if (isset($skipSet[(string) $person['its']])) {
                    $skippedHof = true;
                } else {
                    $activeHof++;
                }
            }
            if ($skippedHof && $activeHof !== 1) {
                $built['errors'][] = "Token {$token}: skipped the head of family. Include that person, or skip the whole party.";
            }
            $partyErrors = array_merge($partyErrors, $built['errors']);
            $changes = [];
            $action = 'create';
            $sharafId = null;
            $status = null;
            $hofBlocked = false;
            $hofBlockReason = null;

            if ($built['errors'] === [] && $positionPlan['positions'] !== [] && $definitionErrors === []) {
                if ($sharaf === null) {
                    $hofTaken = Sharaf::query()
                        ->where('sharaf_definition_id', $definition->id)
                        ->where('hof_its', $built['party']['dulha_its'])
                        ->exists();
                    if ($hofTaken) {
                        $message = "Token {$token}: Dulha ITS {$built['party']['dulha_its']} is already the head of family on this sharaf.";
                        $built['errors'][] = $message;
                        $partyErrors[] = $message;
                    } else {
                        foreach ($built['party']['people'] as $person) {
                            $changes[] = $this->change('add', $person, null);
                        }
                    }
                } else {
                    $action = 'update';
                    $sharafId = $sharaf->id;
                    $status = $this->statusValue($sharaf);
                    $built['party']['headcount'] = count($built['party']['people']) + $this->stayingSkipped($sharaf, $skipSet);
                    $diff = $this->diffExisting($sharaf, $built['party'], $positionPlan['ids'], $skipSet);
                    $changes = $diff['changes'];
                    $hofBlocked = $diff['hof_blocked'];
                    $hofBlockReason = $diff['hof_block_reason'];
                    if ($changes === []) {
                        $action = 'unchanged';
                    }
                }
            } elseif ($sharaf !== null) {
                $action = 'update';
                $sharafId = $sharaf->id;
                $status = $this->statusValue($sharaf);
            }

            $needsRemoval = false;
            $needsHof = false;
            foreach ($changes as $change) {
                if (($change['requires'] ?? null) === 'removal') {
                    $needsRemoval = true;
                }
                if (($change['requires'] ?? null) === 'hof' && empty($change['blocked'])) {
                    $needsHof = true;
                }
            }

            $parties[] = [
                'token' => $token,
                'action' => $built['errors'] === [] ? $action : 'error',
                'sharaf_id' => $sharafId,
                'status' => $status,
                'dulha_its' => $built['party']['dulha_its'],
                'dulha_name' => $built['party']['dulha_name'],
                'headcount' => (int) $built['party']['headcount'],
                'needs_removal_confirm' => $needsRemoval,
                'needs_hof_confirm' => $needsHof,
                'hof_change_blocked' => $hofBlocked,
                'hof_change_reason' => $hofBlockReason,
                'changes' => $this->publicChanges($changes),
                'people' => $this->listedPeople($people, $built['party']['people'], $skipSet, $positionPlan['positions']),
                'members' => $this->publicMembers($sharaf),
                'errors' => $built['errors'],
            ];
            $internalParties[] = [
                'token' => $token,
                'sharaf' => $built['errors'] === [] ? $sharaf : null,
                'party' => $built['party'],
                'changes' => $changes,
                'errors' => $built['errors'],
            ];
        }

        $missing = [];
        foreach ($existing as $token => $sharaf) {
            $token = (string) $token;
            if (isset($grouped[$token])) {
                continue;
            }
            $missing[] = [
                'token' => (string) $token,
                'sharaf_id' => $sharaf->id,
                'hof_its' => (string) $sharaf->hof_its,
                'hof_name' => (string) ($sharaf->name ?? ''),
                'status' => $this->statusValue($sharaf),
            ];
        }

        $public = [
            'file_errors' => $fileErrors,
            'definition_errors' => array_values(array_unique($definitionErrors)),
            'party_errors' => $partyErrors,
            'warnings' => $warnings,
            'bethak_date' => $bethak,
            'party_count' => count($grouped),
            'person_count' => count($rows),
            'can_apply_census' => $fileErrors === [] && $censusErrors === [],
            'can_apply_sharafs' => $fileErrors === [] && $definitionErrors === [] && $partyErrors === [],
            'roles' => $positionPlan['roles'],
            'positions' => $positionPlan['position_list'],
            'census_columns' => $censusMapping['columns'],
            'census_errors' => $censusErrors,
            'census' => $census['public'],
            'parties' => $parties,
            'missing_from_sheet' => $missing,
        ];

        return [
            'public' => $public,
            'census' => $census['rows'],
            'identity' => $this->sheetIdentity($rows, $skipSet, (string) $positionPlan['hof_role'], $censusMapping['map']),
            'parties' => array_values(array_filter(
                $internalParties,
                fn (array $party) => $party['errors'] === []
            )),
        ];
    }

    /**
     * @param  list<string>  $fileErrors
     * @param  list<string>  $definitionErrors
     * @param  list<string>  $warnings
     * @return array{public: array<string, mixed>, census: list<array<string, mixed>>, parties: list<array<string, mixed>>}
     */
    private function emptyAnalysis(array $fileErrors, array $definitionErrors, array $warnings): array
    {
        return [
            'public' => [
                'file_errors' => $fileErrors,
                'definition_errors' => $definitionErrors,
                'party_errors' => [],
                'warnings' => $warnings,
                'bethak_date' => null,
                'party_count' => 0,
                'person_count' => 0,
                'can_apply_census' => false,
                'can_apply_sharafs' => false,
                'roles' => [],
                'positions' => [],
                'census_columns' => [],
                'census_errors' => [],
                'census' => ['creates' => [], 'updates' => [], 'unchanged' => 0, 'skipped' => 0, 'local' => 0],
                'parties' => [],
                'missing_from_sheet' => [],
            ],
            'census' => [],
            'parties' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, list<string>>  $fieldMap  Census field to sheet columns, first non-empty value wins
     * @param  array<string, true>  $skipSet
     * @return array{public: array<string, mixed>, rows: list<array<string, mixed>>, errors: list<string>}
     */
    private function censusPlan(array $rows, string $hofRole, array $fieldMap, array $skipSet): array
    {
        $useWords = $this->censusUsesGenderWords();
        $byIts = [];
        foreach ($rows as $row) {
            $byIts[$row['its']] = $row;
        }

        $existing = Census::query()->whereIn('its_id', array_keys($byIts))->get()->keyBy('its_id');
        $localSet = $this->localCensusIts(array_keys($byIts));
        $creates = [];
        $updates = [];
        $internal = [];
        $errors = [];
        $unchanged = 0;
        $skipped = 0;
        $local = 0;

        if (isset($fieldMap['age'])) {
            foreach ($rows as $row) {
                $its = (string) $row['its'];
                if (isset($skipSet[$its]) || isset($localSet[$its])) {
                    continue;
                }
                [$raw, $column] = $this->firstColumnValue($row['columns'] ?? [], $fieldMap['age']);
                if ($raw !== '' && (!preg_match('/^\d+$/', $raw) || (int) $raw > 120)) {
                    $errors[] = "Row {$row['line']}: {$column} must be a whole number from 0 to 120.";
                }
            }
        }

        foreach ($byIts as $its => $row) {
            $its = (string) $its;
            if (isset($skipSet[$its])) {
                $skipped++;
                continue;
            }
            if (isset($localSet[$its])) {
                $local++;
                continue;
            }
            $target = $this->censusAttributes($row, $useWords, $hofRole, $fieldMap);
            $record = $existing->get($its);
            if ($record === null) {
                $name = $target['name'] ?? null;
                if ($name === null || $name === '') {
                    $errors[] = "Map a column to Name before adding ITS {$its}.";
                    continue;
                }
                $attributes = array_merge(['its_id' => $its, 'hof_id' => $its], $target);
                $creates[] = [
                    'its' => $its,
                    'name' => (string) $name,
                    'fields' => $target,
                ];
                $internal[] = ['action' => 'create', 'attributes' => $attributes, 'model' => null];
                continue;
            }

            $changes = [];
            $attributes = [];
            foreach ($target as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $current = $record->getAttribute($field);
                if ($field === 'age') {
                    if ($current !== null && (int) $current === (int) $value) {
                        continue;
                    }
                    if ($current === null && $value === null) {
                        continue;
                    }
                } elseif (trim((string) $current) === trim((string) $value)) {
                    continue;
                }
                $changes[] = [
                    'field' => $field,
                    'from' => $current === null ? '' : (string) $current,
                    'to' => (string) $value,
                ];
                $attributes[$field] = $value;
            }

            if ($changes === []) {
                $unchanged++;
                continue;
            }

            $label = isset($target['name']) && $target['name'] !== null && $target['name'] !== ''
                ? (string) $target['name']
                : (string) $row['name'];
            $updates[] = [
                'its' => $its,
                'name' => $label,
                'changes' => $changes,
            ];
            $internal[] = ['action' => 'update', 'attributes' => $attributes, 'model' => $record];
        }

        return [
            'public' => [
                'creates' => $creates,
                'updates' => $updates,
                'unchanged' => $unchanged,
                'skipped' => $skipped,
                'local' => $local,
            ],
            'rows' => $internal,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, list<string>>  $fieldMap
     * @return array<string, mixed>
     */
    private function censusAttributes(array $row, bool $useWords, string $hofRole, array $fieldMap): array
    {
        $columns = is_array($row['columns'] ?? null) ? $row['columns'] : [];
        $isHof = ($hofRole !== '' && $row['role'] === $hofRole)
            || ($row['group_id'] !== '' && $row['group_id'] === $row['its']);
        $attributes = [];

        foreach ($fieldMap as $field => $mappedColumns) {
            [$raw] = $this->firstColumnValue($columns, $mappedColumns);
            if ($field === 'name') {
                $attributes['name'] = $raw !== '' ? $raw : null;
            } elseif ($field === 'age') {
                $attributes['age'] = $raw !== '' && preg_match('/^\d+$/', $raw) && (int) $raw <= 120 ? (int) $raw : null;
            } elseif ($field === 'gender') {
                $attributes['gender'] = $this->mapGender($raw, $useWords);
            } elseif ($field === 'arabic_name' || $field === 'jamaat' || $field === 'jamiat') {
                $attributes[$field] = $raw !== '' ? $raw : null;
            } elseif ($field === 'mobile' || $field === 'email') {
                $attributes[$field] = $isHof && $raw !== '' ? $raw : null;
            }
        }

        return $attributes;
    }

    /**
     * People in anjuman.census, or marked source=census, stay as they are.
     *
     * @param  list<int|string>  $itsIds
     * @return array<string, true>
     */
    private function localCensusIts(array $itsIds): array
    {
        $ids = [];
        foreach ($itsIds as $its) {
            $its = (string) $its;
            if ($its !== '') {
                $ids[$its] = $its;
            }
        }
        if ($ids === []) {
            return [];
        }

        $local = [];
        if (Schema::hasColumn('census', 'source')) {
            $found = Census::query()->whereIn('its_id', array_values($ids))->where('source', 'census')->pluck('its_id');
            foreach ($found as $its) {
                $local[(string) $its] = true;
            }
        }
        foreach ($this->anjumanCensusIts(array_values($ids)) as $its) {
            $local[$its] = true;
        }

        return $local;
    }

    /**
     * @param  list<string>  $itsIds
     * @return list<string>
     */
    private function anjumanCensusIts(array $itsIds): array
    {
        if ($itsIds === [] || Schema::getConnection()->getDriverName() === 'sqlite') {
            return [];
        }

        try {
            $columns = DB::select(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                ['anjuman', 'census']
            );
        } catch (\Throwable) {
            return [];
        }

        $itsColumn = null;
        foreach ($columns as $column) {
            if (in_array(strtolower((string) $column->COLUMN_NAME), ['its_id', 'its'], true)) {
                $itsColumn = (string) $column->COLUMN_NAME;
                break;
            }
        }
        if ($itsColumn === null) {
            return [];
        }

        $found = DB::table('anjuman.census')->whereIn($itsColumn, $itsIds)->pluck($itsColumn);
        $local = [];
        foreach ($found as $its) {
            $local[] = (string) $its;
        }

        return $local;
    }

    /**
     * @return list<string>
     */
    private function censusTablesToWrite(): array
    {
        $tables = [];
        if (Schema::hasTable('foreign_census')) {
            $tables[] = 'foreign_census';
        }
        if (Schema::getConnection()->getDriverName() === 'sqlite' || !$this->censusIsView()) {
            $tables[] = 'census';
        }

        return $tables === [] ? ['census'] : $tables;
    }

    private function censusIsView(): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        try {
            $row = DB::selectOne(
                'SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['census']
            );
        } catch (\Throwable) {
            return false;
        }

        return $row !== null && strtoupper((string) $row->TABLE_TYPE) === 'VIEW';
    }

    /**
     * @param  array{action: string, attributes: array<string, mixed>, model: ?Census}  $person
     */
    private function writeCensusPerson(array $person): void
    {
        $attributes = $person['attributes'];
        $its = (string) ($attributes['its_id'] ?? $person['model']?->its_id ?? '');
        foreach ($this->censusTablesToWrite() as $table) {
            $model = $table === 'foreign_census'
                ? ForeignCensus::query()->firstOrNew(['its_id' => $its])
                : ($person['model'] instanceof Census ? $person['model'] : Census::query()->firstOrNew(['its_id' => $its]));
            if (!$model->exists) {
                $model->its_id = $its;
                $model->hof_id = $attributes['hof_id'] ?? $its;
                if ($table === 'census' && Schema::hasColumn('census', 'source')) {
                    $model->source = 'foreign_census';
                }
            }
            if ($table === 'census' && !Schema::hasColumn('census', 'id')) {
                $model->primaryKey = 'its_id';
                $model->incrementing = false;
            }
            if (!Schema::hasColumn($table, 'updated_at')) {
                $model->timestamps = false;
            }
            $model->fill($attributes);
            $model->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, true>  $skipSet
     * @param  array<string, list<string>>  $fieldMap
     * @return list<array{its: string, name: string, phone: string, is_hof: bool}>
     */
    private function sheetIdentity(array $rows, array $skipSet, string $hofRole, array $fieldMap): array
    {
        $people = [];
        foreach ($rows as $row) {
            $its = (string) $row['its'];
            if (isset($skipSet[$its])) {
                continue;
            }
            $isHof = ($hofRole !== '' && $row['role'] === $hofRole)
                || ($row['group_id'] !== '' && $row['group_id'] === $row['its']);
            $name = (string) $row['name'];
            if (isset($fieldMap['name'])) {
                [$mapped] = $this->firstColumnValue($row['columns'] ?? [], $fieldMap['name']);
                if ($mapped !== '') {
                    $name = $mapped;
                }
            }
            $people[] = [
                'its' => $its,
                'name' => $name,
                'phone' => $isHof ? (string) $row['mobile'] : '',
                'is_hof' => $isHof,
            ];
        }

        return $people;
    }

    /**
     * Copy sheet names and the head's phone onto this sharaf definition.
     *
     * @param  list<array{its: string, name: string, phone: string, is_hof: bool}>  $people
     * @return array{sharaf_names: int, member_names: int, member_phones: int}
     */
    private function syncSharafIdentity(SharafDefinition $definition, array $people): array
    {
        $counts = ['sharaf_names' => 0, 'member_names' => 0, 'member_phones' => 0];
        if ($people === []) {
            return $counts;
        }

        $byIts = [];
        foreach ($people as $person) {
            $byIts[$person['its']] = $person;
        }

        $members = SharafMember::query()
            ->whereIn('its_id', array_keys($byIts))
            ->whereHas('sharaf', fn ($query) => $query->where('sharaf_definition_id', $definition->id))
            ->get();
        foreach ($members as $member) {
            $person = $byIts[(string) $member->its_id] ?? null;
            if ($person === null) {
                continue;
            }
            $dirty = false;
            if ($person['name'] !== '' && trim((string) $member->name) !== trim($person['name'])) {
                $member->name = $person['name'];
                $counts['member_names']++;
                $dirty = true;
            }
            if ($person['is_hof'] && $person['phone'] !== '' && trim((string) $member->phone) !== trim($person['phone'])) {
                $member->phone = $person['phone'];
                $counts['member_phones']++;
                $dirty = true;
            }
            if ($dirty) {
                $member->save();
            }
        }

        $hofNames = [];
        foreach ($people as $person) {
            if ($person['is_hof'] && $person['name'] !== '') {
                $hofNames[$person['its']] = $person['name'];
            }
        }
        if ($hofNames === []) {
            return $counts;
        }

        $sharafs = Sharaf::query()
            ->where('sharaf_definition_id', $definition->id)
            ->whereIn('hof_its', array_keys($hofNames))
            ->get();
        foreach ($sharafs as $sharaf) {
            $name = $hofNames[(string) $sharaf->hof_its] ?? '';
            if ($name === '' || trim((string) $sharaf->name) === trim($name)) {
                continue;
            }
            $sharaf->name = $name;
            $sharaf->save();
            $counts['sharaf_names']++;
        }

        return $counts;
    }

    /**
     * @param  array<string, string>|null  $requested  Sheet column to census field. Null uses the defaults.
     * @param  array<string, string>  $headerMap
     * @param  list<string>  $headers
     * @return array{map: array<string, list<string>>, columns: list<array{name: string, field: string}>, errors: list<string>}
     */
    private function censusMapping(?array $requested, array $headerMap, array $headers): array
    {
        $errors = [];
        $fieldMap = [];

        if ($requested === null) {
            foreach (self::DEFAULT_CENSUS_COLUMNS as $field => $column) {
                $actual = $headerMap[mb_strtolower($column)] ?? null;
                if ($actual !== null) {
                    $fieldMap[$field] = [$actual];
                }
            }
        } else {
            foreach ($requested as $column => $field) {
                $field = trim((string) $field);
                $column = trim((string) $column);
                if ($field === '' || $column === '') {
                    continue;
                }
                if (!in_array($field, self::CENSUS_FIELDS, true)) {
                    $errors[] = "Census field \"{$field}\" is not one of name, arabic name, age, gender, jamaat, jamiat, mobile, email.";
                    continue;
                }
                $actual = $headerMap[mb_strtolower($column)] ?? null;
                if ($actual === null) {
                    $errors[] = "Column \"{$column}\" is not in this sheet.";
                    continue;
                }
                $fieldMap[$field][] = $actual;
            }
        }

        $columnToField = [];
        foreach ($fieldMap as $field => $mappedColumns) {
            foreach ($mappedColumns as $column) {
                $columnToField[$column] = $field;
            }
        }
        $columns = [];
        foreach ($headers as $header) {
            $structural = false;
            foreach (self::STRUCTURAL_HEADERS as $required) {
                if (mb_strtolower($header) === mb_strtolower($required)) {
                    $structural = true;
                    break;
                }
            }
            if ($structural) {
                continue;
            }
            $columns[] = [
                'name' => $header,
                'field' => $columnToField[$header] ?? '',
            ];
        }

        return [
            'map' => $fieldMap,
            'columns' => $columns,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $columns
     * @param  list<string>  $mappedColumns
     * @return array{0: string, 1: string}
     */
    private function firstColumnValue(array $columns, array $mappedColumns): array
    {
        $fallback = $mappedColumns[0] ?? '';
        foreach ($mappedColumns as $column) {
            $raw = $this->columnValue($columns, $column);
            if ($raw !== '') {
                return [$raw, $column];
            }
        }

        return ['', $fallback];
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function columnValue(array $columns, string $column): string
    {
        foreach ($columns as $header => $value) {
            if (mb_strtolower((string) $header) === mb_strtolower($column)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $skipIts
     * @return array<string, true>
     */
    private function skipSet(array $skipIts): array
    {
        $set = [];
        foreach ($skipIts as $its) {
            $normalized = $this->digits((string) $its);
            if ($normalized === '') {
                $normalized = trim((string) $its);
            }
            if ($normalized !== '') {
                $set[$normalized] = true;
            }
        }

        return $set;
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return array<string, mixed>|null
     */
    private function headPerson(array $people, string $hofRole): ?array
    {
        foreach ($people as $person) {
            if ($hofRole !== '' && (string) $person['role'] === $hofRole) {
                return $person;
            }
        }

        return $people[0] ?? null;
    }

    /**
     * @param  array<string, true>  $skipSet
     */
    private function stayingSkipped(Sharaf $sharaf, array $skipSet): int
    {
        $count = 0;
        foreach ($sharaf->sharafMembers as $member) {
            if (isset($skipSet[(string) $member->its_id])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $original
     * @param  list<array<string, mixed>>  $builtPeople
     * @param  array<string, true>  $skipSet
     * @param  array<string, SharafPosition>  $positions
     * @return list<array{its: string, name: string, role: string, position: string, skipped: bool}>
     */
    private function listedPeople(array $original, array $builtPeople, array $skipSet, array $positions): array
    {
        $byIts = [];
        foreach ($builtPeople as $person) {
            $byIts[(string) $person['its']] = $person;
        }

        $rows = [];
        foreach ($original as $person) {
            $its = (string) $person['its'];
            $match = $byIts[$its] ?? null;
            $positionName = '';
            if ($match !== null) {
                $positionName = (string) ($match['position_name'] ?? '');
            } elseif (isset($positions[(string) $person['role']])) {
                $position = $positions[(string) $person['role']];
                $positionName = trim((string) ($position->display_name ?: $position->name));
            }
            $rows[] = [
                'its' => $its,
                'name' => (string) $person['name'],
                'role' => (string) $person['role'],
                'position' => $positionName,
                'skipped' => isset($skipSet[$its]),
            ];
        }

        return $rows;
    }

    private function censusUsesGenderWords(): bool
    {
        $sample = Census::query()
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->limit(200)
            ->pluck('gender');
        $words = 0;
        $letters = 0;
        foreach ($sample as $gender) {
            $value = strtolower(trim((string) $gender));
            if ($value === 'male' || $value === 'female') {
                $words++;
            } elseif ($value === 'm' || $value === 'f') {
                $letters++;
            }
        }

        return $words > $letters;
    }

    private function mapGender(string $gender, bool $useWords): ?string
    {
        $value = strtoupper(trim($gender));
        if ($value === '') {
            return null;
        }
        if ($value === 'M' || $value === 'MALE') {
            return $useWords ? 'Male' : ($value === 'MALE' ? 'Male' : 'M');
        }
        if ($value === 'F' || $value === 'FEMALE') {
            return $useWords ? 'Female' : ($value === 'FEMALE' ? 'Female' : 'F');
        }

        return trim($gender);
    }

    /**
     * @param  array<string, int>  $roleMap
     * @param  array<string, int>  $sheetRoles
     * @return array{
     *     errors: list<string>,
     *     positions: array<string, SharafPosition>,
     *     ids: array<string, int>,
     *     hof_role: string,
     *     explicit: bool,
     *     roles: list<array{name: string, count: int, position_id: int|null}>,
     *     position_list: list<array{id: int, name: string, display_name: string, order: int, capacity: int|null}>
     * }
     */
    private function positionPlan(SharafDefinition $definition, array $roleMap, array $sheetRoles, string $module = 'nikah'): array
    {
        $errors = [];
        $positions = [];
        $ids = [];
        $explicit = $roleMap !== [];
        $catalog = $module === 'misaq' ? self::MISAQ_ROLES : self::ROLES;
        $defaultHof = $module === 'misaq' ? 'Group incharge' : 'Dulha';
        $hofRole = $defaultHof;
        $roleNames = array_keys($sheetRoles);

        if ($explicit) {
            $byId = $definition->sharafPositions->keyBy(fn (SharafPosition $position) => (int) $position->id);
            foreach ($roleNames as $role) {
                if (!isset($roleMap[$role]) || (int) $roleMap[$role] < 1) {
                    $errors[] = "Map \"{$role}\" to a sharaf position.";
                    continue;
                }
                $match = $byId->get((int) $roleMap[$role]);
                if ($match === null) {
                    $errors[] = "\"{$role}\" is mapped to a position that is not on this sharaf.";
                    continue;
                }
                $positions[$role] = $match;
                $ids[$role] = $match->id;
            }
            $hofRoles = [];
            foreach ($positions as $role => $position) {
                if ((int) $position->order === 1) {
                    $hofRoles[] = $role;
                }
            }
            if (count($hofRoles) !== 1) {
                $errors[] = 'Map one sheet role to the position with order 1. That person is the head of family.';
                $hofRole = $hofRoles[0] ?? '';
            } else {
                $hofRole = $hofRoles[0];
            }
        } else {
            foreach ($catalog as $role => $seats) {
                $match = $definition->sharafPositions->first(function (SharafPosition $position) use ($role) {
                    $display = trim((string) ($position->display_name ?: $position->name));

                    return strcasecmp($display, $role) === 0 || strcasecmp(trim((string) $position->name), $role) === 0;
                });
                if ($match === null && $module === 'misaq') {
                    $match = $this->misaqPositionByOrder($definition, $role);
                }
                if ($match === null) {
                    $errors[] = "Add a position named \"{$role}\" to this sharaf definition, or map \"{$role}\" to a position.";
                    continue;
                }
                if ($role === $defaultHof && (int) $match->order !== 1) {
                    $errors[] = "The {$defaultHof} position must be order 1 so that person is the head of family.";
                }
                $capacity = $match->capacity === null || (int) $match->capacity < 1 ? 1 : (int) $match->capacity;
                if ($seats > 0 && $capacity < $seats && $role !== 'Extra Member') {
                    $errors[] = "Position \"{$role}\" must hold {$seats}.";
                }
                $positions[$role] = $match;
                $ids[$role] = $match->id;
            }
        }

        $roleRows = [];
        foreach ($sheetRoles as $role => $count) {
            $roleRows[] = [
                'name' => (string) $role,
                'count' => (int) $count,
                'position_id' => $ids[$role] ?? null,
            ];
        }

        return [
            'errors' => $errors,
            'positions' => $positions,
            'ids' => $ids,
            'hof_role' => $hofRole,
            'explicit' => $explicit,
            'roles' => $roleRows,
            'position_list' => $this->positionList($definition),
        ];
    }

    /**
     * @return list<array{id: int, name: string, display_name: string, order: int, capacity: int|null}>
     */
    private function positionList(SharafDefinition $definition): array
    {
        return $definition->sharafPositions
            ->sortBy(fn (SharafPosition $position) => sprintf('%010d-%010d', (int) $position->order, (int) $position->id))
            ->values()
            ->map(fn (SharafPosition $position) => [
                'id' => $position->id,
                'name' => (string) $position->name,
                'display_name' => (string) ($position->display_name ?: $position->name),
                'order' => (int) $position->order,
                'capacity' => $position->capacity === null ? null : (int) $position->capacity,
            ])
            ->all();
    }

    /**
     * A date that differs from the event is a warning. An unreadable date or a missing event date still blocks sharaf apply.
     *
     * @return array{error: ?string, warning: ?string}
     */
    private function bethakCheck(string $bethak, SharafDefinition $definition): array
    {
        if (!preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s*-\s*(.+)$/', trim($bethak), $match)) {
            return ['error' => 'Bethak_Date must look like "16 Oct 2026 - Morning".', 'warning' => null];
        }

        try {
            $parsed = Carbon::parse($match[1].' '.$match[2].' '.$match[3])->toDateString();
        } catch (\Throwable) {
            return ['error' => "Bethak_Date \"{$bethak}\" is not a date this import understands.", 'warning' => null];
        }

        $event = $definition->event;
        if ($event === null || $event->date === null) {
            return ['error' => 'The selected event has no date.', 'warning' => null];
        }

        $eventDate = $event->date->toDateString();
        if ($parsed !== $eventDate) {
            return [
                'error' => null,
                'warning' => "Bethak date {$parsed} does not match the event date {$eventDate}.",
            ];
        }

        return ['error' => null, 'warning' => null];
    }

    private function isUnmappedRole(string $role): bool
    {
        foreach (self::UNMAPPED_ROLES as $ignored) {
            if (strcasecmp(trim($role), $ignored) === 0) {
                return true;
            }
        }

        return false;
    }

    private function misaqPositionByOrder(SharafDefinition $definition, string $role): ?SharafPosition
    {
        $positions = $definition->sharafPositions;
        if ($role === 'Group incharge') {
            $heads = $positions->filter(fn (SharafPosition $position) => (int) $position->order === 1)->values();

            return $heads->count() === 1 ? $heads->first() : null;
        }

        $others = $positions
            ->filter(fn (SharafPosition $position) => (int) $position->order !== 1)
            ->sortBy(fn (SharafPosition $position) => (int) $position->order)
            ->values();
        if ($others->count() === 1) {
            return $others->first();
        }
        $second = $others->filter(fn (SharafPosition $position) => (int) $position->order === 2)->values();

        return $second->count() === 1 ? $second->first() : null;
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @param  array<string, SharafPosition>  $positions
     * @return array{errors: list<string>, party: array<string, mixed>}
     */
    private function buildParty(string $token, array $people, array $positions, string $hofRole, bool $explicit, bool $foreignToken): array
    {
        $errors = [];
        if ($foreignToken) {
            $errors[] = "Token {$token} is already used by a sharaf on another definition.";
        }

        $counts = [];
        $builtPeople = [];
        $dulhaIts = null;
        $dulhaName = null;
        $groupId = $people[0]['group_id'] ?? '';
        foreach ($people as $person) {
            $role = (string) $person['role'];
            if ($this->isUnmappedRole($role)) {
                continue;
            }
            $position = $positions[$role] ?? null;
            if ($position === null && !$explicit && !isset(self::ROLES[$role]) && !isset(self::MISAQ_ROLES[$role])) {
                $errors[] = "Row {$person['line']}: unknown role \"{$role}\".";
            }
            $counts[$role] = ($counts[$role] ?? 0) + 1;
            $isHof = $hofRole !== '' && $role === $hofRole;
            if ($isHof) {
                $dulhaIts = $person['its'];
                $dulhaName = $person['name'];
            }
            $label = $position === null ? '' : trim((string) ($position->display_name ?: $position->name));
            $builtPeople[] = [
                'its' => $person['its'],
                'name' => $person['name'],
                'role' => $role,
                'phone' => $isHof ? (string) $person['mobile'] : '',
                'position_id' => $position?->id,
                'position_name' => $label,
                'is_hof' => $isHof,
                'line' => $person['line'],
            ];
        }

        if ($hofRole !== '' && ($counts[$hofRole] ?? 0) !== 1) {
            $errors[] = "Token {$token} must have exactly one {$hofRole}.";
        }
        $assigned = [];
        foreach ($builtPeople as $person) {
            if ($person['position_id'] === null) {
                continue;
            }
            $assigned[(int) $person['position_id']] = ($assigned[(int) $person['position_id']] ?? 0) + 1;
        }
        $reported = [];
        foreach ($positions as $position) {
            if (isset($reported[(int) $position->id])) {
                continue;
            }
            $reported[(int) $position->id] = true;
            $count = $assigned[(int) $position->id] ?? 0;
            if ($count === 0) {
                continue;
            }
            $capacity = $position->capacity === null || (int) $position->capacity < 1 ? 1 : (int) $position->capacity;
            if ($count > $capacity) {
                $label = trim((string) ($position->display_name ?: $position->name));
                $errors[] = "Token {$token} puts {$count} people in {$label}, which holds {$capacity}.";
            }
        }
        if ($groupId === '') {
            $errors[] = "Token {$token}: Group_ID is empty.";
        } elseif ($dulhaIts !== null && $groupId !== $dulhaIts) {
            $errors[] = "Token {$token}: Group_ID {$groupId} does not match the {$hofRole} ITS {$dulhaIts}.";
        }

        return [
            'errors' => $errors,
            'party' => [
                'token' => $token,
                'dulha_its' => (string) $dulhaIts,
                'dulha_name' => (string) $dulhaName,
                'headcount' => count($builtPeople),
                'people' => $builtPeople,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $party
     * @param  array<string, int>  $positionIds
     * @param  array<string, true>  $skipSet
     * @return array{changes: list<array<string, mixed>>, hof_blocked: bool, hof_block_reason: ?string}
     */
    private function diffExisting(Sharaf $sharaf, array $party, array $positionIds, array $skipSet = []): array
    {
        $changes = [];
        $members = $sharaf->sharafMembers->keyBy(fn (SharafMember $member) => (string) $member->its_id);
        $sheetIts = [];
        foreach ($party['people'] as $person) {
            $sheetIts[$person['its']] = $person;
        }

        $oldHof = (string) $sharaf->hof_its;
        $newHof = (string) $party['dulha_its'];
        $hofChanging = $oldHof !== $newHof;
        $block = $this->hofBlock($sharaf);
        $hofBlocked = $hofChanging && $block !== null;

        foreach ($party['people'] as $person) {
            $existing = $members->get($person['its']);
            $requiresHof = $hofChanging && ($person['its'] === $oldHof || $person['its'] === $newHof || !empty($person['is_hof']));
            if ($existing === null) {
                $change = $this->change('add', $person, $requiresHof ? 'hof' : null);
                if ($requiresHof && $hofBlocked) {
                    $change['blocked'] = true;
                }
                $changes[] = $change;
                continue;
            }

            $sameSeat = (int) $existing->sharaf_position_id === (int) $person['position_id'];
            if (!$sameSeat) {
                $change = $this->change('move', $person, $requiresHof ? 'hof' : null);
                $change['from_role'] = $this->roleForPosition((int) $existing->sharaf_position_id, $positionIds) ?? 'Member';
                if ($requiresHof && $hofBlocked) {
                    $change['blocked'] = true;
                }
                $changes[] = $change;
            }

            if ($person['name'] !== '' && trim((string) $existing->name) !== trim((string) $person['name'])) {
                $changes[] = [
                    'kind' => 'update',
                    'its' => $person['its'],
                    'name' => $person['name'],
                    'role' => $person['role'],
                    'field' => 'name',
                    'from' => (string) $existing->name,
                    'to' => $person['name'],
                    'requires' => null,
                ];
            }
            if (!empty($person['is_hof']) && $person['phone'] !== '' && trim((string) $existing->phone) !== trim((string) $person['phone'])) {
                $changes[] = [
                    'kind' => 'update',
                    'its' => $person['its'],
                    'name' => $person['name'],
                    'role' => $person['role'],
                    'field' => 'phone',
                    'from' => (string) $existing->phone,
                    'to' => $person['phone'],
                    'requires' => $hofChanging ? 'hof' : null,
                    'blocked' => $hofChanging && $hofBlocked,
                ];
            }
        }

        foreach ($members as $its => $existing) {
            $its = (string) $its;
            if (isset($sheetIts[$its]) || isset($skipSet[$its])) {
                continue;
            }
            $isHof = (string) $its === $oldHof;
            $changes[] = [
                'kind' => 'remove',
                'its' => (string) $its,
                'name' => (string) $existing->name,
                'role' => $this->roleForPosition((int) $existing->sharaf_position_id, $positionIds) ?? 'Member',
                'requires' => $isHof ? 'hof' : 'removal',
                'blocked' => $isHof && $hofBlocked,
            ];
        }

        if ((int) $sharaf->capacity !== (int) $party['headcount']) {
            $changes[] = [
                'kind' => 'capacity',
                'from' => (int) $sharaf->capacity,
                'to' => (int) $party['headcount'],
                'requires' => null,
            ];
        }
        if (!$hofChanging && trim((string) $sharaf->name) !== trim((string) $party['dulha_name'])) {
            $changes[] = [
                'kind' => 'rename',
                'from' => (string) $sharaf->name,
                'to' => (string) $party['dulha_name'],
                'requires' => null,
            ];
        }
        if ($hofChanging) {
            $changes[] = [
                'kind' => 'hof',
                'from' => $oldHof,
                'to' => $newHof,
                'to_name' => (string) $party['dulha_name'],
                'requires' => 'hof',
                'blocked' => $hofBlocked,
                'blocked_reason' => $block,
            ];
        }

        return [
            'changes' => $changes,
            'hof_blocked' => $hofBlocked,
            'hof_block_reason' => $hofBlocked ? $block : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function change(string $kind, array $person, ?string $requires): array
    {
        return [
            'kind' => $kind,
            'its' => $person['its'],
            'name' => $person['name'],
            'role' => $person['role'],
            'phone' => $person['phone'] ?? '',
            'position_id' => $person['position_id'],
            'requires' => $requires,
            'blocked' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @return list<array<string, mixed>>
     */
    private function publicChanges(array $changes): array
    {
        $public = [];
        foreach ($changes as $change) {
            unset($change['position_id'], $change['phone']);
            $public[] = $change;
        }

        return $public;
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return list<array{its: string, name: string, role: string, position: string}>
     */
    private function publicPeople(array $people): array
    {
        $rows = [];
        foreach ($people as $person) {
            $rows[] = [
                'its' => (string) $person['its'],
                'name' => (string) $person['name'],
                'role' => (string) $person['role'],
                'position' => (string) ($person['position_name'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{its: string, name: string, position: string}>
     */
    private function publicMembers(?Sharaf $sharaf): array
    {
        if ($sharaf === null) {
            return [];
        }

        $rows = [];
        foreach ($sharaf->sharafMembers as $member) {
            $position = $member->relationLoaded('sharafPosition') ? $member->sharafPosition : null;
            $label = $position === null ? '' : trim((string) ($position->display_name ?: $position->name));
            $rows[] = [
                'its' => (string) $member->its_id,
                'name' => (string) ($member->name ?? ''),
                'position' => $label,
            ];
        }

        return $rows;
    }

    private function hofBlock(Sharaf $sharaf): ?string
    {
        $reasons = [];
        if ($this->statusValue($sharaf) === SharafStatus::CONFIRMED->value) {
            $reasons[] = 'the sharaf is confirmed';
        }
        if (Schema::hasTable('sharaf_clearances') && $sharaf->relationLoaded('sharafClearances') && $sharaf->sharafClearances->isNotEmpty()) {
            $reasons[] = 'the sharaf already has clearance';
        }
        if ($reasons === []) {
            return null;
        }

        return ucfirst(implode(' and ', $reasons)).'.';
    }

    /**
     * @param  array<string, int>  $positionIds
     */
    private function roleForPosition(int $positionId, array $positionIds): ?string
    {
        foreach ($positionIds as $role => $id) {
            if ((int) $id === $positionId) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $party
     * @param  array<string, mixed>  $result
     */
    private function createParty(SharafDefinition $definition, array $party, int $rank, array &$result): void
    {
        $data = $party['party'];
        $sharaf = Sharaf::query()->create([
            'sharaf_definition_id' => $definition->id,
            'rank' => $rank,
            'name' => $data['dulha_name'],
            'capacity' => $data['headcount'],
            'status' => SharafStatus::PENDING->value,
            'hof_its' => $data['dulha_its'],
            'token' => $data['token'],
        ]);

        $slot = [];
        foreach ($data['people'] as $person) {
            $role = $person['role'];
            $slot[$role] = ($slot[$role] ?? 0) + 1;
            $warnings = $this->allocationService->addMember(
                $sharaf->id,
                (int) $person['position_id'],
                $person['its'],
                $slot[$role],
                $person['name'],
                $person['phone'] !== '' ? $person['phone'] : null,
            );
            $this->collectWarnings($result, $data['token'], $warnings);
        }

        $result['created']++;
    }

    /**
     * @param  array<string, mixed>  $party
     * @param  array<string, true>  $confirmRemovals
     * @param  array<string, true>  $confirmHofChanges
     * @param  array<string, mixed>  $result
     */
    private function updateParty(array $party, array $confirmRemovals, array $confirmHofChanges, array &$result): void
    {
        /** @var Sharaf $sharaf */
        $sharaf = $party['sharaf'];
        $token = (string) $party['token'];
        $applied = false;
        if ((string) $sharaf->token !== $token) {
            $sharaf->token = $token;
            $sharaf->save();
            $applied = true;
        }
        $skippedRemoval = false;
        $skippedHof = false;
        $sawBlocked = false;

        foreach ($party['changes'] as $change) {
            $requires = $change['requires'] ?? null;
            if (!empty($change['blocked'])) {
                $sawBlocked = true;
                if ($requires === 'hof' && ($change['kind'] ?? '') === 'hof') {
                    $result['blocked_hof_changes'][] = [
                        'token' => $token,
                        'reason' => $change['blocked_reason'] ?? 'The Dulha change is blocked.',
                    ];
                }
                continue;
            }
            if ($requires === 'removal' && !isset($confirmRemovals[$token])) {
                $skippedRemoval = true;
                continue;
            }
            if ($requires === 'hof' && !isset($confirmHofChanges[$token])) {
                $skippedHof = true;
                continue;
            }
            if ($change['kind'] === 'capacity' && ($skippedRemoval || $skippedHof || $sawBlocked)) {
                continue;
            }

            $applied = true;
            if ($change['kind'] === 'add') {
                $warnings = $this->allocationService->addMember(
                    $sharaf->id,
                    (int) $change['position_id'],
                    $change['its'],
                    null,
                    $change['name'],
                    ($change['phone'] ?? '') !== '' ? $change['phone'] : null,
                );
                $this->collectWarnings($result, $token, $warnings);
            } elseif ($change['kind'] === 'remove') {
                $this->allocationService->removeMember($sharaf->id, $change['its']);
                $result['removals']++;
            } elseif ($change['kind'] === 'move') {
                $member = SharafMember::query()
                    ->where('sharaf_id', $sharaf->id)
                    ->where('its_id', $change['its'])
                    ->first();
                if ($member !== null) {
                    $member->sharaf_position_id = $change['position_id'];
                    $member->name = $change['name'];
                    if (($change['phone'] ?? '') !== '') {
                        $member->phone = $change['phone'];
                    }
                    $member->save();
                }
            } elseif ($change['kind'] === 'update') {
                $member = SharafMember::query()
                    ->where('sharaf_id', $sharaf->id)
                    ->where('its_id', $change['its'])
                    ->first();
                if ($member !== null) {
                    $member->setAttribute($change['field'], $change['to']);
                    $member->save();
                }
            } elseif ($change['kind'] === 'capacity') {
                $sharaf->capacity = $change['to'];
                $sharaf->save();
            } elseif ($change['kind'] === 'rename') {
                $sharaf->name = $change['to'];
                $sharaf->save();
            } elseif ($change['kind'] === 'hof') {
                $sharaf->hof_its = $change['to'];
                $sharaf->name = $change['to_name'];
                $sharaf->save();
                $result['hof_changes']++;
            }
        }

        if ($skippedRemoval) {
            $result['skipped_removals'][] = $token;
        }
        if ($skippedHof) {
            $result['skipped_hof_changes'][] = $token;
        }
        if ($applied) {
            $result['updated']++;
        } else {
            $result['unchanged']++;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $warnings
     */
    private function collectWarnings(array &$result, string $token, array $warnings): void
    {
        if (($warnings['warnings']['same_miqaat'] ?? []) === []) {
            return;
        }
        $result['warnings'][] = [
            'token' => $token,
            'message' => 'A person in this party is already on another sharaf in this miqaat.',
        ];
    }

    private function statusValue(Sharaf $sharaf): string
    {
        $status = $sharaf->status;

        return $status instanceof SharafStatus ? $status->value : (string) $status;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @return list<string>
     */
    private function requiredHeaders(string $module): array
    {
        if ($module === 'misaq') {
            return ['Bethak_Date', 'Group_ID', 'Mumin_ID', 'FullName', 'Mobile_No', 'Age', 'Gender', 'Jamaat', 'Jamiaat'];
        }

        return self::REQUIRED_HEADERS;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySharafResult(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'removals' => 0,
            'hof_changes' => 0,
            'skipped_removals' => [],
            'skipped_hof_changes' => [],
            'blocked_hof_changes' => [],
            'skipped_parties' => [],
            'warnings' => [],
        ];
    }
}
