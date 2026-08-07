<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Suma el {motivo} del cliente al cuerpo de la plantilla del aviso interno
 * `cotizacion.respondida` (pedido del dueño 06-08: la respuesta ahora puede
 * traer un «¿por qué?» escrito por el cliente, y el equipo tiene que leerlo
 * en la misma campanita, no entrar a la orden a buscarlo).
 *
 * El placeholder se rellena SIEMPRE desde CotizacionPublicoController (con el
 * motivo o con «El cliente no indicó el motivo.») — regla de la casa: un
 * placeholder sin dato queda crudo en el texto.
 *
 * Migración y no solo seeder: ConfiguracionSeeder usa firstOrCreate (nunca pisa
 * lo existente), así que en prod esta es la única vía. Patrón anti-pisado de
 * 2026_07_30_100000: solo cambia si el cuerpo sigue siendo el default anterior
 * (respeta plantillas editadas desde la UI), idempotente, e invalida la cache
 * rememberForever de Configuracion. El 'old' es el 'new' que dejó la one-shot
 * del 30-07 y el 'new' calza EXACTO con el default del seeder — las dos mitades
 * las vigila OneShotPlantillasCandadoTest (esta one-shot está en su lista).
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
     * @return array<string, array{old: string, new: string}>
     */
    private function plantillas(): array
    {
        return [
            'notif_plantilla_cotizacion_respondida' => [
                'old' => "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nEquipo: {equipo} · Monto: {total}.",
                'new' => "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nEquipo: {equipo} · Monto: {total}.\n{motivo}",
            ],
        ];
    }
};
