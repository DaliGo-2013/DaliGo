<?php

namespace App\Support;

use Illuminate\Support\Facades\Context;

/**
 * El codigo corto que la pagina de error 500 le muestra al usuario para que se
 * lo dicte a TI ("me salio el error A3F91C"), y que viaja en la MISMA linea de
 * log de la excepcion — con eso se ubica el error exacto sin adivinar la hora.
 *
 * Vive en el Context de la peticion (no en un static) por dos razones:
 *  - `Context` es un binding `scoped`: muere con la instancia de la app, asi que
 *    no se filtra entre peticiones ni entre TESTS del mismo proceso (un static
 *    arrastraria el codigo del test anterior y haria pasar en falso el test de
 *    degradacion).
 *  - Se usa la variante HIDDEN a proposito: el contexto visible lo inyecta el
 *    ContextLogProcessor en el `extra` de TODAS las lineas de log de la
 *    peticion, y aca solo interesa la de la excepcion.
 *
 * Quien lo genera es el callback de `$exceptions->context()` en
 * bootstrap/app.php. OJO: los HttpException estan en $internalDontReport, asi
 * que un `abort(500)` explicito NO se reporta y NO tiene codigo — correcto, no
 * hay excepcion que buscar en el log. La vista degrada sola.
 */
final class CodigoIncidente
{
    private const CLAVE = 'incidente';

    /** Sin 0/O, 1/I/L ni U: el usuario dicta esto por telefono. */
    private const ALFABETO = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const LARGO = 6;

    /**
     * El codigo de ESTA peticion, generandolo la primera vez. Idempotente: si
     * una peticion reporta dos excepciones (el repo tiene varios report($e)
     * "tragados" en try/catch), las dos lineas llevan el mismo codigo y TI lee
     * la historia junta.
     */
    public static function deEstaPeticion(): string
    {
        return Context::rememberHidden(self::CLAVE, static fn (): string => self::generar());
    }

    /** El codigo de esta peticion, o null si no hubo excepcion REPORTADA. */
    public static function actual(): ?string
    {
        $codigo = Context::getHidden(self::CLAVE);

        return is_string($codigo) && preg_match(self::patron(), $codigo) === 1 ? $codigo : null;
    }

    /** Publico para que los tests validen con la MISMA regla, sin duplicarla. */
    public static function patron(): string
    {
        return '/^['.self::ALFABETO.']{'.self::LARGO.'}$/';
    }

    private static function generar(): string
    {
        $tope = strlen(self::ALFABETO) - 1;
        $codigo = '';

        for ($i = 0; $i < self::LARGO; $i++) {
            $codigo .= self::ALFABETO[random_int(0, $tope)];
        }

        return $codigo;
    }
}
