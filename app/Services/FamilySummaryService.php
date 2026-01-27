<?php

namespace App\Services;

use App\Models\Census;

class FamilySummaryService
{
    /**
     * Get family summary for a Head of Family (HOF).
     *
     * @param string $hof_its
     * @return array|null Returns null if HOF not found
     */
    public function getSummary(string $hof_its): ?array
    {
        // Verify HOF exists
        $hof = Census::where('its_id', $hof_its)->first();
        
        if (!$hof) {
            return null;
        }

        // Get all family members (including HOF)
        $familyMembers = Census::where('hof_id', $hof_its)->get();

        // Calculate statistics
        $totalMembers = $familyMembers->count();
        $males = $familyMembers->where('gender', 'male')->count();
        $females = $familyMembers->where('gender', 'female')->count();
        $withMisaq = $familyMembers->where('misaq', 'yes')->count();
        $withoutMisaq = $totalMembers - $withMisaq;
        
        // Age statistics
        $ages = $familyMembers->pluck('age')->filter();
        $averageAge = $ages->isNotEmpty() ? round($ages->avg(), 2) : null;
        $minAge = $ages->isNotEmpty() ? $ages->min() : null;
        $maxAge = $ages->isNotEmpty() ? $ages->max() : null;

        // Marital status breakdown
        $married = $familyMembers->where('marital_status', 'married')->count();
        $single = $familyMembers->where('marital_status', 'single')->count();
        $otherMaritalStatus = $totalMembers - $married - $single;

        return [
            'hof' => [
                'its_id' => $hof->its_id,
                'name' => $hof->name,
                'arabic_name' => $hof->arabic_name,
                'age' => $hof->age,
                'gender' => $hof->gender,
                'misaq' => $hof->misaq,
                'marital_status' => $hof->marital_status,
                'mobile' => $hof->mobile,
                'email' => $hof->email,
                'address' => $hof->address,
                'city' => $hof->city,
                'jamaat' => $hof->jamaat,
                'jamiat' => $hof->jamiat,
            ],
            'statistics' => [
                'total_members' => $totalMembers,
                'males' => $males,
                'females' => $females,
                'with_misaq' => $withMisaq,
                'without_misaq' => $withoutMisaq,
                'married' => $married,
                'single' => $single,
                'other_marital_status' => $otherMaritalStatus,
                'average_age' => $averageAge,
                'min_age' => $minAge,
                'max_age' => $maxAge,
            ],
            'family_members' => $familyMembers->map(function ($member) {
                return [
                    'its_id' => $member->its_id,
                    'name' => $member->name,
                    'arabic_name' => $member->arabic_name,
                    'age' => $member->age,
                    'gender' => $member->gender,
                    'misaq' => $member->misaq,
                    'marital_status' => $member->marital_status,
                    'relationship' => $member->its_id === $member->hof_id ? 'hof' : 'member',
                ];
            })->values()->toArray(),
        ];
    }
}
