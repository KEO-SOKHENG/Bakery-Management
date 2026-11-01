<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // Status Constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'customer_id',
        'order_number',
        'customer_name',
        'subtotal',
        'discount',
        'tax',
        'total',
        'payment_method',
        'payment_status',
        'order_status',
        'loyalty_points_awarded',
        'is_custom',
        'special_instructions',
        'pickup_date',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'pickup_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'loyalty_points_awarded' => 'integer',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function sale()
    {
        return $this->hasOne(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }


    // =========================================================================
    // STATUS WORKFLOW & TRANSITIONS
    // =========================================================================

    /**
     * Define allowed forward and cancellation transitions.
     * Primary: pending -> preparing -> ready_for_pickup -> completed
     * Cancellation: pending -> cancelled, preparing -> cancelled, ready_for_pickup -> cancelled
     * Terminal: completed and cancelled cannot transition to anything.
     */
    public static function validTransitions(): array
    {
        return [
            self::STATUS_PENDING => [
                self::STATUS_PREPARING,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_PREPARING => [
                self::STATUS_READY_FOR_PICKUP,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_READY_FOR_PICKUP => [
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];
    }

    /**
     * Check whether the order can safely transition to the target status.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        $current = $this->normalized_status;
        $target = strtolower(trim($targetStatus));

        $valid = self::validTransitions()[$current] ?? [];
        return in_array($target, $valid, true);
    }

    public function isPending(): bool
    {
        return $this->normalized_status === self::STATUS_PENDING;
    }

    public function isPreparing(): bool
    {
        return $this->normalized_status === self::STATUS_PREPARING;
    }

    public function isReadyForPickup(): bool
    {
        return $this->normalized_status === self::STATUS_READY_FOR_PICKUP;
    }


    public function isCompleted(): bool
    {
        return $this->normalized_status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->normalized_status === self::STATUS_CANCELLED;
    }

    public function canBeEdited(): bool
    {
        return $this->isPending();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->normalized_status, [self::STATUS_PENDING, self::STATUS_PREPARING, self::STATUS_READY_FOR_PICKUP], true);
    }

    // =========================================================================
    // ACCESSORS & HELPERS
    // =========================================================================

    public function getNormalizedStatusAttribute(): string
    {
        $raw = strtolower(trim($this->order_status ?? 'pending'));
        return match ($raw) {
            'baking', 'in baking' => self::STATUS_PREPARING,
            'ready' => self::STATUS_READY_FOR_PICKUP,
            default => $raw,
        };
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->attributes['total'] ?? 0);
    }

    public function getStatusAttribute(): string
    {
        return $this->normalized_status;
    }

    public function getOrderTypeAttribute(): string
    {
        if ($this->is_custom) {
            return 'Custom Cake';
        }

        return match ($this->payment_method ?? 'cash') {
            'card' => 'Pre-Order / Card',
            'qr_code' => 'KHQR / Digital',
            default => 'Store POS',
        };
    }

    public function getItemsSummaryAttribute(): string
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return $this->items->map(function ($item) {
                $name = $item->product ? $item->product->name : 'Item';
                return $item->quantity . 'x ' . $name;
            })->implode(', ');
        }
        return 'Standard Order';
    }

    public function getCustomerPhoneAttribute(): string
    {
        return $this->customer?->phone ?? '';
    }

    public function getCustomerEmailAttribute(): string
    {
        return $this->customer?->email ?? '';
    }

    public function getCustomerAddressAttribute(): string
    {
        return $this->customer?->address ?? '';
    }
}
