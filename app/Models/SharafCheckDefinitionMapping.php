<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharafCheckDefinitionMapping extends Model
{
    use HasFactory, AuditsChanges;

    protected $fillable = [
        'sharaf_definition_mapping_id',
        'source_sharaf_check_definition_id',
        'target_sharaf_check_definition_id',
    ];

    public function definitionMapping(): BelongsTo
    {
        return $this->belongsTo(SharafDefinitionMapping::class, 'sharaf_definition_mapping_id');
    }

    public function sourceCheckDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafCheckDefinition::class, 'source_sharaf_check_definition_id');
    }

    public function targetCheckDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafCheckDefinition::class, 'target_sharaf_check_definition_id');
    }
}
