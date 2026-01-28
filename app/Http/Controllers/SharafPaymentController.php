<?php

namespace App\Http\Controllers;

use App\Models\Sharaf;
use App\Models\SharafPayment;
use App\Services\SharafPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafPaymentController extends Controller
{
    public function __construct(
        protected SharafPaymentService $paymentService
    ) {}

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

        $query = SharafPayment::with(['sharaf', 'paymentDefinition']);

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
            ]
        );

        // Load relationships for response
        $sharafPayment->load(['sharaf', 'paymentDefinition']);

        return $this->jsonSuccessWithData($sharafPayment, 201);
    }
}
