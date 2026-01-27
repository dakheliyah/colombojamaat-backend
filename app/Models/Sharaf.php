<?php

namespace App\Models;

use App\Enums\SharafStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sharaf extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_definition_id',
        'rank',
        'capacity',
        'status',
        'hof_its',
        'lagat_paid',
        'najwa_ada_paid',
    ];

    protected $casts = [
        'rank' => 'integer',
        'capacity' => 'integer',
        'status' => SharafStatus::class,
        'lagat_paid' => 'boolean',
        'najwa_ada_paid' => 'boolean',
    ];

    /**
     * Get the sharaf definition that owns the sharaf.
     */
    public function sharafDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafDefinition::class);
    }

    /**
     * Get the sharaf members for the sharaf.
     */
    public function sharafMembers(): HasMany
    {
        return $this->hasMany(SharafMember::class);
    }

    /**
     * Get the sharaf clearances for the sharaf.
     */
    public function sharafClearances(): HasMany
    {
        return $this->hasMany(SharafClearance::class);
    }

    /**
     * Get the clearance for this sharaf's HOF.
     */
    public function hofClearance()
    {
        return $this->hasOne(SharafClearance::class)
            ->where('hof_its', $this->hof_its);
    }

    /**
     * Check if the sharaf can be confirmed.
     * A sharaf becomes confirmed only when ALL are true:
     * - Clearance for that sharaf's HOF is complete
     * - lagat_paid = true
     * - najwa_ada_paid = true
     */
    public function canBeConfirmed(): bool
    {
        $clearance = $this->hofClearance()->first();
        
        return $clearance 
            && $clearance->is_cleared 
            && $this->lagat_paid 
            && $this->najwa_ada_paid;
    }
}
