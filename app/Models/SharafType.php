<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharafType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the sharaf definitions for this type.
     */
    public function sharafDefinitions(): HasMany
    {
        return $this->hasMany(SharafDefinition::class);
    }

    /**
     * Get the users that can access this sharaf type.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_sharaf_type');
    }
}
