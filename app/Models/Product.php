<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'cost',
        'stock',
        'minimum_stock',
        'shelf_life',
        'expiry_date',
        'status',
        'image_emoji',
        'image_path',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock' => 'integer',
        'minimum_stock' => 'integer',
        'shelf_life' => 'integer',
        'expiry_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function recipe()
    {
        return $this->hasOne(Recipe::class);
    }

    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->minimum_stock;
    }

    public function getImageUrl(): ?string
    {
        if (!empty($this->image_path) && file_exists(public_path($this->image_path))) {
            return asset($this->image_path);
        }
        if (!empty($this->image) && file_exists(public_path($this->image))) {
            return asset($this->image);
        }
        if (!empty($this->image) && file_exists(public_path('storage/' . $this->image))) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->getImageUrl();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 3): bool
    {
        if (!$this->expiry_date || $this->isExpired()) {
            return false;
        }
        return $this->expiry_date->lte(now()->addDays($days));
    }

    public function getIconTheme(): array
    {
        $name = strtolower($this->name ?? '');
        $cat = strtolower($this->category->name ?? '');

        if (str_contains($name, 'cake') || str_contains($cat, 'cake') || str_contains($cat, 'dessert')) {
            return ['bg' => '#fdf2f8', 'border' => '#fbcfe8', 'color' => '#db2777', 'shadow' => 'rgba(219, 39, 119, 0.12)'];
        }
        if (str_contains($name, 'coffee') || str_contains($name, 'coffe') || str_contains($name, 'tea') || str_contains($name, 'matcha') || str_contains($cat, 'drink') || str_contains($cat, 'beverage')) {
            return ['bg' => '#fef3c7', 'border' => '#fde68a', 'color' => '#b45309', 'shadow' => 'rgba(180, 83, 9, 0.12)'];
        }
        if (str_contains($name, 'croissant') || str_contains($name, 'pastr') || str_contains($cat, 'pastr') || str_contains($cat, 'viennoiserie')) {
            return ['bg' => '#fffbeb', 'border' => '#fef3c7', 'color' => '#d97706', 'shadow' => 'rgba(217, 119, 6, 0.12)'];
        }
        if (str_contains($name, 'donut') || str_contains($name, 'cookie') || str_contains($cat, 'cookie')) {
            return ['bg' => '#fff7ed', 'border' => '#ffedd5', 'color' => '#ea580c', 'shadow' => 'rgba(234, 88, 12, 0.12)'];
        }
        // Bread & default
        return ['bg' => '#faf5ff', 'border' => '#f3e8ff', 'color' => '#7c3aed', 'shadow' => 'rgba(124, 58, 237, 0.12)'];
    }

    public function getIconSvg(int $size = 28, ?string $color = null): string
    {
        $name = strtolower($this->name ?? '');
        $cat = strtolower($this->category->name ?? '');
        $theme = $this->getIconTheme();
        $color = $color ?? $theme['color'];

        if (str_contains($name, 'cake') || str_contains($cat, 'cake') || str_contains($name, 'muffin') || str_contains($name, 'cupcake') || str_contains($cat, 'dessert')) {
            return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s2-1 4-1 4 1 4 1 2-1 4-1 4 1 4 1"/><path d="M2 21h20"/><path d="M7 8v3"/><path d="M12 5v6"/><path d="M17 8v3"/><circle cx="12" cy="3" r="1"/></svg>';
        }

        if (str_contains($name, 'coffee') || str_contains($name, 'coffe') || str_contains($name, 'tea') || str_contains($name, 'matcha') || str_contains($cat, 'drink') || str_contains($cat, 'beverage')) {
            return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V8z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>';
        }

        if (str_contains($name, 'croissant') || str_contains($name, 'pastr') || str_contains($cat, 'pastr') || str_contains($cat, 'viennoiserie')) {
            return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C6.5 2 2 6.5 2 12c0 2.5 1 4.8 2.6 6.4C6 20 8.5 21 11.5 21c3.5 0 6.5-1.5 8-4"/><path d="M7 7c2-1 5-1 7 1s2 5 1 7"/><path d="M15 15c2.5-1 4.5-3 5-6"/></svg>';
        }

        if (str_contains($name, 'donut') || str_contains($name, 'doughnut')) {
            return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M12 3a9 9 0 0 1 9 9"/><circle cx="7.5" cy="8.5" r="0.5" fill="'.$color.'"/><circle cx="16.5" cy="8.5" r="0.5" fill="'.$color.'"/><circle cx="15.5" cy="16" r="0.5" fill="'.$color.'"/><circle cx="8.5" cy="15.5" r="0.5" fill="'.$color.'"/></svg>';
        }

        if (str_contains($name, 'cookie') || str_contains($name, 'biscuit') || str_contains($cat, 'cookie')) {
            return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="8" cy="9" r="1.2" fill="'.$color.'"/><circle cx="15.5" cy="10.5" r="1.2" fill="'.$color.'"/><circle cx="10" cy="15" r="1.2" fill="'.$color.'"/><circle cx="15" cy="15" r="1.2" fill="'.$color.'"/></svg>';
        }

        if (str_contains($name, 'bread') || str_contains($name, 'baguette') || str_contains($name, 'loaf') || str_contains($name, 'toast') || str_contains($cat, 'bread')) {
            return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11c0-4 3.5-7 8-7s8 3 8 7v4a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-4z"/><line x1="9" y1="8" x2="10.5" y2="13"/><line x1="14" y1="8" x2="15.5" y2="13"/></svg>';
        }

        // Default Bakery Item icon
        return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>';
    }
}
