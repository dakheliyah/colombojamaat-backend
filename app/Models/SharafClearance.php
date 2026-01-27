<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharafClearance extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharaf_id',
        'hof_its',
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
     * Get the sharaf that owns the sharaf clearance.
     */
    public function sharaf(): BelongsTo
    {
        return $this->belongsTo(Sharaf::class);
    }

    /**
     * Get the HOF person from local_census.
     * Note: This assumes a LocalCensus model exists.
     * You may need to adjust this based on your actual census table structure.
     */
    public function hofLocalPerson()
    {
        // Assuming LocalCensus model exists with ITS_ID as primary key or unique identifier
        return $this->belongsTo(LocalCensus::class, 'hof_its', 'ITS_ID');
    }

    /**
     * Get the HOF person from foreign_census.
     * Note: This assumes a ForeignCensus model exists.
     * You may need to adjust this based on your actual census table structure.
     */
    public function hofForeignPerson()
    {
        // Assuming ForeignCensus model exists with ITS_ID as primary key or unique identifier
        return $this->belongsTo(ForeignCensus::class, 'hof_its', 'ITS_ID');
    }

    /**
     * Get the person who cleared from local_census.
     */
    public function clearedByLocalPerson()
    {
        return $this->belongsTo(LocalCensus::class, 'cleared_by_its', 'ITS_ID');
    }

    /**
     * Get the person who cleared from foreign_census.
     */
    public function clearedByForeignPerson()
    {
        return $this->belongsTo(ForeignCensus::class, 'cleared_by_its', 'ITS_ID');
    }
}
