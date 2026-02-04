<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharafPositionMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_definition_mapping_id',
        'source_sharaf_position_id',
        'target_sharaf_position_id',
    ];

    /**
     * Get the sharaf definition mapping that owns this position mapping.
     */
    public function definitionMapping(): BelongsTo
    {
        return $this->belongsTo(SharafDefinitionMapping::class);
    }

    /**
     * Get the source sharaf position.
     */
    public function sourcePosition(): BelongsTo
    {
        return $this->belongsTo(SharafPosition::class, 'source_sharaf_position_id');
    }

    /**
     * Get the target sharaf position.
     */
    public function targetPosition(): BelongsTo
    {
        return $this->belongsTo(SharafPosition::class, 'target_sharaf_position_id');
    }
}
