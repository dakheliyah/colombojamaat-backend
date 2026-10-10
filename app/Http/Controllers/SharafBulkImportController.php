<?php

namespace App\Http\Controllers;

use App\Models\SharafDefinition;
use App\Services\SharafBulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class SharafBulkImportController extends Controller
{
    public function __construct(
        protected SharafBulkImportService $imports
    ) {}

    public function columns(Request $request): JsonResponse
    {
        $definition = $this->definitionFromQuery($request);
        if ($definition instanceof JsonResponse) {
            return $definition;
        }

        return $this->jsonSuccessWithData($this->imports->columnGuide($definition));
    }

    public function template(Request $request): JsonResponse|Response
    {
        $definition = $this->definitionFromQuery($request);
        if ($definition instanceof JsonResponse) {
            return $definition;
        }

        $slug = Str::slug($definition->name);
        if ($slug === '') {
            $slug = 'sharaf';
        }

        return response($this->imports->templateCsv($definition), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$slug.'-sharaf-import.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function validateUpload(Request $request): JsonResponse
    {
        $loaded = $this->uploadedFile($request);
        if ($loaded instanceof JsonResponse) {
            return $loaded;
        }

        [$definition, $contents] = $loaded;

        return $this->jsonSuccessWithData($this->imports->validateContents($definition, $contents));
    }

    public function import(Request $request): JsonResponse
    {
        $loaded = $this->uploadedFile($request);
        if ($loaded instanceof JsonResponse) {
            return $loaded;
        }

        [$definition, $contents] = $loaded;

        try {
            $result = $this->imports->importContents($definition, $contents);
        } catch (RuntimeException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        }

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'The file has errors and was not imported.',
                'data' => $result['validation'],
            ], 422);
        }

        return $this->jsonSuccessWithData([
            'imported' => count($result['sharaf_ids']),
            'sharaf_ids' => $result['sharaf_ids'],
            'warning_count' => $result['validation']['warning_count'],
        ], 201);
    }

    private function definitionFromQuery(Request $request): SharafDefinition|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        return $this->loadDefinition((int) $request->input('sharaf_definition_id'));
    }

    /**
     * @return array{0: SharafDefinition, 1: string}|JsonResponse
     */
    private function uploadedFile(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'csv_file' => ['required', 'file', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $file = $request->file('csv_file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt'], true)) {
            return $this->jsonError('VALIDATION_ERROR', 'Upload a .csv file.', 422);
        }

        $contents = file_get_contents((string) $file?->getRealPath());
        if ($contents === false) {
            return $this->jsonError('VALIDATION_ERROR', 'The file could not be read.', 422);
        }

        return [
            $this->loadDefinition((int) $request->input('sharaf_definition_id')),
            $contents,
        ];
    }

    private function loadDefinition(int $id): SharafDefinition
    {
        return SharafDefinition::query()
            ->with(['sharafPositions', 'paymentDefinitions', 'event'])
            ->findOrFail($id);
    }
}
