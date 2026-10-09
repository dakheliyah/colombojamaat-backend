<?php

namespace App\Http\Controllers;

use App\Models\Sharaf;
use App\Models\SharafPayment;
use App\Services\SharafPaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharafPaymentController extends Controller
{
    protected $paymentService;

    public function __construct(
        SharafPaymentService $paymentService
    ) {
        $this->paymentService = $paymentService;
    }

    public function lagat(Request $request, string $sharaf_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'paid' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $this->paymentService->toggleLagat((int) $sharaf_id, (bool) $request->input('paid'));

        return $this->jsonSuccess();
    }

    public function najwa(Request $request, string $sharaf_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'paid' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $this->paymentService->toggleNajwaAda((int) $sharaf_id, (bool) $request->input('paid'));

        return $this->jsonSuccess();
    }

    /**
     * Toggle payment status for a sharaf by payment_definition_id.
     * Generic endpoint supporting all payment types (Hadiyat, Misaaq, Nikah, Ziyafat, Najwa Raqam, etc.).
     */
    public function toggle(Request $request, string $sharaf_id, string $payment_definition_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'paid' => ['required', 'boolean'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_currency' => ['nullable', 'string', 'max:3'],
            'payment_method' => ['nullable', 'in:cash,transfer'],
            'payment_city' => ['nullable', 'string', 'max:120'],
            'receipt' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,pdf', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $paid = $request->boolean('paid');
        $paymentMethod = $request->input('payment_method');
        $paymentMethod = is_string($paymentMethod) && $paymentMethod !== '' ? $paymentMethod : null;
        $paymentCity = trim((string) $request->input('payment_city', ''));
        $recordsCollection = $request->exists('paid_amount')
            || $request->exists('payment_method')
            || $request->exists('payment_city');

        if ($paid && $recordsCollection) {
            if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
                return $this->jsonError('VALIDATION_ERROR', 'Payment method is required.', 422);
            }
            if ($paymentCity === '') {
                return $this->jsonError('VALIDATION_ERROR', 'Payment city is required.', 422);
            }
        }
        $receiptPath = null;
        $updateReceipt = !$paid || $paymentMethod === 'cash';

        if ($paid && $paymentMethod === 'transfer' && $request->hasFile('receipt')) {
            $file = $request->file('receipt');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filename = Str::uuid().'.'.$ext;
            $receiptPath = $file->storeAs(
                "sharaf-payments/{$sharaf_id}/{$payment_definition_id}",
                $filename,
                'local'
            );
            $updateReceipt = true;
        }

        try {
            $sharafPayment = $this->paymentService->togglePaymentByDefinitionId(
                (int) $sharaf_id,
                (int) $payment_definition_id,
                $paid,
                $request->input('paid_amount'),
                $request->input('paid_currency'),
                $paid ? $paymentMethod : null,
                $paid && $paymentCity !== '' ? $paymentCity : null,
                $receiptPath,
                $updateReceipt
            );
        } catch (ModelNotFoundException $e) {
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }

            return $this->jsonError(
                'NOT_FOUND',
                'Sharaf not found, or payment definition not found / not applicable for this sharaf.',
                404
            );
        }

        $sharafPayment->load(['sharaf', 'paymentDefinition']);

        return $this->jsonSuccessWithData($sharafPayment, 200);
    }

    /**
     * GET /api/sharafs/{sharaf_id}/payments/{payment_definition_id}/receipt
     * Serve a transfer receipt (image or PDF) for a signed-in user.
     */
    public function receipt(Request $request, string $sharaf_id, string $payment_definition_id): StreamedResponse|JsonResponse
    {
        if (!$request->user()) {
            return $this->jsonError('UNAUTHORIZED', 'Authentication required.', 401);
        }

        $payment = SharafPayment::query()
            ->where('sharaf_id', $sharaf_id)
            ->where('payment_definition_id', $payment_definition_id)
            ->first();

        if (!$payment || !$payment->receipt_path) {
            return $this->jsonError('NOT_FOUND', 'No receipt uploaded for this payment.', 404);
        }

        $fullPath = Storage::disk('local')->path($payment->receipt_path);
        if (!is_file($fullPath)) {
            return $this->jsonError('NOT_FOUND', 'Receipt file not found.', 404);
        }

        $mime = match (strtolower(pathinfo($payment->receipt_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
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
            basename($payment->receipt_path),
            ['Content-Type' => $mime],
            'inline'
        );
    }

    /**
     * Get all sharaf payments with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_id' => ['nullable', 'integer', 'exists:sharafs,id'],
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

        $query = SharafPayment::with(['sharaf', 'paymentDefinition'])
            ->whereHas('sharaf.sharafDefinition.event.miqaat', function ($q) {
                return $q->active();
            });

        // Apply filters
        if ($request->has('sharaf_id')) {
            $query->where('sharaf_id', $request->input('sharaf_id'));
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $results = $query->orderBy('sharaf_id')->orderBy('payment_definition_id')->paginate($perPage, ['*'], 'page', $page);

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
     * Store a newly created or update an existing sharaf payment.
     */
    public function store(Request $request, string $sharaf_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_definition_id' => ['required', 'integer', 'exists:payment_definitions,id'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'payment_currency' => ['nullable', 'string', 'max:3'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        // Verify sharaf exists
        $sharaf = Sharaf::find($sharaf_id);
        if (!$sharaf) {
            return $this->jsonError('NOT_FOUND', 'Sharaf not found.', 404);
        }

        // Create or update the payment (payment_status is always false)
        $sharafPayment = SharafPayment::updateOrCreate(
            [
                'sharaf_id' => $sharaf_id,
                'payment_definition_id' => $request->input('payment_definition_id'),
            ],
            [
                'payment_amount' => $request->input('payment_amount'),
                'payment_status' => false, // Always false as per requirement
                'payment_currency' => $request->input('payment_currency', 'LKR'),
            ]
        );

        // Load relationships for response
        $sharafPayment->load(['sharaf', 'paymentDefinition']);

        return $this->jsonSuccessWithData($sharafPayment, 201);
    }
}
