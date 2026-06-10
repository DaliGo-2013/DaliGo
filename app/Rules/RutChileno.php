<?php

namespace App\Rules;

use App\Models\Cliente;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida un RUT chileno: formato + digito verificador (modulo 11), sobre el
 * valor normalizado (acepta con o sin puntos/guion). Solo para entrada MANUAL:
 * la sync de Bsale no la usa (espejo fiel; no rechaza datos historicos).
 */
class RutChileno implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rut = Cliente::normalizarRut((string) $value);

        if ($rut === null) {
            $fail('El RUT no es válido.');

            return;
        }

        [$cuerpo, $dv] = explode('-', $rut);

        if (! ctype_digit($cuerpo)) {
            $fail('El RUT no es válido.');

            return;
        }

        if ($dv !== Cliente::dvRut((int) $cuerpo)) {
            $fail('El RUT no es válido (revisa el dígito verificador).');
        }
    }
}
