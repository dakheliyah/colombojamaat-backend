<?php

namespace App\Services;

use App\Models\Miqaat;
use App\Models\Sharaf;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SharafReceiptNumberService
{
    /**
     * Assign a per-miqaat receipt number to each sharaf that does not have one.
     * Numbers start at 1 for each miqaat. A sharaf that already has a number keeps it.
     *
     * @param  list<int>  $sharafIds  Issue order for sharafs that still need a number.
     * @return list<array{sharaf_id: int, receipt_no: int, receipt_issued_at: string}>
     */
    public function issue(array $sharafIds): array
    {
        $orderedIds = [];
        foreach ($sharafIds as $id) {
            $id = (int) $id;
            if (!in_array($id, $orderedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        if ($orderedIds === []) {
            return [];
        }

        return DB::transaction(function () use ($orderedIds) {
            $locked = Sharaf::query()
                ->whereIn('id', $orderedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Sharaf $sharaf) => (int) $sharaf->id);

            if ($locked->count() !== count($orderedIds)) {
                throw new RuntimeException('One or more sharafs were not found.');
            }

            $nextByMiqaat = [];
            $miqaats = [];
            $issuedAt = now();

            foreach ($orderedIds as $id) {
                /** @var Sharaf $sharaf */
                $sharaf = $locked->get($id);
                if ($sharaf->receipt_no !== null) {
                    continue;
                }

                $miqaatId = $this->miqaatIdFor($sharaf);
                if (!array_key_exists($miqaatId, $nextByMiqaat)) {
                    $miqaat = Miqaat::query()->whereKey($miqaatId)->lockForUpdate()->first();
                    if ($miqaat === null) {
                        throw new RuntimeException('Sharaf is not linked to a miqaat.');
                    }
                    $miqaats[$miqaatId] = $miqaat;
                    $nextByMiqaat[$miqaatId] = (int) $miqaat->last_receipt_no;
                }

                $nextByMiqaat[$miqaatId]++;
                $sharaf->receipt_no = $nextByMiqaat[$miqaatId];
                $sharaf->receipt_issued_at = $issuedAt;
                $sharaf->save();
            }

            foreach ($nextByMiqaat as $miqaatId => $lastNo) {
                $miqaat = $miqaats[$miqaatId];
                $miqaat->last_receipt_no = $lastNo;
                $miqaat->save();
            }

            return $locked
                ->sortBy(fn (Sharaf $sharaf) => array_search($sharaf->id, $orderedIds, true))
                ->map(fn (Sharaf $sharaf) => $this->payload($sharaf))
                ->values()
                ->all();
        });
    }

    private function miqaatIdFor(Sharaf $sharaf): int
    {
        $miqaatId = DB::table('sharaf_definitions')
            ->join('events', 'events.id', '=', 'sharaf_definitions.event_id')
            ->where('sharaf_definitions.id', $sharaf->sharaf_definition_id)
            ->value('events.miqaat_id');

        if ($miqaatId === null) {
            throw new RuntimeException('Sharaf is not linked to a miqaat.');
        }

        return (int) $miqaatId;
    }

    /**
     * @return array{sharaf_id: int, receipt_no: int, receipt_issued_at: string}
     */
    private function payload(Sharaf $sharaf): array
    {
        return [
            'sharaf_id' => (int) $sharaf->id,
            'receipt_no' => (int) $sharaf->receipt_no,
            'receipt_issued_at' => $sharaf->receipt_issued_at?->toIso8601String() ?? '',
        ];
    }
};
