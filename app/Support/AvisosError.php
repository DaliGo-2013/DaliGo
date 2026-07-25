<?php

namespace App\Support;

use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Los textos que ve el usuario cuando cae en un 403 o un 404, y la logica para
 * decidir CUAL corresponde (la usa el render() de bootstrap/app.php y las vistas
 * de resources/views/errors/).
 *
 * Regla de oro: al usuario JAMAS le llega el mensaje crudo de la excepcion.
 * spatie lanza "User does not have the right permissions." (y lang/es.json no
 * traduce esa clave, asi que saldria en INGLES), Laravel lanza "Invalid
 * signature." y ModelNotFound produce "No query results for model
 * [App\Models\ProduccionReporte] 5" — internals que no aportan nada y filtran
 * la estructura del sistema.
 *
 * La excepcion a esa regla son NUESTROS abort(403, '...'): esos mensajes son
 * copy de negocio ya escrito para la situacion exacta ("Este reporte ya no se
 * puede editar.") y se preservan tal cual — pisarlos con "habla con un
 * administrador" seria mentir, porque el administrador no reabre un reporte
 * cerrado: el remedio es otro.
 */
final class AvisosError
{
    /** 403 por permiso faltante o por ser un recurso de otro usuario. */
    public const SIN_PERMISO = 'No tienes permiso para entrar ahí. Habla con un administrador si necesitas acceso.';

    /** 404: ruta inexistente, registro borrado o binding que no resolvio. */
    public const NO_ENCONTRADO = 'No encontramos lo que buscabas. El enlace puede estar roto o el registro ya no existe.';

    /**
     * Mensajes que NO son nuestros: los pone el framework y estan en ingles.
     * (Los de spatie y firma se descartan por CLASE, no por texto: spatie le
     * appendea la lista de permisos al mensaje cuando esta activa esa opcion.)
     */
    private const DEL_FRAMEWORK = [
        '',
        'Forbidden',
        'Not Found',
        'This action is unauthorized.',
    ];

    /** ¿El mensaje de la excepcion lo escribimos NOSOTROS en un abort()? */
    public static function tieneMensajePropio(HttpException $e): bool
    {
        if ($e instanceof UnauthorizedException || $e instanceof InvalidSignatureException) {
            return false;
        }

        return ! in_array(trim($e->getMessage()), self::DEL_FRAMEWORK, true);
    }

    /** El texto que se le muestra al usuario para un 403. */
    public static function para403(HttpException $e): string
    {
        return self::tieneMensajePropio($e) ? $e->getMessage() : self::SIN_PERMISO;
    }

    /**
     * Por que se denego, SOLO para el log (al usuario le llega el mismo texto en
     * permiso y propiedad: decirle "ese reporte es de otro soplador" le confirma
     * que el recurso existe — enumeracion gratis — y el remedio es identico).
     *
     * En el log si importan: un 403 de 'permiso' con Referer interno es un bug
     * nuestro (mostramos un enlace que no correspondia); uno de 'propiedad' es
     * un enlace viejo o curiosidad del usuario.
     */
    public static function motivo(HttpException $e): string
    {
        return match (true) {
            $e->getStatusCode() === 404 => 'no-encontrado',
            $e instanceof UnauthorizedException => 'permiso',
            $e instanceof InvalidSignatureException => 'firma',
            self::tieneMensajePropio($e) => 'estado',
            default => 'propiedad',
        };
    }
}
