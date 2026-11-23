<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppError extends Model
{
    protected $fillable = [
        'error_ref',
        'module',
        'summary',
        'details',
        'resolved',
    ];

    protected $casts = [
        'resolved' => 'boolean',
    ];
}
