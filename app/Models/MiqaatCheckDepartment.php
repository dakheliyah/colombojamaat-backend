<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiqaatCheckDepartment extends Model
{
    use HasFactory;

    protected $table = 'miqaat_check_definitions';

    protected $primaryKey = 'mcd_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'miqaat_id',
        'user_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
        ];
    }

    public function miqaat(): BelongsTo
    {
        return $this->belongsTo(Miqaat::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(MiqaatCheck::class, 'mcd_id', 'mcd_id');
    }
}

