<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One-shot de plantilla (patron NOTIF-1): el aviso de ingreso al taller deja de
 * afirmar «Falta confirmar la recepcion» y pasa a resolverlo con {recepcion}.
 *
 * Por que hace falta: desde el 28-08-2026 el aviso sale tambien del MOSTRADOR
 * (antes solo del QR del cliente), y ahi la maquina la recibio el staff en
 * persona — `por_confirmar` es false por construccion, porque
 * OrdenServicio::FUENTES_POR_CONFIRMAR solo incluye 'qr' y 'ruta'. Mandar a
 * confirmar algo que no tiene boton de confirmar hace desconfiar del resto del
 * aviso, y es el tipo de detalle que nadie reporta: simplemente se deja de
 * creer en la campanita.
 *
 * La frase la resuelve OrdenServicio::fraseDeRecepcion() (unidad) o queda fija
 * en LoteServicio::notificarIngresoInterno (un lote SIEMPRE espera confirmacion).
 *
 * Seguro por diseno: solo cambia el cuerpo si sigue siendo el default anterior
 * —respeta cualquier plantilla editada desde la UI—, conserva el `asunto`, es
 * idempotente (al re-correr ya no coincide el viejo → no-op) e invalida la cache
 * rememberForever de Configuracion (deploy.sh no corre cache:clear).
 *
 * OJO con el punto de partida: el default vigente en una BD ya migrada es el
 * 'new' de la one-shot 2026_07_30_100000, no el texto original del seeder. El
 * candado OneShotPlantillasCandadoTest verifica esa cadena.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->intercambiarCuerpos('old', 'new');
    }

    public function down(): void
    {
        $this->intercambiarCuerpos('new', 'old');
    }

    /**
     * @param  'old'|'new'  $desde
     * @param  'old'|'new'  $hacia
     */
    private function intercambiarCuerpos(string $desde, string $hacia): void
    {
        foreach ($this->plantillas() as $clave => $cuerpos) {
            $row = DB::table('configuraciones')->where('clave', $clave)->first();
            if (! $row) {
                continue;
            }

            $valor = json_decode((string) $row->valor, true);
            if (! is_array($valor) || ($valor['cuerpo'] ?? null) !== $cuerpos[$desde]) {
                continue; // ausente, ilegible o personalizado → no se toca.
            }

            $valor['cuerpo'] = $cuerpos[$hacia];
            DB::table('configuraciones')
                ->where('clave', $clave)
                ->update(['valor' => json_encode($valor, JSON_UNESCAPED_UNICODE)]);

            Cache::forget('config.'.$clave);
        }
    }

    /**
     * Cuerpos antes/despues por clave. El 'new' debe calzar EXACTO con el default
     * del seeder para que el gate anti-pisado siga funcionando en el proximo cambio.
     *
     * @return array<string, array{old: string, new: string}>
     */
    private function plantillas(): array
    {
        return [
            'notif_plantilla_taller_ingresado' => [
                'old' => "Orden {folio} · {cliente} ingresó {maquinas} en {sucursal} ({condicion}).\nDetalle: {equipo}\nFalta confirmar la recepción.",
                'new' => "Orden {folio} · {cliente} ingresó {maquinas} en {sucursal} ({condicion}).\nDetalle: {equipo}\n{recepcion}",
            ],
        ];
    }
};
