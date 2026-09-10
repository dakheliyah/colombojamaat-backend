<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory, AuditsChanges;

    protected $fillable = [
        'miqaat_id',
        'date',
        'name',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Get the miqaat that owns the event.
     */
    public function miqaat(): BelongsTo
    {
        return $this->belongsTo(Miqaat::class);
    }

    /**
     * Get the sharaf definitions for the event.
     */
    public function sharafDefinitions(): HasMany
    {
        return $this->hasMany(SharafDefinition::class);
    }
}
