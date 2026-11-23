<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'tutor_code',
        'full_name',
        'gender',
        'cnic_gender_hint',
        'qualification',
        'experience',
        'expected_fee_min',
        'expected_fee_max',
        'availability',
        'verification_status',
        'verification_note',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'expected_fee_min' => 'integer',
        'expected_fee_max' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function catalog()
    {
        return $this->belongsToMany(CatalogItem::class, 'tutor_catalog', 'tutor_id', 'catalog_id');
    }

    public function areas()
    {
        return $this->catalog()->where('kind', 'area');
    }

    public function subjects()
    {
        return $this->catalog()->where('kind', 'subject');
    }

    public function documents()
    {
        return $this->hasMany(VerificationDocument::class, 'tutor_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'tutor_id');
    }

    public function exposures()
    {
        return $this->hasMany(OpportunityExposure::class, 'tutor_id');
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'Approved';
    }
}
