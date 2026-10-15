<?php

namespace App\Services;

use App\Models\Miqaat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TanzeemOverviewService
{
    /**
     * Headcount and sharaf totals for every definition in a miqaat.
     * People, gender, and VMS counts skip cancelled sharafs.
     * Null sharaf type ids mean every type. An empty list means the user has none assigned.
     *
     * @param  list<int>|null  $sharafTypeIds
     * @return array<string, mixed>|null
     */
    public function forMiqaat(int $miqaatId, ?array $sharafTypeIds = null): ?array
    {
        $miqaat = Miqaat::query()->find($miqaatId);
        if ($miqaat === null) {
            return null;
        }

        $definitions = $this->limitToSharafTypes(
            DB::table('sharaf_definitions as sd')
                ->join('events as e', 'e.id', '=', 'sd.event_id')
                ->leftJoin('sharaf_types as st', 'st.id', '=', 'sd.sharaf_type_id')
                ->leftJoin('sharafs as s', 's.sharaf_definition_id', '=', 'sd.id')
                ->where('e.miqaat_id', $miqaatId),
            $sharafTypeIds
        )
            ->groupBy(
                'e.id',
                'e.name',
                'e.date',
                'sd.id',
                'sd.name',
                'sd.sort_order',
                'sd.sharaf_type_id',
                'st.name'
            )
            ->orderBy('e.date')
            ->orderBy('e.name')
            ->orderBy('sd.sort_order')
            ->orderBy('sd.name')
            ->select([
                'e.id as event_id',
                'e.name as event_name',
                'e.date as event_date',
                'sd.id as sharaf_definition_id',
                'sd.name as sharaf_definition_name',
                'sd.sharaf_type_id',
                'st.name as sharaf_type_name',
                DB::raw('COUNT(s.id) as total_sharafs'),
                DB::raw("SUM(CASE WHEN s.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_sharafs"),
                DB::raw("SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending_sharafs"),
                DB::raw("SUM(CASE WHEN s.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_sharafs"),
            ])
            ->get();

        $rosters = $this->rostersByDefinition($miqaatId, $sharafTypeIds);

        $overall = $this->emptyCounts();
        $overall['event_count'] = 0;
        $overall['definition_count'] = 0;
        $events = [];

        foreach ($definitions as $row) {
            $eventId = (int) $row->event_id;
            if (! isset($events[$eventId])) {
                $events[$eventId] = array_merge($this->emptyCounts(), [
                    'event_id' => $eventId,
                    'event_name' => $row->event_name,
                    'event_date' => Carbon::parse($row->event_date)->toDateString(),
                    'definitions' => [],
                ]);
                $overall['event_count']++;
            }

            $sharafs = $rosters[(int) $row->sharaf_definition_id] ?? [];
            $members = $this->memberCountsFromSharafs($sharafs);
            $definition = [
                'sharaf_definition_id' => (int) $row->sharaf_definition_id,
                'sharaf_definition_name' => $row->sharaf_definition_name,
                'sharaf_type_id' => $row->sharaf_type_id !== null ? (int) $row->sharaf_type_id : null,
                'sharaf_type_name' => $row->sharaf_type_name,
                'total_sharafs' => $this->int($row->total_sharafs),
                'confirmed_sharafs' => $this->int($row->confirmed_sharafs),
                'pending_sharafs' => $this->int($row->pending_sharafs),
                'cancelled_sharafs' => $this->int($row->cancelled_sharafs),
                'total_members' => $members['total_members'],
                'male_count' => $members['male_count'],
                'female_count' => $members['female_count'],
                'unknown_gender_count' => $members['unknown_gender_count'],
                'on_vms_count' => $members['on_vms_count'],
                'sharafs' => $sharafs,
            ];

            $events[$eventId]['definitions'][] = $definition;
            $this->addCounts($events[$eventId], $definition);
            $this->addCounts($overall, $definition);
            $overall['definition_count']++;
        }

        $assignedTypes = [];
        if ($sharafTypeIds !== null && $sharafTypeIds !== []) {
            $assignedTypes = DB::table('sharaf_types')
                ->whereIn('id', $sharafTypeIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($type) => [
                    'id' => (int) $type->id,
                    'name' => $type->name,
                ])
                ->all();
        }

        return [
            'miqaat_id' => $miqaat->id,
            'miqaat_name' => $miqaat->name,
            'scoped_to_sharaf_types' => $sharafTypeIds !== null,
            'sharaf_types' => $assignedTypes,
            'overall' => $overall,
            'events' => array_values($events),
        ];
    }

    /**
     * @param  list<int>|null  $sharafTypeIds
     */
    private function limitToSharafTypes($query, ?array $sharafTypeIds)
    {
        if ($sharafTypeIds === null) {
            return $query;
        }

        if ($sharafTypeIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('sd.sharaf_type_id', $sharafTypeIds);
    }

    /**
     * @param  list<int>|null  $sharafTypeIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function rostersByDefinition(int $miqaatId, ?array $sharafTypeIds): array
    {
        $rows = $this->limitToSharafTypes(
            DB::table('sharafs as s')
                ->join('sharaf_definitions as sd', 'sd.id', '=', 's.sharaf_definition_id')
                ->join('events as e', 'e.id', '=', 'sd.event_id')
                ->leftJoin('sharaf_members as sm', 'sm.sharaf_id', '=', 's.id')
                ->leftJoin('sharaf_positions as sp', 'sp.id', '=', 'sm.sharaf_position_id')
                ->where('e.miqaat_id', $miqaatId),
            $sharafTypeIds
        )
            ->orderBy('s.rank')
            ->orderBy('s.id')
            ->select([
                'sd.id as sharaf_definition_id',
                's.id as sharaf_id',
                's.name as sharaf_name',
                's.rank',
                's.status',
                'sm.id as member_id',
                'sm.its_id',
                'sm.name as member_name',
                'sm.on_vms',
                'sp.name as position_name',
                'sp.display_name as position_display_name',
                'sp.order as position_order',
            ])
            ->get();

        $itsIds = [];
        foreach ($rows as $row) {
            if ($row->member_id === null) {
                continue;
            }
            $itsIds[] = (string) $row->its_id;
        }
        $census = $this->censusByIts($itsIds);

        $grouped = [];
        foreach ($rows as $row) {
            $definitionId = (int) $row->sharaf_definition_id;
            $sharafId = (int) $row->sharaf_id;
            if (! isset($grouped[$definitionId][$sharafId])) {
                $grouped[$definitionId][$sharafId] = [
                    'sharaf_id' => $sharafId,
                    'name' => $row->sharaf_name,
                    'rank' => (int) $row->rank,
                    'status' => (string) $row->status,
                    'members' => [],
                ];
            }

            if ($row->member_id === null) {
                continue;
            }

            $hit = $census[(string) $row->its_id] ?? null;
            $name = trim((string) ($row->member_name ?? ''));
            if ($name === '') {
                $name = trim((string) ($hit['name'] ?? ''));
            }
            if ($name === '') {
                $name = (string) $row->its_id;
            }

            $gender = strtolower(trim((string) ($hit['gender'] ?? '')));
            $positionLabel = trim((string) ($row->position_display_name ?? ''));
            if ($positionLabel === '') {
                $positionLabel = trim((string) ($row->position_name ?? ''));
            }
            $grouped[$definitionId][$sharafId]['members'][] = [
                'its_id' => (string) $row->its_id,
                'name' => $name,
                'position' => $positionLabel !== '' ? $positionLabel : null,
                'position_order' => $row->position_order !== null ? (int) $row->position_order : null,
                'gender' => in_array($gender, ['male', 'female'], true) ? $gender : null,
                'in_census' => $hit !== null,
                'on_vms' => (bool) $row->on_vms,
            ];
        }

        $rosters = [];
        foreach ($grouped as $definitionId => $sharafs) {
            foreach ($sharafs as &$sharaf) {
                usort($sharaf['members'], function (array $a, array $b): int {
                    $ao = $a['position_order'];
                    $bo = $b['position_order'];
                    if ($ao !== $bo) {
                        if ($ao === null) {
                            return 1;
                        }
                        if ($bo === null) {
                            return -1;
                        }

                        return $ao <=> $bo;
                    }

                    return strcasecmp($a['name'], $b['name']);
                });
                foreach ($sharaf['members'] as &$member) {
                    unset($member['position_order']);
                }
                unset($member);
            }
            unset($sharaf);
            $rosters[$definitionId] = array_values($sharafs);
        }

        return $rosters;
    }

    /**
     * Gender for this overview only. Reads the indexed census tables when they
     * exist, so the census view (used by other pages) is not scanned.
     *
     * @param  list<string>  $itsIds
     * @return array<string, array{name: string, gender: mixed}>
     */
    private function censusByIts(array $itsIds): array
    {
        $itsIds = array_values(array_unique(array_filter(
            array_map(fn ($id) => trim((string) $id), $itsIds),
            fn ($id) => $id !== ''
        )));
        if ($itsIds === []) {
            return [];
        }

        if ($this->canReadCensusSources()) {
            return $this->censusFromSources($itsIds);
        }

        $map = [];
        foreach (DB::table('census')->whereIn('its_id', $itsIds)->get(['its_id', 'name', 'gender']) as $row) {
            $map[(string) $row->its_id] = [
                'name' => trim((string) ($row->name ?? '')),
                'gender' => $row->gender,
            ];
        }

        return $map;
    }

    private function canReadCensusSources(): bool
    {
        if (! Schema::hasTable('foreign_census')) {
            return false;
        }

        $row = DB::selectOne("SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'anjuman' AND TABLE_NAME = 'census' LIMIT 1");

        return $row !== null;
    }

    /**
     * Anjuman census wins when an ITS is in both sources. The census view is a
     * UNION ALL, so a join listed that person twice.
     *
     * @param  list<string>  $itsIds
     * @return array<string, array{name: string, gender: mixed}>
     */
    private function censusFromSources(array $itsIds): array
    {
        $map = [];
        $ints = [];
        foreach ($itsIds as $id) {
            if (ctype_digit($id)) {
                $ints[] = (int) $id;
            }
        }

        if ($ints !== []) {
            foreach (DB::table('anjuman.census')->whereIn('ITS_ID', $ints)->get(['ITS_ID', 'name', 'gender']) as $row) {
                $map[(string) (int) $row->ITS_ID] = [
                    'name' => trim((string) ($row->name ?? '')),
                    'gender' => $row->gender,
                ];
            }
        }

        foreach (DB::table('foreign_census')->whereIn('its_id', $itsIds)->get(['its_id', 'name', 'gender']) as $row) {
            $key = (string) $row->its_id;
            if (isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'name' => trim((string) ($row->name ?? '')),
                'gender' => $row->gender,
            ];
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $sharafs
     * @return array{total_members: int, male_count: int, female_count: int, unknown_gender_count: int, on_vms_count: int}
     */
    private function memberCountsFromSharafs(array $sharafs): array
    {
        $counts = [
            'total_members' => 0,
            'male_count' => 0,
            'female_count' => 0,
            'unknown_gender_count' => 0,
            'on_vms_count' => 0,
        ];

        foreach ($sharafs as $sharaf) {
            if (($sharaf['status'] ?? '') === 'cancelled') {
                continue;
            }
            foreach ($sharaf['members'] as $member) {
                $counts['total_members']++;
                if ($member['gender'] === 'male') {
                    $counts['male_count']++;
                } elseif ($member['gender'] === 'female') {
                    $counts['female_count']++;
                } else {
                    $counts['unknown_gender_count']++;
                }
                if ($member['on_vms']) {
                    $counts['on_vms_count']++;
                }
            }
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'total_sharafs' => 0,
            'confirmed_sharafs' => 0,
            'pending_sharafs' => 0,
            'cancelled_sharafs' => 0,
            'total_members' => 0,
            'male_count' => 0,
            'female_count' => 0,
            'unknown_gender_count' => 0,
            'on_vms_count' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, int>  $counts
     */
    private function addCounts(array &$target, array $counts): void
    {
        foreach (array_keys($this->emptyCounts()) as $key) {
            $target[$key] += $counts[$key];
        }
    }

    private function int(mixed $value): int
    {
        return (int) $value;
    }
}
