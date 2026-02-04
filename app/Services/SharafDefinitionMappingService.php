<?php

namespace App\Services;

use App\Models\PaymentDefinition;
use App\Models\PaymentDefinitionMapping;
use App\Models\Sharaf;
use App\Models\SharafDefinition;
use App\Models\SharafDefinitionMapping;
use App\Models\SharafMember;
use App\Models\SharafPayment;
use App\Models\SharafPosition;
use App\Models\SharafPositionMapping;
use App\Models\SharafShiftAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SharafDefinitionMappingService
{
    /**
     * Create a new sharaf definition mapping.
     */
    public function createMapping(int $sourceId, int $targetId, ?string $createdByIts = null, ?string $notes = null): SharafDefinitionMapping
    {
        // Validate both definitions exist
        $sourceDefinition = SharafDefinition::findOrFail($sourceId);
        $targetDefinition = SharafDefinition::findOrFail($targetId);

        // Validate definitions are from different events
        if ($sourceDefinition->event_id === $targetDefinition->event_id) {
            throw new \InvalidArgumentException('Source and target sharaf definitions must be from different events.');
        }

        // Validate source != target
        if ($sourceId === $targetId) {
            throw new \InvalidArgumentException('Source and target sharaf definitions cannot be the same.');
        }

        // Check for existing mapping (bidirectional check)
        $existingMapping = SharafDefinitionMapping::where(function ($query) use ($sourceId, $targetId) {
            $query->where('source_sharaf_definition_id', $sourceId)
                  ->where('target_sharaf_definition_id', $targetId);
        })->orWhere(function ($query) use ($sourceId, $targetId) {
            $query->where('source_sharaf_definition_id', $targetId)
                  ->where('target_sharaf_definition_id', $sourceId);
        })->first();

        if ($existingMapping) {
            throw new \InvalidArgumentException('A mapping already exists between these two sharaf definitions.');
        }

        // Check for circular mappings
        $this->checkCircularMapping($sourceId, $targetId);

        // Create the mapping
        return SharafDefinitionMapping::create([
            'source_sharaf_definition_id' => $sourceId,
            'target_sharaf_definition_id' => $targetId,
            'created_by_its' => $createdByIts,
            'notes' => $notes,
            'is_active' => true,
        ]);
    }

    /**
     * Get all mappings, optionally filtered by definition ID.
     */
    public function getMappings(?int $definitionId = null): Collection
    {
        $query = SharafDefinitionMapping::with(['sourceDefinition.event', 'targetDefinition.event', 'positionMappings', 'paymentDefinitionMappings']);

        if ($definitionId !== null) {
            $query->forDefinition($definitionId);
        }

        return $query->get();
    }

    /**
     * Delete a mapping (cascade deletes position and payment mappings).
     */
    public function deleteMapping(int $mappingId): void
    {
        $mapping = SharafDefinitionMapping::findOrFail($mappingId);
        $mapping->delete(); // Cascade delete will handle position and payment mappings
    }

    /**
     * Add a position mapping.
     */
    public function addPositionMapping(int $mappingId, int $sourcePositionId, int $targetPositionId): SharafPositionMapping
    {
        $mapping = SharafDefinitionMapping::findOrFail($mappingId);

        // Validate positions exist
        $sourcePosition = SharafPosition::findOrFail($sourcePositionId);
        $targetPosition = SharafPosition::findOrFail($targetPositionId);

        // Validate positions belong to correct definitions
        if ($sourcePosition->sharaf_definition_id !== $mapping->source_sharaf_definition_id) {
            throw new \InvalidArgumentException('Source position does not belong to the source sharaf definition.');
        }

        if ($targetPosition->sharaf_definition_id !== $mapping->target_sharaf_definition_id) {
            throw new \InvalidArgumentException('Target position does not belong to the target sharaf definition.');
        }

        // Validate source != target
        if ($sourcePositionId === $targetPositionId) {
            throw new \InvalidArgumentException('Source and target positions cannot be the same.');
        }

        // Check for duplicate mappings
        $existing = SharafPositionMapping::where('sharaf_definition_mapping_id', $mappingId)
            ->where(function ($query) use ($sourcePositionId, $targetPositionId) {
                $query->where('source_sharaf_position_id', $sourcePositionId)
                      ->orWhere('target_sharaf_position_id', $targetPositionId);
            })
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('A position mapping already exists for this position.');
        }

        return SharafPositionMapping::create([
            'sharaf_definition_mapping_id' => $mappingId,
            'source_sharaf_position_id' => $sourcePositionId,
            'target_sharaf_position_id' => $targetPositionId,
        ]);
    }

    /**
     * Remove a position mapping.
     */
    public function removePositionMapping(int $positionMappingId): void
    {
        $positionMapping = SharafPositionMapping::findOrFail($positionMappingId);
        $positionMapping->delete();
    }

    /**
     * Add a payment definition mapping.
     */
    public function addPaymentDefinitionMapping(int $mappingId, int $sourcePaymentDefId, int $targetPaymentDefId): PaymentDefinitionMapping
    {
        $mapping = SharafDefinitionMapping::findOrFail($mappingId);

        // Validate payment definitions exist
        $sourcePaymentDef = PaymentDefinition::findOrFail($sourcePaymentDefId);
        $targetPaymentDef = PaymentDefinition::findOrFail($targetPaymentDefId);

        // Validate payment definitions belong to correct definitions
        if ($sourcePaymentDef->sharaf_definition_id !== $mapping->source_sharaf_definition_id) {
            throw new \InvalidArgumentException('Source payment definition does not belong to the source sharaf definition.');
        }

        if ($targetPaymentDef->sharaf_definition_id !== $mapping->target_sharaf_definition_id) {
            throw new \InvalidArgumentException('Target payment definition does not belong to the target sharaf definition.');
        }

        // Validate source != target
        if ($sourcePaymentDefId === $targetPaymentDefId) {
            throw new \InvalidArgumentException('Source and target payment definitions cannot be the same.');
        }

        // Check for duplicate mappings
        $existing = PaymentDefinitionMapping::where('sharaf_definition_mapping_id', $mappingId)
            ->where(function ($query) use ($sourcePaymentDefId, $targetPaymentDefId) {
                $query->where('source_payment_definition_id', $sourcePaymentDefId)
                      ->orWhere('target_payment_definition_id', $targetPaymentDefId);
            })
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('A payment definition mapping already exists for this payment definition.');
        }

        return PaymentDefinitionMapping::create([
            'sharaf_definition_mapping_id' => $mappingId,
            'source_payment_definition_id' => $sourcePaymentDefId,
            'target_payment_definition_id' => $targetPaymentDefId,
        ]);
    }

    /**
     * Remove a payment definition mapping.
     */
    public function removePaymentDefinitionMapping(int $paymentMappingId): void
    {
        $paymentMapping = PaymentDefinitionMapping::findOrFail($paymentMappingId);
        $paymentMapping->delete();
    }

    /**
     * Validate that all required mappings exist.
     * 
     * @param int $mappingId
     * @param array|null $sharafIds Optional array of sharaf IDs to validate. If null, validates all sharafs in source definition.
     * @param bool $reverseDirection If true, validates in reverse direction (target -> source)
     * @return array
     */
    public function validateMappingComplete(int $mappingId, ?array $sharafIds = null, bool $reverseDirection = false): array
    {
        $mapping = SharafDefinitionMapping::with(['sourceDefinition.sharafs.sharafMembers', 'sourceDefinition.sharafs.sharafPayments', 'targetDefinition.sharafs.sharafMembers', 'targetDefinition.sharafs.sharafPayments'])->findOrFail($mappingId);

        $missingPositionMappings = [];
        $missingPaymentMappings = [];

        // Determine which definition to check based on direction
        $sourceDefinitionId = $reverseDirection ? $mapping->target_sharaf_definition_id : $mapping->source_sharaf_definition_id;
        $targetDefinitionId = $reverseDirection ? $mapping->source_sharaf_definition_id : $mapping->target_sharaf_definition_id;

        // Get position mappings - reverse lookup if reverse direction
        $positionMappings = [];
        foreach ($mapping->positionMappings as $posMapping) {
            if ($reverseDirection) {
                // Reverse: target -> source
                $positionMappings[$posMapping->target_sharaf_position_id] = $posMapping->source_sharaf_position_id;
            } else {
                // Normal: source -> target
                $positionMappings[$posMapping->source_sharaf_position_id] = $posMapping->target_sharaf_position_id;
            }
        }

        // Get payment definition mappings - reverse lookup if reverse direction
        $paymentMappings = [];
        foreach ($mapping->paymentDefinitionMappings as $payMapping) {
            if ($reverseDirection) {
                // Reverse: target -> source
                $paymentMappings[$payMapping->target_payment_definition_id] = $payMapping->source_payment_definition_id;
            } else {
                // Normal: source -> target
                $paymentMappings[$payMapping->source_payment_definition_id] = $payMapping->target_payment_definition_id;
            }
        }

        // Check positions used in source sharafs (filtered by sharaf_ids if provided)
        $usedPositionsQuery = SharafMember::whereHas('sharaf', function ($query) use ($sourceDefinitionId, $sharafIds) {
            $query->where('sharaf_definition_id', $sourceDefinitionId);
            if ($sharafIds !== null && !empty($sharafIds)) {
                $query->whereIn('id', $sharafIds);
            }
        });
        $usedPositions = $usedPositionsQuery->distinct()->pluck('sharaf_position_id')->toArray();

        foreach ($usedPositions as $positionId) {
            if (!isset($positionMappings[$positionId])) {
                $position = SharafPosition::find($positionId);
                $missingPositionMappings[] = [
                    'position_id' => $positionId,
                    'position_name' => $position ? $position->name : 'Unknown',
                ];
            }
        }

        // Check payment definitions used in source sharafs (filtered by sharaf_ids if provided)
        $usedPaymentDefinitionsQuery = SharafPayment::whereHas('sharaf', function ($query) use ($sourceDefinitionId, $sharafIds) {
            $query->where('sharaf_definition_id', $sourceDefinitionId);
            if ($sharafIds !== null && !empty($sharafIds)) {
                $query->whereIn('id', $sharafIds);
            }
        });
        $usedPaymentDefinitions = $usedPaymentDefinitionsQuery->distinct()->pluck('payment_definition_id')->toArray();

        foreach ($usedPaymentDefinitions as $paymentDefId) {
            if (!isset($paymentMappings[$paymentDefId])) {
                $paymentDef = PaymentDefinition::find($paymentDefId);
                $missingPaymentMappings[] = [
                    'payment_definition_id' => $paymentDefId,
                    'payment_definition_name' => $paymentDef ? $paymentDef->name : 'Unknown',
                ];
            }
        }

        return [
            'is_complete' => empty($missingPositionMappings) && empty($missingPaymentMappings),
            'missing_position_mappings' => $missingPositionMappings,
            'missing_payment_mappings' => $missingPaymentMappings,
        ];
    }

    /**
     * Shift sharafs from source to target definition.
     * 
     * @param int $mappingId
     * @param ?string $shiftedByIts
     * @param ?array $sharafIds Optional array of sharaf IDs to shift. If null, shifts all sharafs from source definition.
     * @param bool $reverseDirection If true, shifts from target to source (reverse direction)
     * @return array
     */
    public function shiftSharafs(int $mappingId, ?string $shiftedByIts = null, ?array $sharafIds = null, bool $reverseDirection = false): array
    {
        $mapping = SharafDefinitionMapping::with(['sourceDefinition', 'targetDefinition', 'positionMappings', 'paymentDefinitionMappings'])->findOrFail($mappingId);

        // Validate mapping is active
        if (!$mapping->is_active) {
            throw new \InvalidArgumentException('Cannot shift sharafs using an inactive mapping.');
        }

        // Determine actual source and target based on direction
        $actualSourceDefinitionId = $reverseDirection ? $mapping->target_sharaf_definition_id : $mapping->source_sharaf_definition_id;
        $actualTargetDefinitionId = $reverseDirection ? $mapping->source_sharaf_definition_id : $mapping->target_sharaf_definition_id;

        // If specific sharaf IDs provided, validate they exist and belong to the actual source definition
        if ($sharafIds !== null && !empty($sharafIds)) {
            $invalidSharafs = Sharaf::whereIn('id', $sharafIds)
                ->where('sharaf_definition_id', '!=', $actualSourceDefinitionId)
                ->pluck('id')
                ->toArray();
            
            if (!empty($invalidSharafs)) {
                throw new \InvalidArgumentException('Some sharaf IDs do not belong to the source sharaf definition: ' . implode(', ', $invalidSharafs));
            }
        }

        // Validate all required mappings exist (only for selected sharafs if provided)
        $validation = $this->validateMappingComplete($mappingId, $sharafIds, $reverseDirection);
        if (!$validation['is_complete']) {
            throw new \InvalidArgumentException('Mapping is incomplete. Missing position or payment definition mappings.');
        }

        return DB::transaction(function () use ($mapping, $shiftedByIts, $validation, $sharafIds, $reverseDirection, $actualSourceDefinitionId, $actualTargetDefinitionId) {
            // Get sharafs from actual source definition (filtered by sharaf_ids if provided)
            $sourceSharafsQuery = Sharaf::where('sharaf_definition_id', $actualSourceDefinitionId);
            
            if ($sharafIds !== null && !empty($sharafIds)) {
                $sourceSharafsQuery->whereIn('id', $sharafIds);
            }
            
            $sourceSharafs = $sourceSharafsQuery
                ->orderBy('rank')
                ->with(['sharafMembers', 'sharafPayments'])
                ->get();

            if ($sourceSharafs->isEmpty()) {
                return [
                    'sharafs_shifted' => 0,
                    'members_shifted' => 0,
                    'clearances_shifted' => 0,
                    'payments_shifted' => 0,
                    'sharaf_ids' => [],
                    'rank_changes' => [],
                    'position_mappings_used' => [],
                    'payment_mappings_used' => [],
                    'audit_log_id' => null,
                ];
            }

            // Get existing sharafs from actual target definition
            $targetSharafs = Sharaf::where('sharaf_definition_id', $actualTargetDefinitionId)
                ->orderBy('rank')
                ->get();

            // Calculate rank offset to maintain relative order
            $maxTargetRank = $targetSharafs->max('rank') ?? 0;
            $minSourceRank = $sourceSharafs->min('rank') ?? 1;
            $offset = $maxTargetRank + 1 - $minSourceRank;

            // Build position mapping lookup (reverse if reverse direction)
            $positionMappingLookup = [];
            foreach ($mapping->positionMappings as $posMapping) {
                if ($reverseDirection) {
                    // Reverse: target -> source
                    $positionMappingLookup[$posMapping->target_sharaf_position_id] = $posMapping->source_sharaf_position_id;
                } else {
                    // Normal: source -> target
                    $positionMappingLookup[$posMapping->source_sharaf_position_id] = $posMapping->target_sharaf_position_id;
                }
            }

            // Build payment definition mapping lookup (reverse if reverse direction)
            $paymentMappingLookup = [];
            foreach ($mapping->paymentDefinitionMappings as $payMapping) {
                if ($reverseDirection) {
                    // Reverse: target -> source
                    $paymentMappingLookup[$payMapping->target_payment_definition_id] = $payMapping->source_payment_definition_id;
                } else {
                    // Normal: source -> target
                    $paymentMappingLookup[$payMapping->source_payment_definition_id] = $payMapping->target_payment_definition_id;
                }
            }

            $shiftedSharafIds = [];
            $rankChanges = [];
            $positionMappingsUsed = [];
            $paymentMappingsUsed = [];
            $totalMembers = 0;
            $totalPayments = 0;

            // Shift each sharaf
            foreach ($sourceSharafs as $sharaf) {
                $oldRank = $sharaf->rank;
                $newRank = $oldRank + $offset;

                // Update sharaf definition and rank
                $sharaf->sharaf_definition_id = $actualTargetDefinitionId;
                $sharaf->rank = $newRank;
                $sharaf->save();

                $shiftedSharafIds[] = $sharaf->id;
                $rankChanges[] = [
                    'sharaf_id' => $sharaf->id,
                    'old_rank' => $oldRank,
                    'new_rank' => $newRank,
                ];

                // Update sharaf members' positions
                foreach ($sharaf->sharafMembers as $member) {
                    $oldPositionId = $member->sharaf_position_id;
                    if (isset($positionMappingLookup[$oldPositionId])) {
                        $newPositionId = $positionMappingLookup[$oldPositionId];
                        $member->sharaf_position_id = $newPositionId;
                        $member->save();

                        // Track the mapping used (source -> target)
                        $mappingKey = $oldPositionId . '->' . $newPositionId;
                        if (!in_array($mappingKey, $positionMappingsUsed)) {
                            $positionMappingsUsed[] = $mappingKey;
                        }
                    }
                    $totalMembers++;
                }

                // Update sharaf payments' payment definitions
                foreach ($sharaf->sharafPayments as $payment) {
                    $oldPaymentDefId = $payment->payment_definition_id;
                    if (isset($paymentMappingLookup[$oldPaymentDefId])) {
                        $newPaymentDefId = $paymentMappingLookup[$oldPaymentDefId];
                        $payment->payment_definition_id = $newPaymentDefId;
                        $payment->save();

                        // Track the mapping used (source -> target)
                        $mappingKey = $oldPaymentDefId . '->' . $newPaymentDefId;
                        if (!in_array($mappingKey, $paymentMappingsUsed)) {
                            $paymentMappingsUsed[] = $mappingKey;
                        }
                    }
                    $totalPayments++;
                }
            }

            // Get count of clearances (they move automatically with sharaf_id)
            $totalClearances = DB::table('sharaf_clearances')
                ->whereIn('sharaf_id', $shiftedSharafIds)
                ->count();

            // Re-sort all sharafs in actual target definition to ensure sequential ranks
            $allTargetSharafs = Sharaf::where('sharaf_definition_id', $actualTargetDefinitionId)
                ->orderBy('rank')
                ->get();

            $rank = 1;
            foreach ($allTargetSharafs as $sharaf) {
                if ($sharaf->rank !== $rank) {
                    $sharaf->rank = $rank;
                    $sharaf->save();
                }
                $rank++;
            }

            // Create audit log
            $auditLog = SharafShiftAuditLog::create([
                'sharaf_definition_mapping_id' => $mapping->id,
                'shifted_by_its' => $shiftedByIts,
                'shift_summary' => [
                    'sharafs_count' => count($shiftedSharafIds),
                    'members_count' => $totalMembers,
                    'clearances_count' => $totalClearances,
                    'payments_count' => $totalPayments,
                    'reverse_direction' => $reverseDirection,
                ],
                'sharaf_ids' => $shiftedSharafIds,
                'position_mappings_used' => array_values(array_unique($positionMappingsUsed)),
                'payment_mappings_used' => array_values(array_unique($paymentMappingsUsed)),
                'rank_changes' => $rankChanges,
                'shifted_at' => now(),
            ]);

            return [
                'sharafs_shifted' => count($shiftedSharafIds),
                'members_shifted' => $totalMembers,
                'clearances_shifted' => $totalClearances,
                'payments_shifted' => $totalPayments,
                'sharaf_ids' => $shiftedSharafIds,
                'rank_changes' => $rankChanges,
                'position_mappings_used' => array_values(array_unique($positionMappingsUsed)),
                'payment_mappings_used' => array_values(array_unique($paymentMappingsUsed)),
                'audit_log_id' => $auditLog->id,
            ];
        });
    }

    /**
     * Check if creating a mapping would create a circular reference.
     */
    private function checkCircularMapping(int $sourceId, int $targetId): void
    {
        // Check if target already maps to source (direct cycle)
        $directCycle = SharafDefinitionMapping::where('source_sharaf_definition_id', $targetId)
            ->where('target_sharaf_definition_id', $sourceId)
            ->exists();

        if ($directCycle) {
            throw new \InvalidArgumentException('Creating this mapping would create a circular reference.');
        }

        // Check for indirect cycles using iterative approach
        $visited = [$sourceId];
        $toCheck = [$targetId];

        while (!empty($toCheck)) {
            $currentId = array_shift($toCheck);

            // Get all definitions that the current definition maps to
            $mappings = SharafDefinitionMapping::where('source_sharaf_definition_id', $currentId)
                ->orWhere('target_sharaf_definition_id', $currentId)
                ->get();

            foreach ($mappings as $mapping) {
                $nextId = $mapping->source_sharaf_definition_id === $currentId
                    ? $mapping->target_sharaf_definition_id
                    : $mapping->source_sharaf_definition_id;

                // If we've reached the source, we have a cycle
                if ($nextId === $sourceId) {
                    throw new \InvalidArgumentException('Creating this mapping would create a circular reference.');
                }

                // If not visited, add to queue
                if (!in_array($nextId, $visited)) {
                    $visited[] = $nextId;
                    $toCheck[] = $nextId;
                }
            }
        }
    }
}
