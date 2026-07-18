<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Single password policy applied to every set/reset path (spec §8).
 * Minimum 10 chars, mixed case, at least one number.
 */
class PasswordPolicy
{
    /** @return array<int, mixed> */
    public static function rules(): array
    {
        return ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()];
    }
}
