<?php

namespace App\Rules;

use App\Models\User;
use App\Services\LoginApprovalService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The configured owner email skips login approval, so no other account may
 * take it in any letter case. The message stays generic so it never reveals
 * which address is the owner's.
 */
class ReservedOwnerEmail implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $owner = app(LoginApprovalService::class)->ownerEmail();

        if ($owner === '' || !is_string($value) || strtolower(trim($value)) !== $owner) {
            return;
        }

        $holder = $this->ignoreId ? User::find($this->ignoreId) : null;

        if (!$holder || strtolower(trim((string) $holder->email)) !== $owner) {
            $fail('The email has already been taken.');
        }
    }
}
