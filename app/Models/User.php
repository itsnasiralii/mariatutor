<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'identifier_hash',
        'identifier_type',
        'cnic_hash',
        'name',
        'email',
        'phone',
        'role',
        'password',
        'active',
        'permissions_json',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function tutorProfile()
    {
        return $this->hasOne(TutorProfile::class, 'user_id');
    }

    public function parentProfile()
    {
        return $this->hasOne(ParentProfile::class, 'user_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isTutor(): bool
    {
        return $this->role === 'tutor';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }
}
