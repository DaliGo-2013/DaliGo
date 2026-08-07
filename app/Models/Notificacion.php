<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Notificacion del motor M15: una fila por (evento disparado × canal).
 *
 * SIN AuditableTrait a proposito (visto bueno 2026-07-02): tabla de alto
 * volumen cuya fila es su propia traza (estado/intentos/ultimo_error);
 * cada reintento muta estado+intentos y auditarlo inundaria `audits`
 * (mismo criterio que ProduccionRegistro/ProduccionMovimiento).
 *
 * Transiciones de estado validas (PLAN-M15 §1.2):
 * - mail/whatsapp: pendiente → enviada | pendiente → fallida → pendiente
 *   (reintento) → … hasta notif_reintentos_max (fallida terminal). Nunca leida.
 * - database: pendiente → enviada → leida (leida_at).
 */
class Notificacion extends Model
{
    protected $table = 'notificaciones';

    public const CANAL_MAIL = 'mail';
    public const CANAL_DATABASE = 'database';
    public const CANAL_WHATSAPP = 'whatsapp';

    public const CANALES = [self::CANAL_MAIL, self::CANAL_DATABASE, self::CANAL_WHATSAPP];

    public const PENDIENTE = 'pendiente';
    public const ENVIADA = 'enviada';
    public const FALLIDA = 'fallida';
    public const LEIDA = 'leida';

    public const ESTADOS = [self::PENDIENTE, self::ENVIADA, self::FALLIDA, self::LEIDA];

    /**
     * Catalogo de eventos notificables (clave => etiqueta para UI/plantillas).
     * Fuente unica para validacion, seeds y vistas (patron MOTIVOS_DEFECTO).
     * Los modulos consumidores (M14/M12/M13) agregan aqui sus eventos al integrar.
     */
    public const EVENTOS = [
        'sistema.prueba' => 'Notificación de prueba',
        // M14 · Aprobaciones (PLAN-M14 §1.3)
        'aprobacion.solicitada' => 'Solicitud de aprobación pendiente',
        'aprobacion.escalada' => 'Solicitud de aprobación escalada',
        'aprobacion.resuelta' => 'Solicitud de aprobación resuelta',
        // Taller · un cliente ingresó un equipo por QR (unidad o cantidad):
        // aviso a ventas + al técnico para que sepan que entró una máquina.
        'taller.ingresado' => 'Ingreso de equipo al taller (QR)',
        // Taller · el técnico marcó la orden como REPARADA: ventas tiene que
        // llamar al cliente para que lo retire (decision del dueño 30-07).
        'taller.reparado' => 'Equipo reparado (avisar al cliente)',
        // Taller · no tuvo arreglo. Mismo destinatario que 'reparado', pero la
        // conversacion es otra: reemplazo, o garantia si fue falla de fabrica.
        'taller.sin_solucion' => 'Equipo sin solución (avisar al cliente)',
        // Traslado de maquinas sucursal -> casa matriz (decision del dueño 03-08).
        'traslado.despachado' => 'Vienen máquinas en camino al taller',
        'traslado.recibido' => 'Traslado recibido en el taller',
        // El aviso que cierra las excusas: salieron N y llegaron menos.
        'traslado.diferencias' => 'Traslado recibido CON DIFERENCIAS',
        // M12 · Cotización del taller al cliente (P-M12-02, fase correo)
        'cotizacion.enviada' => 'Cotización enviada al cliente',
        'cotizacion.respondida' => 'El cliente respondió la cotización',
        'cotizacion.autorizada' => 'Reparación autorizada (pago coordinado)',
        // Tras un NO ACEPTO: se le avisó al cliente que retire sin reparar
        // (pedido del dueño 06-08, junto con el «¿por qué?» de la respuesta).
        'cotizacion.retiro_avisado' => 'Se avisó al cliente: retirar sin reparar',
        // Garantía: salió el correo con el detalle del trabajo (sin cobro) —
        // el par de 'cotizacion.enviada' para que la ruta completa quede en
        // la campanita también cuando no hay cobro (dueño 06-08).
        'garantia.detalle_enviado' => 'Detalle de garantía enviado al cliente',
        // Agenda de terreno · solicitud del cliente (QR) por coordinar
        'terreno.solicitada' => 'Solicitud del cliente por coordinar (terreno)',
        // Agenda de terreno · el cliente respondió a la cita agendada
        'terreno.confirmada' => 'El cliente respondió a la visita agendada',
        // Agenda de terreno · una solicitud fue rechazada (con motivo)
        'terreno.rechazada' => 'Solicitud de terreno rechazada',
        // Logística · vencimiento de documentos de la flota (decisión del dueño
        // 04-08): aviso 30 días antes y aviso cuando ya venció. Lo dispara el
        // comando `vehiculos:avisar-vencimientos`, no una acción de usuario.
        'vehiculo.documento_por_vencer' => 'Documento de vehículo por vencer',
        'vehiculo.documento_vencido' => 'Documento de vehículo VENCIDO',
        // M13 · Devoluciones (flujo A-12, PLAN-M13 §1.3). `solicitada` es el
        // aviso INTERNO (bodega + ventas); `recibida` y `resuelta` van al
        // CLIENTE (destinatario externo → solo mail): el «notificar» con que
        // cierra A-12.
        'devolucion.solicitada' => 'Devolución declarada por un cliente',
        'devolucion.recibida' => 'Devolución recibida en bodega',
        'devolucion.resuelta' => 'Devolución resuelta (aviso al cliente)',
        // M08 · Hoja de ruta (P-DSP-09): el conductor NO pudo entregar una
        // parada — el jefe de despacho decide qué pasa con esa carga (R15;
        // si el rechazo crea la devolución M13 solo es decisión del dueño,
        // pregunta abierta en el parte de P-DSP-09).
        'despacho.parada_rechazada' => 'Entrega rechazada en la puerta',
        // M04 · Inventario (P-M04-12): el sync trajo una office que Bsale creó
        // — llega sin clasificar y alguien con `manage sucursales` tiene que
        // asignarle sucursal y propósito. Lo dispara StockSync, no un usuario.
        'bodega.nueva' => 'Bodega nueva en Bsale (por clasificar)',
    ];

