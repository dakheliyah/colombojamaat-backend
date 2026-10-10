<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditLogPresenter
{
    /** @var array<string, string> */
    private const FK_FIELDS = [
        'sharaf_id' => 'sharaf',
        'sharaf_definition_id' => 'sharaf_definition',
        'source_sharaf_definition_id' => 'sharaf_definition',
        'target_sharaf_definition_id' => 'sharaf_definition',
        'payment_definition_id' => 'payment_definition',
        'source_payment_definition_id' => 'payment_definition',
        'target_payment_definition_id' => 'payment_definition',
        'sharaf_position_id' => 'sharaf_position',
        'source_sharaf_position_id' => 'sharaf_position',
        'target_sharaf_position_id' => 'sharaf_position',
        'sharaf_type_id' => 'sharaf_type',
        'event_id' => 'event',
        'miqaat_id' => 'miqaat',
        'color_legend_id' => 'color_legend',
        'mcd_id' => 'check_definition',
        'wc_id' => 'waj_category',
        'user_id' => 'user',
        'currency_id' => 'currency',
    ];

    /** @var array<string, string> */
    private const LIST_FIELDS = [
        'role_ids' => 'user_role',
        'sharaf_type_ids' => 'sharaf_type',
    ];

    /** @var list<string> */
    private const PERSON_FIELDS = [
        'its_id',
        'hof_its',
        'its_no',
        'master_its',
        'cleared_by_its',
        'verified_by_its',
        'hof_id',
    ];

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array<int, array<string, mixed>>
     */
    public function present(Collection $logs): array
    {
        $lookups = $this->lookups($logs);

        return $logs->map(function (AuditLog $log) use ($lookups) {
            $arr = $log->toArray();
            $arr['entity_label'] = AuditLogService::ENTITY_LABELS[$log->entity] ?? $log->entity;
            $arr['parent_entity_label'] = $log->parent_entity
                ? (AuditLogService::ENTITY_LABELS[$log->parent_entity] ?? $log->parent_entity)
                : null;
            $arr['auditable_name'] = $this->recordName($log, $lookups);
            $arr['old_values'] = $this->annotate($log->old_values, $lookups);
            $arr['new_values'] = $this->annotate($log->new_values, $lookups);
            $arr['summary'] = $this->annotateSummary($log->summary, $log, $lookups);

            return $arr;
        })->values()->all();
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array{fk: array<string, array<string, string>>, person: array<string, string>, group: array<string, string>, payment: array<string, string>, member: array<string, string>, check: array<string, string>, wajebaat: array<string, string>}
     */
    private function lookups(Collection $logs): array
    {
        $fkIds = [];
        $itsIds = [];
        $groups = [];
        $paymentIds = [];
        $memberIds = [];
        $checkIds = [];
        $wajebaatIds = [];

        foreach ($logs as $log) {
            if ($log->entity === 'sharaf_payment') {
                $paymentIds[] = (int) $log->auditable_id;
            } elseif ($log->entity === 'sharaf_member') {
                $memberIds[] = (int) $log->auditable_id;
            } elseif ($log->entity === 'miqaat_check') {
                $checkIds[] = (int) $log->auditable_id;
            } elseif ($log->entity === 'wajebaat') {
                $wajebaatIds[] = (int) $log->auditable_id;
            }
            $this->collectRecordId($fkIds, $log->entity, $log->auditable_id);
            $this->collectRecordId($fkIds, $log->parent_entity, $log->parent_id);
            foreach ([$log->old_values, $log->new_values] as $values) {
                if (! is_array($values)) {
                    continue;
                }
                foreach (self::FK_FIELDS as $field => $kind) {
                    if ($this->isId($values[$field] ?? null)) {
                        $fkIds[$kind][] = $values[$field];
                    }
                }
                foreach (self::LIST_FIELDS as $field => $kind) {
                    foreach ($this->listIds($values[$field] ?? null) as $id) {
                        $fkIds[$kind][] = $id;
                    }
                }
                foreach (self::PERSON_FIELDS as $field) {
                    $its = $this->its($values[$field] ?? null);
                    if ($its !== null) {
                        $itsIds[] = $its;
                    }
                }
                if ($this->isId($values['wg_id'] ?? null) && $this->isId($values['miqaat_id'] ?? null)) {
                    $groups[] = [(int) $values['miqaat_id'], (int) $values['wg_id']];
                }
            }
        }

        return [
            'fk' => $this->loadFkNames($fkIds),
            'person' => $this->loadPersonNames($itsIds),
            'group' => $this->loadGroupNames($groups),
            'payment' => $this->loadPaymentNames($paymentIds),
            'member' => $this->loadMemberNames($memberIds),
            'check' => $this->loadCheckNames($checkIds),
            'wajebaat' => $this->loadWajebaatNames($wajebaatIds),
        ];
    }

    /**
     * @param  array<string, list<int|string>>  $fkIds
     */
    private function collectRecordId(array &$fkIds, ?string $entity, mixed $id): void
    {
        if (! $this->isId($id) || $entity === null) {
            return;
        }

        $kind = match ($entity) {
            'sharaf' => 'sharaf',
            'sharaf_definition' => 'sharaf_definition',
            'payment_definition' => 'payment_definition',
            'sharaf_position' => 'sharaf_position',
            'sharaf_type' => 'sharaf_type',
            'event' => 'event',
            'miqaat' => 'miqaat',
            'user' => 'user',
            'currency' => 'currency',
            'event_color_legend' => 'color_legend',
            'miqaat_check_definition' => 'check_definition',
            default => null,
        };

        if ($kind !== null) {
            $fkIds[$kind][] = $id;
        }
    }

    /**
     * @param  array<string, list<int|string>>  $fkIds
     * @return array<string, array<string, string>>
     */
    private function loadFkNames(array $fkIds): array
    {
        $names = [];
        foreach ($fkIds as $kind => $ids) {
            $ids = array_values(array_unique(array_map(fn ($id) => (int) $id, $ids)));
            if ($ids === []) {
                continue;
            }

            if ($kind === 'sharaf') {
                $rows = DB::table('sharafs')
                    ->leftJoin('census', 'census.its_id', '=', 'sharafs.hof_its')
                    ->whereIn('sharafs.id', $ids)
                    ->get(['sharafs.id', 'sharafs.name', 'census.name as hof_name']);
                foreach ($rows as $row) {
                    $name = $this->text($row->name) ?? $this->text($row->hof_name);
                    if ($name !== null) {
                        $names[$kind][(string) $row->id] = $name;
                    }
                }
                continue;
            }

            if ($kind === 'sharaf_position') {
                $rows = DB::table('sharaf_positions')->whereIn('id', $ids)->get(['id', 'name', 'display_name']);
                foreach ($rows as $row) {
                    $name = $this->text($row->display_name) ?? $this->text($row->name);
                    if ($name !== null) {
                        $names[$kind][(string) $row->id] = $name;
                    }
                }
                continue;
            }

            $rows = match ($kind) {
                'sharaf_definition' => DB::table('sharaf_definitions')->whereIn('id', $ids)->get(['id', 'name']),
                'payment_definition' => DB::table('payment_definitions')->whereIn('id', $ids)->get(['id', 'name']),
                'sharaf_type' => DB::table('sharaf_types')->whereIn('id', $ids)->get(['id', 'name']),
                'event' => DB::table('events')->whereIn('id', $ids)->get(['id', 'name']),
                'miqaat' => DB::table('miqaats')->whereIn('id', $ids)->get(['id', 'name']),
                'user' => DB::table('users')->whereIn('id', $ids)->get(['id', 'name']),
                'user_role' => DB::table('user_roles')->whereIn('id', $ids)->get(['id', 'name']),
                'currency' => DB::table('currencies')->whereIn('id', $ids)->get(['id', 'name']),
                'color_legend' => DB::table('event_color_legends')->whereIn('id', $ids)->get(['id', 'label as name']),
                'check_definition' => DB::table('miqaat_check_definitions')->whereIn('mcd_id', $ids)->get(['mcd_id as id', 'name']),
                'waj_category' => DB::table('waj_categories')->whereIn('wc_id', $ids)->get(['wc_id as id', 'name']),
                default => collect(),
            };

            foreach ($rows as $row) {
                $name = $this->text($row->name);
                if ($name !== null) {
                    $names[$kind][(string) $row->id] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $itsIds
     * @return array<string, string>
     */
    private function loadPersonNames(array $itsIds): array
    {
        $itsIds = array_values(array_unique(array_filter($itsIds, fn (string $its) => $its !== '')));
        if ($itsIds === []) {
            return [];
        }

        $names = [];
        $census = DB::table('census')->whereIn('its_id', $itsIds)->get(['its_id', 'name']);
        foreach ($census as $row) {
            $name = is_string($row->name) ? trim($row->name) : '';
            if ($name !== '') {
                $names[(string) $row->its_id] = $name;
            }
        }

        $missing = array_values(array_filter($itsIds, fn (string $its) => ! isset($names[$its])));
        if ($missing !== []) {
            $users = DB::table('users')->whereIn('its_no', $missing)->get(['its_no', 'name']);
            foreach ($users as $row) {
                $name = is_string($row->name) ? trim($row->name) : '';
                if ($name !== '') {
                    $names[(string) $row->its_no] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $pairs
     * @return array<string, string>
     */
    private function loadGroupNames(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $wgIds = array_values(array_unique(array_map(fn (array $pair) => $pair[1], $pairs)));
        $rows = DB::table('wajebaat_groups')
            ->whereIn('wg_id', $wgIds)
            ->get(['miqaat_id', 'wg_id', 'group_name']);

        $names = [];
        foreach ($rows as $row) {
            $name = is_string($row->group_name) ? trim($row->group_name) : '';
            if ($name === '') {
                continue;
            }
            $names[(int) $row->miqaat_id.'-'.(int) $row->wg_id] = $name;
        }

        return $names;
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, string>
     */
    private function loadPaymentNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('sharaf_payments')
            ->join('payment_definitions', 'payment_definitions.id', '=', 'sharaf_payments.payment_definition_id')
            ->whereIn('sharaf_payments.id', $ids)
            ->get(['sharaf_payments.id', 'payment_definitions.name']);

        $names = [];
        foreach ($rows as $row) {
            $name = $this->text($row->name);
            if ($name !== null) {
                $names[(string) $row->id] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, string>
     */
    private function loadMemberNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('sharaf_members')
            ->leftJoin('census', 'census.its_id', '=', 'sharaf_members.its_id')
            ->whereIn('sharaf_members.id', $ids)
            ->get(['sharaf_members.id', 'sharaf_members.name', 'census.name as census_name', 'sharaf_members.its_id']);

        $names = [];
        foreach ($rows as $row) {
            $name = $this->text($row->name) ?? $this->text($row->census_name);
            if ($name !== null) {
                $names[(string) $row->id] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, string>
     */
    private function loadCheckNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('miqaat_checks')
            ->join('miqaat_check_definitions', 'miqaat_check_definitions.mcd_id', '=', 'miqaat_checks.mcd_id')
            ->whereIn('miqaat_checks.id', $ids)
            ->get(['miqaat_checks.id', 'miqaat_check_definitions.name']);

        $names = [];
        foreach ($rows as $row) {
            $name = $this->text($row->name);
            if ($name !== null) {
                $names[(string) $row->id] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, string>
     */
    private function loadWajebaatNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('wajebaat')
            ->leftJoin('census', 'census.its_id', '=', 'wajebaat.its_id')
            ->whereIn('wajebaat.id', $ids)
            ->get(['wajebaat.id', 'census.name']);

        $names = [];
        foreach ($rows as $row) {
            $name = $this->text($row->name);
            if ($name !== null) {
                $names[(string) $row->id] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array{fk: array<string, array<string, string>>, person: array<string, string>, group: array<string, string>, payment: array<string, string>, member: array<string, string>, check: array<string, string>, wajebaat: array<string, string>}  $lookups
     */
    private function recordName(AuditLog $log, array $lookups): ?string
    {
        $direct = $this->fkName($this->entityKind($log->entity), $log->auditable_id, $lookups);
        if ($direct !== null) {
            return $direct;
        }

        $values = is_array($log->new_values) ? $log->new_values : (is_array($log->old_values) ? $log->old_values : []);

        return match ($log->entity) {
            'sharaf_payment' => $this->fkName('payment_definition', $values['payment_definition_id'] ?? null, $lookups)
                ?? ($lookups['payment'][(string) $log->auditable_id] ?? null),
            'sharaf_member' => $this->text($values['name'] ?? null)
                ?? $this->personName($values['its_id'] ?? null, $lookups)
                ?? ($lookups['member'][(string) $log->auditable_id] ?? null),
            'sharaf_clearance' => $this->personName($values['hof_its'] ?? null, $lookups),
            'wajebaat' => $this->personName($values['its_id'] ?? null, $lookups)
                ?? ($lookups['wajebaat'][(string) $log->auditable_id] ?? null),
            'wajebaat_group' => $this->text($values['group_name'] ?? null)
                ?? $this->personName($values['its_id'] ?? null, $lookups),
            'miqaat_check' => $this->fkName('check_definition', $values['mcd_id'] ?? null, $lookups)
                ?? ($lookups['check'][(string) $log->auditable_id] ?? null),
            'sila_fitra_calculation', 'sila_fitra_config' => $this->personName($values['hof_its'] ?? null, $lookups),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @param  array{fk: array<string, array<string, string>>, person: array<string, string>, group: array<string, string>}  $lookups
     * @return array<string, mixed>|null
     */
    private function annotate(?array $values, array $lookups): ?array
    {
        if ($values === null) {
            return null;
        }

        $out = [];
        foreach ($values as $key => $value) {
            $out[$key] = $this->annotateValue((string) $key, $value, $values, $lookups);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $siblings
     * @param  array{fk: array<string, array<string, string>>, person: array<string, string>, group: array<string, string>}  $lookups
     */
    private function annotateValue(string $key, mixed $value, array $siblings, array $lookups): mixed
    {
        if (isset(self::FK_FIELDS[$key]) && $this->isId($value)) {
            return $this->withName($this->fkName(self::FK_FIELDS[$key], $value, $lookups), $value);
        }

        if (isset(self::LIST_FIELDS[$key])) {
            $ids = $this->listIds($value);
            if ($ids === []) {
                return $value;
            }

            return implode(', ', array_map(
                fn ($id) => $this->withName($this->fkName(self::LIST_FIELDS[$key], $id, $lookups), $id),
                $ids
            ));
        }

        if (in_array($key, self::PERSON_FIELDS, true)) {
            $its = $this->its($value);
            if ($its === null) {
                return $value;
            }

            return $this->withName($lookups['person'][$its] ?? null, $its);
        }

        if ($key === 'wg_id' && $this->isId($value) && $this->isId($siblings['miqaat_id'] ?? null)) {
            $groupKey = (int) $siblings['miqaat_id'].'-'.(int) $value;

            return $this->withName($lookups['group'][$groupKey] ?? null, $value);
        }

        return $value;
    }

    /**
     * @param  array{fk: array<string, array<string, string>>, person: array<string, string>, group: array<string, string>}  $lookups
     */
    private function annotateSummary(?string $summary, AuditLog $log, array $lookups): ?string
    {
        if ($summary === null || $summary === '') {
            return $summary;
        }

        $values = is_array($log->new_values) ? $log->new_values : (is_array($log->old_values) ? $log->old_values : []);
        $sharafId = $values['sharaf_id'] ?? ($log->parent_entity === 'sharaf' ? $log->parent_id : null);
        $sharafName = $this->fkName('sharaf', $sharafId, $lookups);
        if ($sharafName !== null && $this->isId($sharafId)) {
            $summary = str_replace(
                'sharaf #'.$sharafId,
                'sharaf '.$sharafName.' (#'.$sharafId.')',
                $summary
            );
        }

        return $summary;
    }

    /**
     * @param  array{fk: array<string, array<string, string>>, person: array<string, string>, group: array<string, string>}  $lookups
     */
    private function fkName(?string $kind, mixed $id, array $lookups): ?string
    {
        if ($kind === null || ! $this->isId($id)) {
            return null;
        }

        $name = $lookups['fk'][$kind][(string) (int) $id] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array{fk: array<string, array<string, string>>, person: array<string, string>, group: array<string, string>}  $lookups
     */
    private function personName(mixed $its, array $lookups): ?string
    {
        $key = $this->its($its);

        return $key !== null ? ($lookups['person'][$key] ?? null) : null;
    }

    private function entityKind(string $entity): ?string
    {
        return match ($entity) {
            'sharaf' => 'sharaf',
            'sharaf_definition' => 'sharaf_definition',
            'payment_definition' => 'payment_definition',
            'sharaf_position' => 'sharaf_position',
            'sharaf_type' => 'sharaf_type',
            'event' => 'event',
            'miqaat' => 'miqaat',
            'user' => 'user',
            'currency' => 'currency',
            'event_color_legend' => 'color_legend',
            'miqaat_check_definition' => 'check_definition',
            default => null,
        };
    }

    private function withName(?string $name, int|string $id): string
    {
        if ($name === null || $name === '') {
            return (string) $id;
        }

        return $name.' (#'.$id.')';
    }

    private function isId(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        return is_string($value) && preg_match('/\A[1-9][0-9]*\z/', $value) === 1;
    }

    private function its(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $its = trim((string) $value);

        return preg_match('/\A[0-9]+\z/', $its) === 1 && $its !== '0' ? $its : null;
    }

    /**
     * @return list<int|string>
     */
    private function listIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if ($this->isId($item)) {
                $ids[] = $item;
            }
        }

        return $ids;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
