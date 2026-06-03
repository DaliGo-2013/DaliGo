<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class ImpdaliEmail implements ValidationRule
{
    /**
     * Solo se permiten correos del dominio corporativo @impdali.cl.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Str::endsWith(Str::lower((string) $value), '@impdali.cl')) {
            $fail('El correo debe pertenecer al dominio @impdali.cl.');
        }
    }
}
