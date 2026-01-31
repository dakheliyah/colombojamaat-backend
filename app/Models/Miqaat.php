<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Miqaat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'description',
        'active_status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active_status' => 'boolean',
    ];

    /**
     * Scope to only active miqaat (active_status = true).
     */
    public function scopeActive($query)
    {
        return $query->where('active_status', true);
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
}
