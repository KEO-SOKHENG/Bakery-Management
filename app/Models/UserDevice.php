<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id', 'session_key', 'ip_address', 'user_agent', 'browser', 'platform',
        'device_type', 'login_method', 'approval_request_id', 'last_seen_at',
        'revoked_at', 'revoked_by', 'revoke_reason',
    ];

    protected $hidden = ['session_key'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return trim(($this->browser ?: 'Unknown browser') . ' on ' . ($this->platform ?: 'unknown device'));
    }
}
