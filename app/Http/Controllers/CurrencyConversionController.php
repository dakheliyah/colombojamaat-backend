<?php

namespace App\Http\Controllers;

use App\Models\CurrencyConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CurrencyConversionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CurrencyConversion::query()
            ->orderByDesc('effective_date')
            ->orderBy('from_currency')
            ->orderBy('to_currency');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->filled('from_currency')) {
            $query->where('from_currency', strtoupper((string) $request->input('from_currency')));
        }

        if ($request->filled('to_currency')) {
            $query->where('to_currency', strtoupper((string) $request->input('to_currency')));
        }

        return $this->jsonSuccessWithData($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalize($request);

        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $conversion = CurrencyConversion::create($this->payload($request));

        return $this->jsonSuccessWithData($conversion, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $conversion = CurrencyConversion::find($id);
        if (! $conversion) {
            return $this->jsonError('NOT_FOUND', 'Currency conversion not found.', 404);
        }

        $this->normalize($request);

        $merged = array_merge($this->attributesFrom($conversion), $request->only([
            'from_currency',
            'to_currency',
            'rate',
            'effective_date',
            'expiry_date',
            'is_active',
            'source',
            'notes',
        ]));

        $validator = Validator::make($merged, $this->rules($id, $merged), $this->messages());

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $conversion->update($this->payload($request, partial: true));

        return $this->jsonSuccessWithData($conversion->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $conversion = CurrencyConversion::find($id);
        if (! $conversion) {
            return $this->jsonError('NOT_FOUND', 'Currency conversion not found.', 404);
        }

        $conversion->delete();

        return $this->jsonSuccess(200);
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'effective_date.unique' => 'A rate for this currency pair already exists on that date.',
            'from_currency.different' => 'From and to currencies must be different.',
            'from_currency.exists' => 'From currency must exist in the currencies list.',
            'to_currency.exists' => 'To currency must exist in the currencies list.',
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null, array $values = []): array
    {
        $from = strtoupper((string) ($values['from_currency'] ?? request()->input('from_currency', '')));
        $to = strtoupper((string) ($values['to_currency'] ?? request()->input('to_currency', '')));
        $effective = $values['effective_date'] ?? request()->input('effective_date');

        $unique = Rule::unique('currency_conversions')->where(function ($query) use ($from, $to, $effective) {
            return $query
                ->where('from_currency', $from)
                ->where('to_currency', $to)
                ->where('effective_date', $effective);
        });

        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId);
        }

        return [
            'from_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'exists:currencies,code', 'different:to_currency'],
            'to_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'exists:currencies,code'],
            'rate' => ['required', 'numeric', 'min:0.000001'],
            'effective_date' => ['required', 'date', $unique],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'is_active' => ['sometimes', 'boolean'],
            'source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, bool $partial = false): array
    {
        $keys = ['from_currency', 'to_currency', 'rate', 'effective_date', 'expiry_date', 'is_active', 'source', 'notes'];
        $data = $request->only($keys);

        if (! $partial) {
            $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        } elseif (array_key_exists('is_active', $data)) {
            $data['is_active'] = $request->boolean('is_active');
        }

        foreach (['expiry_date', 'source', 'notes'] as $nullable) {
            if (array_key_exists($nullable, $data) && $data[$nullable] === '') {
                $data[$nullable] = null;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFrom(CurrencyConversion $conversion): array
    {
        return [
            'from_currency' => $conversion->from_currency,
            'to_currency' => $conversion->to_currency,
            'rate' => $conversion->rate,
            'effective_date' => optional($conversion->effective_date)->format('Y-m-d'),
            'expiry_date' => optional($conversion->expiry_date)->format('Y-m-d'),
            'is_active' => $conversion->is_active,
            'source' => $conversion->source,
            'notes' => $conversion->notes,
        ];
    }

    private function normalize(Request $request): void
    {
        $merge = [];
        foreach (['from_currency', 'to_currency'] as $field) {
            if ($request->filled($field)) {
                $merge[$field] = strtoupper((string) $request->input($field));
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }
}
