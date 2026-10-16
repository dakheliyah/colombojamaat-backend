<?php

namespace App\Http\Controllers;

use App\Services\FmbOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FmbOverviewController extends Controller
{
    public function __construct(
        protected FmbOverviewService $fmbOverviewService
    ) {}

    /**
     * GET /api/miqaats/{miqaat_id}/fmb-overview
     * Sharaf-definition counts and local/foreign food headcount.
     */
    public function show(Request $request, string $miqaat_id): JsonResponse
    {
        $validator = Validator::make(
            ['miqaat_id' => $miqaat_id],
            ['miqaat_id' => ['required', 'integer']]
        );

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid miqaat ID.',
                422
            );
        }

        $user = $request->user();
        if ($user === null) {
            return $this->jsonError('UNAUTHORIZED', 'No valid session.', 401);
        }

        if (! $user->hasRole('Admin') && ! $user->hasRole('FMB')) {
            return $this->jsonError('FORBIDDEN', 'FMB or Admin role required.', 403);
        }

        $overview = $this->fmbOverviewService->forMiqaat((int) $miqaat_id);

        if ($overview === null) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        return $this->jsonSuccessWithData($overview);
    }
}
