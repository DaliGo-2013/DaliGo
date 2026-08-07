<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * El aviso de «reparación autorizada» dejó de hablarle al técnico (dueño 07-08).
 *
 * El cuerpo cerraba con «El técnico ya puede proceder con la reparación», que
 * ahora es falso en dos sentidos: el técnico NO recibe este aviso (es de plata,
 * va a ventas/admin por ROLES_AVISO_PAGO) y NO espera esta autorización para
 * reparar — con la aceptación del cliente ya tiene luz verde. Dejar la frase
 * mantenía vivo el modelo mental viejo («el taller estaba esperándome»).
 *
 * Mismo patrón anti-pisado que las one-shot anteriores: solo cambia si el cuerpo
 * sigue siendo el default vigente (respeta lo editado desde la UI), conserva el
 * asunto, es idempotente e invalida la caché de Configuracion. Está en la cadena
 * de OneShotPlantillasCandadoTest, que verifica que el 'new' sea exactamente lo
 * que siembra ConfiguracionSeeder.
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
            'notif_plantilla_cotizacion_autorizada' => [
                'old' => "{autorizada_por} autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nEquipo: {equipo}\nPago: {pago}.\nEl técnico ya puede proceder con la reparación.",
                'new' => "{autorizada_por} autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nEquipo: {equipo}\nPago: {pago}.",
            ],
        ];
    }
};
