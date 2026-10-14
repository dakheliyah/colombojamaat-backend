<?php

namespace App\Services;

use App\Models\Miqaat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        $memberCounts = $this->limitToSharafTypes(
            DB::table('sharaf_definitions as sd')
                ->join('events as e', 'e.id', '=', 'sd.event_id')
                ->join('sharafs as s', function ($join) {
                    $join->on('s.sharaf_definition_id', '=', 'sd.id')
                        ->where('s.status', '!=', 'cancelled');
                })
                ->leftJoin('sharaf_members as sm', 'sm.sharaf_id', '=', 's.id')
                ->leftJoin('census as c', 'c.its_id', '=', 'sm.its_id')
                ->where('e.miqaat_id', $miqaatId),
            $sharafTypeIds
        )
            ->groupBy('sd.id')
            ->select([
                'sd.id as sharaf_definition_id',
                DB::raw('COUNT(sm.id) as total_members'),
                DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(c.gender, ''))) = 'male' THEN 1 ELSE 0 END) as male_count"),
                DB::raw("SUM(CASE WHEN LOWER(TRIM(COALESCE(c.gender, ''))) = 'female' THEN 1 ELSE 0 END) as female_count"),
                DB::raw("SUM(CASE WHEN sm.id IS NOT NULL AND LOWER(TRIM(COALESCE(c.gender, ''))) NOT IN ('male', 'female') THEN 1 ELSE 0 END) as unknown_gender_count"),
                DB::raw('SUM(CASE WHEN sm.on_vms = 1 THEN 1 ELSE 0 END) as on_vms_count'),
            ])
            ->get()
            ->keyBy('sharaf_definition_id');

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

            $members = $memberCounts->get($row->sharaf_definition_id);
            $definition = [
                'sharaf_definition_id' => (int) $row->sharaf_definition_id,
                'sharaf_definition_name' => $row->sharaf_definition_name,
                'sharaf_type_id' => $row->sharaf_type_id !== null ? (int) $row->sharaf_type_id : null,
                'sharaf_type_name' => $row->sharaf_type_name,
                'total_sharafs' => $this->int($row->total_sharafs),
                'confirmed_sharafs' => $this->int($row->confirmed_sharafs),
                'pending_sharafs' => $this->int($row->pending_sharafs),
                'cancelled_sharafs' => $this->int($row->cancelled_sharafs),
                'total_members' => $this->int($members?->total_members ?? 0),
                'male_count' => $this->int($members?->male_count ?? 0),
                'female_count' => $this->int($members?->female_count ?? 0),
                'unknown_gender_count' => $this->int($members?->unknown_gender_count ?? 0),
                'on_vms_count' => $this->int($members?->on_vms_count ?? 0),
                'sharafs' => $rosters[(int) $row->sharaf_definition_id] ?? [],
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
                ->leftJoin('census as c', 'c.its_id', '=', 'sm.its_id')
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
                'c.its_id as census_its_id',
                'c.name as census_name',
                'c.gender',
                'sm.on_vms',
            ])
            ->get();

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

            $name = trim((string) ($row->member_name ?? ''));
            if ($name === '') {
                $name = trim((string) ($row->census_name ?? ''));
            }
            if ($name === '') {
                $name = (string) $row->its_id;
            }

            $gender = strtolower(trim((string) ($row->gender ?? '')));
            $grouped[$definitionId][$sharafId]['members'][] = [
                'its_id' => (string) $row->its_id,
                'name' => $name,
                'gender' => in_array($gender, ['male', 'female'], true) ? $gender : null,
                'in_census' => $row->census_its_id !== null,
                'on_vms' => (bool) $row->on_vms,
            ];
        }

        $rosters = [];
        foreach ($grouped as $definitionId => $sharafs) {
            foreach ($sharafs as &$sharaf) {
                usort($sharaf['members'], fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));
            }
            unset($sharaf);
            $rosters[$definitionId] = array_values($sharafs);
        }

        return $rosters;
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
