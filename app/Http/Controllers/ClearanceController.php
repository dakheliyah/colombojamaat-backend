<?php

namespace App\Http\Controllers;

use App\Services\ItsClearanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * POST /api/miqaats/{miqaat_id}/clearance
     * Body: { its_ids: string[] }
     * Returns the same clearance payload as the single-ITS route, keyed by ITS.
     */
    public function bulk(Request $request, string $miqaat_id): JsonResponse
    {
        $validator = Validator::make(
            array_merge($request->all(), ['miqaat_id' => $miqaat_id]),
            [
                'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
                'its_ids' => ['required', 'array', 'min:1', 'max:2000'],
                'its_ids.*' => ['required', 'string', 'max:32'],
            ]
        );

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

        $itsIds = array_values(array_unique(array_map(
            static fn ($id): string => (string) $id,
            $request->input('its_ids')
        )));

        $keyed = new \stdClass();
        foreach ($this->clearance->forMany((int) $miqaat_id, $itsIds) as $itsId => $clearance) {
            $keyed->{(string) $itsId} = $clearance;
        }

        return $this->jsonSuccessWithData($keyed);
    }
}
