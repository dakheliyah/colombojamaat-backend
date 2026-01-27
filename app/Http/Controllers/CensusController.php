<?php

namespace App\Http\Controllers;

use App\Models\Census;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CensusController extends Controller
{
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
            'gender' => ['nullable', 'string', 'in:male,female'],
            'misaq' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'string', 'max:255'],
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

        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->input('city') . '%');
        }

        if ($request->has('jamaat')) {
            $query->where('jamaat', 'like', '%' . $request->input('jamaat') . '%');
        }

        if ($request->has('jamiat')) {
            $query->where('jamiat', 'like', '%' . $request->input('jamiat') . '%');
        }

        if ($request->has('mohalla')) {
            $query->where('mohalla', 'like', '%' . $request->input('mohalla') . '%');
        }

        if ($request->has('area')) {
            $query->where('area', 'like', '%' . $request->input('area') . '%');
        }

        if ($request->has('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        if ($request->has('misaq')) {
            $query->where('misaq', $request->input('misaq'));
        }

        if ($request->has('marital_status')) {
            $query->where('marital_status', $request->input('marital_status'));
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $results = $query->orderBy('name')->paginate($perPage, ['*'], 'page', $page);

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

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $results = Census::orderBy('name')->paginate($perPage, ['*'], 'page', $page);

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
