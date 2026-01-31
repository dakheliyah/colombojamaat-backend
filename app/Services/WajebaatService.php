<?php

namespace App\Services;

use App\Models\Census;
use App\Models\CurrencyConversion;
use App\Models\MiqaatCheck;
use App\Models\WajCategory;
use App\Models\Wajebaat;
use App\Models\WajebaatGroup;
use Illuminate\Support\Facades\Log;

class WajebaatService
{
    /**
     * Convert amount to INR using currency conversion table.
     * 
     * @param string $fromCurrency Source currency code (e.g., 'LKR', 'USD')
     * @param float $amount Amount to convert
     * @param \DateTime|null $date Date to use for conversion rate (default: today)
     * @return float Amount in INR
     */
    public function convertToInr(string $fromCurrency, float $amount, ?\DateTime $date = null): float
    {
        if ($fromCurrency === 'INR') {
            return $amount;
        }

        $rate = CurrencyConversion::getRate($fromCurrency, 'INR', $date);
        
        if (!$rate) {
            // Fallback: if no conversion rate found, return original amount
            // Log warning or throw exception based on your preference
            \Log::warning("No conversion rate found for {$fromCurrency} to INR on " . ($date ? $date->format('Y-m-d') : 'today'));
            return $amount;
        }

        return $amount * $rate;
    }

    /**
     * Assign wc_id by matching (miqaat_id, amount in INR) against waj_categories slabs.
     * All amounts are converted to INR before categorization.
     *
     * Rules:
     * - miqaat_id matches
     * - All amounts are converted to INR using currency_conversions table
     * - Categories must have currency = 'INR' (or null for backward compatibility)
     * - low_bar <= amount_in_inr
     * - and (upper_bar is NULL OR amount_in_inr <= upper_bar)
     * - if multiple match, pick the one with the highest low_bar.
     */
    public function categorize(Wajebaat $wajebaat, bool $save = true): void
    {
        // Convert amount to INR for categorization
        $date = $wajebaat->created_at ? new \DateTime($wajebaat->created_at) : null;
        $amountInInr = $this->convertToInr(
            $wajebaat->currency ?? 'LKR',
            $wajebaat->amount,
            $date
        );

        // Find category using INR amount and INR categories
        $category = $this->findCategoryForAmount(
            $wajebaat->miqaat_id,
            'INR', // Always use INR for categorization
            $amountInInr
        );

        $wajebaat->wc_id = $category?->wc_id;

        if ($save) {
            $wajebaat->save();
        }
    }

