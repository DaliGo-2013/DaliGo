<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Preferencia de canal por usuario/evento: opt-out de notificaciones.
 * Sin fila = default del canal (mail habilitado, whatsapp deshabilitado,
 * database siempre — es el registro in-app).
 *
 * SI se audita (a diferencia de Notificacion): bajo volumen y significativo —
 * quien se dio de baja de que y cuando.
 */
class PreferenciaCanal extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'preferencias_canal';

    protected $fillable = [
        'user_id',
        'evento',
        'canal',
        'habilitado',
    ];

    protected function casts(): array
    {
        return [
            'habilitado' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * ¿El usuario tiene habilitado este canal para este evento?
     * Sin fila explicita rige el default del canal: mail = si,
     * whatsapp = no (stub hasta D-007), database = siempre.
     */
    public static function habilitadoPara(User $user, string $evento, string $canal): bool
    {
        if ($canal === Notificacion::CANAL_DATABASE) {
            return true;
        }

        $preferencia = static::query()
            ->where('user_id', $user->id)
            ->where('evento', $evento)
            ->where('canal', $canal)
            ->first();

        if ($preferencia !== null) {
            return $preferencia->habilitado;
        }

        return $canal === Notificacion::CANAL_MAIL;
    }
}
