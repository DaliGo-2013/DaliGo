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
            // Plantillas ricas de aprobaciones (hallazgo #8 del QA 15-07: los
            // correos eran esqueleticos y los 3 titulos de resolucion identicos).
            // Placeholders: los entrega Aprobaciones::datosNotificacion (+ url).
            [
                'clave' => 'notif_plantilla_aprobacion_solicitada',
                'valor' => json_encode([
                    'asunto' => 'Aprobación pendiente: {descripcion}',
                    'cuerpo' => "{solicitante} pide: {tipo}.\nMotivo: {motivo}\nMagnitud: {magnitud}\n\nResuélvela aquí: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Plantilla al crearse una solicitud de aprobación (va al rol aprobador).',
            ],
            [
                'clave' => 'notif_plantilla_aprobacion_escalada',
                'valor' => json_encode([
                    'asunto' => 'Solicitud escalada sin respuesta: {descripcion}',
                    'cuerpo' => "Escaló a tu rol por falta de respuesta.\nSolicitante: {solicitante}\nMotivo: {motivo}\nMagnitud: {magnitud}\n\nResuélvela aquí: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Plantilla cuando una solicitud escala al siguiente rol.',
            ],
            [
                'clave' => 'notif_plantilla_aprobacion_resuelta',
                'valor' => json_encode([
                    'asunto' => '{resultado}: {descripcion}',
                    'cuerpo' => "Tu solicitud quedó: {resultado}.\n{resultado_motivo}\n\nVer tus solicitudes: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Plantilla al resolverse una solicitud (va al solicitante); el asunto distingue Aprobada/Rechazada.',
            ],
            // Cotización del taller al cliente (P-M12-02, fase correo). Aviso
            // INTERNO a los roles del taller/ventas; la carta al cliente es un
            // Mailable dedicado (CotizacionCliente), no pasa por plantilla.
            [
                'clave' => 'notif_plantilla_cotizacion_enviada',
                'valor' => json_encode([
                    'asunto' => 'Cotización enviada — Orden {folio} ({cliente})',
                    'cuerpo' => "Se envió la cotización de la orden {folio} a {cliente} por {total}.\nEquipo: {equipo}\nEnviada por: {enviada_por}.\n\nVer la orden: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno al enviarse una cotización al cliente (taller/ventas).',
            ],
            [
                'clave' => 'notif_plantilla_cotizacion_respondida',
                'valor' => json_encode([
                    'asunto' => 'Cotización {respuesta} — Orden {folio} ({cliente})',
                    'cuerpo' => "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nEquipo: {equipo} · Monto: {total}.\n\nVer la orden: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno cuando el cliente acepta o no acepta la cotización; el asunto distingue la respuesta.',
            ],
            [
                'clave' => 'notif_plantilla_cotizacion_autorizada',
                'valor' => json_encode([
                    'asunto' => 'Reparación autorizada — Orden {folio} ({cliente})',
                    'cuerpo' => "Ventas autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nEquipo: {equipo}\nPago: {pago} · autorizó: {autorizada_por}.\nTécnico: puedes proceder con la reparación.\n\nVer la orden: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso (a técnico + ventas) cuando se autoriza la reparación tras coordinar el pago.',
            ],
            // Solicitud del cliente (QR) que entra "por coordinar" a la agenda de
            // terreno: aviso a ventas para que la coordinen con el cliente.
            [
                'clave' => 'notif_plantilla_terreno_solicitada',
                'valor' => json_encode([
                    'asunto' => 'Nueva solicitud por coordinar: {cliente} ({tipo})',
                    'cuerpo' => "{cliente} pidió {tipo} en {ciudad}.\nServicio: {servicio} · Dirección: {direccion}\nTeléfono: {telefono} · Prefiere: {preferida}\nDetalle del cliente: {descripcion}\n\nCoordínala en la agenda de terreno: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas cuando un cliente deja una solicitud por coordinar (QR) en la agenda de terreno.',
            ],
            [
                'clave' => 'notif_plantilla_terreno_confirmada',
                'valor' => json_encode([
                    'asunto' => 'Cliente {respuesta}: {cliente} ({tipo})',
                    'cuerpo' => "{cliente} respondió a su visita del {fecha}: {respuesta}.\nComentario del cliente: {nota}\n\nVer en la agenda: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas cuando el cliente confirma (o avisa que no puede) su visita agendada.',
            ],
            [
                'clave' => 'notif_plantilla_terreno_rechazada',
                'valor' => json_encode([
                    'asunto' => 'Solicitud rechazada: {cliente} ({tipo})',
                    'cuerpo' => "Se rechazó la solicitud de {cliente} ({tipo}).\nMotivo: {motivo}\nRechazó: {rechazado_por} · Teléfono: {telefono} · Prefería: {preferida}\nSe avisó al cliente por correo.\n\nVer en la agenda: {url}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas cuando se rechaza una solicitud de terreno (con el motivo).',
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
