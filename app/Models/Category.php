<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $description
 * @property string $status
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function getIconSvg(int $size = 20, string $color = 'currentColor'): string
    {
        $catName = strtolower($this->name ?? '');
        return match(true) {
            str_contains($catName, 'viennoiserie') || str_contains($catName, 'pastr') => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C6.5 2 2 6.5 2 12c0 2.5 1 4.8 2.6 6.4C6 20 8.5 21 11.5 21c3.5 0 6.5-1.5 8-4"/><path d="M7 7c2-1 5-1 7 1s2 5 1 7"/><path d="M15 15c2.5-1 4.5-3 5-6"/></svg>',
            str_contains($catName, 'cake') || str_contains($catName, 'dessert') => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s2-1 4-1 4 1 4 1 2-1 4-1 4 1 4 1"/><path d="M2 21h20"/><path d="M12 5v6"/><circle cx="12" cy="3" r="1"/></svg>',
            str_contains($catName, 'beverage') || str_contains($catName, 'coffee') || str_contains($catName, 'drink') => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V8z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>',
            str_contains($catName, 'cookie') || str_contains($catName, 'donut') || str_contains($catName, 'snack') => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="8" cy="9" r="1" fill="'.$color.'"/><circle cx="15.5" cy="10.5" r="1" fill="'.$color.'"/><circle cx="10" cy="15" r="1" fill="'.$color.'"/></svg>',
            str_contains($catName, 'bread') => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11c0-4 3.5-7 8-7s8 3 8 7v4a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-4z"/><line x1="9" y1="8" x2="10.5" y2="13"/><line x1="14" y1="8" x2="15.5" y2="13"/></svg>',
            default => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
        };
    }
}
