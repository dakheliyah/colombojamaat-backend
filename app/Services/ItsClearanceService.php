<?php

namespace App\Services;

use App\Models\Census;
use App\Models\MiqaatCheck;
use App\Models\MiqaatCheckDepartment;
use App\Models\Sharaf;
use App\Models\Wajebaat;
use App\Models\WajebaatGroup;

class ItsClearanceService
{
    /**
     * Clearance for one ITS.
     * Miqaat check definitions are evaluated for the family Head of Family (and wajebaat-group HoFs).
     * An ITS with no census row is checked against its own miqaat checks.
     * Payment definitions are evaluated for sharafs in this miqaat where the given ITS is Head of Family.
     * can_mark_paid stays department-only so wajebaat mark-paid matches the existing guard.
     * is_cleared is true only when both department checks and those payments are clear.
     *
     * @return array<string, mixed>
     */
    public function forIts(int $miqaatId, string $itsId): array
    {
        return $this->forMany($miqaatId, [$itsId])[$itsId];
    }

    /**
     * Clearance for many ITS numbers in a handful of queries.
     * Each entry matches {@see forIts()}.
     *
     * @param  array<int, string>  $itsIds
     * @return array<string, array<string, mixed>>
     */
    public function forMany(int $miqaatId, array $itsIds): array
    {
        $itsIds = array_values(array_unique(array_map(static fn ($id): string => (string) $id, $itsIds)));
        if ($itsIds === []) {
            return [];
        }

        $people = Census::query()->whereIn('its_id', $itsIds)->get()->keyBy('its_id');

        $hofIds = [];
        foreach ($itsIds as $itsId) {
            $person = $people->get($itsId);
            if ($person) {
                $hofIds[] = (string) ($person->hof_id ?? $itsId);
            }
        }
        $hofIds = array_values(array_unique($hofIds));

        $groupByHof = [];
        $membersByWg = collect();
        $memberCensus = collect();
        if ($hofIds !== []) {
            $groups = WajebaatGroup::query()
                ->where('miqaat_id', $miqaatId)
                ->where(function ($q) use ($hofIds) {
                    $q->whereIn('master_its', $hofIds)->orWhereIn('its_id', $hofIds);
                })
                ->orderBy('id')
                ->get();

            foreach ($groups as $group) {
                foreach (array_filter([(string) $group->master_its, (string) $group->its_id]) as $key) {
                    if (! isset($groupByHof[$key])) {
                        $groupByHof[$key] = $group;
                    }
                }
            }

            $wgIds = collect($groupByHof)->pluck('wg_id')->unique()->filter()->values()->all();
            if ($wgIds !== []) {
                $groupMembers = WajebaatGroup::query()
                    ->where('miqaat_id', $miqaatId)
                    ->whereIn('wg_id', $wgIds)
                    ->get(['wg_id', 'its_id', 'master_its']);
                $membersByWg = $groupMembers->groupBy(fn ($row) => (string) $row->wg_id);

                $memberIts = $groupMembers->pluck('its_id')
                    ->merge($groupMembers->pluck('master_its'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                if ($memberIts !== []) {
                    $memberCensus = Census::query()
                        ->whereIn('its_id', $memberIts)
                        ->get(['its_id', 'hof_id'])
                        ->keyBy('its_id');
                }
            }
        }

        $hofsToCheckByIts = [];
        $allHofsToCheck = [];
        foreach ($itsIds as $itsId) {
            $person = $people->get($itsId);
            if (! $person) {
                $allHofsToCheck[] = $itsId;
                continue;
            }
            $hofItsId = (string) ($person->hof_id ?? $itsId);
            $toCheck = [$hofItsId];
            $group = $groupByHof[$hofItsId] ?? null;
            if ($group) {
                $rows = $membersByWg->get((string) $group->wg_id, collect());
                $groupMemberItsIds = $rows->pluck('its_id')->filter()->all();
                $masterIts = $group->master_its;
                if ($masterIts && ! in_array($masterIts, $groupMemberItsIds, true)) {
                    $groupMemberItsIds[] = $masterIts;
                }
                $groupHofItsIds = [];
                foreach ($groupMemberItsIds as $memberItsId) {
                    $hof = $memberCensus->get($memberItsId)?->hof_id;
                    if ($hof) {
                        $groupHofItsIds[] = (string) $hof;
                    }
                }
                $toCheck = array_values(array_unique(array_merge($toCheck, $groupHofItsIds)));
            }
            $hofsToCheckByIts[$itsId] = $toCheck;
            foreach ($toCheck as $hof) {
                $allHofsToCheck[] = $hof;
            }
        }
        $allHofsToCheck = array_values(array_unique($allHofsToCheck));

        $departments = MiqaatCheckDepartment::query()
            ->where('miqaat_id', $miqaatId)
            ->orderBy('name')
            ->get(['mcd_id', 'name', 'user_type']);

        $checksByIts = collect();
        if ($departments->isNotEmpty() && $allHofsToCheck !== []) {
            $checksByIts = MiqaatCheck::query()
                ->whereIn('its_id', $allHofsToCheck)
                ->whereIn('mcd_id', $departments->pluck('mcd_id')->all())
                ->get()
                ->groupBy('its_id');
        }

        $pendingByHof = [];
        foreach ($allHofsToCheck as $hof) {
            $pendingByHof[$hof] = $this->pendingDepartmentsFrom($departments, $checksByIts->get($hof));
        }

        $wajebaatByIts = [];
        foreach (
            Wajebaat::query()
                ->where('miqaat_id', $miqaatId)
                ->whereIn('its_id', $itsIds)
                ->orderBy('id')
                ->get() as $wajebaat
        ) {
            $key = (string) $wajebaat->its_id;
            if (! isset($wajebaatByIts[$key])) {
                $wajebaatByIts[$key] = $wajebaat;
            }
        }

        $sharafsByHof = Sharaf::query()
            ->forMiqaat($miqaatId)
            ->whereIn('sharafs.hof_its', $itsIds)
            ->with(['sharafDefinition.paymentDefinitions', 'sharafPayments'])
            ->get()
            ->groupBy(fn (Sharaf $sharaf) => (string) $sharaf->hof_its);

        $result = [];
        foreach ($itsIds as $itsId) {
            $person = $people->get($itsId);
            $payments = $this->paymentRowsFrom($sharafsByHof->get($itsId, collect()));
            $result[$itsId] = $this->assembleClearance(
                $itsId,
                $person,
                $wajebaatByIts[$itsId] ?? null,
                $person ? ($hofsToCheckByIts[$itsId] ?? [(string) ($person->hof_id ?? $itsId)]) : [],
                $pendingByHof,
                $payments
            );
        }

        return $result;
    }

    /**
     * Department-check clearance used by GET /wajebaat/{its}/clearance.
     *
     * @return array<string, mixed>
     */
    public function departmentStatus(int $miqaatId, string $itsId): array
    {
        $person = Census::where('its_id', $itsId)->first();
        if (! $person) {
            return $this->clearedWithoutCensus($miqaatId, $itsId);
        }

        $hofItsId = $person->hof_id ?? $itsId;
        $hofItsIdsToCheck = [$hofItsId];

        $hofGroup = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->where(function ($q) use ($hofItsId) {
                $q->where('master_its', $hofItsId)
                    ->orWhere('its_id', $hofItsId);
            })
            ->first();

        if ($hofGroup) {
            $groupMembers = WajebaatGroup::query()
                ->where('miqaat_id', $miqaatId)
                ->where('wg_id', $hofGroup->wg_id)
                ->get(['its_id']);

            $masterIts = $hofGroup->master_its;
            $groupMemberItsIds = $groupMembers->pluck('its_id')->toArray();
            if ($masterIts && ! in_array($masterIts, $groupMemberItsIds, true)) {
                $groupMemberItsIds[] = $masterIts;
            }

            if ($groupMemberItsIds !== []) {
                $groupHofItsIds = Census::whereIn('its_id', $groupMemberItsIds)
                    ->get(['its_id', 'hof_id'])
                    ->pluck('hof_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                $hofItsIdsToCheck = array_values(array_unique(array_merge($hofItsIdsToCheck, $groupHofItsIds)));
            }
        }

        $wajebaat = Wajebaat::query()
            ->forItsInMiqaat($itsId, $miqaatId)
            ->first();

        $allPending = [];
        $hofClearanceStatus = [];

        foreach ($hofItsIdsToCheck as $hofIts) {
            $pending = $this->pendingDepartmentChecks($miqaatId, (string) $hofIts);
            $hofClearanceStatus[] = [
                'hof_its_id' => $hofIts,
                'pending_departments' => $pending,
                'can_mark_paid' => $pending === [],
            ];

            foreach ($pending as $dept) {
                $exists = false;
                foreach ($allPending as $existing) {
                    if ($existing['mcd_id'] === $dept['mcd_id']) {
                        $exists = true;
                        break;
                    }
                }
                if (! $exists) {
                    $allPending[] = $dept;
                }
            }
        }

        return [
            'wajebaat' => $wajebaat,
            'hof_its_id' => $hofItsId,
            'hof_clearance_status' => $hofClearanceStatus,
            'pending_departments' => $allPending,
            'can_mark_paid' => $allPending === [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function paymentChecks(int $miqaatId, string $itsId): array
    {
        $sharafs = Sharaf::query()
            ->forMiqaat($miqaatId)
            ->where('sharafs.hof_its', $itsId)
            ->with(['sharafDefinition.paymentDefinitions', 'sharafPayments'])
            ->get();

        $payments = [];
        foreach ($sharafs as $sharaf) {
            $definitions = $sharaf->sharafDefinition?->paymentDefinitions ?? collect();
            $paidByDefinition = $sharaf->sharafPayments->keyBy('payment_definition_id');
            foreach ($definitions as $definition) {
                $payment = $paidByDefinition->get($definition->id);
                $payments[] = [
                    'payment_definition_id' => (int) $definition->id,
                    'name' => (string) $definition->name,
                    'user_type' => $definition->user_type,
                    'sharaf_id' => (int) $sharaf->id,
                    'sharaf_definition_id' => (int) $sharaf->sharaf_definition_id,
                    'sharaf_definition_name' => $sharaf->sharafDefinition?->name,
                    'is_paid' => $payment !== null && (bool) $payment->payment_status,
                ];
            }
        }

        return $payments;
    }

    /**
     * @return array<int, array{mcd_id: int, name: string, user_type: ?string}>
     */
    public function pendingDepartmentChecks(int $miqaatId, string $itsId): array
    {
        $departments = MiqaatCheckDepartment::query()
            ->where('miqaat_id', $miqaatId)
            ->orderBy('name')
            ->get(['mcd_id', 'name', 'user_type']);

        if ($departments->isEmpty()) {
            return [];
        }

        $checks = MiqaatCheck::query()
            ->where('its_id', $itsId)
            ->whereIn('mcd_id', $departments->pluck('mcd_id')->all())
            ->get()
            ->keyBy('mcd_id');

        $pending = [];
        foreach ($departments as $dept) {
            $check = $checks->get($dept->mcd_id);
            if (! $check || ! $check->is_cleared) {
                $pending[] = [
                    'mcd_id' => (int) $dept->mcd_id,
                    'name' => (string) $dept->name,
                    'user_type' => $dept->user_type?->value,
                ];
            }
        }

        return $pending;
    }

    /**
     * @return array<string, mixed>
     */
    protected function clearedWithoutCensus(int $miqaatId, string $itsId): array
    {
        $wajebaat = Wajebaat::query()
            ->forItsInMiqaat($itsId, $miqaatId)
            ->first();

        return [
            'wajebaat' => $wajebaat,
            'hof_its_id' => $itsId,
            'hof_clearance_status' => [
                [
                    'hof_its_id' => $itsId,
                    'pending_departments' => [],
                    'can_mark_paid' => true,
                ],
            ],
            'pending_departments' => [],
            'can_mark_paid' => true,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MiqaatCheckDepartment>  $departments
     * @param  \Illuminate\Support\Collection<int, MiqaatCheck>|null  $checks
     * @return array<int, array{mcd_id: int, name: string, user_type: ?string}>
     */
    protected function pendingDepartmentsFrom($departments, $checks): array
    {
        if ($departments->isEmpty()) {
            return [];
        }

        $byMcd = $checks ? $checks->keyBy('mcd_id') : collect();
        $pending = [];
        foreach ($departments as $dept) {
            $check = $byMcd->get($dept->mcd_id);
            if (! $check || ! $check->is_cleared) {
                $pending[] = [
                    'mcd_id' => (int) $dept->mcd_id,
                    'name' => (string) $dept->name,
                    'user_type' => $dept->user_type?->value,
                ];
            }
        }

        return $pending;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Sharaf>  $sharafs
     * @return array<int, array<string, mixed>>
     */
    protected function paymentRowsFrom($sharafs): array
    {
        $payments = [];
        foreach ($sharafs as $sharaf) {
            $definitions = $sharaf->sharafDefinition?->paymentDefinitions ?? collect();
            $paidByDefinition = $sharaf->sharafPayments->keyBy('payment_definition_id');
            foreach ($definitions as $definition) {
                $payment = $paidByDefinition->get($definition->id);
                $payments[] = [
                    'payment_definition_id' => (int) $definition->id,
                    'name' => (string) $definition->name,
                    'user_type' => $definition->user_type,
                    'sharaf_id' => (int) $sharaf->id,
                    'sharaf_definition_id' => (int) $sharaf->sharaf_definition_id,
                    'sharaf_definition_name' => $sharaf->sharafDefinition?->name,
                    'is_paid' => $payment !== null && (bool) $payment->payment_status,
                ];
            }
        }

        return $payments;
    }

    /**
     * @param  array<int, string>  $hofsToCheck
     * @param  array<string, array<int, array<string, mixed>>>  $pendingByHof
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<string, mixed>
     */
    protected function assembleClearance(
        string $itsId,
        ?Census $person,
        mixed $wajebaat,
        array $hofsToCheck,
        array $pendingByHof,
        array $payments
    ): array {
        if (! $person) {
            $pending = $pendingByHof[$itsId] ?? [];
            $data = [
                'wajebaat' => $wajebaat,
                'hof_its_id' => $itsId,
                'hof_clearance_status' => [
                    [
                        'hof_its_id' => $itsId,
                        'pending_departments' => $pending,
                        'can_mark_paid' => $pending === [],
                    ],
                ],
                'pending_departments' => $pending,
                'can_mark_paid' => $pending === [],
            ];
        } else {
            $allPending = [];
            $hofClearanceStatus = [];
            foreach ($hofsToCheck as $hofIts) {
                $pending = $pendingByHof[$hofIts] ?? [];
                $hofClearanceStatus[] = [
                    'hof_its_id' => $hofIts,
                    'pending_departments' => $pending,
                    'can_mark_paid' => $pending === [],
                ];
                foreach ($pending as $dept) {
                    $exists = false;
                    foreach ($allPending as $existing) {
                        if ($existing['mcd_id'] === $dept['mcd_id']) {
                            $exists = true;
                            break;
                        }
                    }
                    if (! $exists) {
                        $allPending[] = $dept;
                    }
                }
            }

            $data = [
                'wajebaat' => $wajebaat,
                'hof_its_id' => (string) ($person->hof_id ?? $itsId),
                'hof_clearance_status' => $hofClearanceStatus,
                'pending_departments' => $allPending,
                'can_mark_paid' => $allPending === [],
            ];
        }

        $pendingPayments = array_values(array_filter(
            $payments,
            static fn (array $payment): bool => $payment['is_paid'] !== true
        ));
        $data['its_id'] = $itsId;
        $data['payments'] = $payments;
        $data['pending_payments'] = $pendingPayments;
        $data['payments_cleared'] = $pendingPayments === [];
        $data['is_cleared'] = ($data['can_mark_paid'] ?? false) === true && $pendingPayments === [];

        return $data;
    }
}
