<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationDocument extends Model
{
    protected $fillable = [
        'tutor_id',
        'document_type',
        'original_name',
        'stored_name',
        'review_status',
        'reviewer_id',
        'review_note',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function tutor()
    {
        return $this->belongsTo(TutorProfile::class, 'tutor_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
