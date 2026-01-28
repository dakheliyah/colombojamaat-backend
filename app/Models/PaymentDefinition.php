<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_definition_id',
        'name',
        'description',
    ];

    /**
     * Get the sharaf definition that owns the payment definition.
     */
    public function sharafDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafDefinition::class);
    }

    /**
     * Get the sharaf payments for the payment definition.
     */
    public function sharafPayments(): HasMany
    {
        return $this->hasMany(SharafPayment::class);
    }
}
