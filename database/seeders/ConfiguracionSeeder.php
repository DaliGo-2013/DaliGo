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
            // --- Servicio técnico: tope de mano de obra (28-08-2026) ---
            // Regla del dueño: «no quiero que se sumen 5 horas […] cuando un dispensador se
            // desarma completo más estos cambios máximo puede ser dos horas, más de ahí no
            // pasa». El desarme se paga una vez, así que las horas de los trabajos marcados se
            // suman pero con este techo. Se edita en «Costos generales de reparación»; el mismo
            // 2.0 vive como fallback en TiempoReparacion::TOPE_HORAS_DEFAULT, así que
            // parametrizarlo NO cambia el comportamiento con BD virgen (regla de oro del plan).
            [
                'clave' => 'st_tope_horas_mano_obra',
                'valor' => '2',
                'tipo' => Configuracion::TIPO_DECIMAL,
                'grupo' => 'servicio_tecnico',
                'descripcion' => 'Tope de horas de mano de obra por orden de servicio técnico (el desarme se paga una vez, no una hora por cada trabajo).',
            ],
            // --- Dashboard (PLAN-PARAMETRICOS, DASH-1) ---
            // Ventanas del pulso del Inicio. El default (7) es el valor histórico
            // y vive también como fallback en DashboardController: parametrizar
            // NO cambia el comportamiento con BD virgen (regla de oro del plan).
            // Claves SEPARADAS a propósito: son ventanas distintas aunque ambas
            // digan 7 (hallazgos #1 y #2 del mapa F0-DASH). Rango 2-31 validado
            // en la UI (ConfiguracionController::RANGOS) y clampeado al leer.
            [
                'clave' => 'dashboard_dias_serie_produccion',
                'valor' => '7',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'dashboard',
                'descripcion' => 'Días de producción que muestran las mini-barras del Inicio (incluye hoy). Rango 2-31.',
            ],
            [
                'clave' => 'dashboard_dias_referencia_merma',
                'valor' => '7',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'dashboard',
                'descripcion' => 'Contra cuántos días previos se compara la merma de hoy en el Inicio (el «prom. N días»). Rango 2-31.',
            ],
            // Cortes de antigüedad del taller (DASH-2, hallazgo #3): definen
            // los tramos 0-R / (R+1)-A / A+ de los equipos activos del Inicio.
            // Par ORDENADO: reciente < antiguo (validación cruzada en la UI +
            // clamp al leer). La «última semana» del flujo NO es parámetro:
            // quedó fija en 7 con su porqué (veredicto del dueño al #4).
            [
                'clave' => 'dashboard_corte_taller_reciente',
                'valor' => '7',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'dashboard',
                'descripcion' => 'Dónde termina el tramo reciente de los equipos activos del taller (días). Rango 2-60, menor que el corte antiguo.',
            ],
            [
                'clave' => 'dashboard_corte_taller_antiguo',
                'valor' => '30',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'dashboard',
                'descripcion' => 'Desde cuántos días un equipo activo del taller cuenta como antiguo. Rango 7-180, mayor que el corte reciente.',
            ],
            // --- Comercial (PLAN-PARAMETRICOS, COM-1) ---
            // Las dos listas del negocio, editables una-por-línea en la UI
            // (ConfiguracionController::LISTAS_SIMPLES). Los defaults son los
            // valores históricos; los fallbacks viven en Cliente::SEGMENTOS y
            // ProductoController::PRESETS_CATEGORIA_INTERNA (regla de oro).
            [
                'clave' => 'clientes_segmentos',
                'valor' => json_encode(['mayorista', 'retail', 'recurrente'], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'comercial',
                'descripcion' => 'Segmentos para clasificar clientes (uno por línea). Agregar es libre; quitar uno con clientes asignados se rechaza.',
            ],
            [
                'clave' => 'catalogo_categorias_sugeridas',
                'valor' => json_encode(['Repuestos industriales'], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'comercial',
                'descripcion' => 'Categorías internas que el corrector del catálogo sugiere aunque ningún producto las use todavía (una por línea).',
            ],
            // Feriados de Chile para calcular días hábiles (App\Support\DiasHabiles):
            // hoy los usa la cita de retiro tras un rechazo (dueño 07-08). 2026 está
            // completo; de 2027 van los de fecha fija + Semana Santa — los MOVIBLES
            // de 2027 (Pueblos Indígenas, San Pedro y San Pablo, Encuentro de Dos
            // Mundos, Iglesias Evangélicas) hay que cargarlos cuando se publiquen
            // (feriados.cl). Si la lista envejece, solo se saltan fines de semana.
            [
                'clave' => 'feriados_chile',
                'valor' => json_encode([
                    '2026-01-01', '2026-04-03', '2026-04-04', '2026-05-01', '2026-05-21',
                    '2026-06-21', '2026-06-29', '2026-07-16', '2026-08-15', '2026-09-18',
                    '2026-09-19', '2026-10-12', '2026-10-31', '2026-11-01', '2026-12-08',
                    '2026-12-25',
                    '2027-01-01', '2027-03-26', '2027-03-27', '2027-05-01', '2027-05-21',
                    '2027-07-16', '2027-08-15', '2027-09-18', '2027-09-19', '2027-11-01',
                    '2027-12-08', '2027-12-25',
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'general',
                'descripcion' => 'Feriados de Chile (YYYY-MM-DD) para días hábiles. Renovar cada año con los movibles (feriados.cl).',
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
            // El cliente NO aceptó → se le citó por correo a retirar su equipo sin
            // reparar el día hábil siguiente. Desde el 07-08 sale AUTOMÁTICO al
            // momento del rechazo ({avisado_por} = «el sistema…»); el botón manual
            // quedó de respaldo. Cambiar este default exige one-shot (2026_08_07_170000).
            [
                'clave' => 'notif_plantilla_cotizacion_retiro_avisado',
                'valor' => json_encode([
                    'asunto' => 'Retiro citado — Orden {folio} ({cliente})',
                    'cuerpo' => "A {cliente} se le citó por correo a retirar su equipo sin reparar el {retiro_dia} (orden {folio}).\nEquipo: {equipo}\nAvisó: {avisado_por}.\nCiclo cerrado: no hay que enviarle nada más.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso interno cuando, tras un NO ACEPTO, se cita al cliente a retirar su equipo sin reparar (automático al rechazo).',
            ],
            // El técnico avisó al cliente que su equipo está listo. Le importa a
            // SALA DE VENTAS: el cliente llega al mostrador a pagar y retirar.
            [
                'clave' => 'notif_plantilla_taller_listo_para_retiro',
                'valor' => json_encode([
                    'asunto' => 'Listo para retirar — Orden {folio} ({cliente})',
                    'cuerpo' => "A {cliente} se le avisó por correo que su equipo está listo para retirar (orden {folio}).\nEquipo: {equipo}\n{cobro}\nAvisó: {avisado_por}.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a ventas/taller cuando el técnico le dice al cliente que su equipo está listo para retirar (el cobro es en sala de ventas).',
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
                    // Sin la línea «El técnico ya puede proceder»: este aviso es de
                    // PLATA y va a ventas/admin (ROLES_AVISO_PAGO), y el taller no
                    // espera esta autorización — repara con la aceptación del
                    // cliente (dueño 07-08; migración 2026_08_07_150200).
                    'cuerpo' => "{autorizada_por} autorizó la reparación de la orden {folio} ({cliente}) por {total}.\nEquipo: {equipo}\nPago: {pago}.",
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
            // El técnico CERRÓ el trabajo en terreno (dueño 14-08-2026). Los dos
            // avisos van a ventas «por la zona»: jefe de ventas + el vendedor del
            // cliente. El `{detalle}` es el paso a paso que escribió el técnico, y
            // es el corazón del aviso: es lo que ventas necesita para facturar o
            // para hablar con el cliente sin volver a preguntarle nada al técnico.
            [
                'clave' => 'notif_plantilla_terreno_realizado',
                'valor' => json_encode([
                    'asunto' => 'Trabajo listo: {cliente} ({tipo}) · {ciudad}',
                    // Los {repuestos} van en el cuerpo porque el vendedor factura
                    // desde ACÁ: si tuviera que entrar a la app para saber qué se
                    // usó, la factura se armaría preguntándole al técnico.
                    'cuerpo' => "{tecnico} cerró el trabajo de {cliente} el {fecha}.\nTipo: {tipo} · {ciudad} · {direccion}\n\nQué se hizo:\n{detalle}\n\n{repuestos_titulo}:\n{repuestos}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al jefe de ventas y al vendedor del cliente cuando el técnico marca un trabajo de terreno como realizado.',
            ],
            [
                'clave' => 'notif_plantilla_terreno_no_realizado',
                'valor' => json_encode([
                    'asunto' => 'NO se pudo hacer: {cliente} ({tipo}) · {ciudad}',
                    // Los repuestos también acá: una visita que no se pudo terminar
                    // igual gasta repuestos (se cambió el filtro y faltó la membrana),
                    // y ese consumo hay que facturarlo o reponerlo igual.
                    'cuerpo' => "{tecnico} fue a {cliente} el {fecha} y el trabajo NO se pudo hacer.\nTipo: {tipo} · {ciudad} · {direccion}\n\nPor qué:\n{detalle}\n\n{repuestos_titulo}:\n{repuestos}\n\nSi hay que volver, ventas coordina una visita nueva.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al jefe de ventas y al vendedor del cliente cuando el técnico no pudo hacer el trabajo (falta un repuesto, el cliente no quiso, etc.).',
            ],
            // AVISOS PARA EL TÉCNICO sobre su propia agenda (dueño 14-08-2026). Los
            // escribe dirigidos a él y en segunda persona: el que los lee es el que
            // tiene que subirse a la camioneta. El {hora} y la {direccion} van
            // primero porque son lo que decide a qué hora sale y para dónde.
            [
                'clave' => 'notif_plantilla_terreno_agendado',
                'valor' => json_encode([
                    'asunto' => 'Te agendaron: {cliente} el {fecha} ({ciudad})',
                    'cuerpo' => "Tienes un trabajo nuevo en la agenda.\n\n{fecha} · {hora}\n{cliente} — {tipo}\n{direccion}, {ciudad}\nTeléfono: {telefono}\nServicio: {servicio}\n\nQué hay que hacer:\n{descripcion}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al técnico industrial cuando le agendan un trabajo en terreno (o cuando el jefe de ventas autoriza la cita).',
            ],
            [
                'clave' => 'notif_plantilla_terreno_reagendado',
                'valor' => json_encode([
                    'asunto' => 'Cambió tu visita a {cliente}: ahora {fecha}',
                    // El {antes} es lo que permite saber CUÁL de sus trabajos se movió.
                    'cuerpo' => "Te movieron un trabajo de la agenda.\n\nAntes: {antes}\nAhora: {fecha} · {hora}\n\n{cliente} — {tipo}\n{direccion}, {ciudad}\nTeléfono: {telefono}\nTécnico asignado: {tecnico}",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al técnico industrial cuando le cambian la fecha, la hora o el técnico de un trabajo ya agendado.',
            ],
            [
                'clave' => 'notif_plantilla_terreno_cancelado',
                'valor' => json_encode([
                    'asunto' => 'NO vayas: se canceló {cliente} del {fecha}',
                    'cuerpo' => "Se canceló un trabajo que tenías agendado.\n\n{fecha} · {hora}\n{cliente} — {tipo}\n{direccion}, {ciudad}\n\nNo vayas: ventas te avisa si se vuelve a coordinar.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al técnico industrial cuando le cancelan un trabajo que ya estaba agendado (para que no viaje al cliente).',
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
            // --- M11 · Corte SIC (P-M11-21): horarios de turno + umbral ---
            // HIPOTESIS editable (patron D-003): las horas reales las confirma
            // el dueño/Luis como ajuste de DATOS desde la UI, no de codigo. El
            // turno noche cruza medianoche a proposito (inicio > fin).
            [
                'clave' => 'produccion_turnos',
                'valor' => json_encode([
                    'dia' => ['inicio' => '08:00', 'fin' => '20:00'],
                    'noche' => ['inicio' => '20:00', 'fin' => '08:00'],
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Horarios de los turnos (hora chilena, HH:MM). Los usa el corte SIC para proyectar; un turno con inicio > fin cruza la medianoche. HIPÓTESIS: confirmar horas reales.',
            ],
            [
                'clave' => 'produccion_umbral_proyeccion',
                'valor' => '85',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'produccion',
                'descripcion' => 'Proyección mínima del turno (% de la meta asignada) bajo la cual el corte SIC avisa al jefe. El 2º corte consecutivo bajo el umbral llega URGENTE; el 3º ya no repite.',
            ],
            // La duración del turno NO existía como dato en ninguna parte
            // (verificado P-M11-11): el OEE la necesita para la Disponibilidad.
            // 720 = 12 h (día/noche cubren las 24). Hipótesis [B] editable acá.
            // OJO (merge del Director, 10-ago): produccion_turnos (arriba) y esta
            // clave son DOS hipótesis del mismo hecho — hoy coherentes (12 h).
            // Si Luis confirma horarios distintos, ajustar AMBAS; unificarlas es
            // pulido pendiente de F3 (derivar minutos de los horarios).
            [
                'clave' => 'produccion_minutos_turno',
                'valor' => '720',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'produccion',
                'descripcion' => 'Minutos que dura un turno de producción (día o noche). Lo usa el OEE como tiempo planificado por turno trabajado. Mantener coherente con produccion_turnos.',
            ],
            // --- Ventanas del panel y de los informes (OPE-1, PLAN-PARAMETRICOS §5.3 #1) ---
            // Son el DEFAULT del rango al abrir cada pantalla: el filtro de fechas
            // de la propia pantalla siempre puede pedir otro periodo (hasta 92 días).
            [
                'clave' => 'produccion_dias_panel',
                'valor' => '7',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'produccion',
                'descripcion' => 'Días que muestra al abrirse la sección «Producción por periodo» del panel del jefe (hoy incluido). El filtro de fechas del panel puede pedir otro rango cuando haga falta.',
            ],
            [
                'clave' => 'produccion_dias_informe_maquina',
                'valor' => '30',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'produccion',
                'descripcion' => 'Días que mira al abrirse el informe de rendimiento por máquina (hoy incluido). El filtro de fechas del informe puede pedir otro rango cuando haga falta.',
            ],
            [
                'clave' => 'produccion_dias_informe_tipo',
                'valor' => '30',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'produccion',
                'descripcion' => 'Días que mira al abrirse el informe de producción por tipo de botellón (hoy incluido). El filtro de fechas del informe puede pedir otro rango cuando haga falta.',
            ],
            // --- Listas de motivos y procedencias (OPE-2, PLAN-PARAMETRICOS §5.3 #9 y #13) ---
            // Defaults = las constantes vivas EXACTAS (ProduccionParada::MOTIVOS /
            // MOTIVOS_PLANIFICADOS y ProduccionAsignacion::PROCEDENCIAS, regla de
            // oro). El par planificados ⊆ motivos lo valida la UI de Configuración;
            // motivo y clase se PERSISTEN en cada parada, así que editar las listas
            // solo gobierna paradas futuras — el OEE histórico no se reescribe.
            [
                'clave' => 'produccion_motivos_parada',
                'valor' => json_encode([
                    'Faltaron preformas',
                    'Falla de máquina',
                    'Mantención de máquina',
                    'Cambio de molde',
                    'Molde dañado',
                    'Corte de luz',
                    'Scrap de arranque',
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Motivos que puede tocar el operario al registrar una parada de máquina (uno por línea). Quitar uno marcado como planificado se rechaza; las paradas ya registradas conservan su motivo.',
            ],
            [
                'clave' => 'produccion_motivos_planificados',
                'valor' => json_encode([
                    'Mantención de máquina',
                    'Cambio de molde',
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Motivos de parada que cuentan como PLANIFICADOS para el OEE (no descuentan disponibilidad). Deben existir en la lista de motivos de parada; el cambio solo afecta paradas futuras.',
            ],
            [
                'clave' => 'produccion_procedencias_preforma',
                'valor' => json_encode(['saco', 'caja'], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Formatos en que puede llegar la preforma del turno (uno por línea): el selector opcional del formulario de asignar producción. Las asignaciones viejas conservan el suyo.',
            ],
            // Quiénes cuentan como soplador (pedido del dueño 28-08-2026: en
            // «Asignar producción» aparecía cualquiera — poblaba por PERMISO).
            // El default vive también en User::ROLES_SOPLADOR (regla de oro);
            // un rol que no exista se rechaza al guardar y se descarta al leer.
            [
                'clave' => 'produccion_roles_soplador',
                'valor' => json_encode(\App\Models\User::ROLES_SOPLADOR, JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Roles cuyos usuarios aparecen como sopladores (uno por línea): asignar producción, historial por soplador y notas. Escribir el rol como en Administración → Roles.',
            ],
            // --- Motivos de defecto por calidad (MIPROD-1, pedido del dueño 21-08) ---
            // Defaults = la conducta NUEVA que pidió el dueño (no la histórica, a
            // propósito): una segunda es por definición un defecto estético, y las
            // malas pierden «Scrap de arranque» (decisión informada: el desglose de
            // scrap del OEE queda sin fuente hacia adelante; el histórico persiste).
            [
                'clave' => 'produccion_motivos_segunda',
                'valor' => json_encode(['Detalles estéticos'], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Motivos que puede marcar el operario en los botellones de SEGUNDA (uno por línea). Las tandas ya registradas conservan su motivo aunque salga de la lista.',
            ],
            [
                'clave' => 'produccion_motivos_malas',
                'valor' => json_encode([
                    'Burbujas / aire',
                    'Rebaba',
                    'Cuello o rosca deforme',
                    'Mal sellado',
                    'Punto frío',
                    'Contaminación / suciedad',
                    'Material quemado',
                    'Espesor irregular',
                    'Rayas o marcas',
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Motivos que puede marcar el operario en los botellones MALOS (uno por línea). Reponer «Scrap de arranque» reactiva el desglose de scrap del informe OEE.',
            ],
            // Whitelist de preformas del selector de asignar producción (pedido
            // del dueño 31-08): se edita con CHECKBOXES del catálogo. Vacía =
            // modo automático (todas las que califican) — el histórico, regla
            // de oro. El nombre de la clave es Producto::CLAVE_PREFORMAS_VISIBLES.
            [
                'clave' => \App\Models\Producto::CLAVE_PREFORMAS_VISIBLES,
                'valor' => '[]',
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'produccion',
                'descripcion' => 'Preformas que ofrece el selector de Asignar producción (se marcan con checkbox). Sin selección se ofrecen todas las del catálogo que califican.',
            ],
            // --- Flota de vehículos (LOG-2, PLAN-PARAMETRICOS §5.4 #1) ---
            // Grupo `vehiculos` y no `logistica`: el idioma del seeder agrupa por
            // apartado/pantalla (el hermano `despachos` ya sentó el precedente
            // aunque ambos cuelguen del menú Logística).
            [
                'clave' => 'vehiculos_dias_aviso',
                'valor' => '30',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'vehiculos',
                'descripcion' => 'Días antes del vencimiento en que un documento de la flota pasa a «Por vencer» (badge naranjo en listado, ficha y Excel) y entra al aviso diario. La deuda ya vencida avisa aparte.',
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
            // M11 · Corte SIC (P-M11-21). Clave nueva → firstOrCreate en el
            // deploy, sin one-shot. {urgencia} SIEMPRE viaja en el payload:
            // '' en el primer aviso y '⚠ URGENTE: ' en el segundo corte
            // consecutivo (no existe flag urgente; el asunto ES la señal).
            [
                'clave' => 'notif_plantilla_produccion_meta_en_riesgo',
                'valor' => json_encode([
                    'asunto' => '{urgencia}Meta en riesgo: {soplador} proyecta {proyeccion}%',
                    'cuerpo' => "La producción de {soplador} (turno {turno}) va más lento que la meta.\nProducido hasta ahora: {producido} de {meta} asignadas · proyección del turno: {proyeccion}%.\nMáquinas del turno: {maquinas}\nParadas abiertas:\n{paradas_abiertas}\nRevisa el reporte y coordina con el soplador antes de que termine el turno.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso al jefe de producción cuando el corte SIC proyecta el turno bajo el umbral (M11; el 2º corte consecutivo llega con asunto ⚠ URGENTE).',
            ],
            // ── M11 · Moldes (P-M11-12) ─────────────────────────────────────
            [
                'clave' => 'notif_plantilla_molde_umbral_mantencion',
                'valor' => json_encode([
                    'asunto' => 'Al molde {molde} le toca mantención',
                    'cuerpo' => "El molde {molde} ({tipo_botellon}) cruzó su umbral de mantención.\nCiclos acumulados: {ciclos} · umbral: {umbral}.\nRegistra la mantención en su ficha para resetear el contador.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a producción cuando un molde cruza su umbral de ciclos (M11 F3; una vez por cruce — registrar la mantención re-arma el aviso).',
            ],
            [
                'clave' => 'notif_plantilla_molde_correctiva_pendiente',
                'valor' => json_encode([
                    'asunto' => 'Molde dañado: correctiva pendiente para {molde}',
                    'cuerpo' => "Un reporte aprobado trae una parada «Molde dañado» del molde {molde} ({tipo_botellon}).\nSe creó una mantención CORRECTIVA pendiente en su ficha — regístrala cuando el molde quede reparado.\nCiclos acumulados: {ciclos}.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso a producción cuando un reporte aprobado trae parada «Molde dañado» (M11 F3; la correctiva nace pendiente, una por reporte).',
            ],

            // ── MSG-1 · Chat interno (PLAN-MENSAJES) ───────────────────────
            // Clave nueva → el firstOrCreate del seeder la crea en el deploy,
            // sin one-shot. Anti-spam de RÁFAGA: se dispara solo al pasar de
            // 0 no-leídos en el hilo, así que un chat activo manda UN aviso.
            [
                'clave' => 'notif_plantilla_mensaje_recibido',
                'valor' => json_encode([
                    'asunto' => 'Mensaje de {emisor}',
                    'cuerpo' => "{emisor} te escribió por el chat interno:\n\n«{extracto}»\n\nRespóndele desde Mensajes en DaliGo.",
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => 'notificaciones',
                'descripcion' => 'Aviso de mensaje del chat interno (MSG-1; ráfaga: solo el primero mientras el receptor no lea el hilo).',
            ],
        ];

        // ── Destinatarios por evento (Configuración → Avisos y destinatarios) ──
        // El default vive en AudienciasNotificacion::DEFAULTS (fuente única: el
        // seeder DERIVA de ahí para no escribir las listas dos veces). Clave
        // nueva → firstOrCreate la crea en el deploy sin one-shot; una edición
        // del dueño jamás se pisa.
        foreach (\App\Support\AudienciasNotificacion::DEFAULTS as $evento => $rolesDefault) {
            $ajustes[] = [
                'clave' => \App\Support\AudienciasNotificacion::clave($evento),
                'valor' => json_encode(array_values($rolesDefault), JSON_UNESCAPED_UNICODE),
                'tipo' => Configuracion::TIPO_JSON,
                'grupo' => \App\Support\AudienciasNotificacion::GRUPO,
                'descripcion' => 'Roles que reciben «'.\App\Models\Notificacion::EVENTOS[$evento].'». Se edita en Configuración → Avisos y destinatarios.',
            ];
        }

        // ── Límite de sesiones paralelas (Configuración → Sesiones por usuario) ──
        // Default 3 (pedido del dueño 01-09; hoy era ilimitado — el 3 ES el
        // cambio de conducta, no una parametrización de lo histórico). Claves
        // nuevas → firstOrCreate en el deploy sin one-shot.
        $ajustes[] = [
            'clave' => \App\Support\LimiteSesiones::CLAVE_DEFAULT,
            'valor' => (string) \App\Support\LimiteSesiones::DEFAULT,
            'tipo' => Configuracion::TIPO_INTEGER,
            'grupo' => \App\Support\LimiteSesiones::GRUPO,
            'descripcion' => 'Sesiones abiertas a la vez por usuario (0 = sin límite). Al topar, entra la nueva y se cierra la más antigua. Se edita en Configuración → Sesiones por usuario.',
        ];
        $ajustes[] = [
            'clave' => \App\Support\LimiteSesiones::CLAVE_ROLES,
            'valor' => '[]',
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => \App\Support\LimiteSesiones::GRUPO,
            'descripcion' => 'Límite de sesiones POR ROL (mapa rol → número; vacío = todos heredan el default). Se edita en Configuración → Sesiones por usuario.',
        ];
        $ajustes[] = [
            'clave' => \App\Support\LimiteSesiones::CLAVE_USUARIOS,
            'valor' => '[]',
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => \App\Support\LimiteSesiones::GRUPO,
            'descripcion' => 'Límite de sesiones POR USUARIO puntual (mapa id → número; gana sobre el rol y el default). Se edita en Configuración → Sesiones por usuario.',
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
