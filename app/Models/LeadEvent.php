<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadEvent extends Model
{
    protected $fillable = [
        'request_id',
        'actor_id',
        'event_type',
        'details',
        'private',
    ];

    protected $casts = [
        'private' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(TuitionRequest::class, 'request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
