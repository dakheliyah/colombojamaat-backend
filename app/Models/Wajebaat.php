<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wajebaat extends Model
{
    use HasFactory;

    protected $table = 'wajebaat';

    protected $fillable = [
        'miqaat_id',
        'its_id',
        'wg_id',
        'amount',
        'currency',
        'conversion_rate',
        'status',
        'wc_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'conversion_rate' => 'decimal:6',
        'status' => 'boolean',
    ];

    public function miqaat(): BelongsTo
    {
        return $this->belongsTo(Miqaat::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Census::class, 'its_id', 'its_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WajCategory::class, 'wc_id', 'wc_id');
    }

    /**
     * Membership rows for this group identifier within this miqaat.
     * Note: this is query-constrained since the join key is composite (miqaat_id + wg_id).
     */
    public function groupRows(): HasMany
    {
        return $this->hasMany(WajebaatGroup::class, 'wg_id', 'wg_id')
            ->where('miqaat_id', $this->miqaat_id);
    }

    /**
     * Manual clearance override (same miqaat + same ITS).
     * Miqaat is matched via the check's definition (miqaat_id lives on miqaat_check_definitions).
     */
    public function miqaatCheck(): HasOne
    {
        return $this->hasOne(MiqaatCheck::class, 'its_id', 'its_id')
            ->join('miqaat_check_definitions', 'miqaat_checks.mcd_id', '=', 'miqaat_check_definitions.mcd_id')
            ->whereColumn('miqaat_check_definitions.miqaat_id', 'wajebaat.miqaat_id')
            ->select('miqaat_checks.*');
    }

    /**
     * Returns true if this member is paid.
     */
    public function isCleared(): bool
    {
        return (bool) $this->status;
    }

    /**
     * Scope: find the wajebaat record for a given ITS in a given miqaat.
     */
    public function scopeForItsInMiqaat($query, string $itsId, int $miqaatId)
    {
        return $query->where('its_id', $itsId)->where('miqaat_id', $miqaatId);
    }
}

