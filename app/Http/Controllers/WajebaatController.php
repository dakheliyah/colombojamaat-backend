<?php

namespace App\Http\Controllers;

use App\Models\Census;
use App\Models\MiqaatCheck;
use App\Models\MiqaatCheckDepartment;
use App\Models\Wajebaat;
use App\Models\WajebaatGroup;
use App\Services\WajebaatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WajebaatController extends Controller
{
    public function __construct(
        protected WajebaatService $wajebaatService
    ) {}

    /**
     * Bulk-save Takhmeen amounts.
     *
     * If an its_id is passed (as a separate field), the API checks if they belong to a wajebaat_groups
     * and returns all associated members' data.
     *
     * Currency Logic: Payments are processed using the currency stored in the wajebaat record.
     * conversion_rate is only for reporting purposes and does not affect the stored amount.
     */
    public function takhmeenStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'entries' => ['required', 'array', 'min:1', 'max:1000'],
            'entries.*.its_id' => ['required', 'string', 'exists:census,its_id'],
            'entries.*.amount' => ['required', 'numeric', 'min:0'],
            'entries.*.currency' => ['nullable', 'string', 'size:3'],
            'entries.*.conversion_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'its_id' => ['nullable', 'string', 'exists:census,its_id'], // optional: check this ITS for group membership
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $miqaatId = (int) $request->input('miqaat_id');
        $entries = (array) $request->input('entries', []);
        $lookupIts = $request->filled('its_id') ? (string) $request->input('its_id') : null;

        $saved = [];

        DB::transaction(function () use ($miqaatId, $entries, &$saved) {
            foreach ($entries as $entry) {
                $itsId = (string) $entry['its_id'];

                // Check if this member belongs to a group and link wg_id
                $groupRow = WajebaatGroup::query()->forMember($miqaatId, $itsId)->first();
                $wgId = $groupRow?->wg_id;

                // Store amount in the currency provided (no conversion at write-time)
                $wajebaat = Wajebaat::updateOrCreate(
                    [
                        'miqaat_id' => $miqaatId,
                        'its_id' => $itsId,
                    ],
                    [
                        'wg_id' => $wgId,
                        'amount' => $entry['amount'],
                        'currency' => $entry['currency'] ?? 'LKR',
                        'conversion_rate' => $entry['conversion_rate'] ?? 1.0,
                    ]
                );

                // Auto-categorize based on stored amount (no conversion at write-time)
                $this->wajebaatService->categorize($wajebaat, true);

                $saved[] = $wajebaat;
            }
        });

        $response = [
            'saved' => $saved,
        ];

        // If an its_id is passed, check if they belong to a wajebaat_groups and return all associated members' data
        if ($lookupIts !== null) {
            $groupData = $this->groupDataForIts($miqaatId, $lookupIts);
            if ($groupData !== null) {
                $response['group'] = $groupData;
            }
        }

        return $this->jsonSuccessWithData($response, 201);
    }

    /**
     * GET: single wajebaat for a member in a miqaat.
     * Returns 404 if no wajebaat exists for the given miqaat_id and its_id.
     */
    public function show(string $miqaat_id, string $its_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
            'its_id' => $its_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'its_id' => ['required', 'string', 'exists:census,its_id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $wajebaat = Wajebaat::query()
            ->forItsInMiqaat((string) $its_id, (int) $miqaat_id)
            ->first();

        if ($wajebaat === null) {
            return $this->jsonError('NOT_FOUND', 'Wajebaat record not found for this miqaat and member.', 404);
        }

        return $this->jsonSuccessWithData($wajebaat);
    }

    /**
     * PATCH: mark as paid/unpaid for a specific member in a miqaat.
     *
     * Department Guard:
     * Before saving paid=true, the controller queries the miqaat_checks table.
     * If any check for that its_id is false (or missing), return 403 Forbidden
     * with a JSON payload listing the specific mcd_id names that are pending.
     *
     * Currency Logic: The payment status is updated using the currency already stored
     * in the wajebaat record. No currency conversion is performed.
     */
    public function financeAdaUpdate(Request $request, string $miqaat_id, string $its_id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), [
            'miqaat_id' => $miqaat_id,
            'its_id' => $its_id,
        ]), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'its_id' => ['required', 'string', 'exists:census,its_id'],
            'paid' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $miqaatId = (int) $miqaat_id;
        $itsId = (string) $its_id;
        $paid = (bool) $request->input('paid');

        // Department Guard: Before saving paid status, check miqaat_checks
        if ($paid) {
            $pending = $this->pendingDepartmentChecks($miqaatId, $itsId);

            if (!empty($pending)) {
                return response()->json([
                    'success' => false,
                    'error' => 'DEPARTMENT_CHECKS_PENDING',
                    'message' => 'Cannot mark as paid: department checks are pending.',
                    'pending_departments' => $pending, // List of { mcd_id, name } for pending departments
                ], 403);
            }
        }

        // Update payment status (currency is already stored in wajebaat record)
        $wajebaat = Wajebaat::updateOrCreate(
            [
                'miqaat_id' => $miqaatId,
                'its_id' => $itsId,
            ],
            [
                'status' => $paid,
            ]
        );

        return $this->jsonSuccessWithData($wajebaat);
    }

    /**
     * GET: clearance status for a member in a miqaat.
     *
     * Returns wajebaat record (if any), pending department checks, and whether the member can be marked paid.
     */
    public function clearance(string $miqaat_id, string $its_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
            'its_id' => $its_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'its_id' => ['required', 'string', 'exists:census,its_id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $miqaatId = (int) $miqaat_id;
        $itsId = (string) $its_id;

        $wajebaat = Wajebaat::query()
            ->forItsInMiqaat($itsId, $miqaatId)
            ->first();

        $pending = $this->pendingDepartmentChecks($miqaatId, $itsId);

        $data = [
            'wajebaat' => $wajebaat,
            'pending_departments' => $pending,
            'can_mark_paid' => empty($pending),
        ];

        return $this->jsonSuccessWithData($data);
    }

    /**
     * If member is in a group, return all associated members' data for that wg_id (within the miqaat).
     */
    protected function groupDataForIts(int $miqaatId, string $itsId): ?array
    {
        $row = WajebaatGroup::query()->forMember($miqaatId, $itsId)->first();

        if (!$row) {
            return null;
        }

        $members = WajebaatGroup::query()
            ->forGroup($miqaatId, (int) $row->wg_id)
            ->get();

        $memberIts = $members->pluck('its_id')->values();

        $people = Census::query()
            ->whereIn('its_id', $memberIts)
            ->get()
            ->keyBy('its_id');

        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $memberIts)
            ->get()
            ->keyBy('its_id');

        return [
            'wg_id' => (int) $row->wg_id,
            'group_name' => $row->group_name,
            'group_type' => $row->group_type,
            'master_its' => (string) $row->master_its,
            'members' => $memberIts->map(function ($mIts) use ($people, $wajebaats) {
                return [
                    'its_id' => (string) $mIts,
                    'person' => $people->get($mIts),
                    'wajebaat' => $wajebaats->get($mIts),
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Return pending department checks for a member ITS in a miqaat.
     *
     * The Department Guard queries miqaat_checks table for the given its_id.
     * A department is considered pending if:
     * - There is no check row for that department, OR
     * - The check row exists but is_cleared=false
     *
     * Returns array of pending departments with mcd_id and name:
     * [{ mcd_id: int, name: string }, ...]
     */
    protected function pendingDepartmentChecks(int $miqaatId, string $itsId): array
    {
        // Get all check definitions for this miqaat
        $departments = MiqaatCheckDepartment::query()
            ->where('miqaat_id', $miqaatId)
            ->orderBy('name')
            ->get(['mcd_id', 'name', 'user_type']);

        if ($departments->isEmpty()) {
            // If no departments are configured, nothing blocks payment.
            return [];
        }

        $mcdIds = $departments->pluck('mcd_id')->all();

        // Get all existing checks for this member for these definitions (miqaat is implied by definition)
        $checks = MiqaatCheck::query()
            ->where('its_id', $itsId)
            ->whereIn('mcd_id', $mcdIds)
            ->get()
            ->keyBy('mcd_id');

        $pending = [];

        // Check each department: if missing or not cleared, it's pending
        foreach ($departments as $dept) {
            $check = $checks->get($dept->mcd_id);
            if (!$check || !$check->is_cleared) {
                $pending[] = [
                    'mcd_id' => (int) $dept->mcd_id,
                    'name' => (string) $dept->name,
                    'user_type' => $dept->user_type?->value,
                ];
            }
        }

        return $pending;
    }
}

