<?php

namespace App\Http\Controllers;

use App\Models\Sharaf;
use App\Services\SharafClearanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafClearanceController extends Controller
{
    public function __construct(
        protected SharafClearanceService $clearanceService
    ) {}

    public function store(Request $request, string $sharaf_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_cleared' => ['required', 'boolean'],
            'cleared_by_its' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharaf = Sharaf::findOrFail($sharaf_id);
        $hofIts = $sharaf->hof_its;
        $isCleared = (bool) $request->input('is_cleared');
        $clearedByIts = $request->filled('cleared_by_its') ? (string) $request->input('cleared_by_its') : null;

        $this->clearanceService->toggleClearance(
            (int) $sharaf_id,
            $hofIts,
            $isCleared,
            $clearedByIts
        );

        return $this->jsonSuccess();
    }
}
