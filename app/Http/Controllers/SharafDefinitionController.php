<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PaymentDefinition;
use App\Models\Sharaf;
use App\Models\SharafDefinition;
use App\Models\SharafPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafDefinitionController extends Controller
{
    /**
     * GET /api/events/{eventId}/sharaf-definitions
     * Optional query: include=positions,payment-definitions
     */
    public function index(Request $request, string $event_id): JsonResponse
    {
        $validator = Validator::make(
            ['event_id' => $event_id],
            ['event_id' => ['required', 'integer']]
        );
        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid event ID.',
                400
            );
        }

        $eventId = (int) $event_id;
        $event = Event::find($eventId);
        if (! $event) {
            return $this->jsonError('NOT_FOUND', 'Event not found.', 404);
        }

        $includeRaw = $request->query('include', '');
        $allowedIncludes = ['positions', 'payment-definitions'];
        $includeList = array_map('trim', array_filter(explode(',', $includeRaw)));
        foreach ($includeList as $value) {
            if (! in_array($value, $allowedIncludes, true)) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'Invalid value in include. Allowed: ' . implode(', ', $allowedIncludes) . '.',
                    400
                );
            }
        }

        $includePositions = in_array('positions', $includeList, true);
        $includePaymentDefinitions = in_array('payment-definitions', $includeList, true);

        $query = SharafDefinition::where('event_id', $eventId);
        if ($includePositions || $includePaymentDefinitions) {
            $with = [];
            if ($includePositions) {
                $with['sharafPositions'] = fn ($q) => $q->orderBy('order');
            }
            if ($includePaymentDefinitions) {
                $with['paymentDefinitions'] = fn ($q) => $q->orderBy('name');
            }
            $query->with($with);
        }

        $definitions = $query->get();

        if (! $includePositions && ! $includePaymentDefinitions) {
            return $this->jsonSuccessWithData($definitions);
        }

        $data = $definitions->map(function (SharafDefinition $def) use ($includePositions, $includePaymentDefinitions) {
            $arr = $def->toArray();
            if ($includePositions) {
                $arr['positions'] = $def->sharafPositions->map(fn (SharafPosition $p) => $p->toArray())->values()->all();
                unset($arr['sharaf_positions']);
            }
            if ($includePaymentDefinitions) {
                $arr['payment_definitions'] = $def->paymentDefinitions->map(fn (PaymentDefinition $pd) => $pd->toArray())->values()->all();
                unset($arr['payment_definitions']);
            }
            return $arr;
        })->all();

        return $this->jsonSuccessWithData($data);
    }

    public function sharafs(string $sd_id): JsonResponse
    {
        $sharafs = Sharaf::where('sharaf_definition_id', $sd_id)
            ->with(['sharafDefinition', 'sharafMembers', 'sharafClearances', 'sharafPayments.paymentDefinition'])
            ->get();

        return $this->jsonSuccessWithData($sharafs);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharafDefinition = SharafDefinition::create([
            'event_id' => $request->input('event_id'),
            'name' => $request->input('name'),
            'key' => $request->input('key'),
            'description' => $request->input('description'),
        ]);

        return $this->jsonSuccessWithData($sharafDefinition, 201);
    }

    /**
     * Update an existing sharaf definition.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sharafDefinition = SharafDefinition::find($id);

        if (! $sharafDefinition) {
            return $this->jsonError('NOT_FOUND', 'Sharaf definition not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'event_id' => ['sometimes', 'integer', 'exists:events,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharafDefinition->update($request->only(['event_id', 'name', 'key', 'description']));

        return $this->jsonSuccessWithData($sharafDefinition->fresh('event'));
    }

    public function positions(string $id): JsonResponse
    {
        $sharafDefinition = SharafDefinition::find($id);

        if (!$sharafDefinition) {
            return $this->jsonError('NOT_FOUND', 'Sharaf definition not found.', 404);
        }

        $positions = SharafPosition::where('sharaf_definition_id', $id)
            ->orderBy('order')
            ->get();

        return $this->jsonSuccessWithData($positions);
    }

    public function paymentDefinitions(string $id): JsonResponse
    {
        $sharafDefinition = SharafDefinition::find($id);

        if (!$sharafDefinition) {
            return $this->jsonError('NOT_FOUND', 'Sharaf definition not found.', 404);
        }

        $paymentDefinitions = PaymentDefinition::where('sharaf_definition_id', $id)
            ->orderBy('name')
            ->get();

        return $this->jsonSuccessWithData($paymentDefinitions);
    }
}
