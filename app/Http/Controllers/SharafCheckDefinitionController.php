<?php

namespace App\Http\Controllers;

use App\Models\SharafCheck;
use App\Models\SharafCheckDefinition;
use App\Models\SharafCheckDefinitionMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafCheckDefinitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['nullable', 'integer', 'exists:sharaf_definitions,id'],
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

        $query = SharafCheckDefinition::with('sharafDefinition');

        if ($request->has('sharaf_definition_id')) {
            $query->where('sharaf_definition_id', $request->input('sharaf_definition_id'));
        }

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $results = $query->orderBy('sharaf_definition_id')->orderBy('name')->paginate($perPage, ['*'], 'page', $page);

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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_type' => ['nullable', 'string', 'max:255'],
            'status_api_url' => ['nullable', 'string', 'max:500'],
            'webhook_url' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $exists = SharafCheckDefinition::where('sharaf_definition_id', $request->input('sharaf_definition_id'))
            ->where('name', $request->input('name'))
            ->exists();

        if ($exists) {
            return $this->jsonError(
                'DUPLICATE_ERROR',
                'A check definition with this name already exists for this sharaf definition.',
                422
            );
        }

        $statusApiUrl = $this->optionalHttpUrl($request->input('status_api_url'));
        $webhookUrl = $this->optionalHttpUrl($request->input('webhook_url'));
        if ($statusApiUrl === false || $webhookUrl === false) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'Status API and webhook URLs must be http or https. Placeholders: {its_id}, {hof_its}, {sharaf_id}, {check_definition_id}, {check_name}.',
                422
            );
        }

        $definition = SharafCheckDefinition::create([
            'sharaf_definition_id' => $request->input('sharaf_definition_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'user_type' => $request->input('user_type', 'Finance'),
            'status_api_url' => $statusApiUrl,
            'webhook_url' => $webhookUrl,
        ]);

        return $this->jsonSuccessWithData($definition, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $definition = SharafCheckDefinition::find($id);

        if (! $definition) {
            return $this->jsonError('NOT_FOUND', 'Check definition not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['sometimes', 'integer', 'exists:sharaf_definitions,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_type' => ['sometimes', 'string', 'max:255'],
            'status_api_url' => ['nullable', 'string', 'max:500'],
            'webhook_url' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharafDefinitionId = $request->input('sharaf_definition_id', $definition->sharaf_definition_id);
        $name = $request->input('name', $definition->name);

        if ($request->has('name') || $request->has('sharaf_definition_id')) {
            $exists = SharafCheckDefinition::where('sharaf_definition_id', $sharafDefinitionId)
                ->where('name', $name)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return $this->jsonError(
                    'DUPLICATE_ERROR',
                    'A check definition with this name already exists for this sharaf definition.',
                    422
                );
            }
        }

        $payload = $request->only(['sharaf_definition_id', 'name', 'description', 'user_type']);
        if ($request->exists('status_api_url')) {
            $statusApiUrl = $this->optionalHttpUrl($request->input('status_api_url'));
            if ($statusApiUrl === false) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'Status API URL must be http or https. Placeholders: {its_id}, {hof_its}, {sharaf_id}, {check_definition_id}, {check_name}.',
                    422
                );
            }
            $payload['status_api_url'] = $statusApiUrl;
        }
        if ($request->exists('webhook_url')) {
            $webhookUrl = $this->optionalHttpUrl($request->input('webhook_url'));
            if ($webhookUrl === false) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'Webhook URL must be http or https. Placeholders: {its_id}, {hof_its}, {sharaf_id}, {check_definition_id}, {check_name}.',
                    422
                );
            }
            $payload['webhook_url'] = $webhookUrl;
        }
        $definition->update($payload);

        return $this->jsonSuccessWithData($definition->fresh('sharafDefinition'));
    }

    /**
     * Preview rows that will cascade-delete with this check definition.
     */
    public function deletionImpact(int $id): JsonResponse
    {
        $definition = SharafCheckDefinition::find($id);

        if (! $definition) {
            return $this->jsonError('NOT_FOUND', 'Check definition not found.', 404);
        }

        $checks = SharafCheck::query()
            ->with(['sharaf'])
            ->where('sharaf_check_definition_id', $definition->id)
            ->orderBy('id')
            ->get()
            ->map(fn (SharafCheck $check) => [
                'id' => $check->id,
                'sharaf_id' => $check->sharaf_id,
                'hof_its' => $check->sharaf?->hof_its,
                'hof_name' => $check->sharaf?->hof_name,
                'sharaf_name' => $check->sharaf?->name,
                'is_done' => (bool) $check->is_done,
                'checked_by_its' => $check->checked_by_its,
                'checked_at' => $check->checked_at,
            ])
            ->values();

        $mappings = SharafCheckDefinitionMapping::query()
            ->with(['sourceCheckDefinition', 'targetCheckDefinition'])
            ->where(function ($query) use ($definition) {
                $query->where('source_sharaf_check_definition_id', $definition->id)
                    ->orWhere('target_sharaf_check_definition_id', $definition->id);
            })
            ->orderBy('id')
            ->get()
            ->map(function (SharafCheckDefinitionMapping $mapping) use ($definition) {
                $isSource = (int) $mapping->source_sharaf_check_definition_id === (int) $definition->id;
                $other = $isSource ? $mapping->targetCheckDefinition : $mapping->sourceCheckDefinition;

                return [
                    'id' => $mapping->id,
                    'sharaf_definition_mapping_id' => $mapping->sharaf_definition_mapping_id,
                    'role' => $isSource ? 'source' : 'target',
                    'other_check_definition_id' => $other?->id,
                    'other_check_definition_name' => $other?->name,
                ];
            })
            ->values();

        return $this->jsonSuccessWithData([
            'checks' => $checks,
            'checks_count' => $checks->count(),
            'mappings' => $mappings,
            'mappings_count' => $mappings->count(),
        ]);
    }

    /**
     * Delete a check definition. Recorded checks and definition mappings cascade.
     */
    public function destroy(int $id): JsonResponse
    {
        $definition = SharafCheckDefinition::find($id);

        if (! $definition) {
            return $this->jsonError('NOT_FOUND', 'Check definition not found.', 404);
        }

        $definition->delete();

        return $this->jsonSuccess(200);
    }

    /**
     * @return string|null|false null when blank, false when the value is not an http(s) URL
     */
    private function optionalHttpUrl(mixed $value): string|null|false
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return false;
        }
        $url = trim($value);
        if ($url === '') {
            return null;
        }

        $probe = strtr($url, [
            '{its_id}' => '1',
            '{hof_its}' => '1',
            '{sharaf_id}' => '1',
            '{check_definition_id}' => '1',
            '{check_name}' => 'check',
        ]);

        if (! filter_var($probe, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $probe)) {
            return false;
        }

        return $url;
    }
}
