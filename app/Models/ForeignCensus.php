<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForeignCensus extends Model
{
    protected $table = 'foreign_census';

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
}
