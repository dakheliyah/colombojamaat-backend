<?php

namespace App\Services;

use App\Models\Sharaf;
use App\Models\SharafDefinition;
use App\Models\SharafMember;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SharafRankBulkUpdateService
{
    /**
     * Assign absolute ranks for sharafs in one definition, matched by HOF / first-seat ITS.
     *
     * Only `rank` is rewritten. Members, payments, checks, clearance, token, and name stay on
     * the same sharaf row. Unlisted sharafs keep their current ranks; the final set of ranks
     * across the definition must remain unique.
     *
     * @param  list<array{its: string, rank: int}>  $assignments
     * @return array{
     *     updated: list<array{id: int, its: string, old_rank: int, new_rank: int}>,
     *     unchanged: list<array{id: int, its: string, rank: int}>,
     *     sharafs: \Illuminate\Support\Collection<int, Sharaf>
     * }
     */
    public function assignByIts(SharafDefinition $definition, array $assignments): array
    {
        if ($assignments === []) {
            throw new InvalidArgumentException('assignments must include at least one ITS and rank.');
        }

        $normalized = [];
        $seenIts = [];
        $seenRanks = [];

        foreach ($assignments as $index => $row) {
            $its = $this->normalizeIts($row['its'] ?? null);
            $rank = isset($row['rank']) ? (int) $row['rank'] : 0;
            $line = $index + 1;

            if ($its === '') {
                throw new InvalidArgumentException("Row {$line}: ITS is required.");
            }
            if ($rank < 1) {
                throw new InvalidArgumentException("Row {$line}: rank/tarteeb must be an integer >= 1.");
            }
            if (isset($seenIts[$its])) {
                throw new InvalidArgumentException("Duplicate ITS in file: {$its}.");
            }
            if (isset($seenRanks[$rank])) {
                throw new InvalidArgumentException("Duplicate rank/tarteeb in file: {$rank}.");
            }

            $seenIts[$its] = true;
            $seenRanks[$rank] = true;
            $normalized[] = ['its' => $its, 'rank' => $rank];
        }

        return DB::transaction(function () use ($definition, $normalized) {
            $sharafs = Sharaf::where('sharaf_definition_id', $definition->id)
                ->lockForUpdate()
                ->orderBy('rank')
                ->orderBy('id')
                ->get();

            if ($sharafs->isEmpty()) {
                throw new InvalidArgumentException('This sharaf definition has no sharafs to update.');
            }

            $byId = $sharafs->keyBy('id');
            $lookup = $this->buildItsLookup($sharafs);

            /** @var array<int, int> $finalRankById */
            $finalRankById = [];
            foreach ($sharafs as $sharaf) {
                $finalRankById[(int) $sharaf->id] = (int) $sharaf->rank;
            }

            $updated = [];
            foreach ($normalized as $row) {
                $sharafId = $lookup[$row['its']] ?? null;
                if ($sharafId === null) {
                    throw new InvalidArgumentException(
                        "ITS {$row['its']} was not found as HOF or first-seat member in this definition."
                    );
                }

                $sharaf = $byId->get($sharafId);
                $oldRank = (int) $sharaf->rank;
                $finalRankById[$sharafId] = $row['rank'];

                if ($oldRank !== $row['rank']) {
                    $updated[] = [
                        'id' => $sharafId,
                        'its' => (string) $sharaf->hof_its,
                        'matched_its' => $row['its'],
                        'old_rank' => $oldRank,
                        'new_rank' => $row['rank'],
                    ];
                }
            }

            $ranks = array_values($finalRankById);
            if (count($ranks) !== count(array_unique($ranks))) {
                $counts = array_count_values($ranks);
                $dupes = [];
                foreach ($counts as $rank => $count) {
                    if ($count > 1) {
                        $dupes[] = (string) $rank;
                    }
                }
                throw new InvalidArgumentException(
                    'Rank collision after apply. Conflicting rank(s): '.implode(', ', $dupes)
                    .'. Include every conflicting ITS in the file, or choose free rank numbers.'
                );
            }

            if ($updated === []) {
                return [
                    'updated' => [],
                    'unchanged' => $sharafs->map(fn (Sharaf $s) => [
                        'id' => (int) $s->id,
                        'its' => (string) $s->hof_its,
                        'rank' => (int) $s->rank,
                    ])->values()->all(),
                    'sharafs' => $sharafs,
                ];
            }

            // Two-phase write avoids unique(sharaf_definition_id, rank) collisions mid-update.
            $offset = (int) $sharafs->max('rank');
            $phase = 0;
            foreach ($finalRankById as $id => $rank) {
                $phase++;
                DB::table('sharafs')
                    ->where('sharaf_definition_id', $definition->id)
                    ->where('id', $id)
                    ->update([
                        'rank' => $offset + $phase,
                        'updated_at' => now(),
                    ]);
            }
            foreach ($finalRankById as $id => $rank) {
                DB::table('sharafs')
                    ->where('sharaf_definition_id', $definition->id)
                    ->where('id', $id)
                    ->update([
                        'rank' => $rank,
                        'updated_at' => now(),
                    ]);
            }

            $ordered = Sharaf::where('sharaf_definition_id', $definition->id)
                ->orderBy('rank')
                ->orderBy('id')
                ->get();

            $unchanged = [];
            foreach ($ordered as $sharaf) {
                $changed = false;
                foreach ($updated as $row) {
                    if ($row['id'] === (int) $sharaf->id) {
                        $changed = true;
                        break;
                    }
                }
                if (! $changed) {
                    $unchanged[] = [
                        'id' => (int) $sharaf->id,
                        'its' => (string) $sharaf->hof_its,
                        'rank' => (int) $sharaf->rank,
                    ];
                }
            }

            return [
                'updated' => $updated,
                'unchanged' => $unchanged,
                'sharafs' => $ordered,
            ];
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Sharaf>  $sharafs
     * @return array<string, int> its => sharaf_id
     */
    private function buildItsLookup($sharafs): array
    {
        $lookup = [];
        foreach ($sharafs as $sharaf) {
            $hof = $this->normalizeIts($sharaf->hof_its);
            if ($hof !== '' && ! isset($lookup[$hof])) {
                $lookup[$hof] = (int) $sharaf->id;
            }
        }

        $ids = $sharafs->pluck('id')->all();
        if ($ids === []) {
            return $lookup;
        }

        $firstSeatMembers = SharafMember::query()
            ->whereIn('sharaf_id', $ids)
            ->whereHas('sharafPosition', function ($q) {
                $q->where('order', 1);
            })
            ->orderBy('sp_keyno')
            ->orderBy('id')
            ->get(['sharaf_id', 'its_id']);

        foreach ($firstSeatMembers as $member) {
            $its = $this->normalizeIts($member->its_id);
            if ($its === '' || isset($lookup[$its])) {
                continue;
            }
            $lookup[$its] = (int) $member->sharaf_id;
        }

        return $lookup;
    }

    private function normalizeIts(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
