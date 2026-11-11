<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Product names are unique ignoring letter case and surrounding whitespace.
 * Validation-only: there is no database index behind this rule, so
 * lower()/trim() run in SQL to compare the same way on PostgreSQL and SQLite.
 */
class UniqueProductName implements ValidationRule
{
    public const MESSAGE = 'Product name already exists. Please use a different name.';

    public function __construct(private readonly ?int $ignoreId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }

        $exists = Product::query()
            ->whereRaw('lower(trim(name)) = lower(?)', [trim($value)])
            ->when($this->ignoreId, fn ($q) => $q->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail(self::MESSAGE);
        }
    }
}
