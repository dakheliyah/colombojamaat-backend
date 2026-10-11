<?php

namespace App\Http\Controllers;

use App\Services\TanzeemOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TanzeemOverviewController extends Controller
{
    public function __construct(
        protected TanzeemOverviewService $tanzeemOverviewService
    ) {}

    /**
     * GET /api/miqaats/{miqaat_id}/tanzeem-overview
     * Sharaf and headcount totals per definition for Tanzeem BGI.
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

        $sharafTypeIds = $user->hasRole('Admin')
            ? null
            : $user->sharafTypes->pluck('id')->map(fn ($id) => (int) $id)->all();

        $overview = $this->tanzeemOverviewService->forMiqaat((int) $miqaat_id, $sharafTypeIds);

        if ($overview === null) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        return $this->jsonSuccessWithData($overview);
    }
}
