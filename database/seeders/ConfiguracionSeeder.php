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
            // Plantillas ricas de aprobaciones (hallazgo #8 del QA 15-07 + lote
            // NOTIF-1: especificas, con el objeto y el cambio pedido).
            // Placeholders: los entrega Aprobaciones::datosNotificacion. La {url}
            // ya NO va en el cuerpo: la fila navega (urlDestino) y el correo
            // pone un boton estructural desde el payload.
            //
            // PATRON DE ENTREGA (lote NOTIF-1): firstOrCreate JAMAS pisa una
            // clave ya sembrada en prod → todo cambio de texto aqui viaja
            // ADEMAS en una migracion de datos one-shot que actualiza SOLO si
            // el valor vigente es EXACTAMENTE el texto del seed anterior (una
            // edicion manual desde la UI se respeta). Ver
            // database/migrations/2026_07_22_100000_actualiza_plantillas_aprobacion_notif1.php.
            [
                'clave' => 'notif_plantilla_aprobacion_solicitada',
                'valor' => json_encode([
                    'asunto' => 'Aprobación pendiente: {descripcion} ({magnitud})',
                    'cuerpo' => "{solicitante} pide: {tipo}.\nMotivo: {motivo}\nSobre: {objeto}\nCambio: {cambio}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Plantilla al crearse una solicitud de aprobación (va al rol aprobador).',
            ],
            [
                'clave' => 'notif_plantilla_aprobacion_escalada',
                'valor' => json_encode([
                    'asunto' => 'Solicitud escalada sin respuesta: {descripcion}',
                    'cuerpo' => "Escaló a tu rol desde {rol_anterior} tras {minutos} min sin respuesta.\nSolicitante: {solicitante}\nMotivo: {motivo}\nSobre: {objeto}\nCambio: {cambio}\nPendiente desde: {pendiente_desde}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Plantilla cuando una solicitud escala al siguiente rol.',
            ],
            [
                'clave' => 'notif_plantilla_aprobacion_resuelta',
                'valor' => json_encode([
                    'asunto' => '{resultado}: {descripcion} — {magnitud}',
                    'cuerpo' => "Tu solicitud quedó: {resultado} por {resuelto_por}. Monto: {magnitud}.\n{resultado_motivo}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Plantilla al resolverse una solicitud (va al solicitante); el asunto distingue Aprobada/Rechazada.',
            ],
            // Ingreso de un equipo al taller por QR (aviso INTERNO a ventas + el
            // técnico). Clave nueva → el firstOrCreate del seeder la crea en el
            // deploy; no requiere migración.
            [
                'clave' => 'notif_plantilla_taller_ingresado',
                'valor' => json_encode([
                    'asunto' => 'Ingreso al taller: {cliente} ({condicion})',
                    // Sin la {url} cruda (el correo ya trae el boton «Abrir en DaliGo»
                    // con el mismo enlace) y sin el imperativo «confirmalo», que le
                    // llega tambien a vendedores que NO tienen ese permiso.
                    'cuerpo' => "Orden {folio} · {cliente} ingresó {maquinas} en {sucursal} ({condicion}).\nDetalle: {equipo}\nFalta confirmar la recepción.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno (ventas + técnico) cuando un cliente ingresa un equipo por QR (unidad o lote).',
            ],
            // El técnico marcó la orden como REPARADA → ventas llama al cliente para
            // que la retire. Clave nueva → el firstOrCreate del seeder la crea en el
            // deploy; no requiere migración one-shot.
            [
                'clave' => 'notif_plantilla_taller_reparado',
                'valor' => json_encode([
                    'asunto' => 'Equipo reparado — Orden {folio} ({cliente})',
                    // El teléfono va EN EL CUERPO a propósito: el destinatario tiene
                    // que llamar, y sin él el aviso obliga a abrir la ficha para
                    // conseguir el dato con el que se actúa.
                    'cuerpo' => "{tecnico} marcó como reparada la orden {folio} de {cliente}.\nEquipo: {equipo}\nTrabajo: {trabajo}\nRetiro en: {retiro}\nFalta avisarle al cliente que puede retirarlo ({telefono}).",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas cuando el técnico marca un equipo como reparado (jefatura recibe todas; cada vendedor, las de su cartera).',
            ],
            // No tuvo arreglo. Mismos destinatarios que «reparado», pero este SÍ
            // lleva el diagnóstico: de él depende la conversación que sigue
            // (reemplazo, o garantía si fue falla de fábrica).
            [
                'clave' => 'notif_plantilla_taller_sin_solucion',
                'valor' => json_encode([
                    'asunto' => 'Sin solución — Orden {folio} ({cliente})',
                    // {aviso_cliente} dice si el correo al cliente SALIÓ de verdad: nunca
                    // se afirma a ciegas (si falla, el aviso pide llamarlo).
                    'cuerpo' => "{tecnico} cerró SIN SOLUCIÓN la orden {folio} de {cliente}.\nEquipo: {equipo}\nDiagnóstico: {diagnostico}\nRetiro en: {retiro}\n{aviso_cliente}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas cuando el técnico cierra una orden sin solución (jefatura recibe todas; cada vendedor, las de su cartera).',
            ],
            // Traslado de máquinas sucursal → casa matriz (decisión del dueño 03-08).
            // Claves nuevas → el firstOrCreate las crea en el deploy.
            [
                'clave' => 'notif_plantilla_traslado_despachado',
                'valor' => json_encode([
                    'asunto' => 'Vienen {total} máquinas desde {origen} — traslado {codigo}',
                    'cuerpo' => "{emisor} despachó {total} máquina(s) desde {origen} hacia {destino}.\nTraslado: {codigo} · Conductor: {conductor}\nFalta confirmar la recepción en el taller. Hasta que no se confirme, esas máquinas NO se pueden reparar.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al taller (técnico, jefe de bodega, jefe de ventas) cuando una sucursal despacha máquinas a reparar.',
            ],
            [
                'clave' => 'notif_plantilla_traslado_recibido',
                'valor' => json_encode([
                    'asunto' => 'Traslado {codigo} recibido en {destino}',
                    'cuerpo' => "{receptor} confirmó la recepción del traslado {codigo} en {destino}.\nDespachado por {emisor} desde {origen}.\nLlegaron {recibidas} de {total} máquinas. Ya se pueden reparar.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso de vuelta a la sucursal que despachó, cuando el taller confirma que llegó completo.',
            ],
            [
                'clave' => 'notif_plantilla_traslado_diferencias',
                'valor' => json_encode([
                    'asunto' => '⚠ Faltan {faltantes} máquinas — traslado {codigo}',
                    // Los DOS nombres van en el cuerpo a propósito: es el punto del
                    // registro. Sin emisor y receptor nombrados, la diferencia
                    // vuelve a ser una discusión sin responsables.
                    'cuerpo' => "El traslado {codigo} se recibió con DIFERENCIAS.\nSalieron {total} máquinas de {origen}, llegaron {recibidas} a {destino}: faltan {faltantes}.\nDespachó: {emisor} · Recibió: {receptor}\nMáquinas sin confirmar: {faltantes_detalle}\nObservación del receptor: {observacion}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a jefatura y a las dos sucursales cuando llegan menos máquinas de las despachadas, con emisor y receptor nombrados.',
            ],
            // Cotización del taller al cliente (P-M12-02, fase correo). Aviso
            // INTERNO a los roles del taller/ventas; la carta al cliente es un
            // Mailable dedicado (CotizacionCliente), no pasa por plantilla.
            [
                'clave' => 'notif_plantilla_cotizacion_enviada',
                'valor' => json_encode([
                    'asunto' => 'Cotización enviada — Orden {folio} ({cliente})',
                    'cuerpo' => "Se envió la cotización de la orden {folio} a {cliente} por {total}.\nEquipo: {equipo}\nEnviada por: {enviada_por}.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno al enviarse una cotización al cliente (taller/ventas).',
            ],
            [
                'clave' => 'notif_plantilla_cotizacion_respondida',
                'valor' => json_encode([
                    'asunto' => 'Cotización {respuesta} — Orden {folio} ({cliente})',
                    // {motivo} llega SIEMPRE relleno desde CotizacionPublicoController
                    // (el «¿por qué?» del cliente, o «El cliente no indicó el motivo.»).
                    // Cambiar este default exige migración (ver 2026_08_06_140100).
                    'cuerpo' => "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nEquipo: {equipo} · Monto: {total}.\n{motivo}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno cuando el cliente acepta o no acepta la cotización; el asunto distingue la respuesta.',
            ],
            // El cliente NO aceptó y alguien del equipo le avisó por correo que
            // puede pasar a retirar su equipo sin reparar (pedido del dueño 06-08).
            [
                'clave' => 'notif_plantilla_cotizacion_retiro_avisado',
                'valor' => json_encode([
                    'asunto' => 'Retiro avisado — Orden {folio} ({cliente})',
                    'cuerpo' => "A {cliente} se le avisó por correo que puede pasar a retirar su equipo sin reparar (orden {folio}).\nEquipo: {equipo}\nAvisó: {avisado_por}.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno cuando, tras un NO ACEPTO, se le avisa al cliente que retire su equipo sin reparar.',
            ],
            // Garantía: salió el correo con el detalle del trabajo (sin cobro).
            // Es el equivalente de 'cotizacion.enviada' para el caso garantía,
            // así toda la ruta de la máquina queda en la campanita (dueño 06-08).
            [
                'clave' => 'notif_plantilla_garantia_detalle_enviado',
                'valor' => json_encode([
                    'asunto' => 'Garantía: detalle enviado — Orden {folio} ({cliente})',
                    'cuerpo' => "Se le envió a {cliente} el detalle del trabajo por garantía (sin cobro) de la orden {folio}.\nEquipo: {equipo}\nEnvió: {enviado_por}.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno cuando se envía al cliente el detalle del trabajo en garantía (sin cobro).',
            ],
            [
                'clave' => 'notif_plantilla_cotizacion_autorizada',
                'valor' => json_encode([
                    'asunto' => 'Reparación autorizada — Orden {folio} ({cliente})',
                    // Decia «Ventas autorizó» en duro, pero el permiso 'autorizar
                    // reparacion' lo tienen tambien el tecnico y el vendedor, asi que
                    // atribuia mal la decision. Y «Técnico: puedes proceder» era una
                    // segunda persona dirigida a UNO de los cuatro destinatarios.
                    'cuerpo' => "{autorizada_por} autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nEquipo: {equipo}\nPago: {pago}.\nEl técnico ya puede proceder con la reparación.",
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
                    'cuerpo' => "{cliente} pidió {tipo} en {ciudad}.\nServicio: {servicio} · Dirección: {direccion}\nTeléfono: {telefono} · Prefiere: {preferida}\nDetalle del cliente: {descripcion}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas cuando un cliente deja una solicitud por coordinar (QR) en la agenda de terreno.',
            ],
            [
                'clave' => 'notif_plantilla_terreno_confirmada',
                'valor' => json_encode([
                    'asunto' => 'Cliente {respuesta}: {cliente} ({tipo})',
                    'cuerpo' => "{cliente} respondió a su visita del {fecha}: {respuesta}.\nComentario del cliente: {nota}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas cuando el cliente confirma (o avisa que no puede) su visita agendada.',
            ],
            [
                'clave' => 'notif_plantilla_terreno_rechazada',
                'valor' => json_encode([
                    'asunto' => 'Solicitud rechazada: {cliente} ({tipo})',
                    // {aviso_cliente} en vez de afirmar «Se avisó al cliente por
                    // correo» a ciegas: el envio puede fallar, y en ese caso hay que
                    // llamarlo. Lo rellena AgendaTrabajo::avisarRechazoInterno con lo
                    // que paso DE VERDAD.
                    'cuerpo' => "Se rechazó la solicitud de {cliente} ({tipo}).\nMotivo: {motivo}\nRechazó: {rechazado_por} · Teléfono: {telefono} · Prefería: {preferida}\n{aviso_cliente}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas cuando se rechaza una solicitud de terreno (con el motivo).',
            ],
            // Logística · vencimiento de documentos de la flota (decisión del
            // dueño 04-08). Claves nuevas → el firstOrCreate del seeder las crea
            // en el deploy; no requieren migración one-shot.
            [
                'clave' => 'notif_plantilla_vehiculo_documento_por_vencer',
                'valor' => json_encode([
                    'asunto' => 'Por vencer: {total} documento(s) de {patente}',
                    // El detalle va EN EL CUERPO: el aviso tiene que decir QUÉ
                    // documento y para cuándo, o obliga a abrir la ficha para
                    // saber si hay que salir corriendo o no.
                    'cuerpo' => "{vehiculo} ({base}) tiene {total} documento(s) por vencer:\n{documentos}\nConductor asignado: {conductor}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a gerencia y logística cuando a un documento de un vehículo le faltan 30 días o menos para vencer.',
            ],
            [
                'clave' => 'notif_plantilla_vehiculo_documento_vencido',
                'valor' => json_encode([
                    'asunto' => '⚠ VENCIDO: {total} documento(s) de {patente}',
                    // La última línea no es adorno: con el permiso de circulación
                    // o el SOAP vencidos el vehículo no puede circular, y esa es
                    // la consecuencia que ordena la prioridad de quien lee.
                    'cuerpo' => "{vehiculo} ({base}) tiene {total} documento(s) VENCIDO(S):\n{documentos}\nConductor asignado: {conductor}\nCon el permiso de circulación o el SOAP vencidos el vehículo no puede circular.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a gerencia y logística cuando un documento de un vehículo venció (dentro de los últimos 30 días).',
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
            // --- M11 · Producción: motivos del ajuste (hallazgo #6 del QA
            // 15-07, idea del dueño: lenguaje COMÚN entre quien pide el ajuste
            // y quien lo aprueba). Los lee el form de ajustar como chips
            // (<x-reason-chips>); «Otro» con texto libre sigue disponible.
            [
                'clave' => 'motivos_ajuste_produccion',
                'valor' => json_encode([
                    'Error de digitación del soplador',
                    'Conteo corregido en bodega',
                    'Se contó producción de otro turno',
                    'Merma mal clasificada',
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Motivos frecuentes del ajuste de un reporte de producción (chips del form de ajustar). Lista JSON de textos; «Otro» permite texto libre siempre.',
            ],
            // --- DESPACHOS-v1 · Espejo de documentos de venta (P-DSP-01) ---
            [
                'clave' => 'documentos_sync_desde',
                'valor' => null,
                'tipo' => Configuracion::TIPO_STRING,
                'grupo' => 'despachos',
                // OJO: `descripcion` es varchar(191), no 255 — el proyecto fija
                // Schema::defaultStringLength(191) por el límite de índice de MySQL 5.7
                // con utf8mb4 (AppServiceProvider). Texto más largo revienta el deploy y
                // SQLite NO lo caza. Lo fija ConfiguracionSeedLongitudTest; el detalle
                // del backfill por tramos vive en docs/planes/PLAN-DESPACHOS-V1.md.
                'descripcion' => 'Fecha de arranque (Y-m-d) del espejo de documentos. Vacío = últimos 7 días en el primer run; un piso antiguo se pone al día por tramos de 30 días.',
            ],
            [
                'clave' => 'documentos_sync_watermark',
                'valor' => null,
                'tipo' => Configuracion::TIPO_STRING,
                'grupo' => 'despachos',
                'descripcion' => 'Interno (lo escribe bsale:sync-documents): hasta dónde el espejo de documentos quedó completo. No editar a mano.',
            ],
            // ── M13 · Devoluciones (PLAN-M13 §1.3) ─────────────────────────────
            // El umbral del reembolso NO se siembra aquí: la regla M14 apunta a
            // `umbral_aprobacion_clp`, ya sembrado arriba y reservado para esto.
            [
                'clave' => 'devolucion_bodega_reingreso',
                'valor' => 'CONTENEDORES',
                'tipo' => Configuracion::TIPO_STRING,
                'grupo' => 'devoluciones',
                // Texto libre a propósito: la estructura de bodegas es D-003 y
                // está EN CURSO — cuando cierre, este valor se ajusta desde la UI.
                'descripcion' => 'Nombre de la bodega a la que reingresa un producto devuelto en buen estado (D-003 abierta: se ajusta cuando el catastro de bodegas cierre).',
            ],
            [
                'clave' => 'devolucion_fotos_min',
                'valor' => '2',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'devoluciones',
                'descripcion' => 'Fotos que el cliente debe adjuntar al declarar una devolución (mismo estándar que el ingreso QR del taller).',
            ],
            // Plantillas M15 de devoluciones. Claves nuevas → el firstOrCreate
            // las crea en el deploy; no requieren migración one-shot.
            [
                'clave' => 'notif_plantilla_devolucion_solicitada',
                'valor' => json_encode([
                    'asunto' => 'Devolución {folio}: {cliente} ({canal})',
                    'cuerpo' => "{cliente} declaró una devolución por {canal}.\nProducto: {producto}\nMotivo: {motivo}\nFalta recibirla en bodega.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno (bodega + ventas) cuando un cliente declara una devolución desde el link público.',
            ],
            [
                'clave' => 'notif_plantilla_devolucion_recibida',
                'valor' => json_encode([
                    'asunto' => 'Recibimos tu devolución {folio}',
                    'cuerpo' => "Hola {cliente}: tu devolución {folio} llegó a bodega y está en revisión.\nProducto: {producto}\nTe avisaremos el resultado por este medio.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Correo al CLIENTE cuando bodega recibe físicamente su devolución.',
            ],
            [
                'clave' => 'notif_plantilla_devolucion_resuelta',
                'valor' => json_encode([
                    'asunto' => 'Tu devolución {folio}: {resultado}',
                    'cuerpo' => "Hola {cliente}: tu devolución {folio} quedó resuelta.\nResultado: {resultado}\n{detalle}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Correo al CLIENTE con el resultado de su devolución (el «notificar» con que cierra el flujo A-12).',
            ],
            // Hoja de ruta (P-DSP-09): el conductor no pudo entregar una parada.
            // Clave nueva → el firstOrCreate la crea en el deploy, sin one-shot.
            [
                'clave' => 'notif_plantilla_despacho_parada_rechazada',
                'valor' => json_encode([
                    'asunto' => 'Entrega rechazada: {cliente} (hoja {folio_hoja})',
                    'cuerpo' => "{conductor} no pudo entregar en la puerta.\nCliente: {cliente}\nDocumento folio: {folio_documento}\nMotivo: {motivo}\nLa carga vuelve a bodega; decide qué pasa con ella desde la hoja de ruta.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno (despacho + logística) cuando el conductor rechaza una parada en la puerta (R15).',
            ],
            // M04-F1 (P-M04-12): el sync trajo una bodega que Bsale creó.
            // Clave nueva → el firstOrCreate la crea en el deploy, sin one-shot.
            [
                'clave' => 'notif_plantilla_bodega_nueva',
                'valor' => json_encode([
                    'asunto' => 'Bodega nueva en Bsale: {nombre}',
                    'cuerpo' => "El espejo de inventario trajo una bodega nueva desde Bsale.\nNombre: {nombre}\nOffice Bsale: {office_id}\nLlega sin clasificar: asígnale sucursal y propósito desde su ficha.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno (quienes administran sucursales) cuando el sync adopta una bodega creada en Bsale (M04).',
            ],
            // M04-F2 (P-M04-20): el ciclo de la baja con traslado. Claves
            // nuevas → firstOrCreate en el deploy, sin one-shot.
            [
                'clave' => 'notif_plantilla_bodega_baja_completada',
                'valor' => json_encode([
                    'asunto' => 'Baja completada: {bodega} quedó vacía',
                    'cuerpo' => "El espejo confirmó stock 0 en {bodega}: la baja que pediste se completó sola.\nLa orden de traslado #{orden} (hacia {destino}) quedó marcada como completada.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al solicitante cuando el sync confirma stock 0 en una bodega en baja y la orden de traslado se completa sola (M04-F2).',
            ],
            [
                'clave' => 'notif_plantilla_bodega_stock_en_baja',
                'valor' => json_encode([
                    'asunto' => 'Llegó stock a {bodega}, que está en baja',
                    'cuerpo' => "{bodega} está en proceso de baja (orden #{orden} hacia {destino}) y el espejo detectó stock NUEVO por encima de la foto de la orden.\nLa bodega NO vuelve a operación sola: revisa la orden y decide si trasladas también lo nuevo o anulas la baja.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al solicitante cuando llega stock a una bodega en proceso de baja (una sola vez por orden; M04-F2).',
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
