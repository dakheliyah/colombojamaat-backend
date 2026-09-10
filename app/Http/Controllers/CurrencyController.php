<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Currency::query()->orderBy('sort_order')->orderBy('code');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return $this->jsonSuccessWithData($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $maxOrder = (int) Currency::max('sort_order');

        $currency = Currency::create([
            'code' => strtoupper($request->input('code')),
            'name' => $request->input('name'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'sort_order' => $request->input('sort_order', $maxOrder + 1),
        ]);

        return $this->jsonSuccessWithData($currency, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $currency = Currency::find($id);
        if (! $currency) {
            return $this->jsonError('NOT_FOUND', 'Currency not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/', Rule::unique('currencies', 'code')->ignore($id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $data = $request->only(['code', 'name', 'is_active', 'sort_order']);
        if (array_key_exists('code', $data) && is_string($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $currency->update($data);

        return $this->jsonSuccessWithData($currency->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $currency = Currency::find($id);
        if (! $currency) {
            return $this->jsonError('NOT_FOUND', 'Currency not found.', 404);
        }

        $currency->delete();

        return $this->jsonSuccess(200);
    }
}
