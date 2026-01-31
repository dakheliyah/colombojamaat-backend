<?php

namespace App\Http\Controllers;

use App\Models\PaymentDefinition;
use App\Models\Sharaf;
use App\Models\SharafDefinition;
use App\Models\SharafPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafDefinitionController extends Controller
{
    public function index(string $event_id): JsonResponse
    {
        $definitions = SharafDefinition::where('event_id', $event_id)->get();

        return $this->jsonSuccessWithData($definitions);
    }

    public function sharafs(string $sd_id): JsonResponse
    {
        $sharafs = Sharaf::where('sharaf_definition_id', $sd_id)
            ->with(['sharafDefinition', 'sharafMembers', 'sharafClearances', 'sharafPayments.paymentDefinition'])
            ->get();

        return $this->jsonSuccessWithData($sharafs);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharafDefinition = SharafDefinition::create([
            'event_id' => $request->input('event_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return $this->jsonSuccessWithData($sharafDefinition, 201);
    }

    /**
     * Update an existing sharaf definition.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sharafDefinition = SharafDefinition::find($id);

        if (! $sharafDefinition) {
            return $this->jsonError('NOT_FOUND', 'Sharaf definition not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'event_id' => ['sometimes', 'integer', 'exists:events,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharafDefinition->update($request->only(['event_id', 'name', 'description']));

        return $this->jsonSuccessWithData($sharafDefinition->fresh('event'));
    }

    public function positions(string $id): JsonResponse
    {
        $sharafDefinition = SharafDefinition::find($id);

        if (!$sharafDefinition) {
            return $this->jsonError('NOT_FOUND', 'Sharaf definition not found.', 404);
        }

        $positions = SharafPosition::where('sharaf_definition_id', $id)
            ->orderBy('order')
            ->get();

        return $this->jsonSuccessWithData($positions);
    }

    public function paymentDefinitions(string $id): JsonResponse
    {
        $sharafDefinition = SharafDefinition::find($id);

        if (!$sharafDefinition) {
            return $this->jsonError('NOT_FOUND', 'Sharaf definition not found.', 404);
        }

        $paymentDefinitions = PaymentDefinition::where('sharaf_definition_id', $id)
            ->orderBy('name')
            ->get();

        return $this->jsonSuccessWithData($paymentDefinitions);
    }
}
