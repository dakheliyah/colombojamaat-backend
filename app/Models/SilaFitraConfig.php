<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SilaFitraConfig extends Model
{
    protected $table = 'sila_fitra_config';

    protected $fillable = [
        'miqaat_id',
        'misaqwala_rate',
        'non_misaq_hamal_mayat_rate',
        'currency',
    ];

    protected $casts = [
        'misaqwala_rate' => 'decimal:2',
        'non_misaq_hamal_mayat_rate' => 'decimal:2',
    ];

    public function miqaat(): BelongsTo
    {
        return $this->belongsTo(Miqaat::class);
    }
}
