<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * El aviso interno del retiro tras un NO ACEPTO ahora dice la CITA (día hábil
 * siguiente) y que el ciclo quedó cerrado (dueño 07-08: la carta sale sola al
 * momento del rechazo; el técnico solo lee esta campanita y sigue con lo suyo).
 * También cambia el asunto: «Retiro avisado» → «Retiro citado».
 *
 * Mismo patrón anti-pisado de las one-shot anteriores, con UNA diferencia: como
 * aquí cambia también el asunto, el gate compara el JSON completo (cuerpo Y
 * asunto deben ser los default del 07-08 en la mañana) y reemplaza ambos. Solo
 * el cuerpo lo vigila OneShotPlantillasCandadoTest (está en su lista);
 * idempotente e invalida la caché rememberForever de Configuracion.
 */
return new class extends Migration
{
    private const CLAVE = 'notif_plantilla_cotizacion_retiro_avisado';

    private const ASUNTOS = [
        'old' => 'Retiro avisado — Orden {folio} ({cliente})',
        'new' => 'Retiro citado — Orden {folio} ({cliente})',
    ];

    public function up(): void
    {
        $this->intercambiar('old', 'new');
    }

    public function down(): void
    {
        $this->intercambiar('new', 'old');
    }

    /**
     * @param  'old'|'new'  $desde
     * @param  'old'|'new'  $hacia
     */
    private function intercambiar(string $desde, string $hacia): void
    {
        $cuerpos = $this->plantillas()[self::CLAVE];

        $row = DB::table('configuraciones')->where('clave', self::CLAVE)->first();
        if (! $row) {
            return;
        }

        $valor = json_decode((string) $row->valor, true);
        if (! is_array($valor)
            || ($valor['cuerpo'] ?? null) !== $cuerpos[$desde]
            || ($valor['asunto'] ?? null) !== self::ASUNTOS[$desde]) {
            return; // ausente, ilegible o personalizado → no se toca.
        }

        $valor['cuerpo'] = $cuerpos[$hacia];
        $valor['asunto'] = self::ASUNTOS[$hacia];
        DB::table('configuraciones')
            ->where('clave', self::CLAVE)
            ->update(['valor' => json_encode($valor, JSON_UNESCAPED_UNICODE)]);

        Cache::forget('config.'.self::CLAVE);
    }

    /**
     * @return array<string, array{old: string, new: string}>
     */
    private function plantillas(): array
    {
        return [
            self::CLAVE => [
                'old' => "A {cliente} se le avisó por correo que puede pasar a retirar su equipo sin reparar (orden {folio}).\nEquipo: {equipo}\nAvisó: {avisado_por}.",
                'new' => "A {cliente} se le citó por correo a retirar su equipo sin reparar el {retiro_dia} (orden {folio}).\nEquipo: {equipo}\nAvisó: {avisado_por}.\nCiclo cerrado: no hay que enviarle nada más.",
            ],
        ];
    }
};
