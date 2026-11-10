<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $supplier_id
 * @property string $name
 * @property string $unit
 * @property float $quantity
 * @property float $cost
 * @property float $minimum_quantity
 * @property \Carbon\Carbon|null $expiry_date
 * @property string $status
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'name',
        'unit',
        'quantity',
        'cost',
        'minimum_quantity',
        'expiry_date',
        'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'cost' => 'decimal:2',
        'minimum_quantity' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredients')
                    ->withPivot('quantity', 'unit')
                    ->withTimestamps();
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class)->orderBy('id', 'desc');
    }

    public function isLowStock(): bool
    {
        return (float) $this->quantity > 0 && (float) $this->quantity <= (float) $this->minimum_quantity;
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->quantity <= 0;
    }

    public function canDelete(): bool
    {
        return $this->recipes()->count() === 0 
            && $this->purchaseOrderItems()->count() === 0;
    }

    // Accessors for blade compatibility
    public function getCurrentStockAttribute()
    {
        return (float) $this->quantity;
    }

    public function getReorderLevelAttribute()
    {
        return (float) $this->minimum_quantity;
    }

    public function getPurchasePriceAttribute()
    {
        return (float) $this->cost;
    }
}
