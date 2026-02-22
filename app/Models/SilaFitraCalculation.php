<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SilaFitraCalculation extends Model
{
    protected $fillable = [
        'miqaat_id',
        'hof_its',
        'misaqwala_count',
        'non_misaq_count',
        'hamal_count',
        'mayat_count',
        'calculated_amount',
        'currency',
        'receipt_path',
        'payment_verified',
        'verified_by_its',
        'verified_at',
    ];

    protected $casts = [
        'misaqwala_count' => 'integer',
        'non_misaq_count' => 'integer',
        'hamal_count' => 'integer',
        'mayat_count' => 'integer',
        'calculated_amount' => 'decimal:2',
        'payment_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function miqaat(): BelongsTo
    {
        return $this->belongsTo(Miqaat::class);
    }
}
