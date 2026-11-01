<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'must_change_password',
        'role',
        'custom_permissions',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'custom_permissions' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function assignedProductions()
    {
        return $this->hasMany(Production::class, 'baker_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function isBaker(): bool
    {
        return $this->role === 'baker';
    }



    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function workSchedules()
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasPermission(string $permission): bool
    {
        return \App\Services\PermissionService::check($this, $permission);
    }
}
