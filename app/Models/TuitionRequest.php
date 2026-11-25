<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TuitionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_ref',
        'parent_user_id',
        'area_id',
        'subject_id',
        'class_id',
        'mode_id',
        'subarea_text',
        'budget_min',
        'budget_max',
        'preferred_time',
        'requirements',
        'tutor_gender_preference',
        'status',
        'assigned_admin_id',
        'selected_tutor_id',
        'contact_released_at',
        'commission_snapshot_type',
        'commission_snapshot_value',
    ];

    protected $casts = [
        'contact_released_at' => 'datetime',
        'budget_min' => 'integer',
        'budget_max' => 'integer',
        'commission_snapshot_value' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function area()
    {
        return $this->belongsTo(CatalogItem::class, 'area_id');
    }

    public function subject()
    {
        return $this->belongsTo(CatalogItem::class, 'subject_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(CatalogItem::class, 'class_id');
    }

    public function mode()
    {
        return $this->belongsTo(CatalogItem::class, 'mode_id');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function selectedTutor()
    {
        return $this->belongsTo(TutorProfile::class, 'selected_tutor_id');
    }

    public function exposures()
    {
        return $this->hasMany(OpportunityExposure::class, 'request_id');
    }

    public function events()
    {
        return $this->hasMany(LeadEvent::class, 'request_id');
    }

    public function payments()
    {
        return $this->hasMany(PaymentEntry::class, 'request_id');
    }
}