    /**
     * Find the best matching category slab for a given miqaat, currency, and amount.
     * 
     * Note: Amount should already be converted to INR before calling this method.
     * 
     * Currency matching:
     * - Categories with currency = 'INR' are used for categorization
     * - Categories with currency = null are also matched (backward compatibility)
     * - Categories with other currencies are ignored
     */
    public function findCategoryForAmount(int $miqaatId, ?string $currency, $amount): ?WajCategory
    {
        return WajCategory::query()
            ->where('miqaat_id', $miqaatId)
            ->where(function ($q) use ($currency) {
                // Match INR categories or categories without currency (backward compatibility)
                if ($currency === 'INR') {
                    $q->where('currency', 'INR')
                        ->orWhereNull('currency');
                } else {
                    // For non-INR currencies, only match categories without currency (backward compatibility)
                    // This should not happen in normal flow as amounts are converted to INR first
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

    /**
     * Categorize wajebaat records with aggregation logic:
     * - Isolated members: categorize individually
     * - Group masters: aggregate all group members' families' wajebaat amounts, categorize total, assign to master_its
     * - Normal HoF: aggregate all family members' wajebaat amounts, categorize total, assign to hof_its
     * 
     * @param int $miqaatId
     * @return array Statistics about categorization
     */
    public function categorizeWithAggregation(int $miqaatId): array
    {
        $stats = [
            'isolated_count' => 0,
            'group_masters_count' => 0,
            'hof_count' => 0,
            'errors' => [],
        ];

        // Get all wajebaat records for this miqaat
        $allWajebaats = Wajebaat::where('miqaat_id', $miqaatId)->get();

        // Track which HoF/master_its have been processed to avoid duplicates
        $processedHofs = [];
        $processedMasters = [];

        // Process isolated members first (categorize individually)
        foreach ($allWajebaats as $wajebaat) {
            if ($wajebaat->is_isolated) {
                try {
                    $this->categorize($wajebaat, true);
                    $stats['isolated_count']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'its_id' => $wajebaat->its_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        // Get all non-isolated wajebaat records
        $nonIsolatedWajebaats = $allWajebaats->where('is_isolated', false);

        // Group by HoF to process families
        $hofGroups = [];
        foreach ($nonIsolatedWajebaats as $wajebaat) {
            $person = Census::where('its_id', $wajebaat->its_id)->first();
            if (!$person) {
                continue;
            }

            $hofItsId = $person->hof_id ?? $wajebaat->its_id;
            
            if (!isset($hofGroups[$hofItsId])) {
                $hofGroups[$hofItsId] = [];
            }
            $hofGroups[$hofItsId][] = $wajebaat;
        }

        // Process each HoF
        foreach ($hofGroups as $hofItsId => $wajebaats) {
            // Check if this HoF is a group master
            $group = WajebaatGroup::query()
                ->where('miqaat_id', $miqaatId)
                ->where('master_its', $hofItsId)
                ->first();

            if ($group && !in_array($hofItsId, $processedMasters)) {
                // This is a group master - aggregate all group members' families
                $this->categorizeGroupMaster($miqaatId, $hofItsId, $group->wg_id);
                $processedMasters[] = $hofItsId;
                $stats['group_masters_count']++;
            } elseif (!in_array($hofItsId, $processedHofs)) {
                // Check if this HoF is a group member (not master)
                $isGroupMember = WajebaatGroup::query()
                    ->where('miqaat_id', $miqaatId)
                    ->where('its_id', $hofItsId)
                    ->exists();

                if (!$isGroupMember) {
                    // Normal HoF (not in any group) - aggregate family members
                    $this->categorizeHoF($miqaatId, $hofItsId);
                    $processedHofs[] = $hofItsId;
                    $stats['hof_count']++;
                }
                // If HoF is a group member, skip - they'll be handled by the group master
            }
        }

        return $stats;
    }

    /**
     * Categorize a group master by aggregating all group members' families' wajebaat amounts.
     * This method is public so it can be called from the controller.
     */
    public function categorizeGroupMaster(int $miqaatId, string $masterItsId, int $wgId): void
    {
        // Get all group members
        $groupMembers = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->where('wg_id', $wgId)
            ->get(['its_id']);

        $groupMemberItsIds = $groupMembers->pluck('its_id')->toArray();
        
        // Also include master_its if not already in members list
        if (!in_array($masterItsId, $groupMemberItsIds)) {
            $groupMemberItsIds[] = $masterItsId;
        }

        // Get all family members for all group members
        $allFamilyItsIds = [];
        foreach ($groupMemberItsIds as $memberItsId) {
            // Get all family members (where hof_id = member_its_id OR its_id = member_its_id)
            $familyMembers = Census::where('hof_id', $memberItsId)
                ->orWhere('its_id', $memberItsId)
                ->pluck('its_id')
                ->toArray();
            $allFamilyItsIds = array_merge($allFamilyItsIds, $familyMembers);
        }

        // Remove duplicates
        $allFamilyItsIds = array_unique($allFamilyItsIds);

        // Get all wajebaat records for these family members (excluding isolated)
        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $allFamilyItsIds)
            ->where('is_isolated', false)
            ->get();

        // Aggregate amounts (convert to INR)
        $totalAmountInInr = 0;
        foreach ($wajebaats as $wajebaat) {
            $date = $wajebaat->created_at ? new \DateTime($wajebaat->created_at) : null;
            $amountInInr = $this->convertToInr(
                $wajebaat->currency ?? 'LKR',
                $wajebaat->amount,
                $date
            );
            $totalAmountInInr += $amountInInr;
        }

        // Find category for total amount
        $category = $this->findCategoryForAmount($miqaatId, 'INR', $totalAmountInInr);
        $wcId = $category?->wc_id;

        // Only update the master_its wajebaat record with the category
        // All other group members and their family members' records remain NULL (unless is_isolated = true)
        Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->where('its_id', $masterItsId)
            ->where('is_isolated', false)
            ->update(['wc_id' => $wcId]);

        // Set all group members' wajebaat records to NULL (unless isolated)
        Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $groupMemberItsIds)
            ->where('its_id', '!=', $masterItsId)
            ->where('is_isolated', false)
            ->update(['wc_id' => null]);

        // Set all family members' wajebaat records to NULL (unless isolated)
        Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $allFamilyItsIds)
            ->where('its_id', '!=', $masterItsId)
            ->whereNotIn('its_id', $groupMemberItsIds) // Don't update group members again
            ->where('is_isolated', false)
            ->update(['wc_id' => null]);
    }

    /**
     * Categorize a normal HoF by aggregating all family members' wajebaat amounts.
     * This method is public so it can be called from the controller.
     */
    public function categorizeHoF(int $miqaatId, string $hofItsId): void
    {
        // Get all family members (where hof_id = hof_its_id OR its_id = hof_its_id)
        $familyMembers = Census::where('hof_id', $hofItsId)
            ->orWhere('its_id', $hofItsId)
            ->pluck('its_id')
            ->toArray();

        // Get all wajebaat records for these family members (excluding isolated)
        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $familyMembers)
            ->where('is_isolated', false)
            ->get();

        // Aggregate amounts (convert to INR)
        $totalAmountInInr = 0;
        foreach ($wajebaats as $wajebaat) {
            $date = $wajebaat->created_at ? new \DateTime($wajebaat->created_at) : null;
            $amountInInr = $this->convertToInr(
                $wajebaat->currency ?? 'LKR',
                $wajebaat->amount,
                $date
            );
            $totalAmountInInr += $amountInInr;
        }

        // Find category for total amount
        $category = $this->findCategoryForAmount($miqaatId, 'INR', $totalAmountInInr);
        $wcId = $category?->wc_id;

        // Only update the hof_its wajebaat record with the category
        // All other family members' records remain NULL (unless is_isolated = true)
        Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->where('its_id', $hofItsId)
            ->where('is_isolated', false)
            ->update(['wc_id' => $wcId]);

        // Set all other family members' wajebaat records to NULL (unless isolated)
        Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $familyMembers)
            ->where('its_id', '!=', $hofItsId)
            ->where('is_isolated', false)
            ->update(['wc_id' => null]);
    }
}

