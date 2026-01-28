<?php

namespace App\Services;

use App\Enums\SharafStatus;
use App\Models\Sharaf;

class SharafConfirmationEvaluator
{
    /**
     * Evaluate and update the confirmation status of a sharaf.
     * A sharaf becomes confirmed only when ALL are true:
     * - Clearance for that sharaf's HOF is complete
     * - All required payments for the sharaf definition are paid
     *
     * @param int $sharafId
     * @return void
     */
    public function evaluateAndUpdate(int $sharafId): void
    {
        $sharaf = Sharaf::findOrFail($sharafId);
        
        if ($sharaf->canBeConfirmed()) {
            $sharaf->status = SharafStatus::CONFIRMED;
            $sharaf->save();
        }
    }
}
