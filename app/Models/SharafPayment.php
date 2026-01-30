<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharafPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_id',
        'payment_definition_id',
        'payment_amount',
        'payment_status',
        'payment_currency',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
        'payment_status' => 'boolean',
    ];

    /**
     * Get the sharaf that owns the payment.
     */
    public function sharaf(): BelongsTo
    {
        return $this->belongsTo(Sharaf::class);
    }

    /**
     * Get the payment definition for the payment.
     */
    public function paymentDefinition(): BelongsTo
    {
        return $this->belongsTo(PaymentDefinition::class);
    }
}
