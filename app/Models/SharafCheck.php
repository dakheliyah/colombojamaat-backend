<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharafCheck extends Model
{
    use HasFactory, AuditsChanges;

    protected $fillable = [
        'sharaf_id',
        'sharaf_check_definition_id',
        'is_done',
        'checked_by_its',
        'checked_at',
        'notes',
    ];

    protected $casts = [
        'is_done' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function sharaf(): BelongsTo
    {
        return $this->belongsTo(Sharaf::class);
    }

    public function checkDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafCheckDefinition::class, 'sharaf_check_definition_id');
    }
}
