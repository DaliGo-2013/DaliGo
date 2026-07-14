<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    /**
     * Parametros globales base. Idempotente (firstOrCreate por clave): es seguro
     * re-ejecutarlo; nunca pisa el valor editado desde la UI. Mas parametros se
     * agregan a medida que existan los modulos que los consumen.
     */
    public function run(): void
    {
        $ajustes = [
            [
                'clave' => 'umbral_aprobacion_clp',
                'valor' => '1000000',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'cotizaciones',
                'descripcion' => 'Monto en CLP sobre el cual una cotización requiere aprobación.',
            ],
            [
                'clave' => 'cotizacion_vigencia_dias',
                'valor' => '5',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'cotizaciones',
                'descripcion' => 'Días de vigencia por defecto de una cotización.',
            ],
            // --- M15 · Notificaciones (PLAN-M15 §1.3) ---
            // Plantillas por evento: clave = notif_plantilla_{evento con . → _}.
            // El dispatcher las lee y reemplaza {placeholders} desde el payload.
            [
                'clave' => 'notif_plantilla_sistema_prueba',
                'valor' => json_encode([
                    'asunto' => 'Notificación de prueba — {nombre}',
                    'cuerpo' => "Hola {nombre}:\n\nEsta es una notificación de prueba del motor de notificaciones. Si la estás leyendo, el canal funciona.\n\nEnviada el {fecha}.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Plantilla del evento de prueba (asunto y cuerpo; {nombre} y {fecha} se reemplazan al enviar).',
            ],
            [
                'clave' => 'notif_reintentos_max',
                'valor' => '3',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'notificaciones',
                'descripcion' => 'Máximo de intentos de envío por notificación antes de quedar fallida definitiva.',
            ],
            [
                'clave' => 'notif_backoff_minutos',
                'valor' => '[5,15,60]',
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Minutos de espera antes de cada reintento (1°, 2°, 3°…); el último valor se repite si hay más intentos.',
            ],
            [
                'clave' => 'notif_remitente_nombre',
                'valor' => 'DaliGo',
                'tipo' => Configuracion::TIPO_STRING,
                'grupo' => 'notificaciones',
                'descripcion' => 'Nombre del remitente en los correos del sistema (placeholder hasta decidir D-001).',
            ],
            // --- M14 · Aprobaciones (PLAN-M14 §1.3) ---
            [
                'clave' => 'umbral_ajuste_produccion_unidades',
                'valor' => '50',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'aprobaciones',
                'descripcion' => 'Unidades (suma de las diferencias |Δ| de las 5 cantidades) desde las cuales un ajuste de reporte de producción requiere aprobación; bajo el umbral se auto-aprueba con registro.',
            ],
            [
                'clave' => 'aprobacion_escala_minutos',
                'valor' => '30',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'aprobaciones',
                'descripcion' => 'Minutos sin respuesta antes de escalar una solicitud pendiente al siguiente rol (granularidad efectiva 15 min por la grilla */15 del scheduler: escala en el siguiente slot tras vencer).',
            ],
            // --- DESPACHOS-v1 · Espejo de documentos de venta (P-DSP-01) ---
            [
                'clave' => 'documentos_sync_desde',
                'valor' => null,
                'tipo' => Configuracion::TIPO_STRING,
                'grupo' => 'despachos',
                'descripcion' => 'Fecha de arranque (Y-m-d) del espejo de documentos de venta: la sync nunca retrocede más atrás. Vacío = últimos 7 días en el primer run. La define el dueño; un piso antiguo se pone al día por tramos de 30 días (backfill de los ~676k históricos PROHIBIDO de un tirón).',
            ],
            [
                'clave' => 'documentos_sync_watermark',
                'valor' => null,
                'tipo' => Configuracion::TIPO_STRING,
                'grupo' => 'despachos',
                'descripcion' => 'Interno (lo escribe bsale:sync-documents): hasta dónde el espejo de documentos quedó completo. No editar a mano.',
            ],
        ];

        foreach ($ajustes as $a) {
            Configuracion::firstOrCreate(
                ['clave' => $a['clave']],
                [
                    'valor' => $a['valor'],
                    'tipo' => $a['tipo'],
                    'grupo' => $a['grupo'],
                    'descripcion' => $a['descripcion'],
                ],
            );
        }
    }
}
