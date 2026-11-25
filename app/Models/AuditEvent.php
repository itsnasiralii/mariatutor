<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditEvent extends Model
{
    protected $fillable = [
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'summary',
        'ip_address',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(
        string $action,
        string $resourceType,
        string|int|null $resourceId = null,
        string $summary = '',
        ?int $actorId = null,
        ?string $ipAddress = null
    ): self {
        return static::create([
            'actor_id' => $actorId ?? Auth::id(),
            'action' => mb_substr($action, 0, 80),
            'resource_type' => mb_substr($resourceType, 0, 60),
            'resource_id' => $resourceId !== null ? mb_substr((string) $resourceId, 0, 64) : null,
            'summary' => mb_substr($summary, 0, 500),
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }
}
