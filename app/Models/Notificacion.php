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
        // M12 · Cotización del taller al cliente (P-M12-02, fase correo)
        'cotizacion.enviada' => 'Cotización enviada al cliente',
        'cotizacion.respondida' => 'El cliente respondió la cotización',
        'cotizacion.autorizada' => 'Reparación autorizada (pago coordinado)',
        // Agenda de terreno · solicitud del cliente (QR) por coordinar
        'terreno.solicitada' => 'Solicitud del cliente por coordinar (terreno)',
        // Agenda de terreno · el cliente respondió a la cita agendada
        'terreno.confirmada' => 'El cliente respondió a la visita agendada',
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
     * Destino accionable de la notificacion segun su evento (hallazgo #5 del
     * QA 15-07: "toda alerta necesita superficie donde actuar"). Los eventos
     * de aprobacion llegan al APROBADOR (solicitada/escalada → su bandeja) o
     * al SOLICITANTE (resuelta → sus solicitudes). Null = fila no accionable.
     */
    public function urlDestino(): ?string
    {
        return match ($this->evento) {
            'aprobacion.solicitada', 'aprobacion.escalada' => route('aprobaciones.index'),
            'aprobacion.resuelta' => route('aprobaciones.mias'),
            // El origen (morph) es la OrdenServicio: se aterriza en su detalle.
            'cotizacion.enviada', 'cotizacion.respondida', 'cotizacion.autorizada' => $this->notificable_id
                ? route('admin.servicio-tecnico.show', $this->notificable_id)
                : null,
            // La solicitud por coordinar y la respuesta del cliente se ven en la agenda.
            'terreno.solicitada', 'terreno.confirmada' => route('admin.agenda-terreno.index'),
            default => null,
        };
    }
}
