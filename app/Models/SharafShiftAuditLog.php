<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharafShiftAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_definition_mapping_id',
        'shifted_by_its',
        'shift_summary',
        'sharaf_ids',
        'position_mappings_used',
        'payment_mappings_used',
        'rank_changes',
        'shifted_at',
    ];

    protected $casts = [
        'shift_summary' => 'array',
        'sharaf_ids' => 'array',
        'position_mappings_used' => 'array',
        'payment_mappings_used' => 'array',
        'rank_changes' => 'array',
        'shifted_at' => 'datetime',
    ];

    /**
     * Get the sharaf definition mapping that owns this audit log.
     */
    public function definitionMapping(): BelongsTo
    {
        return $this->belongsTo(SharafDefinitionMapping::class);
    }
}
