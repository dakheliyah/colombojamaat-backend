<?php

namespace App\Http\Controllers;

use App\Models\Miqaat;
use App\Models\SilaFitraConfig;
use App\Models\SilaFitraCalculation;
use App\Services\SilaFitraHouseholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SilaFitraController extends Controller
{
    public function __construct(
        protected SilaFitraHouseholdService $householdService
    ) {}

    /**
     * GET /api/miqaats/{miqaatId}/sila-fitra/me
     * Return current user's household calculation. 401 if not authenticated.
     */
    public function me(Request $request, int $miqaatId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonError('UNAUTHORIZED', 'Authentication required.', 401);
        }

        if (!Miqaat::where('id', $miqaatId)->exists()) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        $hofIts = $this->householdService->getHofItsForUser($user);
        $calculation = SilaFitraCalculation::where('miqaat_id', $miqaatId)
            ->where('hof_its', $hofIts)
            ->first();

        return response()->json($calculation ? $this->calculationToArray($calculation) : null);
    }

    /**
     * POST /api/miqaats/{miqaatId}/sila-fitra/save
     * Upsert calculation for household. User must be allowed to act for hof_its.
     */
    public function save(Request $request, int $miqaatId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonError('UNAUTHORIZED', 'Authentication required.', 401);
        }

        if (!Miqaat::where('id', $miqaatId)->exists()) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'hof_its' => ['required', 'string', 'max:255'],
            'misaqwala_count' => ['required', 'integer', 'min:0'],
            'non_misaq_count' => ['required', 'integer', 'min:0'],
            'hamal_count' => ['required', 'integer', 'min:0'],
            'mayat_count' => ['required', 'integer', 'min:0'],
            'haj_e_badal' => ['nullable', 'integer', 'min:0'],
            'calculated_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $hofIts = $request->input('hof_its');
        if (!$this->householdService->userCanActForHousehold($user, $hofIts)) {
            return $this->jsonError('FORBIDDEN', 'You are not allowed to save for this household.', 403);
        }

        $config = SilaFitraConfig::where('miqaat_id', $miqaatId)->first();
        if ($config) {
            $baseExpected = (float) $config->misaqwala_rate * (int) $request->input('misaqwala_count')
                + (float) $config->non_misaq_hamal_mayat_rate
                * ((int) $request->input('non_misaq_count') + (int) $request->input('hamal_count') + (int) $request->input('mayat_count'));
            $hajEBadalAmount = $request->has('haj_e_badal') && $request->input('haj_e_badal') !== null
                ? (float) $request->input('haj_e_badal')
                : 0.0;
            $expected = $baseExpected + $hajEBadalAmount;
            $actual = (float) $request->input('calculated_amount');
            if (abs($expected - $actual) > 0.01) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'Calculated amount does not match the formula for this miqaat.',
                    422
                );
            }
        }

        $currency = $request->filled('currency') ? strtoupper($request->input('currency')) : 'LKR';

        $calculation = SilaFitraCalculation::updateOrCreate(
            [
                'miqaat_id' => $miqaatId,
                'hof_its' => $hofIts,
            ],
            [
                'misaqwala_count' => $request->input('misaqwala_count'),
                'non_misaq_count' => $request->input('non_misaq_count'),
                'hamal_count' => $request->input('hamal_count'),
                'mayat_count' => $request->input('mayat_count'),
                'haj_e_badal' => $request->has('haj_e_badal') ? $request->input('haj_e_badal') : null,
                'calculated_amount' => $request->input('calculated_amount'),
                'currency' => $currency,
            ]
        );

        $status = $calculation->wasRecentlyCreated ? 201 : 200;

        return response()->json($this->calculationToArray($calculation), $status);
    }

    /**
     * POST /api/miqaats/{miqaatId}/sila-fitra/receipt
     * Upload receipt image for current user's household calculation.
     */
    public function uploadReceipt(Request $request, int $miqaatId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonError('UNAUTHORIZED', 'Authentication required.', 401);
        }

        if (!Miqaat::where('id', $miqaatId)->exists()) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        $hofIts = $this->householdService->getHofItsForUser($user);
        $calculation = SilaFitraCalculation::where('miqaat_id', $miqaatId)
            ->where('hof_its', $hofIts)
            ->first();

        if (!$calculation) {
            return $this->jsonError('VALIDATION_ERROR', 'Save calculation first before uploading a receipt.', 400);
        }

        $file = $request->file('receipt') ?? $request->file('receipt_image');
        if (!$file || !$file->isValid()) {
            return $this->jsonError('VALIDATION_ERROR', 'Valid receipt image file is required.', 400);
        }

        $validator = Validator::make(
            ['receipt' => $file],
            ['receipt' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120']]
        );
        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid file (image, max 5MB).',
                400
            );
        }

        $dir = "sila-fitra/{$miqaatId}/{$hofIts}";
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid() . '.' . $ext;
        $path = $file->storeAs($dir, $filename, 'local');

        $calculation->update(['receipt_path' => $path]);

        return response()->json([
            'receipt_path' => $path,
            'calculation_id' => $calculation->id,
        ]);
    }

    /**
     * GET /api/miqaats/{miqaatId}/sila-fitra/submissions
     * List calculations for Finance. Optional verified=0|1.
     */
    public function submissions(Request $request, int $miqaatId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonError('UNAUTHORIZED', 'Authentication required.', 401);
        }
        if (!$user->hasRole('Finance')) {
            return $this->jsonError('FORBIDDEN', 'Finance role required.', 403);
        }

        if (!Miqaat::where('id', $miqaatId)->exists()) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        $query = SilaFitraCalculation::where('miqaat_id', $miqaatId)->orderBy('id');
        if ($request->has('verified')) {
            $v = $request->input('verified');
            if (in_array($v, ['0', '1'], true)) {
                $query->where('payment_verified', (bool) (int) $v);
            }
        }

        $data = $query->get()->map(fn (SilaFitraCalculation $c) => $this->calculationToArray($c))->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * PATCH /api/miqaats/{miqaatId}/sila-fitra/{calculationId}/verify
     * Mark payment as verified. Finance only.
     */
    public function verify(Request $request, int $miqaatId, int $calculationId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonError('UNAUTHORIZED', 'Authentication required.', 401);
        }
        if (!$user->hasRole('Finance')) {
            return $this->jsonError('FORBIDDEN', 'Finance role required.', 403);
        }

        $calculation = SilaFitraCalculation::where('id', $calculationId)
            ->where('miqaat_id', $miqaatId)
            ->first();

        if (!$calculation) {
            return $this->jsonError('NOT_FOUND', 'Calculation not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'verified' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $verified = $request->boolean('verified');
        $calculation->update([
            'payment_verified' => $verified,
            'verified_by_its' => $verified ? $user->its_no : null,
            'verified_at' => $verified ? now() : null,
        ]);

        return response()->json($this->calculationToArray($calculation->fresh()));
    }

    /**
     * GET /api/miqaats/{miqaatId}/sila-fitra/receipt/{calculationId}
     * Serve receipt image. Finance or household member.
     */
    public function serveReceipt(Request $request, int $miqaatId, int $calculationId): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->jsonError('UNAUTHORIZED', 'Authentication required.', 401);
        }

        $calculation = SilaFitraCalculation::where('id', $calculationId)
            ->where('miqaat_id', $miqaatId)
            ->first();

        if (!$calculation) {
            return $this->jsonError('NOT_FOUND', 'Calculation not found.', 404);
        }
        if (!$calculation->receipt_path) {
            return $this->jsonError('NOT_FOUND', 'No receipt uploaded for this calculation.', 404);
        }

        $allowed = $user->hasRole('Finance')
            || $this->householdService->userCanActForHousehold($user, $calculation->hof_its);
        if (!$allowed) {
            return $this->jsonError('FORBIDDEN', 'You may not view this receipt.', 403);
        }

        $fullPath = Storage::disk('local')->path($calculation->receipt_path);
        if (!is_file($fullPath)) {
            return $this->jsonError('NOT_FOUND', 'Receipt file not found.', 404);
        }

        $mime = match (strtolower(pathinfo($calculation->receipt_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return response()->streamDownload(
            function () use ($fullPath) {
                $stream = fopen($fullPath, 'r');
                if ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            basename($calculation->receipt_path),
            ['Content-Type' => $mime],
            'inline'
        );
    }

    private function calculationToArray(SilaFitraCalculation $c): array
    {
        return [
            'id' => $c->id,
            'miqaat_id' => $c->miqaat_id,
            'hof_its' => $c->hof_its,
            'misaqwala_count' => $c->misaqwala_count,
            'non_misaq_count' => $c->non_misaq_count,
            'hamal_count' => $c->hamal_count,
            'mayat_count' => $c->mayat_count,
            'haj_e_badal' => $c->haj_e_badal,
            'calculated_amount' => $c->calculated_amount,
            'currency' => $c->currency,
            'receipt_path' => $c->receipt_path,
            'payment_verified' => (bool) $c->payment_verified,
            'verified_by_its' => $c->verified_by_its,
            'verified_at' => $c->verified_at?->format('c'),
            'created_at' => $c->created_at?->format('c'),
            'updated_at' => $c->updated_at?->format('c'),
        ];
    }
}
