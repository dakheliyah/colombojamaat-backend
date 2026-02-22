<?php

namespace App\Services;

use App\Models\Census;
use App\Models\User;

class SilaFitraHouseholdService
{
    /**
     * Get the HOF ITS for the user's household.
     * If the user is in census, returns their household's hof_id; otherwise treats user as HOF (its_no).
     */
    public function getHofItsForUser(User $user): string
    {
        $hofId = Census::where('its_id', $user->its_no)->value('hof_id');

        return $hofId ?? $user->its_no;
    }

    /**
     * Check whether the user is allowed to act for the given household (hof_its).
     * True if user is the HOF (its_no === hof_its) or a family member (census hof_id === hof_its).
     */
    public function userCanActForHousehold(User $user, string $hofIts): bool
    {
        if ($user->its_no === $hofIts) {
            return true;
        }

        $userHofId = Census::where('its_id', $user->its_no)->value('hof_id');

        return $userHofId === $hofIts;
    }
}
