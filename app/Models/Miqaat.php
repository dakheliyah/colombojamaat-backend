<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Miqaat extends Model
{
    use HasFactory, AuditsChanges;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'description',
        'active_status',
        'archived',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active_status' => 'boolean',
        'archived' => 'boolean',
    ];

    /**
     * Scope to only the live miqaat (active_status = true, not archived).
     */
    public function scopeActive($query)
    {
        return $query->where('active_status', true)->where('archived', false);
    }

    /**
     * Scope to miqaats that should appear in Settings and dropdowns.
     */
    public function scopeNotArchived($query)
    {
        return $query->where('archived', false);
    }

    /**
     * Get the currently active miqaat, or null if none.
     */
    public static function getActive(): ?self
    {
        return static::active()->first();
    }

    /**
     * Get the active miqaat id, or null if none.
     */
    public static function getActiveId(): ?int
    {
        $active = static::getActive();

        return $active?->id;
    }

    /**
     * Get the events for the miqaat.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Get the check definitions (departments) for this miqaat.
     */
    public function checkDefinitions(): HasMany
    {
        return $this->hasMany(MiqaatCheckDepartment::class, 'miqaat_id', 'id');
    }

    /**
     * Get the Sila Fitra config for this miqaat.
     */
    public function silaFitraConfig(): HasOne
    {
        return $this->hasOne(SilaFitraConfig::class);
    }

    /**
     * Get the Sila Fitra calculations for this miqaat.
     */
    public function silaFitraCalculations(): HasMany
    {
        return $this->hasMany(SilaFitraCalculation::class);
    }
}
