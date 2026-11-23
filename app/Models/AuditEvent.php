<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $fillable = [
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'summary',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
