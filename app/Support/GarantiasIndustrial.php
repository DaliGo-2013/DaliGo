<?php

namespace App\Support;

use App\Models\Instalacion;

/**
 * Las garantías del servicio INDUSTRIAL (terreno), listas para mostrarle al
 * cliente (dueño, 20-08-2026: «esto es importante para que todos los clientes
 * sepan al momento de llevar a cabo un arreglo»).
 *
 * POR QUÉ EXISTE ESTA CLASE y no un @php en la plantilla: los mismos plazos van a
 * aparecer en más de una superficie —hoy el correo de la visita, mañana la
 * cotización industrial o la pantalla pública del QR— y una tabla de garantías
 * escrita dos veces es una tabla que algún día va a decir dos cosas distintas en
 * dos correos. Los números viven en `config/servicio_tecnico.garantias_industrial`
 * y acá solo se leen y se redactan.
 *
 * NO tiene nada que ver con las garantías del TALLER de dispensadores
 * (`OrdenServicio::GARANTIA_MESES` y `GARANTIA_REPARACION_MESES`), que son otras y
 * siguen vigentes: un dispensador reparado en el taller tiene 3 meses, una planta
 * reparada en terreno tiene 1 mes. Confirmado con el dueño el 20-08.
 */
class GarantiasIndustrial
{
    /**
     * Garantía del EQUIPO NUEVO por categoría, cuando se instala por primera vez.
     * Devuelve [categoria => ['label' => 'Llenadora', 'plazo' => '1 año']] en el
     * orden del catálogo de instalaciones.
     *
     * @return array<string, array{label: string, plazo: string}>
     */
    public static function equipoNuevo(): array
    {
        $meses = (array) config('servicio_tecnico.garantias_industrial.equipo_nuevo_meses', []);
        // Cómo se le nombra el equipo AL CLIENTE cuando el rótulo del catálogo no
        // alcanza («Planta» → «Planta de osmosis»). Solo las que hace falta aclarar;
        // el resto cae en el rótulo del catálogo, que sigue siendo la fuente única.
        $etiquetas = (array) config('servicio_tecnico.garantias_industrial.etiquetas_cliente', []);
        $out = [];

        // Se recorre CATEGORIAS y no el config: así el orden es el del catálogo y
        // una categoría nueva sin plazo configurado no desaparece en silencio del
        // correo — sale con su plazo en null y el candado lo caza.
        foreach (Instalacion::CATEGORIAS as $categoria) {
            if (! array_key_exists($categoria, $meses)) {
                continue;
            }

            $out[$categoria] = [
                'label' => $etiquetas[$categoria]
                    ?? Instalacion::CATEGORIA_ETIQUETAS[$categoria]
                    ?? ucfirst($categoria),
                'plazo' => self::plazo((int) $meses[$categoria]),
            ];
        }

        return $out;
    }

    /** Garantía de una REPARACIÓN industrial, desde el día en que se repara. */
    public static function reparacion(): string
    {
        return self::plazo((int) config('servicio_tecnico.garantias_industrial.reparacion_meses', 1));
    }

    /** Garantía del TRABAJO de instalación (el armado), desde que queda funcionando. */
    public static function instalacion(): string
    {
        return self::plazo((int) config('servicio_tecnico.garantias_industrial.instalacion_meses', 1));
    }

    /**
     * Los meses en palabras, como se los lee un cliente: 1 → «1 mes», 6 → «6
     * meses», 12 → «1 año», 18 → «18 meses», 24 → «2 años».
     *
     * Los múltiplos exactos de 12 se dicen en años porque «12 meses» y «1 año» son
     * lo mismo y el cliente entiende el segundo más rápido; los que no son
     * múltiplos se quedan en meses en vez de inventar «un año y medio», que abre
     * la discusión de cuándo vence.
     */
    public static function plazo(int $meses): string
    {
        if ($meses <= 0) {
            return 'sin garantía';
        }

        if ($meses % 12 === 0) {
            $anios = intdiv($meses, 12);

            return $anios === 1 ? '1 año' : "{$anios} años";
        }

        return $meses === 1 ? '1 mes' : "{$meses} meses";
    }
}
