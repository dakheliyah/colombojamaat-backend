<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharafPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_definition_id',
        'name',
        'display_name',
        'capacity',
        'order',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Get the sharaf definition that owns the sharaf position.
     */
    public function sharafDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafDefinition::class);
    }

    /**
     * Get the sharaf members for the sharaf position.
     */
    public function sharafMembers(): HasMany
    {
        return $this->hasMany(SharafMember::class);
    }
}