    protected $fillable = [
        'evento',
        'notificable_type',
        'notificable_id',
        'user_id',
        'destinatario',
        'canal',
        'titulo',
        'cuerpo',
        'payload',
        'estado',
        'intentos',
        'ultimo_error',
        'programada_para',
        'enviada_at',
        'leida_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'intentos' => 'integer',
            'programada_para' => 'datetime',
            'enviada_at' => 'datetime',
            'leida_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notificable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * El destino de la fila, SOLO si este usuario puede llegar (null si no).
     *
     * La bandeja enlazaba a ciegas con urlDestino(): las notificaciones de
     * cotizacion se despachan a ROLES_AVISO (tecnico/jefe_ventas/vendedor/admin),
     * asi que un vendedor SIN 'ver todo servicio tecnico' que tocaba la de un
     * cliente de OTRA cartera caia en 403 (ServicioTecnicoController::show ->
     * OrdenServicio::esVisiblePara). Los permisos que se chequean aca son los
     * mismos gates de esas rutas en routes/web.php.
     */
    public function urlDestinoPara(?User $user): ?string
    {
        $url = $this->urlDestino();

        if ($url === null || $user === null) {
            return null;
        }

        $puede = match ($this->evento) {
            'aprobacion.solicitada', 'aprobacion.escalada' => $user->can('aprobar solicitudes'),
            'aprobacion.resuelta' => true, // "mis solicitudes": basta estar autenticado
            // Si el destino es la ficha de una orden, además del permiso hay que
            // respetar el scope de cartera del vendedor (igual que cotizacion.*).
            'taller.ingresado' => $user->canAny(['view servicio tecnico', 'manage servicio tecnico'])
                && (! $this->notificable instanceof OrdenServicio || $this->notificable->esVisiblePara($user)),
            // Detalle de una orden: permiso Y scope de cartera del vendedor.
            'taller.reparado', 'taller.sin_solucion',
            'cotizacion.enviada', 'cotizacion.respondida', 'cotizacion.autorizada',
            'cotizacion.retiro_avisado', 'garantia.detalle_enviado' => $user->canAny(['view servicio tecnico', 'manage servicio tecnico'])
                && $this->notificable instanceof OrdenServicio
                && $this->notificable->esVisiblePara($user),
            'terreno.solicitada', 'terreno.confirmada', 'terreno.rechazada' => $user->canAny(['ver agenda terreno', 'agendar servicio terreno']),
            // La ficha del traslado la abre quien despacha o quien recibe.
            'traslado.despachado', 'traslado.recibido', 'traslado.diferencias' => $user->canAny(['despachar traslado servicio', 'recibir traslado servicio']),
            // La ficha del vehículo: mismo gate que su ruta en routes/web.php.
            'vehiculo.documento_por_vencer', 'vehiculo.documento_vencido' => $user->canAny(['ver vehiculos', 'manage vehiculos']),
            // La ficha de la devolución: mismo gate que su ruta (M13). Los
            // eventos al CLIENTE (recibida/resuelta) no llegan aquí — van por
            // mail a un destinatario externo, sin fila de campanita.
            'devolucion.solicitada' => $user->canAny(['view devoluciones', 'manage devoluciones']),
            // La hoja de ruta: mismo gate que su show en routes/web.php.
            'despacho.parada_rechazada' => $user->canAny(['manage hojas ruta', 'autorizar pagos ruta', 'autorizar ruta', 'autorizar carga']),
            // La ficha de clasificación: mismo gate que bodegas.edit (M04-F1).
            'bodega.nueva' => $user->can('manage sucursales'),
            default => false,
        };

        return $puede ? $url : null;
    }

    /** No-leidas de la campanita de un usuario (canal database, aun no leidas). */
    public function scopeCampanitaDe($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->where('canal', self::CANAL_DATABASE)
            ->where('estado', self::ENVIADA);
    }

    /** Marca una notificacion in-app como leida (idempotente). */
    public function marcarLeida(): void
    {
        if ($this->estado !== self::LEIDA) {
            $this->update(['estado' => self::LEIDA, 'leida_at' => now()]);
        }
    }

    /**
     * Fragmento de aterrizaje PUNTUAL de una aprobacion (lote NOTIF-1): la
     * bandeja y «Mis solicitudes» emiten `id="aprobacion-{id}"` por tarjeta/fila.
     * Sin id → cadena vacia (la lista pelada, como antes).
     *
     * Punto UNICO a proposito: lo usan `urlDestino()` (la fila de la campanita)
     * y el `payload['url']` que arma `Aprobaciones` para el boton del correo —
     * duplicar el calculo fue justo lo que dejo al correo apuntando a la lista
     * mientras la campanita ya aterrizaba en la tarjeta (deuda cerrada el 28-07).
     */
    public static function anclaAprobacion(?int $aprobacionId): string
    {
        return $aprobacionId ? '#aprobacion-'.$aprobacionId : '';
    }

    /**
     * Destino accionable de la notificacion segun su evento (hallazgo #5 del
     * QA 15-07: "toda alerta necesita superficie donde actuar"). Los eventos
     * de aprobacion llegan al APROBADOR (solicitada/escalada → su bandeja) o
     * al SOLICITANTE (resuelta → sus solicitudes). Null = fila no accionable.
     */
    public function urlDestino(): ?string
    {
        $ancla = self::anclaAprobacion($this->notificable_id);

        return match ($this->evento) {
            'aprobacion.solicitada', 'aprobacion.escalada' => route('aprobaciones.index').$ancla,
            'aprobacion.resuelta' => route('aprobaciones.mias').$ancla,
            // Ingreso por confirmar. Si el origen es UNA orden, se aterriza en su
            // ficha, que es donde está el botón «Confirmar recepción»; si es un LOTE
            // (N órdenes, sin ficha propia), en el listado.
            'taller.ingresado' => $this->notificable instanceof OrdenServicio && $this->notificable_id
                ? route('admin.servicio-tecnico.show', $this->notificable_id)
                : route('admin.servicio-tecnico.index'),
            // El origen (morph) es la OrdenServicio: se aterriza en su detalle.
            'taller.reparado', 'taller.sin_solucion',
            'cotizacion.enviada', 'cotizacion.respondida', 'cotizacion.autorizada',
            'cotizacion.retiro_avisado', 'garantia.detalle_enviado' => $this->notificable_id
                ? route('admin.servicio-tecnico.show', $this->notificable_id)
                : null,
            // La solicitud por coordinar, la respuesta del cliente y el rechazo se
            // ven en la agenda de terreno.
            'terreno.solicitada', 'terreno.confirmada', 'terreno.rechazada' => route('admin.agenda-terreno.index'),
            // El traslado aterriza en SU ficha: es donde se confirma la recepcion
            // y donde se ve que maquina falta.
            'traslado.despachado', 'traslado.recibido', 'traslado.diferencias' => $this->notificable_id
                ? route('admin.traslados.show', $this->notificable_id)
                : route('admin.traslados.index'),
            // El vencimiento aterriza en la ficha del vehículo: es donde se
            // actualiza la fecha del documento que venció.
            'vehiculo.documento_por_vencer', 'vehiculo.documento_vencido' => $this->notificable_id
                ? route('admin.vehiculos.show', $this->notificable_id)
                : route('admin.vehiculos.index'),
            // La devolución aterriza en SU ficha: es donde bodega la recibe,
            // la categoriza y la resuelve (M13).
            'devolucion.solicitada' => $this->notificable_id
                ? route('admin.devoluciones.show', $this->notificable_id)
                : route('admin.devoluciones.index'),
            // El rechazo en puerta aterriza en la HOJA DE RUTA (el morph es la
            // hoja): ahí el jefe ve la parada rechazada con su motivo.
            'despacho.parada_rechazada' => $this->notificable_id
                ? route('admin.hojas-ruta.show', $this->notificable_id)
                : route('admin.hojas-ruta.index'),
            // La bodega nueva aterriza en su FICHA DE CLASIFICACIÓN: la acción
            // pendiente es asignarle sucursal y propósito (M04-F1).
            'bodega.nueva' => $this->notificable_id
                ? route('admin.bodegas.edit', $this->notificable_id)
                : route('admin.bodegas.index'),
            default => null,
        };
    }
}
