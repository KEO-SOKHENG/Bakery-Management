<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LoginApprovalRequest extends Model
{
    use HasUuids;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
    public const CONSUMED = 'consumed';

    protected $fillable = [
        'user_id', 'token_hash', 'ip_address', 'user_agent', 'browser', 'platform',
        'device_type', 'status', 'decided_by', 'decided_at', 'expires_at', 'consumed_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
