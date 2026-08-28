<?php

namespace Tests\Support;

use App\Models\OrdenServicio;
use App\Models\TiempoReparacion;

/**
 * Helpers para los trabajos MARCADOS del parte del técnico (28-08-2026).
 *
 * Existe porque el cambio de «un trabajo, resuelto por el texto» a «varios trabajos marcados»
 * toca el payload de todos los tests que guardan un parte, y repetir el armado en 6 archivos es
 * exactamente cómo dos de ellos terminarían mandando cosas distintas.
 *
 * OJO CON `trabajos` EN EL PAYLOAD: el guardado distingue la clave AUSENTE (esta pantalla no
 * preguntó por los trabajos → se conservan) de la clave VACÍA (el técnico desmarcó todo → se
 * borran). Un test que quiera probar el guardado normal tiene que mandarla con lo que la orden
 * ya tiene marcado, que es lo que hace el formulario real; omitirla prueba otro camino.
 */
trait MarcaTrabajos
{
    /** Crea un trabajo en el catálogo y devuelve la fila (para poder marcarla). */
    protected function trabajoDelCatalogo(string $trabajo, float $horas = 1.5): TiempoReparacion
    {
        return TiempoReparacion::create([
            'trabajo' => $trabajo,
            'horas' => $horas,
            'grupo' => 'Reparada',
            'activo' => true,
        ]);
    }

    /**
     * Deja la orden con ese trabajo MARCADO (y en el catálogo), como queda después de que el
     * técnico lo marca y guarda: el texto que lee el cliente y el pivote con las horas
     * congeladas. Devuelve la fila del catálogo.
     */
    protected function marcarTrabajo(OrdenServicio $orden, string $trabajo, float $horas = 1.5): TiempoReparacion
    {
        $fila = TiempoReparacion::where('trabajo', $trabajo)->first()
            ?? $this->trabajoDelCatalogo($trabajo, $horas);

        $orden->trabajos()->syncWithoutDetaching([$fila->id => ['horas' => $fila->horas]]);
        $orden->forceFill(['trabajo_realizado' => $trabajo])->save();
        $orden->load('trabajos');

        return $fila;
    }

    /**
     * El trozo de payload que manda el formulario para el trabajo: el centinela + el texto + los
     * ids marcados. Se usa en todo PUT a `reparacion.guardar` que no esté probando justamente
     * este campo.
     */
    protected function payloadTrabajo(OrdenServicio $orden): array
    {
        $texto = (string) $orden->trabajo_realizado;

        return [
            'trabajos' => $orden->trabajos->pluck('id')->all(),
            'trabajos_extra' => (string) $orden->trabajos_extra,
        ] + (blank($texto) ? [] : [
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => $texto,
        ]);
    }
}
