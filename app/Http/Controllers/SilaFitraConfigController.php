<?php

namespace App\Http\Controllers;

use App\Models\Miqaat;
use App\Models\SilaFitraConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SilaFitraConfigController extends Controller
{
    /**
     * GET /api/miqaats/{miqaatId}/sila-fitra-config
     * Return config for the miqaat. 404 if no config.
     */
    public function show(int $miqaatId): JsonResponse
    {
        if (!Miqaat::where('id', $miqaatId)->exists()) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        $config = SilaFitraConfig::where('miqaat_id', $miqaatId)->first();

        if (!$config) {
            return $this->jsonError('NOT_FOUND', 'No Sila Fitra config for this miqaat.', 404);
        }

        return response()->json($this->configToArray($config));
    }

    /**
     * PUT /api/miqaats/{miqaatId}/sila-fitra-config
     * Create or update config. Admin only.
     */
    public function update(Request $request, int $miqaatId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonError('UNAUTHORIZED', 'Authentication required.', 401);
        }
        if (!$user->hasRole('Admin')) {
            return $this->jsonError('FORBIDDEN', 'Admin role required.', 403);
        }

        if (!Miqaat::where('id', $miqaatId)->exists()) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'misaqwala_rate' => ['required', 'numeric', 'min:0'],
            'non_misaq_hamal_mayat_rate' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $currency = $request->filled('currency') ? strtoupper($request->input('currency')) : 'LKR';

        $config = SilaFitraConfig::updateOrCreate(
            ['miqaat_id' => $miqaatId],
            [
                'misaqwala_rate' => $request->input('misaqwala_rate'),
                'non_misaq_hamal_mayat_rate' => $request->input('non_misaq_hamal_mayat_rate'),
                'currency' => $currency,
            ]
        );

        $status = $config->wasRecentlyCreated ? 201 : 200;

        return response()->json($this->configToArray($config), $status);
    }

    private function configToArray(SilaFitraConfig $config): array
    {
        return [
            'id' => $config->id,
            'miqaat_id' => $config->miqaat_id,
            'misaqwala_rate' => $config->misaqwala_rate,
            'non_misaq_hamal_mayat_rate' => $config->non_misaq_hamal_mayat_rate,
            'currency' => $config->currency,
            'created_at' => $config->created_at?->format('c'),
            'updated_at' => $config->updated_at?->format('c'),
        ];
    }
}
