<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpportunityExposure extends Model
{
    protected $fillable = [
        'request_id',
        'tutor_id',
        'shown_at',
        'viewed_at',
        'interested_at',
        'interest_note',
    ];

    protected $casts = [
        'shown_at' => 'datetime',
        'viewed_at' => 'datetime',
        'interested_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(TuitionRequest::class, 'request_id');
    }

    public function tutor()
    {
        return $this->belongsTo(TutorProfile::class, 'tutor_id');
    }
}
