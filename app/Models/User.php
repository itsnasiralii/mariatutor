<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;

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
        'identifier_hash',
        'cnic_hash',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (! in_array($user->role, UserRole::values(), true)) {
                throw new InvalidArgumentException("Invalid user role: {$user->role}");
            }
        });
    }

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
        return $this->role === UserRole::SuperAdmin->value;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::Admin->value, UserRole::SuperAdmin->value], true);
    }

    public function isTutor(): bool
    {
        return $this->role === UserRole::Tutor->value;
    }

    public function isParent(): bool
    {
        return $this->role === UserRole::Parent->value;
    }
}
