<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharafMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_id',
        'sharaf_position_id',
        'its_id',
        'sp_keyno',
        'name',
        'phone',
        'najwa',
        'on_vms',
    ];

    protected $casts = [
        'on_vms' => 'boolean',
    ];

    /**
     * Get the sharaf that owns the sharaf member.
     */
    public function sharaf(): BelongsTo
    {
        return $this->belongsTo(Sharaf::class);
    }

    /**
     * Get the sharaf position for the sharaf member.
     */
    public function sharafPosition(): BelongsTo
    {
        return $this->belongsTo(SharafPosition::class);
    }

    /**
     * Scope a query to order by sp_keyno ascending.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sp_keyno', 'asc');
    }

    /**
     * Get the person from local_census.
     * Note: This assumes a LocalCensus model exists.
     * You may need to adjust this based on your actual census table structure.
     */
    public function localPerson()
    {
        // Assuming LocalCensus model exists with ITS_ID as primary key or unique identifier
        // Adjust this based on your actual implementation
        return $this->belongsTo(LocalCensus::class, 'its_id', 'ITS_ID');
    }

    /**
     * Get the person from foreign_census.
     * Note: This assumes a ForeignCensus model exists.
     * You may need to adjust this based on your actual census table structure.
     */
    public function foreignPerson()
    {
        // Assuming ForeignCensus model exists with ITS_ID as primary key or unique identifier
        // Adjust this based on your actual implementation
        return $this->belongsTo(ForeignCensus::class, 'its_id', 'ITS_ID');
    }

    /**
     * Get the person (either from local or foreign census).
     * This is a helper method that checks both tables.
     * Note: This requires implementing a unified query or creating a Person model.
     * For now, use localPerson() or foreignPerson() directly based on your needs.
     */
    public function person()
    {
        // Implementation depends on your census table structure
        // You may want to:
        // 1. Create a unified Person model that queries both tables
        // 2. Use a database view that unions both census tables
        // 3. Check local first, then foreign in application logic
        return $this->localPerson()->first() ?? $this->foreignPerson()->first();
    }
}
