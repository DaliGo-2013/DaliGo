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
}
