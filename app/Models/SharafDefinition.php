<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharafDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'key',
        'description',
    ];

    /**
     * Get the event that owns the sharaf definition.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the sharafs for the sharaf definition.
     */
    public function sharafs(): HasMany
    {
        return $this->hasMany(Sharaf::class);
    }

    /**
     * Get the sharaf positions for the sharaf definition.
     */
    public function sharafPositions(): HasMany
    {
        return $this->hasMany(SharafPosition::class);
    }

    /**
     * Get the payment definitions for the sharaf definition.
     */
    public function paymentDefinitions(): HasMany
    {
        return $this->hasMany(PaymentDefinition::class);
    }
}
