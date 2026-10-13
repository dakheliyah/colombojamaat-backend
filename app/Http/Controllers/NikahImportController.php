<?php

namespace App\Http\Controllers;

use App\Models\SharafDefinition;
use App\Services\NikahImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class NikahImportController extends Controller
{
    public function __construct(
        protected NikahImportService $imports
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $loaded = $this->uploadedFile($request);
        if ($loaded instanceof JsonResponse) {
            return $loaded;
        }

        [$definition, $path, $roleMap, $censusMap, $skipIts, $module] = $loaded;

        return $this->jsonSuccessWithData($this->imports->preview($definition, $path, $roleMap, $censusMap, $skipIts, $module));
    }

    public function applyCensus(Request $request): JsonResponse
    {
        $loaded = $this->uploadedFile($request);
        if ($loaded instanceof JsonResponse) {
            return $loaded;
        }

        [$definition, $path, $roleMap, $censusMap, $skipIts, $module] = $loaded;

        try {
            $result = $this->imports->applyCensus($definition, $path, $roleMap, $censusMap, $skipIts, $module);
        } catch (RuntimeException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        }

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => $result['message'],
                'data' => $result['preview'],
            ], 422);
        }

        return $this->jsonSuccessWithData($result['result']);
    }

    public function applySharafs(Request $request): JsonResponse
    {
        $loaded = $this->uploadedFile($request, true);
        if ($loaded instanceof JsonResponse) {
            return $loaded;
        }

        [$definition, $path, $confirmRemovals, $confirmHofChanges, $roleMap, $censusMap, $skipIts, $skipTokens, $module] = $loaded;

        try {
            $result = $this->imports->applySharafs(
                $definition,
                $path,
                $confirmRemovals,
                $confirmHofChanges,
                $roleMap,
                $censusMap,
                $skipIts,
                $skipTokens,
                $module,
            );
        } catch (RuntimeException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        }

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => $result['message'],
                'data' => $result['preview'],
            ], 422);
        }

        return $this->jsonSuccessWithData($result['result'], 201);
    }

    /**
     * @return array{0: SharafDefinition, 1: string, 2?: list<string>, 3?: list<string>}|JsonResponse
     */
    private function uploadedFile(Request $request, bool $withConfirms = false): array|JsonResponse
    {
        $rules = [
            'sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'file' => ['required', 'file', 'max:10240'],
        ];
        if ($withConfirms) {
            $rules['confirm_removals'] = ['nullable', 'array'];
            $rules['confirm_removals.*'] = ['string', 'max:50'];
            $rules['confirm_hof_changes'] = ['nullable', 'array'];
            $rules['confirm_hof_changes.*'] = ['string', 'max:50'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if ($extension !== 'xlsx') {
            return $this->jsonError('VALIDATION_ERROR', 'Upload an .xlsx file.', 422);
        }

        $path = (string) $file?->getRealPath();
        if ($path === '' || !is_readable($path)) {
            return $this->jsonError('VALIDATION_ERROR', 'The file could not be read.', 422);
        }

        $definition = SharafDefinition::query()
            ->with(['sharafPositions', 'event'])
            ->findOrFail((int) $request->input('sharaf_definition_id'));

        $roleMap = $this->roleMap($request);
        $censusMap = $this->censusMap($request);
        $skipIts = $this->stringList($request, 'skip_its');
        $module = strtolower(trim((string) $request->input('module', 'nikah')));
        if (!in_array($module, ['nikah', 'misaq'], true)) {
            return $this->jsonError('VALIDATION_ERROR', 'Choose Nikah or Misaq.', 422);
        }

        if (!$withConfirms) {
            return [$definition, $path, $roleMap, $censusMap, $skipIts, $module];
        }

        return [
            $definition,
            $path,
            array_values($request->input('confirm_removals', [])),
            array_values($request->input('confirm_hof_changes', [])),
            $roleMap,
            $censusMap,
            $skipIts,
            $this->stringList($request, 'skip_tokens'),
            $module,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function roleMap(Request $request): array
    {
        $raw = $request->input('role_map', []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $role => $positionId) {
            $role = trim((string) $role);
            if ($role === '' || $positionId === null || $positionId === '' || !is_numeric($positionId)) {
                continue;
            }
            $map[$role] = (int) $positionId;
        }

        return $map;
    }

    /**
     * Null means the sheet columns keep their default census mapping.
     *
     * @return array<string, string>|null
     */
    private function censusMap(Request $request): ?array
    {
        if (!$request->has('census_map')) {
            return null;
        }

        $raw = $request->input('census_map');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $column => $field) {
            $column = trim((string) $column);
            if ($column === '') {
                continue;
            }
            $map[$column] = trim((string) $field);
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function stringList(Request $request, string $key): array
    {
        $raw = $request->input($key, []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $list = [];
        foreach ($raw as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $list[$value] = $value;
            }
        }

        return array_values($list);
    }
}
