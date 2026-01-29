<?php

namespace App\Services;

use App\Models\MiqaatCheck;
use App\Models\WajCategory;
use App\Models\Wajebaat;
use App\Models\WajebaatGroup;

class WajebaatService
{
    /**
     * Assign wc_id by matching (miqaat_id, currency, amount) against waj_categories slabs.
     *
     * Rules:
     * - miqaat_id matches
     * - currency matches (if category has currency set)
     * - low_bar <= amount
     * - and (upper_bar is NULL OR amount <= upper_bar)
     * - if multiple match, pick the one with the highest low_bar.
     */
    public function categorize(Wajebaat $wajebaat, bool $save = true): void
    {
        $category = $this->findCategoryForAmount(
            $wajebaat->miqaat_id,
            $wajebaat->currency,
            $wajebaat->amount
        );

        $wajebaat->wc_id = $category?->wc_id;

        if ($save) {
            $wajebaat->save();
        }
    }

    /**
     * Find the best matching category slab for a given miqaat, currency, and amount.
     * 
     * Currency matching:
     * - If category has currency set, it only matches wajebaats with the same currency
     * - If category has no currency (null), it matches any currency (backward compatibility)
     */
    public function findCategoryForAmount(int $miqaatId, ?string $currency, $amount): ?WajCategory
    {
        return WajCategory::query()
            ->where('miqaat_id', $miqaatId)
            ->where(function ($q) use ($currency) {
                // Match categories with the same currency, or categories without currency set
                if ($currency) {
                    $q->where('currency', $currency)
                        ->orWhereNull('currency');
                } else {
                    // If wajebaat has no currency, only match categories without currency
                    $q->whereNull('currency');
                }
            })
            ->where('low_bar', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('upper_bar')
                    ->orWhere('upper_bar', '>=', $amount);
            })
            ->orderByDesc('low_bar')
            ->orderBy('upper_bar')
            ->first();
    }

    /**
     * Get the group membership row for a member ITS (if any) in a given miqaat.
     */
    public function groupRowForMember(int $miqaatId, string $itsId): ?WajebaatGroup
    {
        return WajebaatGroup::query()
            ->forMember($miqaatId, $itsId)
            ->first();
    }

    /**
     * Return wg_id for a member ITS (if any) in a given miqaat.
     */
    public function groupIdForMember(int $miqaatId, string $itsId): ?int
    {
        return $this->groupRowForMember($miqaatId, $itsId)?->wg_id;
    }

    /**
     * Group is considered cleared only when every member is cleared.
     * Cleared semantics (manual override applies):
     * - paid (`wajebaat.status = 1`) OR
     * - manually cleared (`miqaat_checks.is_cleared = 1`)
     */
    public function isGroupCleared(int $miqaatId, int $wgId): bool
    {
        $memberIts = WajebaatGroup::query()
            ->forGroup($miqaatId, $wgId)
            ->pluck('its_id')
            ->values();

        if ($memberIts->isEmpty()) {
            return false;
        }

        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $memberIts)
            ->get()
            ->keyBy('its_id');

        foreach ($memberIts as $itsId) {
            /** @var Wajebaat|null $wajebaat */
            $wajebaat = $wajebaats->get($itsId);

            if (!$wajebaat || !$wajebaat->isCleared()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strict paid-only group evaluation (manual override ignored).
     */
    public function isGroupFullyPaid(int $miqaatId, int $wgId): bool
    {
        $memberIts = WajebaatGroup::query()
            ->forGroup($miqaatId, $wgId)
            ->pluck('its_id')
            ->values();

        if ($memberIts->isEmpty()) {
            return false;
        }

        $paidCount = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $memberIts)
            ->where('status', true)
            ->count();

        return $paidCount === $memberIts->count();
    }
}

