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
        // M12 · Cotización del taller al cliente (P-M12-02, fase correo)
        'cotizacion.enviada' => 'Cotización enviada al cliente',
        'cotizacion.respondida' => 'El cliente respondió la cotización',
        'cotizacion.autorizada' => 'Reparación autorizada (pago coordinado)',
        // Agenda de terreno · solicitud del cliente (QR) por coordinar
        'terreno.solicitada' => 'Solicitud del cliente por coordinar (terreno)',
        // Agenda de terreno · el cliente respondió a la cita agendada
        'terreno.confirmada' => 'El cliente respondió a la visita agendada',
        // Agenda de terreno · una solicitud fue rechazada (con motivo)
        'terreno.rechazada' => 'Solicitud de terreno rechazada',
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
            'taller.reparado',
            'cotizacion.enviada', 'cotizacion.respondida', 'cotizacion.autorizada' => $user->canAny(['view servicio tecnico', 'manage servicio tecnico'])
                && $this->notificable instanceof OrdenServicio
                && $this->notificable->esVisiblePara($user),
            'terreno.solicitada', 'terreno.confirmada', 'terreno.rechazada' => $user->canAny(['ver agenda terreno', 'agendar servicio terreno']),
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
            'taller.reparado',
            'cotizacion.enviada', 'cotizacion.respondida', 'cotizacion.autorizada' => $this->notificable_id
                ? route('admin.servicio-tecnico.show', $this->notificable_id)
                : null,
            // La solicitud por coordinar, la respuesta del cliente y el rechazo se
            // ven en la agenda de terreno.
            'terreno.solicitada', 'terreno.confirmada', 'terreno.rechazada' => route('admin.agenda-terreno.index'),
            default => null,
        };
    }
}
