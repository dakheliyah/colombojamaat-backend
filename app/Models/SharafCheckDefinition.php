<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharafCheckDefinition extends Model
{
    use HasFactory, AuditsChanges;

    protected $fillable = [
        'sharaf_definition_id',
        'name',
        'description',
        'user_type',
        'status_api_url',
        'webhook_url',
    ];

    public function sharafDefinition(): BelongsTo
    {
        return $this->belongsTo(SharafDefinition::class);
    }

    public function sharafChecks(): HasMany
    {
        return $this->hasMany(SharafCheck::class);
    }
}
