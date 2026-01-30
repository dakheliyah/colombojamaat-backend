<?php

namespace App\Http\Controllers;

use App\Models\Census;
use App\Models\MiqaatCheck;
use App\Models\MiqaatCheckDepartment;
use App\Models\WajCategory;
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
            'entries.*.is_isolated' => ['nullable', 'boolean'],
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
        if (($err = $this->ensureActiveMiqaat($miqaatId)) !== null) {
            return $err;
        }
        $entries = (array) $request->input('entries', []);
        $lookupIts = $request->filled('its_id') ? (string) $request->input('its_id') : null;

        $saved = [];

        DB::transaction(function () use ($miqaatId, $entries, &$saved) {
            foreach ($entries as $entry) {
                $itsId = (string) $entry['its_id'];
                $isIsolated = isset($entry['is_isolated']) ? (bool) $entry['is_isolated'] : null;

                // Get current wajebaat to check existing is_isolated status
                $currentWajebaat = Wajebaat::query()
                    ->where('miqaat_id', $miqaatId)
                    ->where('its_id', $itsId)
                    ->first();
                
                // Determine final is_isolated status
                $finalIsIsolated = $isIsolated !== null ? $isIsolated : ($currentWajebaat?->is_isolated ?? false);
                
                // If is_isolated is being set to true, remove from any groups
                if ($isIsolated === true) {
                    // Remove from wajebaat_groups
                    WajebaatGroup::query()
                        ->where('miqaat_id', $miqaatId)
                        ->where('its_id', $itsId)
                        ->delete();
                }

                // Check if this member belongs to a group and link wg_id (only if not isolated)
                $wgId = null;
                if (!$finalIsIsolated) {
                    $groupRow = WajebaatGroup::query()->forMember($miqaatId, $itsId)->first();
                    $wgId = $groupRow?->wg_id;
                }

                // Store amount in the currency provided (no conversion at write-time)
                $updateData = [
                    'wg_id' => $wgId,
                    'amount' => $entry['amount'],
                    'currency' => $entry['currency'] ?? 'LKR',
                    'conversion_rate' => $entry['conversion_rate'] ?? 1.0,
                ];

                // Only update is_isolated if explicitly provided
                if ($isIsolated !== null) {
                    $updateData['is_isolated'] = $isIsolated;
                }

                $wajebaat = Wajebaat::updateOrCreate(
                    [
                        'miqaat_id' => $miqaatId,
                        'its_id' => $itsId,
                    ],
                    $updateData
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
     * Includes is_isolated and category (wc_id, name, hex_color) for frontend display.
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
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $wajebaat = Wajebaat::query()
            ->forItsInMiqaat((string) $its_id, (int) $miqaat_id)
            ->with('category')
            ->first();

        if ($wajebaat === null) {
            return $this->jsonError('NOT_FOUND', 'Wajebaat record not found for this miqaat and member.', 404);
        }

        $data = $this->formatWajebaatWithCategory($wajebaat);

        return $this->jsonSuccessWithData($data);
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
            'is_isolated' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;
        $itsId = (string) $its_id;
        $paid = (bool) $request->input('paid');
        $isIsolated = $request->filled('is_isolated') ? (bool) $request->input('is_isolated') : null;

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

        // If is_isolated is being set to true, remove from any groups
        if ($isIsolated === true) {
            // Remove from wajebaat_groups
            WajebaatGroup::query()
                ->where('miqaat_id', $miqaatId)
                ->where('its_id', $itsId)
                ->delete();
        }

        // Update payment status and is_isolated (currency is already stored in wajebaat record)
        $updateData = ['status' => $paid];
        if ($isIsolated !== null) {
            $updateData['is_isolated'] = $isIsolated;
            // If setting to isolated, also set wg_id to null
            if ($isIsolated === true) {
                $updateData['wg_id'] = null;
            }
        }

        $wajebaat = Wajebaat::updateOrCreate(
            [
                'miqaat_id' => $miqaatId,
                'its_id' => $itsId,
            ],
            $updateData
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
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
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
     * GET: all wajebaat records for a specific miqaat.
     * Returns empty array if no records exist (not 404).
     */
    public function index(string $miqaat_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;

        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->with('category')
            ->get();

        return $this->jsonSuccessWithData($wajebaats->values()->all());
    }

    /**
     * GET: all wajebaat records for a specific ITS ID across all miqaats.
     * Ordered by created_at descending (most recent first).
     * Returns empty array if no records exist (not 404).
     */
    public function history(string $its_id): JsonResponse
    {
        $validator = Validator::make([
            'its_id' => $its_id,
        ], [
            'its_id' => ['required', 'string', 'exists:census,its_id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $itsId = (string) $its_id;

        $wajebaats = Wajebaat::query()
            ->where('its_id', $itsId)
            ->with(['category', 'miqaat:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->jsonSuccessWithData($wajebaats->values()->all());
    }

    /**
     * GET: all wajebaat categories configured for a specific miqaat.
     * Ordered by low_bar ascending.
     * Returns empty array if no categories exist (not 404).
     */
    public function categories(string $miqaat_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;

        $categories = WajCategory::query()
            ->where('miqaat_id', $miqaatId)
            ->orderBy('low_bar', 'asc')
            ->get();

        // Transform to include 'color' field (from hex_color) and 'id' (from wc_id) for frontend compatibility
        $transformed = $categories->map(function ($category) {
            $data = $category->toArray();
            $data['id'] = $category->wc_id; // Add 'id' alias for frontend
            $data['color'] = $category->hex_color; // Add 'color' alias for frontend
            return $data;
        });

        return $this->jsonSuccessWithData($transformed->values()->all());
    }

    /**
     * GET: list all wajebaat groups for a miqaat.
     * Returns empty array if no groups exist (not 404).
     */
    public function groupsIndex(string $miqaat_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;

        // Get all unique wg_id values for this miqaat
        $wgIds = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->distinct()
            ->pluck('wg_id')
            ->values();

        if ($wgIds->isEmpty()) {
            return $this->jsonSuccessWithData([]);
        }

        // Build GroupData for each group
        $groups = $wgIds->map(function ($wgId) use ($miqaatId) {
            return $this->buildGroupData($miqaatId, (int) $wgId);
        })->filter()->values()->all();

        return $this->jsonSuccessWithData($groups);
    }

    /**
     * POST: create a new wajebaat group.
     */
    public function groupsStore(Request $request, string $miqaat_id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), [
            'miqaat_id' => $miqaat_id,
        ]), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'master_its' => ['required', 'string', 'exists:census,its_id'],
            'member_its_ids' => ['required', 'array', 'min:1'],
            'member_its_ids.*' => ['required', 'string', 'exists:census,its_id'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'group_type' => ['nullable', 'string', 'in:business_grouping,personal_grouping,organization'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;
        $masterIts = (string) $request->input('master_its');
        $memberItsIds = array_unique(array_map('strval', $request->input('member_its_ids', [])));
        $groupName = $request->input('group_name');
        $groupType = $request->input('group_type');

        // Master cannot be in members list
        if (in_array($masterIts, $memberItsIds, true)) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'Master ITS cannot be in the members list.',
                422
            );
        }

        // Check if any member is isolated (cannot be part of any group)
        $allMemberIts = array_merge([$masterIts], $memberItsIds);
        $isolatedMembers = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $allMemberIts)
            ->where('is_isolated', true)
            ->pluck('its_id')
            ->all();

        if (!empty($isolatedMembers)) {
            return response()->json([
                'success' => false,
                'error' => 'ISOLATED_MEMBER',
                'message' => 'One or more members are marked as isolated and cannot be part of any group.',
                'isolated_its_ids' => $isolatedMembers,
            ], 422);
        }

        // Check if any member already belongs to another group
        $existingMembers = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $allMemberIts)
            ->get();

        if ($existingMembers->isNotEmpty()) {
            $conflictingIts = $existingMembers->pluck('its_id')->unique()->values()->all();
            return response()->json([
                'success' => false,
                'error' => 'CONFLICT',
                'message' => 'One or more members already belong to another group.',
                'conflicting_its_ids' => $conflictingIts,
            ], 409);
        }

        // Get next wg_id for this miqaat
        $nextWgId = $this->getNextWgId($miqaatId);

        // Create group membership records
        DB::transaction(function () use ($miqaatId, $nextWgId, $masterIts, $memberItsIds, $groupName, $groupType) {
            // Create master record
            WajebaatGroup::create([
                'miqaat_id' => $miqaatId,
                'wg_id' => $nextWgId,
                'master_its' => $masterIts,
                'its_id' => $masterIts,
                'group_name' => $groupName,
                'group_type' => $groupType,
            ]);

            // Create member records
            foreach ($memberItsIds as $itsId) {
                WajebaatGroup::create([
                    'miqaat_id' => $miqaatId,
                    'wg_id' => $nextWgId,
                    'master_its' => $masterIts,
                    'its_id' => $itsId,
                    'group_name' => $groupName,
                    'group_type' => $groupType,
                ]);
            }

            // Update wajebaat records to link to this group
            Wajebaat::query()
                ->where('miqaat_id', $miqaatId)
                ->whereIn('its_id', array_merge([$masterIts], $memberItsIds))
                ->update(['wg_id' => $nextWgId]);
        });

        $groupData = $this->buildGroupData($miqaatId, $nextWgId);

        return $this->jsonSuccessWithData($groupData, 201);
    }

    /**
     * PUT: update an existing wajebaat group.
     */
    public function groupsUpdate(Request $request, string $miqaat_id, string $wg_id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), [
            'miqaat_id' => $miqaat_id,
            'wg_id' => $wg_id,
        ]), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'wg_id' => ['required', 'integer'],
            'master_its' => ['nullable', 'string', 'exists:census,its_id'],
            'member_its_ids' => ['nullable', 'array', 'min:1'],
            'member_its_ids.*' => ['required', 'string', 'exists:census,its_id'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'group_type' => ['nullable', 'string', 'in:business_grouping,personal_grouping,organization'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;
        $wgId = (int) $wg_id;

        // Check if group exists
        $existingGroup = WajebaatGroup::query()
            ->forGroup($miqaatId, $wgId)
            ->first();

        if (!$existingGroup) {
            return $this->jsonError('NOT_FOUND', 'Wajebaat group not found.', 404);
        }

        $masterIts = $request->filled('master_its') ? (string) $request->input('master_its') : $existingGroup->master_its;
        $memberItsIds = $request->filled('member_its_ids')
            ? array_unique(array_map('strval', $request->input('member_its_ids', [])))
            : null;
        $groupName = $request->input('group_name', $existingGroup->group_name);
        $groupType = $request->input('group_type', $existingGroup->group_type?->value ?? $existingGroup->group_type);

        // If member_its_ids is provided, validate
        if ($memberItsIds !== null) {
            // Master cannot be in members list
            if (in_array($masterIts, $memberItsIds, true)) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'Master ITS cannot be in the members list.',
                    422
                );
            }

            // Get current members (excluding this group)
            $currentMemberIts = WajebaatGroup::query()
                ->forGroup($miqaatId, $wgId)
                ->pluck('its_id')
                ->all();

            // Check if any new members are isolated (cannot be part of any group)
            $newMemberIts = array_diff($memberItsIds, $currentMemberIts);
            $allNewMemberIts = array_merge([$masterIts], $newMemberIts);
            if (!empty($allNewMemberIts)) {
                $isolatedMembers = Wajebaat::query()
                    ->where('miqaat_id', $miqaatId)
                    ->whereIn('its_id', $allNewMemberIts)
                    ->where('is_isolated', true)
                    ->pluck('its_id')
                    ->all();

                if (!empty($isolatedMembers)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'ISOLATED_MEMBER',
                        'message' => 'One or more members are marked as isolated and cannot be part of any group.',
                        'isolated_its_ids' => $isolatedMembers,
                    ], 422);
                }
            }

            // Check if any new members already belong to another group
            if (!empty($newMemberIts)) {
                $existingMembers = WajebaatGroup::query()
                    ->where('miqaat_id', $miqaatId)
                    ->where('wg_id', '!=', $wgId)
                    ->whereIn('its_id', array_merge([$masterIts], $newMemberIts))
                    ->get();

                if ($existingMembers->isNotEmpty()) {
                    $conflictingIts = $existingMembers->pluck('its_id')->unique()->values()->all();
                    return response()->json([
                        'success' => false,
                        'error' => 'CONFLICT',
                        'message' => 'One or more members already belong to another group.',
                        'conflicting_its_ids' => $conflictingIts,
                    ], 409);
                }
            }
        }

        // Update group
        DB::transaction(function () use ($miqaatId, $wgId, $masterIts, $memberItsIds, $groupName, $groupType) {
            // If member_its_ids is provided, rebuild the group
            if ($memberItsIds !== null) {
                // Remove old members (except master if master is changing)
                WajebaatGroup::query()
                    ->forGroup($miqaatId, $wgId)
                    ->delete();

                // Create master record
                WajebaatGroup::create([
                    'miqaat_id' => $miqaatId,
                    'wg_id' => $wgId,
                    'master_its' => $masterIts,
                    'its_id' => $masterIts,
                    'group_name' => $groupName,
                    'group_type' => $groupType,
                ]);

                // Create member records
                foreach ($memberItsIds as $itsId) {
                    WajebaatGroup::create([
                        'miqaat_id' => $miqaatId,
                        'wg_id' => $wgId,
                        'master_its' => $masterIts,
                        'its_id' => $itsId,
                        'group_name' => $groupName,
                        'group_type' => $groupType,
                    ]);
                }

                // Update wajebaat records
                $allMemberIts = array_merge([$masterIts], $memberItsIds);
                Wajebaat::query()
                    ->where('miqaat_id', $miqaatId)
                    ->whereIn('its_id', $allMemberIts)
                    ->update(['wg_id' => $wgId]);

                // Remove wg_id from wajebaat records that are no longer in this group
                Wajebaat::query()
                    ->where('miqaat_id', $miqaatId)
                    ->where('wg_id', $wgId)
                    ->whereNotIn('its_id', $allMemberIts)
                    ->update(['wg_id' => null]);
            } else {
                // Just update group metadata
                WajebaatGroup::query()
                    ->forGroup($miqaatId, $wgId)
                    ->update([
                        'master_its' => $masterIts,
                        'group_name' => $groupName,
                        'group_type' => $groupType,
                    ]);
            }
        });

        $groupData = $this->buildGroupData($miqaatId, $wgId);

        return $this->jsonSuccessWithData($groupData);
    }

    /**
     * DELETE: delete a wajebaat group.
     */
    public function groupsDestroy(string $miqaat_id, string $wg_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
            'wg_id' => $wg_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'wg_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;
        $wgId = (int) $wg_id;

        // Check if group exists
        $existingGroup = WajebaatGroup::query()
            ->forGroup($miqaatId, $wgId)
            ->first();

        if (!$existingGroup) {
            return $this->jsonError('NOT_FOUND', 'Wajebaat group not found.', 404);
        }

        DB::transaction(function () use ($miqaatId, $wgId) {
            // Set wajebaat.wg_id to null (cascade)
            Wajebaat::query()
                ->where('miqaat_id', $miqaatId)
                ->where('wg_id', $wgId)
                ->update(['wg_id' => null]);

            // Delete group membership records
            WajebaatGroup::query()
                ->forGroup($miqaatId, $wgId)
                ->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Wajebaat group deleted successfully.',
        ], 200);
    }

    /**
     * Build GroupData structure for a given group.
     */
    protected function buildGroupData(int $miqaatId, int $wgId): ?array
    {
        $groupRow = WajebaatGroup::query()
            ->forGroup($miqaatId, $wgId)
            ->first();

        if (!$groupRow) {
            return null;
        }

        // Get all members of this group
        $members = WajebaatGroup::query()
            ->forGroup($miqaatId, $wgId)
            ->get();

        $memberIts = $members->pluck('its_id')->values();

        // Get census records for all members
        $people = Census::query()
            ->whereIn('its_id', $memberIts)
            ->get()
            ->keyBy('its_id');

        // Get wajebaat records for all members in this miqaat
        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $memberIts)
            ->get()
            ->keyBy('its_id');

        return [
            'wg_id' => $wgId,
            'master_its' => (string) $groupRow->master_its,
            'group_name' => $groupRow->group_name,
            'group_type' => $groupRow->group_type?->value ?? $groupRow->group_type,
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
     * Get the next wg_id for a miqaat.
     */
    protected function getNextWgId(int $miqaatId): int
    {
        $maxWgId = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->max('wg_id');

        return ($maxWgId ?? 0) + 1;
    }

    /**
     * GET: all members of a wajebaat group with their census and wajebaat data.
     * Returns 404 if group doesn't exist.
     */
    public function groupMembers(string $miqaat_id, string $wg_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
            'wg_id' => $wg_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'wg_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;
        $wgId = (int) $wg_id;

        $groupData = $this->buildGroupData($miqaatId, $wgId);

        if (!$groupData) {
            return $this->jsonError('NOT_FOUND', 'Wajebaat group not found.', 404);
        }

        return $this->jsonSuccessWithData($groupData);
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
     * GET: Mumin Dashboard profile — group (Level 1) or family (Level 2).
     *
     * Resolves profile scope for the logged-in mumin:
     * - Level 1 (Group): If user's ITS is in wajebaat_groups for the miqaat, returns group profile with all members.
     * - Level 2 (Family): If not in a group, returns family profile (census where hof_id = user's hof_id).
     *
     * Returns members with census, wajebaat (including is_isolated and category), and clearance_status for primary its_id.
     */
    public function muminProfile(string $miqaat_id, string $its_id): JsonResponse
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
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;
        $itsId = (string) $its_id;

        $profileType = 'family';
        $masterIts = null;
        $hofIts = null;
        $wgId = null;
        $groupName = null;
        $memberIts = collect();

        // Check if user is in a wajebaat group
        $groupRow = WajebaatGroup::query()->forMember($miqaatId, $itsId)->first();

        if ($groupRow !== null) {
            $profileType = 'group';
            $wgId = (int) $groupRow->wg_id;
            $masterIts = (string) $groupRow->master_its;
            $groupName = $groupRow->group_name;

            $memberIts = WajebaatGroup::query()
                ->forGroup($miqaatId, $wgId)
                ->pluck('its_id')
                ->unique()
                ->values();
        } else {
            // Family profile: get census for user → hof_id, then family members
            $userCensus = Census::query()->where('its_id', $itsId)->first();
            if ($userCensus === null) {
                return $this->jsonError('NOT_FOUND', 'Census record not found for this ITS.', 404);
            }

            $hofIts = (string) $userCensus->hof_id;
            $hof = Census::query()->where('its_id', $hofIts)->first();
            $members = Census::query()
                ->where('hof_id', $hofIts)
                ->where('its_id', '!=', $hofIts)
                ->orderBy('age', 'desc')
                ->get();

            $memberIts = collect([$hof])->merge($members)->filter()->pluck('its_id')->values();
        }

        // Fetch census for all members
        $people = Census::query()
            ->whereIn('its_id', $memberIts)
            ->get()
            ->keyBy('its_id');

        // Fetch wajebaat for all members in this miqaat (only those who have records)
        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $memberIts)
            ->with('category')
            ->get()
            ->keyBy('its_id');

        $members = $memberIts->map(function ($mIts) use ($people, $wajebaats) {
            $wajebaat = $wajebaats->get($mIts);
            return [
                'its_id' => (string) $mIts,
                'person' => $people->get($mIts),
                'wajebaat' => $wajebaat !== null ? $this->formatWajebaatWithCategory($wajebaat) : null,
            ];
        })->values()->all();

        $pending = $this->pendingDepartmentChecks($miqaatId, $itsId);
        $clearanceStatus = [
            'can_mark_paid' => empty($pending),
            'pending_departments' => $pending,
        ];

        $data = [
            'profile_type' => $profileType,
            'members' => $members,
            'clearance_status' => $clearanceStatus,
        ];

        if ($profileType === 'group') {
            $data['master_its'] = $masterIts;
            $data['wg_id'] = $wgId;
            $data['group_name'] = $groupName;
        } else {
            $data['hof_its'] = $hofIts;
        }

        return $this->jsonSuccessWithData($data);
    }

    /**
     * GET: wajebaat records for multiple ITSs in one call.
     * Query params: its_ids=123,456,789 (comma-separated)
     * Returns array of wajebaat objects with is_isolated and category populated.
     */
    public function wajebaatByItsList(Request $request, string $miqaat_id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->query(), [
            'miqaat_id' => $miqaat_id,
        ]), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'its_ids' => ['required', 'string', 'regex:/^[\d,]+$/'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }
        if (($err = $this->ensureActiveMiqaat((int) $miqaat_id)) !== null) {
            return $err;
        }

        $miqaatId = (int) $miqaat_id;
        $itsIds = array_filter(array_map('trim', explode(',', (string) $request->query('its_ids'))));

        if (empty($itsIds)) {
            return $this->jsonSuccessWithData([]);
        }

        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $itsIds)
            ->with('category')
            ->get();

        $data = $wajebaats->map(fn ($w) => $this->formatWajebaatWithCategory($w))->values()->all();

        return $this->jsonSuccessWithData($data);
    }

    /**
     * Format wajebaat for API response with category as { wc_id, name, hex_color }.
     */
    protected function formatWajebaatWithCategory(Wajebaat $wajebaat): array
    {
        $data = $wajebaat->toArray();
        $data['is_isolated'] = (bool) ($wajebaat->is_isolated ?? false);

        $category = $wajebaat->relationLoaded('category') ? $wajebaat->category : $wajebaat->category;
        $data['category'] = $category !== null
            ? [
                'wc_id' => (int) $category->wc_id,
                'name' => (string) $category->name,
                'hex_color' => (string) ($category->hex_color ?? ''),
            ]
            : null;

        return $data;
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

