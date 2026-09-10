<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entity',
        'auditable_id',
        'action',
        'actor_its',
        'actor_name',
        'summary',
        'old_values',
        'new_values',
        'metadata',
        'parent_entity',
        'parent_id',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'auditable_id' => 'integer',
        'parent_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
