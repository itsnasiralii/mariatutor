<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'request_id',
        'tutor_id',
        'parent_user_id',
        'rating',
        'body',
        'status',
        'moderator_id',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function request()
    {
        return $this->belongsTo(TuitionRequest::class, 'request_id');
    }

    public function tutor()
    {
        return $this->belongsTo(TutorProfile::class, 'tutor_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
