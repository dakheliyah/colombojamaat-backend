<?php

namespace App\Http\Controllers;

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
}
