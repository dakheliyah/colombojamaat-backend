<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WajCategory extends Model
{
    use HasFactory;

    protected $table = 'waj_categories';

    protected $primaryKey = 'wc_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'miqaat_id',
        'currency',
        'name',
        'low_bar',
        'upper_bar',
        'hex_color',
        'order',
    ];

    protected $casts = [
        'low_bar' => 'decimal:2',
        'upper_bar' => 'decimal:2',
        'order' => 'integer',
    ];

    public function miqaat(): BelongsTo
    {
        return $this->belongsTo(Miqaat::class);
    }

    public function wajebaats(): HasMany
    {
        return $this->hasMany(Wajebaat::class, 'wc_id', 'wc_id');
    }
}

