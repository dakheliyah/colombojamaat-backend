<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharafDefinitionMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_sharaf_definition_id',
        'target_sharaf_definition_id',
        'is_active',
        'created_by_its',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the source sharaf definition.
     */
    public function sourceDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafDefinition::class, 'source_sharaf_definition_id');
    }

    /**
     * Get the target sharaf definition.
     */
    public function targetDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafDefinition::class, 'target_sharaf_definition_id');
    }

    /**
     * Get the position mappings for this definition mapping.
     */
    public function positionMappings(): HasMany
    {
        return $this->hasMany(SharafPositionMapping::class);
    }

    /**
     * Get the payment definition mappings for this definition mapping.
     */
    public function paymentDefinitionMappings(): HasMany
    {
        return $this->hasMany(PaymentDefinitionMapping::class);
    }

    /**
     * Get the audit logs for this definition mapping.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(SharafShiftAuditLog::class);
    }

    /**
     * Scope a query to only include active mappings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to get mappings involving a specific definition.
     */
    public function scopeForDefinition($query, int $definitionId)
    {
        return $query->where(function ($q) use ($definitionId) {
            $q->where('source_sharaf_definition_id', $definitionId)
              ->orWhere('target_sharaf_definition_id', $definitionId);
        });
    }

    /**
     * Get the other definition in the mapping.
     */
    public function getOtherDefinition(int $definitionId): ?SharafDefinition
    {
        if ($this->source_sharaf_definition_id === $definitionId) {
            return $this->targetDefinition;
        }
        
        if ($this->target_sharaf_definition_id === $definitionId) {
            return $this->sourceDefinition;
        }
        
        return null;
    }

    /**
     * Check if all positions and payment definitions are mapped.
     */
    public function isFullyMapped(): bool
    {
        // This will be implemented in the service layer
        // as it requires checking actual sharafs in the source definition
        return false;
    }
}
