<?php

namespace App\Http\Controllers;

use App\Services\ItsClearanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ClearanceController extends Controller
{
    public function __construct(protected ItsClearanceService $clearance)
    {
    }

    /**
     * GET /api/miqaats/{miqaat_id}/clearance/{its_id}
     * HoF miqaat checks plus payment definitions for sharafs where this ITS is Head of Family.
     */
    public function show(string $miqaat_id, string $its_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
            'its_id' => $its_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'its_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        return $this->jsonSuccessWithData(
            $this->clearance->forIts((int) $miqaat_id, (string) $its_id)
        );
    }
}
