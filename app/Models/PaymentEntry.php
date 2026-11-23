<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEntry extends Model
{
    protected $fillable = [
        'request_id',
        'amount_pkr',
        'platform_commission_pkr',
        'tutor_amount_pkr',
        'method',
        'reference',
        'status',
        'recorded_by_id',
        'notes',
    ];

    protected $casts = [
        'amount_pkr' => 'integer',
        'platform_commission_pkr' => 'integer',
        'tutor_amount_pkr' => 'integer',
    ];

    public function request()
    {
        return $this->belongsTo(TuitionRequest::class, 'request_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
