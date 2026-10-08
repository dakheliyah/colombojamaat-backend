<?php

namespace App\Http\Controllers;

use App\Models\Census;
use App\Services\CensusRelativesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CensusController extends Controller
{
    public function __construct(private CensusRelativesService $relatives)
    {
    }

    /**
     * Get a census record by ITS ID.
     */
    public function show(string $its_id): JsonResponse
    {
        $census = Census::where('its_id', $its_id)->first();

        if (!$census) {
            return $this->jsonError('NOT_FOUND', 'Census record not found.', 404);
        }

        return $this->jsonSuccessWithData($census);
    }

    /**
     * Person, spouse, father, and mother for sharaf auto-fill.
     */
    public function relatives(string $its_id): JsonResponse
    {
        $data = $this->relatives->forIts($its_id);
        if ($data === null) {
            return $this->jsonError('NOT_FOUND', 'Census record not found.', 404);
        }

        return $this->jsonSuccessWithData($data);
    }

    /**
     * Get family members for a Head of Family (HOF) by HOF ITS ID.
     */
    public function familyMembers(string $hof_its): JsonResponse
    {
        // First verify the HOF exists
        $hof = Census::where('its_id', $hof_its)->first();

        if (!$hof) {
            return $this->jsonError('NOT_FOUND', 'Head of Family not found.', 404);
        }

        // Get the HOF record and all family members
        // First get HOF, then get family members
        $hof = Census::where('its_id', $hof_its)->first();
        $members = Census::where('hof_id', $hof_its)
            ->where('its_id', '!=', $hof_its)
            ->orderBy('age', 'desc')
            ->get();
        
        // Combine HOF first, then members
        $family = collect([$hof])->merge($members)->filter();

        return $this->jsonSuccessWithData($family);
    }

    /**
     * Search/filter census records.
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'its_id' => ['nullable', 'string'],
            'hof_id' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'jamaat' => ['nullable', 'string', 'max:255'],
            'jamiat' => ['nullable', 'string', 'max:255'],
            'mohalla' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'misaq' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $query = Census::query();

        // Apply filters
        if ($request->has('name')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('name') . '%')
                  ->orWhere('arabic_name', 'like', '%' . $request->input('name') . '%');
            });
        }

        if ($request->has('its_id')) {
            $query->where('its_id', $request->input('its_id'));
        }

        if ($request->has('hof_id')) {
            $query->where('hof_id', $request->input('hof_id'));
        }

        foreach (['city', 'jamaat', 'jamiat', 'mohalla', 'area', 'gender', 'misaq', 'marital_status'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }

        if ($request->filled('q')) {
            $term = '%' . $request->input('q') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('arabic_name', 'like', $term)
                    ->orWhere('its_id', 'like', $term)
                    ->orWhere('hof_id', 'like', $term);
            });
        }

        return $this->paginatedCensus($query, $request);
    }

    /**
     * Distinct values for census list filters.
     */
    public function filters(): JsonResponse
    {
        $columns = ['city', 'jamaat', 'jamiat', 'mohalla', 'area', 'gender', 'misaq', 'marital_status'];
        $data = [];

        foreach ($columns as $column) {
            $data[$column] = Census::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->values();
        }

        return $this->jsonSuccessWithData($data);
    }

    /**
     * Get all census records with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        return $this->paginatedCensus(Census::query(), $request);
    }

    /**
     * Paginate a census query. Password is omitted from list payloads.
     */
    private function paginatedCensus($query, Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $results = $query
            ->orderByRaw("name IS NULL OR name = ''")
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);
        $results->getCollection()->each->makeHidden(['pwd']);

        return $this->jsonSuccessWithData([
            'data' => $results->items(),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }

    /**
     * Get detailed census record with family relationships.
     */
    public function showWithRelations(string $its_id): JsonResponse
    {
        $census = Census::where('its_id', $its_id)
            ->with(['hof', 'members'])
            ->first();

        if (!$census) {
            return $this->jsonError('NOT_FOUND', 'Census record not found.', 404);
        }

        return $this->jsonSuccessWithData($census);
    }
}
