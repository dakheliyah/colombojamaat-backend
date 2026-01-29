<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WajebaatGroup extends Model
{
    use HasFactory;

    protected $table = 'wajebaat_groups';

    protected $fillable = [
        'wg_id',
        'miqaat_id',
        'master_its',
        'its_id',
    ];

    /**
     * Miqaat for this membership row.
     */
    public function miqaat(): BelongsTo
    {
        return $this->belongsTo(Miqaat::class);
    }

    /**
     * Master (group head) census record.
     */
    public function master(): BelongsTo
    {
        return $this->belongsTo(Census::class, 'master_its', 'its_id');
    }

    /**
     * Member census record.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Census::class, 'its_id', 'its_id');
    }

    /**
     * Scope by group id within a miqaat.
     */
    public function scopeForGroup($query, int $miqaatId, int $wgId)
    {
        return $query->where('miqaat_id', $miqaatId)->where('wg_id', $wgId);
    }

    /**
     * Scope by member ITS within a miqaat (returns their membership row if any).
     */
    public function scopeForMember($query, int $miqaatId, string $itsId)
    {
        return $query->where('miqaat_id', $miqaatId)->where('its_id', $itsId);
    }
}

