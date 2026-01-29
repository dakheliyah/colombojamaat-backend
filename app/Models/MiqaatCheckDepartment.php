<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiqaatCheckDepartment extends Model
{
    use HasFactory;

    protected $table = 'miqaat_check_departments';

    protected $primaryKey = 'mcd_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'name',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(MiqaatCheck::class, 'mcd_id', 'mcd_id');
    }
}

