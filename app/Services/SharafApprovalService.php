<?php

namespace App\Services;

use App\Enums\SharafStatus;
use App\Models\Sharaf;

class SharafApprovalService
{
    /**
     * Change the status of a sharaf.
     *
     * @param int $sharafId
     * @param SharafStatus $status
     * @return void
     */
    public function changeStatus(int $sharafId, SharafStatus $status): void
    {
        $sharaf = Sharaf::findOrFail($sharafId);
        $sharaf->status = $status;
        $sharaf->save();
    }
}
