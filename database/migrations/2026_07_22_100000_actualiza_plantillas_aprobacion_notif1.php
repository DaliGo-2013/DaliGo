<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Migración de DATOS one-shot (lote NOTIF-1) — el patrón de ENTREGA de
 * plantillas: ConfiguracionSeeder usa firstOrCreate, que JAMÁS pisa una clave
 * ya sembrada en producción, así que un texto nuevo del seeder no llega solo.
 * Esta migración actualiza cada plantilla SOLO si su valor vigente es
 * EXACTAMENTE el texto del seed anterior — una edición manual hecha desde la
 * UI se respeta (no se pisa). En una BD fresca no hay filas: no hace nada y
 * el seeder del deploy siembra directamente el texto nuevo.
 */
return new class extends Migration
{
    /** clave => [texto del seed ANTERIOR, texto NUEVO] */
    private const PLANTILLAS = [
        'notif_plantilla_aprobacion_solicitada' => [
            [
                'asunto' => 'Aprobación pendiente: {descripcion}',
                'cuerpo' => "{solicitante} pide: {tipo}.\nMotivo: {motivo}\nMagnitud: {magnitud}\n\nResuélvela aquí: {url}",
            ],
            [
                'asunto' => 'Aprobación pendiente: {descripcion} ({magnitud})',
                'cuerpo' => "{solicitante} pide: {tipo}.\nMotivo: {motivo}\nSobre: {objeto}\nCambio: {cambio}",
            ],
        ],
        'notif_plantilla_aprobacion_escalada' => [
            [
                'asunto' => 'Solicitud escalada sin respuesta: {descripcion}',
                'cuerpo' => "Escaló a tu rol por falta de respuesta.\nSolicitante: {solicitante}\nMotivo: {motivo}\nMagnitud: {magnitud}\n\nResuélvela aquí: {url}",
            ],
            [
                'asunto' => 'Solicitud escalada sin respuesta: {descripcion}',
                'cuerpo' => "Escaló a tu rol desde {rol_anterior} tras {minutos} min sin respuesta.\nSolicitante: {solicitante}\nMotivo: {motivo}\nSobre: {objeto}\nCambio: {cambio}\nPendiente desde: {pendiente_desde}",
            ],
        ],
        'notif_plantilla_aprobacion_resuelta' => [
            [
                'asunto' => '{resultado}: {descripcion}',
                'cuerpo' => "Tu solicitud quedó: {resultado}.\n{resultado_motivo}\n\nVer tus solicitudes: {url}",
            ],
            [
                'asunto' => '{resultado}: {descripcion} — {magnitud}',
                'cuerpo' => "Tu solicitud quedó: {resultado} por {resuelto_por}. Monto: {magnitud}.\n{resultado_motivo}",
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::PLANTILLAS as $clave => [$viejo, $nuevo]) {
            $this->reemplazarSiIntacta($clave, $viejo, $nuevo);
        }
    }

    /** Rollback simétrico: vuelve al texto anterior SOLO si nadie editó el nuevo. */
    public function down(): void
    {
        foreach (self::PLANTILLAS as $clave => [$viejo, $nuevo]) {
            $this->reemplazarSiIntacta($clave, $nuevo, $viejo);
        }
    }

    private function reemplazarSiIntacta(string $clave, array $esperado, array $reemplazo): void
    {
        $fila = DB::table('configuraciones')->where('clave', $clave)->first();

        // Se compara el JSON DECODIFICADO (no el string crudo): inmune a
        // diferencias de escape/orden de serialización entre entornos.
        if ($fila === null || json_decode($fila->valor, true) != $esperado) {
            return;
        }

        DB::table('configuraciones')->where('clave', $clave)->update([
            'valor' => json_encode($reemplazo, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        // Configuracion::get cachea rememberForever: sin esto, el texto viejo
        // seguiría sirviéndose desde el caché aunque la fila ya cambió.
        Cache::forget('config.'.$clave);
    }
};
