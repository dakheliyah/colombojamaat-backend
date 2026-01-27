<?php

namespace App\Http\Controllers;

use App\Services\FamilySummaryService;
use Illuminate\Http\JsonResponse;

class FamilySummaryController extends Controller
{
    public function __construct(
        protected FamilySummaryService $familySummaryService
    ) {}

    public function show(string $hof_its): JsonResponse
    {
        $summary = $this->familySummaryService->getSummary($hof_its);

        if ($summary === null) {
            return $this->jsonError('NOT_FOUND', 'HOF not found in census.', 404);
        }

        return $this->jsonSuccessWithData($summary);
    }
}
