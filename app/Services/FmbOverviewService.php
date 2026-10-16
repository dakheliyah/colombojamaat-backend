<?php

namespace App\Services;

use App\Models\Miqaat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FmbOverviewService
{
    /**
     * Sharaf-definition totals plus unique people for food.
     * Cancelled sharafs stay in the sharaf counts and are left out of the people.
     * Local is anjuman.census. Foreign is foreign_census, and someone in both is local.
     * Each person is counted once per event, and once in the miqaat total.
     *
     * @return array<string, mixed>|null
     */
    public function forMiqaat(int $miqaatId): ?array
    {
        $miqaat = Miqaat::query()->find($miqaatId);
        if ($miqaat === null) {
            return null;
        }

        $definitions = DB::table('sharaf_definitions as sd')
            ->join('events as e', 'e.id', '=', 'sd.event_id')
            ->leftJoin('sharaf_types as st', 'st.id', '=', 'sd.sharaf_type_id')
            ->leftJoin('sharafs as s', 's.sharaf_definition_id', '=', 'sd.id')
            ->where('e.miqaat_id', $miqaatId)
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

        $memberRows = DB::table('sharaf_members as sm')
            ->join('sharafs as s', 's.id', '=', 'sm.sharaf_id')
            ->join('sharaf_definitions as sd', 'sd.id', '=', 's.sharaf_definition_id')
            ->join('events as e', 'e.id', '=', 'sd.event_id')
            ->where('e.miqaat_id', $miqaatId)
            ->where('s.status', '!=', 'cancelled')
            ->select([
                'e.id as event_id',
                'sd.id as sharaf_definition_id',
                'sm.its_id',
            ])
            ->get();

        $itsIds = [];
        foreach ($memberRows as $row) {
            $key = $this->itsKey($row->its_id);
            if ($key !== null) {
                $itsIds[] = $key;
            }
        }
        $profiles = $this->profiles($itsIds);

        $overall = $this->emptySharafCounts();
        $overall['event_count'] = 0;
        $overall['definition_count'] = 0;
        $events = [];
        $definitionsById = [];

        foreach ($definitions as $row) {
            $eventId = (int) $row->event_id;
            if (! isset($events[$eventId])) {
                $events[$eventId] = array_merge($this->emptySharafCounts(), [
                    'event_id' => $eventId,
                    'event_name' => $row->event_name,
                    'event_date' => Carbon::parse($row->event_date)->toDateString(),
                    'people' => [],
                    'definitions' => [],
                ]);
                $overall['event_count']++;
            }

            $definitionId = (int) $row->sharaf_definition_id;
            $definition = array_merge($this->emptyMemberCounts(), [
                'sharaf_definition_id' => $definitionId,
                'sharaf_definition_name' => $row->sharaf_definition_name,
                'sharaf_type_id' => $row->sharaf_type_id !== null ? (int) $row->sharaf_type_id : null,
                'sharaf_type_name' => $row->sharaf_type_name,
                'total_sharafs' => $this->int($row->total_sharafs),
                'confirmed_sharafs' => $this->int($row->confirmed_sharafs),
                'pending_sharafs' => $this->int($row->pending_sharafs),
                'cancelled_sharafs' => $this->int($row->cancelled_sharafs),
            ]);

            $events[$eventId]['definitions'][] = $definition;
            $definitionsById[$definitionId] = count($events[$eventId]['definitions']) - 1;
            $this->addSharafCounts($events[$eventId], $definition);
            $this->addSharafCounts($overall, $definition);
            $overall['definition_count']++;
        }

        $overallPeople = [];
        foreach ($memberRows as $row) {
            $eventId = (int) $row->event_id;
            $definitionId = (int) $row->sharaf_definition_id;
            if (! isset($events[$eventId]) || ! isset($definitionsById[$definitionId])) {
                continue;
            }

            $index = $definitionsById[$definitionId];
            $key = $this->itsKey($row->its_id);
            $gender = $key !== null ? ($profiles[$key]['gender'] ?? null) : null;
            $events[$eventId]['definitions'][$index]['total_members']++;
            if ($gender === 'male') {
                $events[$eventId]['definitions'][$index]['male_count']++;
            } elseif ($gender === 'female') {
                $events[$eventId]['definitions'][$index]['female_count']++;
            } else {
                $events[$eventId]['definitions'][$index]['unknown_gender_count']++;
            }

            if ($key === null) {
                continue;
            }
            $events[$eventId]['people'][$key] = true;
            $overallPeople[$key] = true;
        }

        $overall['food'] = $this->tally(array_keys($overallPeople), $profiles);
        $eventList = [];
        foreach ($events as $event) {
            $people = $event['people'];
            unset($event['people']);
            $event['food'] = $this->tally(array_keys($people), $profiles);
            $eventList[] = $event;
        }

        return [
            'miqaat_id' => $miqaat->id,
            'miqaat_name' => $miqaat->name,
            'overall' => $overall,
            'events' => $eventList,
        ];
    }

    /**
     * @param  list<string>  $itsIds
     * @return array<string, array{source: string, gender: ?string, below_15: bool}>
     */
    private function profiles(array $itsIds): array
    {
        $itsIds = array_values(array_unique($itsIds));
        if ($itsIds === []) {
            return [];
        }

        if ($this->canReadAnjumanCensus()) {
            return $this->profilesFromSources($itsIds);
        }

        return $this->profilesFromLocalTables($itsIds);
    }

    private function canReadAnjumanCensus(): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        try {
            $row = DB::selectOne("SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'anjuman' AND TABLE_NAME = 'census' LIMIT 1");

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Anjuman census wins when an ITS is in both sources.
     *
     * @param  list<string>  $itsIds
     * @return array<string, array{source: string, gender: ?string, below_15: bool}>
     */
    private function profilesFromSources(array $itsIds): array
    {
        $map = [];
        $ints = [];
        foreach ($itsIds as $id) {
            if (ctype_digit($id)) {
                $ints[] = (int) $id;
            }
        }

        foreach (array_chunk($ints, 800) as $chunk) {
            foreach (DB::table('anjuman.census')->whereIn('ITS_ID', $chunk)->get(['ITS_ID', 'gender', 'age']) as $row) {
                $key = $this->itsKey($row->ITS_ID);
                if ($key === null) {
                    continue;
                }
                $map[$key] = $this->profile('local', $row->gender, $row->age);
            }
        }

        if (Schema::hasTable('foreign_census')) {
            foreach (array_chunk($itsIds, 800) as $chunk) {
                foreach (DB::table('foreign_census')->whereIn('its_id', $chunk)->get(['its_id', 'gender', 'age']) as $row) {
                    $key = $this->itsKey($row->its_id);
                    if ($key === null || isset($map[$key])) {
                        continue;
                    }
                    $map[$key] = $this->profile('foreign', $row->gender, $row->age);
                }
            }
        }

        return $map;
    }

    /**
     * Tests have no anjuman schema. The local census table stands in for it.
     *
     * @param  list<string>  $itsIds
     * @return array<string, array{source: string, gender: ?string, below_15: bool}>
     */
    private function profilesFromLocalTables(array $itsIds): array
    {
        $map = [];
        if (Schema::hasTable('census')) {
            $select = ['its_id', 'gender'];
            if (Schema::hasColumn('census', 'age')) {
                $select[] = 'age';
            }
            foreach (array_chunk($itsIds, 800) as $chunk) {
                foreach (DB::table('census')->whereIn('its_id', $chunk)->get($select) as $row) {
                    $key = $this->itsKey($row->its_id);
                    if ($key === null) {
                        continue;
                    }
                    $map[$key] = $this->profile('local', $row->gender, $row->age ?? null);
                }
            }
        }

        if (Schema::hasTable('foreign_census')) {
            $select = ['its_id', 'gender'];
            if (Schema::hasColumn('foreign_census', 'age')) {
                $select[] = 'age';
            }
            foreach (array_chunk($itsIds, 800) as $chunk) {
                foreach (DB::table('foreign_census')->whereIn('its_id', $chunk)->get($select) as $row) {
                    $key = $this->itsKey($row->its_id);
                    if ($key === null || isset($map[$key])) {
                        continue;
                    }
                    $map[$key] = $this->profile('foreign', $row->gender, $row->age ?? null);
                }
            }
        }

        return $map;
    }

    /**
     * @return array{source: string, gender: ?string, below_15: bool}
     */
    private function profile(string $source, mixed $gender, mixed $age): array
    {
        return [
            'source' => $source,
            'gender' => $this->genderKind($gender),
            'below_15' => $this->below15($age),
        ];
    }

    private function genderKind(mixed $gender): ?string
    {
        $value = strtolower(trim((string) ($gender ?? '')));
        if ($value === 'm' || $value === 'male' || str_starts_with($value, 'male')) {
            return 'male';
        }
        if ($value === 'f' || $value === 'female' || str_starts_with($value, 'female')) {
            return 'female';
        }

        return null;
    }

    private function below15(mixed $age): bool
    {
        if ($age === null || $age === '') {
            return false;
        }
        if (! is_numeric($age)) {
            return false;
        }

        return (int) $age < 15;
    }

    /**
     * @param  list<string>  $itsIds
     * @param  array<string, array{source: string, gender: ?string, below_15: bool}>  $profiles
     * @return array{local: array<string, int>, foreign: array<string, int>, unlisted: array<string, int>}
     */
    private function tally(array $itsIds, array $profiles): array
    {
        $food = [
            'local' => $this->emptyGroup(),
            'foreign' => $this->emptyGroup(),
            'unlisted' => $this->emptyGroup(),
        ];

        foreach ($itsIds as $its) {
            $profile = $profiles[$its] ?? null;
            $bucket = $profile['source'] ?? 'unlisted';
            $food[$bucket]['total']++;
            $gender = $profile['gender'] ?? null;
            if ($gender === 'male') {
                $food[$bucket]['male']++;
            } elseif ($gender === 'female') {
                $food[$bucket]['female']++;
            } else {
                $food[$bucket]['unknown_gender']++;
            }
            if (! empty($profile['below_15'])) {
                $food[$bucket]['below_15']++;
            }
        }

        return $food;
    }

    /**
     * @return array{total: int, male: int, female: int, unknown_gender: int, below_15: int}
     */
    private function emptyGroup(): array
    {
        return [
            'total' => 0,
            'male' => 0,
            'female' => 0,
            'unknown_gender' => 0,
            'below_15' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptySharafCounts(): array
    {
        return [
            'total_sharafs' => 0,
            'confirmed_sharafs' => 0,
            'pending_sharafs' => 0,
            'cancelled_sharafs' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyMemberCounts(): array
    {
        return [
            'total_members' => 0,
            'male_count' => 0,
            'female_count' => 0,
            'unknown_gender_count' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, int>  $counts
     */
    private function addSharafCounts(array &$target, array $counts): void
    {
        foreach (array_keys($this->emptySharafCounts()) as $key) {
            $target[$key] += $counts[$key];
        }
    }

    private function itsKey(mixed $its): ?string
    {
        $value = trim((string) ($its ?? ''));
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (string) (int) $value;
        }

        return $value;
    }

    private function int(mixed $value): int
    {
        return (int) $value;
    }
}
