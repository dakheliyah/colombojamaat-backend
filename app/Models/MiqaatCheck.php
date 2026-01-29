<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiqaatCheck extends Model
{
    use HasFactory;

    protected $table = 'miqaat_checks';

    protected $fillable = [
        'miqaat_id',
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

    public function miqaat(): BelongsTo
    {
        return $this->belongsTo(Miqaat::class);
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

