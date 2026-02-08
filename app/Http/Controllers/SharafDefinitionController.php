<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PaymentDefinition;
use App\Models\Sharaf;
use App\Models\SharafDefinition;
use App\Models\SharafPosition;
use App\Models\Census;
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

        $user = $request->user();
        if (! $user) {
            return $this->jsonError('UNAUTHORIZED', 'No valid session.', 401);
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

        $allowedSharafTypeIds = $user->sharafTypes()->pluck('id')->all();
        $query = SharafDefinition::where('event_id', $eventId)->with('sharafType');
        if ($allowedSharafTypeIds !== []) {
            $query->whereIn('sharaf_type_id', $allowedSharafTypeIds);
        } else {
            $query->whereRaw('0 = 1');
        }
        if ($includePositions || $includePaymentDefinitions) {
            $with = ['sharafType'];
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
            'sharaf_type_id' => ['required', 'integer', 'exists:sharaf_types,id'],
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
            'sharaf_type_id' => $request->input('sharaf_type_id'),
            'name' => $request->input('name'),
            'key' => $request->input('key'),
            'description' => $request->input('description'),
        ]);

        return $this->jsonSuccessWithData($sharafDefinition->load('sharafType'), 201);
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
            'sharaf_type_id' => ['sometimes', 'integer', 'exists:sharaf_types,id'],
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

        $sharafDefinition->update($request->only(['event_id', 'sharaf_type_id', 'name', 'key', 'description']));

        return $this->jsonSuccessWithData($sharafDefinition->fresh(['event', 'sharafType']));
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

    /**
     * GET /api/sharaf-definitions/{sd_id}/sharafs-with-members
     * Returns all sharafs for a specific sharaf definition with their members.
     * Optimized for PDF generation and reporting.
     */
    public function sharafsWithMembers(Request $request, string $sd_id): JsonResponse
    {
        $validator = Validator::make(
            array_merge(['sd_id' => $sd_id], $request->all()),
            [
                'sd_id' => ['required', 'integer'],
                'event_id' => ['nullable', 'integer', 'exists:events,id'],
            ]
        );

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid parameters.',
                422
            );
        }

        $sdId = (int) $sd_id;
        $sharafDefinition = SharafDefinition::find($sdId);

        if (!$sharafDefinition) {
            return $this->jsonError('NOT_FOUND', 'Sharaf definition not found.', 404);
        }

        // Build query for sharafs
        $query = Sharaf::where('sharaf_definition_id', $sdId)
            ->whereHas('sharafDefinition.event.miqaat', fn ($q) => $q->active())
            ->with([
                'sharafDefinition',
                'sharafMembers.sharafPosition' => function ($q) {
                    $q->orderBy('order');
                }
            ])
            ->orderBy('rank');

        // Filter by event_id if provided
        if ($request->has('event_id')) {
            $eventId = (int) $request->input('event_id');
            $query->whereHas('sharafDefinition', function ($q) use ($eventId) {
                $q->where('event_id', $eventId);
            });
        }

        $sharafs = $query->get();

        // Get all member ITS numbers for bulk census lookup (includes HOF from sharaf_members)
        $allMemberItsNumbers = [];
        foreach ($sharafs as $sharaf) {
            foreach ($sharaf->sharafMembers as $member) {
                $allMemberItsNumbers[] = $member->its_id;
            }
        }
        $allMemberItsNumbers = array_unique($allMemberItsNumbers);
        $censusRecords = Census::whereIn('its_id', $allMemberItsNumbers)
            ->get()
            ->keyBy('its_id');

        // Format response with members
        $data = $sharafs->map(function (Sharaf $sharaf) use ($censusRecords) {
            $sharafArray = $sharaf->toArray();
            
            // Add event_id from sharaf definition
            $sharafArray['event_id'] = $sharaf->sharafDefinition->event_id ?? null;
            
            // Get HOF info from sharaf_members (find member with HOF position or matching hof_its)
            $hofMember = $sharaf->sharafMembers->first(function ($member) use ($sharaf) {
                // Check if member's position is HOF, or if member's its_id matches sharaf's hof_its
                return $member->sharafPosition && 
                       (strtolower($member->sharafPosition->name ?? '') === 'hof' || 
                        $member->its_id === $sharaf->hof_its);
            });
            
            if ($hofMember) {
                $hofCensus = $censusRecords->get($hofMember->its_id);
                $sharafArray['hof_its'] = $hofMember->its_id;
                $sharafArray['hof_name'] = $hofCensus ? $hofCensus->name : ($hofMember->name ?? null);
            } else {
                // Fallback to sharaf.hof_its if not found in members (shouldn't happen normally)
                $sharafArray['hof_its'] = $sharaf->hof_its;
                $hofCensus = $censusRecords->get($sharaf->hof_its);
                $sharafArray['hof_name'] = $hofCensus ? $hofCensus->name : null;
            }
            
            // Format members with position details, ordered by sp_keyno (ascending)
            // If sp_keyno is null, use sharaf_position.order as fallback
            $members = $sharaf->sharafMembers
                ->sortBy(function ($member) {
                    // Sort by sp_keyno first, then by position order if sp_keyno is null
                    return $member->sp_keyno ?? ($member->sharafPosition->order ?? 9999);
                })
                ->map(function ($member) {
                    $memberArray = $member->toArray();
                    // Add 'its' field as alias of 'its_id' for compatibility
                    $memberArray['its'] = $member->its_id;
                    return $memberArray;
                })
                ->values()
                ->all();
            
            $sharafArray['members'] = $members;
            
            return $sharafArray;
        })->all();

        return $this->jsonSuccessWithData($data);
    }
}
