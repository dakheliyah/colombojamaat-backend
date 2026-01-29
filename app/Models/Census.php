<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Census extends Model
{
    protected $table = 'census';

    protected $fillable = [
        'its_id',
        'hof_id',
        'father_its',
        'mother_its',
        'spouse_its',
        'sabeel',
        'name',
        'arabic_name',
        'age',
        'gender',
        'misaq',
        'marital_status',
        'blood_group',
        'mobile',
        'email',
        'address',
        'city',
        'pincode',
        'mohalla',
        'area',
        'jamaat',
        'jamiat',
        'pwd',
        'synced',
    ];

    protected $casts = [
        'age' => 'integer',
    ];

    /**
     * Get the HOF (Head of Family) - the census row where its_id = this record's hof_id.
     */
    public function hof(): BelongsTo
    {
        return $this->belongsTo(Census::class, 'hof_id', 'its_id');
    }

    /**
     * Get family members - census rows where hof_id = this record's its_id.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Census::class, 'hof_id', 'its_id');
    }

    /**
     * Wajebaat records for this ITS.
     */
    public function wajebaats(): HasMany
    {
        return $this->hasMany(Wajebaat::class, 'its_id', 'its_id');
    }

    /**
     * Group membership rows where this person is a member.
     */
    public function wajebaatGroupRows(): HasMany
    {
        return $this->hasMany(WajebaatGroup::class, 'its_id', 'its_id');
    }

    /**
     * Group membership rows where this person is the master.
     */
    public function wajebaatGroupsAsMasterRows(): HasMany
    {
        return $this->hasMany(WajebaatGroup::class, 'master_its', 'its_id');
    }
}
