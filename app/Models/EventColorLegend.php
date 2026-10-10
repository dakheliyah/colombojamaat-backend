<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventColorLegend extends Model
{
    use HasFactory, AuditsChanges;

    protected $fillable = [
        'event_id',
        'label',
        'hex_color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sharafs(): HasMany
    {
        return $this->hasMany(Sharaf::class, 'color_legend_id');
    }
}
