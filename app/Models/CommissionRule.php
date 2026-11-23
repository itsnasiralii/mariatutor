<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $fillable = [
        'name',
        'rule_type',
        'value',
        'active',
        'effective_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'value' => 'integer',
        'effective_at' => 'datetime',
    ];
}
