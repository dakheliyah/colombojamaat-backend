<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class MiqaatCheck extends Model
{
    use HasFactory;

    protected $table = 'miqaat_checks';

    protected $fillable = [
        'its_id',
        'mcd_id',
        'is_cleared',
        'cleared_by_its',
        'cleared_at',
        'notes',
    ];

    protected $casts = [
        'is_cleared' => 'boolean',
        'cleared_at' => 'datetime',
    ];

    /**
     * Miqaat is determined via the check definition (definition belongs to one miqaat).
     */
    public function miqaat(): HasOneThrough
    {
        return $this->hasOneThrough(
            Miqaat::class,
            MiqaatCheckDepartment::class,
            'mcd_id',   // FK on definitions pointing to this check's mcd_id
            'id',       // PK on miqaats
            'mcd_id',   // FK on checks
            'miqaat_id' // FK on definitions pointing to miqaat
        );
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Census::class, 'its_id', 'its_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(MiqaatCheckDepartment::class, 'mcd_id', 'mcd_id');
    }
}

