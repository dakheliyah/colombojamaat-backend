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
            'entries.*.wg_id' => ['nullable', 'integer'],
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

        // Pre-validate wg_id and is_isolated combinations before transaction
        foreach ($entries as $index => $entry) {
            $itsId = (string) $entry['its_id'];
            $isIsolated = isset($entry['is_isolated']) ? (bool) $entry['is_isolated'] : null;
            // Check if wg_id key exists to distinguish between explicitly null vs undefined
            $wgIdKeyExists = array_key_exists('wg_id', $entry);
            $explicitWgId = $wgIdKeyExists ? ($entry['wg_id'] === null ? null : (int) $entry['wg_id']) : null;

            // Get current wajebaat to check existing is_isolated status
            $currentWajebaat = Wajebaat::query()
                ->where('miqaat_id', $miqaatId)
                ->where('its_id', $itsId)
                ->first();
            
            // Determine final is_isolated status
            $finalIsIsolated = $isIsolated !== null ? $isIsolated : ($currentWajebaat?->is_isolated ?? false);
            
            // Validation: If is_isolated = true, wg_id must be null
            if ($finalIsIsolated && $wgIdKeyExists && $explicitWgId !== null) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'Isolated members cannot be part of any group',
                    422
                );
            }
            
            // If explicit wg_id is provided (key exists, and value is not null), validate group membership
            if ($wgIdKeyExists && $explicitWgId !== null) {
                // Check if group exists
                $groupExists = WajebaatGroup::query()
                    ->forGroup($miqaatId, $explicitWgId)
                    ->exists();
                
                if (!$groupExists) {
                    return response()->json([
                        'success' => false,
                        'error' => 'NOT_FOUND',
                        'message' => 'Group not found',
                    ], 404);
                }
                
                // Validate that the its_id is a member of the specified group
                $groupMember = WajebaatGroup::query()
                    ->forGroup($miqaatId, $explicitWgId)
                    ->where('its_id', $itsId)
                    ->first();
                
                if (!$groupMember) {
                    return $this->jsonError(
                        'VALIDATION_ERROR',
                        'ITS ID is not a member of the specified group',
                        422
                    );
                }
            }
        }

        $saved = [];

        DB::transaction(function () use ($miqaatId, $entries, &$saved) {
            foreach ($entries as $entry) {
                $itsId = (string) $entry['its_id'];
                $isIsolated = isset($entry['is_isolated']) ? (bool) $entry['is_isolated'] : null;
                // Check if wg_id key exists to distinguish between explicitly null vs undefined
                $wgIdKeyExists = array_key_exists('wg_id', $entry);
                $explicitWgId = $wgIdKeyExists ? ($entry['wg_id'] === null ? null : (int) $entry['wg_id']) : null;

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

                // Determine wg_id based on explicit control or auto-detection
                $wgId = null;
                
                if ($wgIdKeyExists) {
                    // wg_id key exists - use explicit value (could be null for personal entry, or integer for group)
                    // Validation already done above, so we can safely use the value
                    $wgId = $explicitWgId; // This will be null if explicitly set to null, or the integer value
                } elseif (!$finalIsIsolated) {
                    // wg_id key not provided (undefined) - use auto-detection (backward compatibility)
                    $groupRow = WajebaatGroup::query()->forMember($miqaatId, $itsId)->first();
                    $wgId = $groupRow?->wg_id;
                }
                // If isolated and wg_id not explicitly provided, wgId remains null

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

                // Include wg_id in the unique key to allow separate records for personal (wg_id=NULL) and group entries
                $wajebaat = Wajebaat::updateOrCreate(
                    [
                        'miqaat_id' => $miqaatId,
                        'its_id' => $itsId,
                        'wg_id' => $wgId, // Include wg_id in unique key
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
     * GET: Get all related ITS IDs for a given ITS ID.
     * 
     * This endpoint:
     * 1. Finds the person's family (using hof_id from census)
     * 2. Gets the hof_its (Head of Family)
     * 3. Checks if hof_its has wajebaat grouping for the miqaat
     * 4. Returns all ITS IDs from both family and wajebaat group
     */
    public function relatedIts(string $miqaat_id, string $its_id): JsonResponse
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

        // Step 1: Get the person's census record
        $person = Census::where('its_id', $itsId)->first();
        
        if (!$person) {
            return $this->jsonError('NOT_FOUND', 'Person not found in census.', 404);
        }

        // Step 2: Get the hof_its (Head of Family)
        $hofIts = $person->hof_id;

        // Step 3: Get all family members (including HOF)
        $familyMembers = Census::where('hof_id', $hofIts)->get();
        $familyItsIds = $familyMembers->pluck('its_id')->unique()->values()->toArray();

        // Step 4: Check if hof_its has wajebaat grouping for this miqaat
        $groupItsIds = [];
        $groupInfo = null;

        // Check if hof_its is a master of a group
        $masterGroup = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->where('master_its', $hofIts)
            ->first();

        if ($masterGroup) {
            // Get all members of this group
            $groupMembers = WajebaatGroup::query()
                ->forGroup($miqaatId, $masterGroup->wg_id)
                ->get();
            $groupItsIds = $groupMembers->pluck('its_id')->unique()->values()->toArray();
            
            $groupInfo = [
                'wg_id' => $masterGroup->wg_id,
                'group_name' => $masterGroup->group_name,
                'group_type' => $masterGroup->group_type?->value ?? $masterGroup->group_type,
            ];
        } else {
            // Check if hof_its is a member of a group (not master)
            $memberGroup = WajebaatGroup::query()
                ->forMember($miqaatId, $hofIts)
                ->first();

            if ($memberGroup) {
                // Get all members of this group
                $groupMembers = WajebaatGroup::query()
                    ->forGroup($miqaatId, $memberGroup->wg_id)
                    ->get();
                $groupItsIds = $groupMembers->pluck('its_id')->unique()->values()->toArray();
                
                $groupInfo = [
                    'wg_id' => $memberGroup->wg_id,
                    'group_name' => $memberGroup->group_name,
                    'group_type' => $memberGroup->group_type?->value ?? $memberGroup->group_type,
                ];
            }
        }

        // Step 5: Combine family and group ITS IDs (remove duplicates)
        $allRelatedItsIds = collect($familyItsIds)
            ->merge($groupItsIds)
            ->unique()
            ->values()
            ->toArray();

        return $this->jsonSuccessWithData([
            'its_id' => $itsId,
            'hof_its' => $hofIts,
            'family_its_ids' => $familyItsIds,
            'group_its_ids' => $groupItsIds,
            'all_related_its_ids' => $allRelatedItsIds,
            'group_info' => $groupInfo,
        ]);
    }

    /**
     * GET: Get all wajebaat records for related ITS IDs (family + group families).
     * 
     * This endpoint:
     * 1. Finds the person's family (using hof_id from census)
     * 2. Checks if the person (or their hof_its) is part of a wajebaat group for the miqaat
     * 3. For each group member, gets their family members too
     * 4. Creates a full list of all ITS IDs (person's family + all group members' families)
     * 5. Fetches all wajebaat records for those ITS IDs in the given miqaat
     * 6. Returns all wajebaat records with relationships (category, person, etc.)
     */
    public function related(string $miqaat_id, string $its_id): JsonResponse
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

        // Step 1: Get the person's census record
        $person = Census::where('its_id', $itsId)->first();
        
        if (!$person) {
            return $this->jsonError('NOT_FOUND', 'Person not found in census.', 404);
        }

        // Step 2: Get the hof_its (Head of Family)
        $hofIts = $person->hof_id;

        // Step 3: Get all family members (including HOF)
        $familyMembers = Census::where('hof_id', $hofIts)->get();
        $familyItsIds = $familyMembers->pluck('its_id')->unique()->values()->toArray();

        // Step 4: Check if person or hof_its is part of a wajebaat group for this miqaat
        $groupMemberFamiliesItsIds = [];
        $groupInfo = null;

        // First, check if the person itself is in a group
        $personGroup = WajebaatGroup::query()
            ->forMember($miqaatId, $itsId)
            ->first();

        // If person is not in a group, check if hof_its is in a group
        if (!$personGroup) {
            $personGroup = WajebaatGroup::query()
                ->forMember($miqaatId, $hofIts)
                ->first();
        }

        // Also check if hof_its is a master of a group
        if (!$personGroup) {
            $personGroup = WajebaatGroup::query()
                ->where('miqaat_id', $miqaatId)
                ->where('master_its', $hofIts)
                ->first();
        }

        if ($personGroup) {
            // Get all members of this group
            $groupMembers = WajebaatGroup::query()
                ->forGroup($miqaatId, $personGroup->wg_id)
                ->get();
            $groupMemberItsIds = $groupMembers->pluck('its_id')->unique()->values()->all();
            
            // Also include the master_its in the list (master might not be in the members list)
            $masterIts = $personGroup->master_its;
            if (!in_array($masterIts, $groupMemberItsIds)) {
                $groupMemberItsIds[] = $masterIts;
            }
            
            $groupInfo = [
                'wg_id' => $personGroup->wg_id,
                'group_name' => $personGroup->group_name,
                'group_type' => $personGroup->group_type?->value ?? $personGroup->group_type,
            ];

            // Step 5: For each group member (including master), get their family members
            foreach ($groupMemberItsIds as $groupMemberIts) {
                $groupMember = Census::where('its_id', $groupMemberIts)->first();
                if ($groupMember) {
                    $memberHofIts = $groupMember->hof_id;
                    // Get all family members of this group member
                    $memberFamily = Census::where('hof_id', $memberHofIts)->get();
                    $memberFamilyItsIds = $memberFamily->pluck('its_id')->unique()->values()->all();
                    $groupMemberFamiliesItsIds = array_merge($groupMemberFamiliesItsIds, $memberFamilyItsIds);
                }
            }
            
            // Remove duplicates from group member families and re-index
            $groupMemberFamiliesItsIds = array_values(array_unique($groupMemberFamiliesItsIds));
        }

        // Step 6: Combine person's family and all group members' families (remove duplicates)
        $allRelatedItsIds = collect($familyItsIds)
            ->merge($groupMemberFamiliesItsIds)
            ->unique()
            ->values()
            ->toArray();

        // Step 7: Fetch all wajebaat records for these ITS IDs in the given miqaat
        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $allRelatedItsIds)
            ->with('category')
            ->orderBy('its_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($w) {
                return $this->formatWajebaatWithCategoryAndAmounts($w);
            });

        return $this->jsonSuccessWithData([
            'its_id' => $itsId,
            'hof_its' => $hofIts,
            'family_its_ids' => $familyItsIds,
            'group_member_families_its_ids' => array_values($groupMemberFamiliesItsIds),
            'all_related_its_ids' => $allRelatedItsIds,
            'group_info' => $groupInfo,
            'wajebaats' => $wajebaats,
        ]);
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

        // Department Guard: Before saving paid status, check miqaat_checks at HoF level
        if ($paid) {
            // Get the person's census record to find HoF
            $person = Census::where('its_id', $itsId)->first();
            if (!$person) {
                return $this->jsonError('NOT_FOUND', 'Person not found in census.', 404);
            }

            // Get HoF its_id (if person is HoF themselves, hof_id = its_id)
            $hofItsId = $person->hof_id ?? $itsId;

            // Collect all HoF its_ids that need clearance checks
            $hofItsIdsToCheck = [$hofItsId];

            // Check if the HoF is part of a wajebaat group (as master or member)
            $hofGroup = WajebaatGroup::query()
                ->where('miqaat_id', $miqaatId)
                ->where(function ($q) use ($hofItsId) {
                    $q->where('master_its', $hofItsId)
                      ->orWhere('its_id', $hofItsId);
                })
                ->first();

            if ($hofGroup) {
                $wgId = $hofGroup->wg_id;

                // Get all group members (including master)
                $groupMembers = WajebaatGroup::query()
                    ->where('miqaat_id', $miqaatId)
                    ->where('wg_id', $wgId)
                    ->get(['its_id']);

                // Also include the master_its if it's not already in the members list
                $masterIts = $hofGroup->master_its;
                $groupMemberItsIds = $groupMembers->pluck('its_id')->toArray();
                if ($masterIts && !in_array($masterIts, $groupMemberItsIds)) {
                    $groupMemberItsIds[] = $masterIts;
                }

                // Get HoF its_ids for all group members
                if (!empty($groupMemberItsIds)) {
                    $groupMemberCensus = Census::whereIn('its_id', $groupMemberItsIds)
                        ->get(['its_id', 'hof_id']);

                    // Collect unique HoF its_ids from group members
                    $groupHofItsIds = $groupMemberCensus
                        ->pluck('hof_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    // Merge with existing list (avoid duplicates)
                    $hofItsIdsToCheck = array_unique(array_merge($hofItsIdsToCheck, $groupHofItsIds));
                }
            }

            // Check clearance for all relevant HoF its_ids
            // If any HoF has pending departments, the member cannot be marked paid
            $allPending = [];
            foreach ($hofItsIdsToCheck as $hofIts) {
                $pending = $this->pendingDepartmentChecks($miqaatId, $hofIts);
                
                // Merge pending departments (avoid duplicates by mcd_id)
                foreach ($pending as $dept) {
                    $exists = false;
                    foreach ($allPending as $existing) {
                        if ($existing['mcd_id'] === $dept['mcd_id']) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $allPending[] = $dept;
                    }
                }
            }

            if (!empty($allPending)) {
                return response()->json([
                    'success' => false,
                    'error' => 'DEPARTMENT_CHECKS_PENDING',
                    'message' => 'Cannot mark as paid: department checks are pending for Head of Family or group members.',
                    'pending_departments' => $allPending, // List of { mcd_id, name, user_type } for pending departments
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

        // Get the person's census record to find HoF
        $person = Census::where('its_id', $itsId)->first();
        if (!$person) {
            return $this->jsonError('NOT_FOUND', 'Person not found in census.', 404);
        }

        // Get HoF its_id (if person is HoF themselves, hof_id = its_id)
        $hofItsId = $person->hof_id ?? $itsId;

        // Collect all HoF its_ids that need clearance checks
        $hofItsIdsToCheck = [$hofItsId];

        // Check if the HoF is part of a wajebaat group (as master or member)
        $hofGroup = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->where(function ($q) use ($hofItsId) {
                $q->where('master_its', $hofItsId)
                  ->orWhere('its_id', $hofItsId);
            })
            ->first();

        if ($hofGroup) {
            $wgId = $hofGroup->wg_id;

            // Get all group members (including master)
            $groupMembers = WajebaatGroup::query()
                ->where('miqaat_id', $miqaatId)
                ->where('wg_id', $wgId)
                ->get(['its_id']);

            // Also include the master_its if it's not already in the members list
            $masterIts = $hofGroup->master_its;
            $groupMemberItsIds = $groupMembers->pluck('its_id')->toArray();
            if ($masterIts && !in_array($masterIts, $groupMemberItsIds)) {
                $groupMemberItsIds[] = $masterIts;
            }

            // Get HoF its_ids for all group members
            if (!empty($groupMemberItsIds)) {
                $groupMemberCensus = Census::whereIn('its_id', $groupMemberItsIds)
                    ->get(['its_id', 'hof_id']);

                // Collect unique HoF its_ids from group members
                $groupHofItsIds = $groupMemberCensus
                    ->pluck('hof_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                // Merge with existing list (avoid duplicates)
                $hofItsIdsToCheck = array_unique(array_merge($hofItsIdsToCheck, $groupHofItsIds));
            }
        }

        // Get wajebaat record for the original its_id (not HoF)
        $wajebaat = Wajebaat::query()
            ->forItsInMiqaat($itsId, $miqaatId)
            ->first();

        // Check clearance for all relevant HoF its_ids
        // If any HoF has pending departments, the member cannot be marked paid
        $allPending = [];
        $hofClearanceStatus = [];

        foreach ($hofItsIdsToCheck as $hofIts) {
            $pending = $this->pendingDepartmentChecks($miqaatId, $hofIts);
            $hofClearanceStatus[] = [
                'hof_its_id' => $hofIts,
                'pending_departments' => $pending,
                'can_mark_paid' => empty($pending),
            ];

            // Merge pending departments (avoid duplicates by mcd_id)
            foreach ($pending as $dept) {
                $exists = false;
                foreach ($allPending as $existing) {
                    if ($existing['mcd_id'] === $dept['mcd_id']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $allPending[] = $dept;
                }
            }
        }

        $data = [
            'wajebaat' => $wajebaat,
            'hof_its_id' => $hofItsId,
            'hof_clearance_status' => $hofClearanceStatus,
            'pending_departments' => $allPending,
            'can_mark_paid' => empty($allPending),
        ];

        return $this->jsonSuccessWithData($data);
    }

    /**
     * POST: Re-categorize wajebaat record(s).
     * 
     * This endpoint re-runs the categorization logic for wajebaat records.
     * Useful when:
     * - Currency conversion rates are updated
     * - Category definitions are changed
     * - Need to bulk re-categorize existing records
     * 
     * Can categorize:
     * - A single wajebaat record (by miqaat_id and its_id) - use route with its_id
     * - All wajebaat records for a miqaat - use route without its_id
     */
    public function categorize(Request $request, string $miqaat_id, ?string $its_id = null): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), [
            'miqaat_id' => $miqaat_id,
            'its_id' => $its_id,
        ]), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'its_id' => ['nullable', 'string', 'exists:census,its_id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $miqaatId = (int) $miqaat_id;
        $itsId = $its_id ? (string) $its_id : null;

        // Check if categories exist for this miqaat
        $categoryCount = WajCategory::where('miqaat_id', $miqaatId)->count();
        $warning = null;
        if ($categoryCount === 0) {
            $warning = "No wajebaat categories found for miqaat_id={$miqaatId}. All wc_id values will be NULL. Please create categories first.";
        }

        if ($itsId) {
            // For specific ITS ID, we need to determine if it's isolated, part of a group, or normal HoF
            // Then trigger categorization for the appropriate aggregation unit
            $person = Census::where('its_id', $itsId)->first();
            if (!$person) {
                return $this->jsonError('NOT_FOUND', 'Person not found in census.', 404);
            }

            $hofItsId = $person->hof_id ?? $itsId;

            // Check if isolated
            $wajebaat = Wajebaat::query()
                ->where('miqaat_id', $miqaatId)
                ->where('its_id', $itsId)
                ->first();

            if ($wajebaat && $wajebaat->is_isolated) {
                // Categorize individually
                try {
                    $oldWcId = $wajebaat->wc_id;
                    $this->wajebaatService->categorize($wajebaat, true);
                    $wajebaat->refresh();
                    $categorized = [[
                        'id' => $wajebaat->id,
                        'its_id' => $wajebaat->its_id,
                        'amount' => $wajebaat->amount,
                        'currency' => $wajebaat->currency,
                        'old_wc_id' => $oldWcId,
                        'new_wc_id' => $wajebaat->wc_id,
                        'type' => 'isolated',
                    ]];
                } catch (\Exception $e) {
                    return $this->jsonError('CATEGORIZATION_ERROR', $e->getMessage(), 500);
                }
            } else {
                // Check if HoF is a group master
                $group = WajebaatGroup::query()
                    ->where('miqaat_id', $miqaatId)
                    ->where('master_its', $hofItsId)
                    ->first();

                if ($group) {
                    // Categorize as group master
                    $this->wajebaatService->categorizeGroupMaster($miqaatId, $hofItsId, $group->wg_id);
                } else {
                    // Categorize as normal HoF
                    $this->wajebaatService->categorizeHoF($miqaatId, $hofItsId);
                }

                // Get updated wajebaat records
                $wajebaats = Wajebaat::query()
                    ->where('miqaat_id', $miqaatId)
                    ->where('its_id', $itsId)
                    ->get();

                $categorized = [];
                foreach ($wajebaats as $w) {
                    $categorized[] = [
                        'id' => $w->id,
                        'its_id' => $w->its_id,
                        'amount' => $w->amount,
                        'currency' => $w->currency,
                        'new_wc_id' => $w->wc_id,
                        'type' => $group ? 'group_master' : 'hof',
                    ];
                }
            }

            $responseData = [
                'categorized_count' => count($categorized),
                'categorized' => $categorized,
            ];
        } else {
            // Categorize all wajebaat records for this miqaat using aggregation logic
            $stats = $this->wajebaatService->categorizeWithAggregation($miqaatId);

            // Get all updated wajebaat records
            $wajebaats = Wajebaat::query()
                ->where('miqaat_id', $miqaatId)
                ->get(['id', 'its_id', 'amount', 'currency', 'wc_id', 'is_isolated']);

            $responseData = [
                'categorized_count' => $wajebaats->count(),
                'isolated_count' => $stats['isolated_count'],
                'group_masters_count' => $stats['group_masters_count'],
                'hof_count' => $stats['hof_count'],
                'errors_count' => count($stats['errors']),
                'errors' => $stats['errors'],
            ];
        }

        if ($warning) {
            $responseData['warning'] = $warning;
        }

        return $this->jsonSuccessWithData($responseData);
    }

    /**
     * GET: Get aggregated amounts (LKR and INR) for a member.
     * 
     * Returns the final aggregated amount for:
     * - Group: If member is a group master, aggregates all group members' families
     * - Family: If member is a normal HoF, aggregates all family members
     * - Individual: If member is isolated, returns their own amount
     * 
     * This endpoint does NOT categorize, only calculates and returns amounts.
     */
    public function getAggregatedAmounts(string $miqaat_id, string $its_id): JsonResponse
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

        // Get the person's census record
        $person = Census::where('its_id', $itsId)->first();
        if (!$person) {
            return $this->jsonError('NOT_FOUND', 'Person not found in census.', 404);
        }

        // Get wajebaat record to check if isolated
        $wajebaat = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->where('its_id', $itsId)
            ->first();

        $hofItsId = $person->hof_id ?? $itsId;
        $isIsolated = $wajebaat && $wajebaat->is_isolated;

        $totalAmountInr = 0;
        $totalAmountsByCurrency = [];
        $type = '';
        $details = [];

        if ($isIsolated) {
            // Individual: return own amount
            if ($wajebaat) {
                $baseCurrency = $wajebaat->currency ?? 'LKR';
                $baseAmount = (float) $wajebaat->amount;
                $totalAmountInr = $this->wajebaatService->convertToInr(
                    $baseCurrency,
                    $baseAmount,
                    $wajebaat->created_at ? new \DateTime($wajebaat->created_at) : null
                );
                
                $totalAmountsByCurrency[$baseCurrency] = $baseAmount;
                $type = 'individual';
                $details = [
                    'its_id' => $itsId,
                    'amount' => $baseAmount,
                    'currency' => $baseCurrency,
                ];
            }
        } else {
            // Check if HoF is a group master
            $group = WajebaatGroup::query()
                ->where('miqaat_id', $miqaatId)
                ->where('master_its', $hofItsId)
                ->first();

            if ($group) {
                // Group master: aggregate all group members' families
                $result = $this->getGroupMasterAmounts($miqaatId, $hofItsId, $group->wg_id);
                $totalAmountInr = $result['total_inr'];
                $totalAmountsByCurrency = $result['total_by_currency'];
                $type = 'group';
                $details = $result['details'];
            } else {
                // Normal HoF: aggregate family members
                $result = $this->getHoFAmounts($miqaatId, $hofItsId);
                $totalAmountInr = $result['total_inr'];
                $totalAmountsByCurrency = $result['total_by_currency'];
                $type = 'family';
                $details = $result['details'];
            }
        }

        // Format amounts by currency for response
        $amountsByCurrency = [];
        foreach ($totalAmountsByCurrency as $currency => $amount) {
            $amountsByCurrency[] = [
                'currency' => $currency,
                'amount' => round($amount, 2),
            ];
        }

        return $this->jsonSuccessWithData([
            'its_id' => $itsId,
            'hof_its_id' => $hofItsId,
            'type' => $type,
            'is_isolated' => $isIsolated,
            'total_amount_inr' => round($totalAmountInr, 2),
            'total_amounts_by_currency' => $amountsByCurrency,
            'details' => $details,
        ]);
    }

    /**
     * Get aggregated amounts for a group master.
     */
    protected function getGroupMasterAmounts(int $miqaatId, string $masterItsId, int $wgId): array
    {
        // Get all group members
        $groupMembers = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->where('wg_id', $wgId)
            ->get(['its_id']);

        $groupMemberItsIds = $groupMembers->pluck('its_id')->toArray();
        
        // Also include master_its if not already in members list
        if (!in_array($masterItsId, $groupMemberItsIds)) {
            $groupMemberItsIds[] = $masterItsId;
        }

        // Get all family members for all group members
        $allFamilyItsIds = [];
        foreach ($groupMemberItsIds as $memberItsId) {
            $familyMembers = Census::where('hof_id', $memberItsId)
                ->orWhere('its_id', $memberItsId)
                ->pluck('its_id')
                ->toArray();
            $allFamilyItsIds = array_merge($allFamilyItsIds, $familyMembers);
        }

        $allFamilyItsIds = array_unique($allFamilyItsIds);

        // Get all wajebaat records for these family members (excluding isolated)
        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $allFamilyItsIds)
            ->where('is_isolated', false)
            ->get();

        $totalAmountInr = 0;
        $totalAmountsByCurrency = [];
        $breakdown = [];

        foreach ($wajebaats as $wajebaat) {
            $date = $wajebaat->created_at ? new \DateTime($wajebaat->created_at) : null;
            $baseCurrency = $wajebaat->currency ?? 'LKR';
            $baseAmount = (float) $wajebaat->amount;
            $amountInInr = $this->wajebaatService->convertToInr(
                $baseCurrency,
                $baseAmount,
                $date
            );
            
            // Track totals by currency
            if (!isset($totalAmountsByCurrency[$baseCurrency])) {
                $totalAmountsByCurrency[$baseCurrency] = 0;
            }
            $totalAmountsByCurrency[$baseCurrency] += $baseAmount;
            $totalAmountInr += $amountInInr;

            $breakdown[] = [
                'its_id' => $wajebaat->its_id,
                'amount' => $baseAmount,
                'currency' => $baseCurrency,
                'amount_inr' => round($amountInInr, 2),
            ];
        }

        return [
            'total_inr' => $totalAmountInr,
            'total_by_currency' => $totalAmountsByCurrency,
            'details' => [
                'master_its' => $masterItsId,
                'wg_id' => $wgId,
                'group_members' => $groupMemberItsIds,
                'family_members_count' => count($allFamilyItsIds),
                'wajebaat_records_count' => $wajebaats->count(),
                'breakdown' => $breakdown,
            ],
        ];
    }

    /**
     * Get aggregated amounts for a normal HoF.
     */
    protected function getHoFAmounts(int $miqaatId, string $hofItsId): array
    {
        // Get all family members
        $familyMembers = Census::where('hof_id', $hofItsId)
            ->orWhere('its_id', $hofItsId)
            ->pluck('its_id')
            ->toArray();

        // Get all wajebaat records for these family members (excluding isolated)
        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $familyMembers)
            ->where('is_isolated', false)
            ->get();

        $totalAmountInr = 0;
        $totalAmountsByCurrency = [];
        $breakdown = [];

        foreach ($wajebaats as $wajebaat) {
            $date = $wajebaat->created_at ? new \DateTime($wajebaat->created_at) : null;
            $baseCurrency = $wajebaat->currency ?? 'LKR';
            $baseAmount = (float) $wajebaat->amount;
            $amountInInr = $this->wajebaatService->convertToInr(
                $baseCurrency,
                $baseAmount,
                $date
            );
            
            // Track totals by currency
            if (!isset($totalAmountsByCurrency[$baseCurrency])) {
                $totalAmountsByCurrency[$baseCurrency] = 0;
            }
            $totalAmountsByCurrency[$baseCurrency] += $baseAmount;
            $totalAmountInr += $amountInInr;

            $breakdown[] = [
                'its_id' => $wajebaat->its_id,
                'amount' => $baseAmount,
                'currency' => $baseCurrency,
                'amount_inr' => round($amountInInr, 2),
            ];
        }

        return [
            'total_inr' => $totalAmountInr,
            'total_by_currency' => $totalAmountsByCurrency,
            'details' => [
                'hof_its' => $hofItsId,
                'family_members_count' => count($familyMembers),
                'wajebaat_records_count' => $wajebaats->count(),
                'breakdown' => $breakdown,
            ],
        ];
    }

    /**
     * Convert amount to LKR.
     */
    protected function convertToLkr(string $fromCurrency, float $amount): float
    {
        if ($fromCurrency === 'LKR') {
            return $amount;
        }

        // Convert to INR first, then to LKR
        $amountInInr = $this->wajebaatService->convertToInr($fromCurrency, $amount);
        
        // Get LKR to INR rate and calculate inverse
        $rate = \App\Models\CurrencyConversion::getRate('LKR', 'INR');
        if ($rate) {
            // If 1 LKR = rate INR, then 1 INR = 1/rate LKR
            return $amountInInr / $rate;
        }

        // Fallback: if no rate, return original amount
        return $amount;
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
        
        // Also include the master_its in the list (master might not be in the members list)
        $masterIts = $groupRow->master_its;
        $allItsIds = $memberIts->merge([$masterIts])->unique()->values();

        // Get census records for all members (including master)
        $people = Census::query()
            ->whereIn('its_id', $allItsIds)
            ->get()
            ->keyBy('its_id');

        // Get wajebaat records for all members (including master) in this miqaat
        // Note: A person can have multiple wajebaat records (personal with wg_id=NULL and group with wg_id)
        $wajebaats = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->whereIn('its_id', $allItsIds)
            ->with('category')
            ->get()
            ->groupBy('its_id'); // Group by its_id to get all records per person

        return [
            'wg_id' => $wgId,
            'master_its' => (string) $groupRow->master_its,
            'group_name' => $groupRow->group_name,
            'group_type' => $groupRow->group_type?->value ?? $groupRow->group_type,
            'members' => $allItsIds->map(function ($mIts) use ($people, $wajebaats) {
                $personWajebaats = $wajebaats->get($mIts);
                return [
                    'its_id' => (string) $mIts,
                    'person' => $people->get($mIts),
                    'wajebaat' => $personWajebaats ? $personWajebaats->map(function ($w) {
                        return $this->formatWajebaatWithCategoryAndAmounts($w);
                    })->values()->all() : null, // Return all records as array with category and amounts
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
     * GET: wajebaat group by master ITS ID.
     * Returns the group where the specified ITS ID is the master.
     */
    public function getByMaster(string $miqaat_id, string $its_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
            'its_id' => $its_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'its_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid miqaat_id or its_id.',
                422
            );
        }

        $miqaatId = (int) $miqaat_id;

        // Find group where this ITS ID is the master
        $groupRow = WajebaatGroup::query()
            ->where('miqaat_id', $miqaatId)
            ->where('master_its', $its_id)
            ->first();

        if (!$groupRow) {
            return $this->jsonError(
                'NOT_FOUND',
                'No wajebaat group found where this ITS ID is the master.',
                404
            );
        }

        $wgId = $groupRow->wg_id;
        $groupData = $this->buildGroupData($miqaatId, $wgId);

        if (!$groupData) {
            return $this->jsonError(
                'NOT_FOUND',
                'Wajebaat group data not found.',
                404
            );
        }

        return $this->jsonSuccessWithData($groupData);
    }

    /**
     * GET: wajebaat group by member ITS ID.
     * Returns the group where the specified ITS ID is a member (not necessarily the master).
     */
    public function getByMember(string $miqaat_id, string $its_id): JsonResponse
    {
        $validator = Validator::make([
            'miqaat_id' => $miqaat_id,
            'its_id' => $its_id,
        ], [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'its_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid miqaat_id or its_id.',
                422
            );
        }

        $miqaatId = (int) $miqaat_id;

        // First, check if there's a wajebaat record with a wg_id for this member
        $wajebaat = Wajebaat::query()
            ->where('miqaat_id', $miqaatId)
            ->where('its_id', $its_id)
            ->whereNotNull('wg_id')
            ->first();

        if ($wajebaat) {
            // Found wajebaat with wg_id, get the group
            $wgId = $wajebaat->wg_id;
            $groupData = $this->buildGroupData($miqaatId, $wgId);

            if ($groupData) {
                return $this->jsonSuccessWithData($groupData);
            }
        }

        // Fallback: check wajebaat_groups table directly (in case wajebaat record doesn't exist yet)
        $groupRow = WajebaatGroup::query()
            ->forMember($miqaatId, $its_id)
            ->first();

        if (!$groupRow) {
            return $this->jsonError(
                'NOT_FOUND',
                'No wajebaat group found where this ITS ID is a member.',
                404
            );
        }

        $wgId = $groupRow->wg_id;
        $groupData = $this->buildGroupData($miqaatId, $wgId);

        if (!$groupData) {
            return $this->jsonError(
                'NOT_FOUND',
                'Wajebaat group data not found.',
                404
            );
        }

        return $this->jsonSuccessWithData($groupData);
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
     * Format wajebaat for API response with category and amounts in base currency and INR.
     */
    protected function formatWajebaatWithCategoryAndAmounts(Wajebaat $wajebaat): array
    {
        $data = $wajebaat->toArray();
        $data['is_isolated'] = (bool) ($wajebaat->is_isolated ?? false);

        // Get category information
        $category = $wajebaat->relationLoaded('category') ? $wajebaat->category : $wajebaat->category;
        
        // Calculate amounts
        $baseCurrency = $wajebaat->currency ?? 'LKR';
        $baseAmount = (float) $wajebaat->amount;
        
        // Convert to INR
        $date = $wajebaat->created_at ? new \DateTime($wajebaat->created_at) : null;
        $amountInInr = $this->wajebaatService->convertToInr($baseCurrency, $baseAmount, $date);
        
        // Convert to LKR (if not already LKR)
        $amountInLkr = $this->convertToLkr($baseCurrency, $baseAmount);

        // Format category with amounts
        $data['category'] = $category !== null
            ? [
                'wc_id' => (int) $category->wc_id,
                'name' => (string) $category->name,
                'hex_color' => (string) ($category->hex_color ?? ''),
                'amount_base_currency' => round($baseAmount, 2),
                'base_currency' => $baseCurrency,
                'amount_inr' => round($amountInInr, 2),
                'amount_lkr' => round($amountInLkr, 2),
            ]
            : null;

        // Also add amounts to the main data object for easy access
        $data['amount_base_currency'] = round($baseAmount, 2);
        $data['base_currency'] = $baseCurrency;
        $data['amount_inr'] = round($amountInInr, 2);
        $data['amount_lkr'] = round($amountInLkr, 2);

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

