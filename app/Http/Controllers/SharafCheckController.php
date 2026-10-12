<?php

namespace App\Http\Controllers;

use App\Models\SharafCheck;
use App\Services\SharafCheckService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SharafCheckController extends Controller
{
    public function __construct(private SharafCheckService $checkService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_id' => ['nullable', 'integer', 'exists:sharafs,id'],
            'sharaf_check_definition_id' => ['nullable', 'integer', 'exists:sharaf_check_definitions,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $query = SharafCheck::with(['checkDefinition', 'sharaf']);

        if ($request->filled('sharaf_id')) {
            $query->where('sharaf_id', $request->input('sharaf_id'));
        }
        if ($request->filled('sharaf_check_definition_id')) {
            $query->where('sharaf_check_definition_id', $request->input('sharaf_check_definition_id'));
        }

        return $this->jsonSuccessWithData($query->orderBy('id')->get());
    }

    /**
     * PATCH /api/sharafs/{sharaf_id}/checks/{check_definition_id}
     */
    public function toggle(Request $request, string $sharaf_id, string $check_definition_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'done' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        try {
            $check = $this->checkService->toggleByDefinitionId(
                (int) $sharaf_id,
                (int) $check_definition_id,
                $request->boolean('done'),
                $this->actorIts($request),
                $request->exists('notes') ? $request->input('notes') : null
            );
        } catch (ModelNotFoundException) {
            return $this->jsonError(
                'NOT_FOUND',
                'Sharaf not found, or check definition not found / not applicable for this sharaf.',
                404
            );
        }

        $check->load(['sharaf', 'checkDefinition']);

        return $this->jsonSuccessWithData($check);
    }

    /**
     * POST /api/sharafs/{sharaf_id}/checks/{check_definition_id}/refresh
     * Read done/not-done from the definition's status API and store it.
     */
    public function refresh(Request $request, string $sharaf_id, string $check_definition_id): JsonResponse
    {
        try {
            $check = $this->checkService->refreshFromApi(
                (int) $sharaf_id,
                (int) $check_definition_id,
                $this->actorIts($request)
            );
        } catch (ModelNotFoundException) {
            return $this->jsonError(
                'NOT_FOUND',
                'Sharaf not found, or check definition not found / not applicable for this sharaf.',
                404
            );
        } catch (RuntimeException $e) {
            return $this->jsonError('STATUS_API_ERROR', $e->getMessage(), 502);
        }

        $check->load(['sharaf', 'checkDefinition']);

        return $this->jsonSuccessWithData($check);
    }

    private function actorIts(Request $request): ?string
    {
        $user = $request->user();
        $its = $user?->its_no ?? null;
        if (is_string($its) && trim($its) !== '') {
            return trim($its);
        }

        $cookie = $request->cookie('user');
        if (is_string($cookie) && trim($cookie) !== '') {
            return trim($cookie);
        }

        return null;
    }
}
