<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

/**
 * Conversacion 1-a-1 del chat interno (MSG-1, PLAN-MENSAJES §5.1): una fila
 * por PAR de usuarios, canonico (menor id / mayor id) — (A,B) y (B,A) son la
 * misma conversacion, garantizado por entre() + el unique compuesto.
 *
 * Los no-leidos son contadores POR LADO denormalizados: Mensajeria::enviar()
 * suma +1 al del receptor bajo lock; marcarLeida() pone el propio en 0. La
 * deriva no puede acumularse: cada lectura re-ancla a cero.
 *
 * Sin Auditable a proposito: alto volumen y la fila es su propia traza
 * (mismo criterio que Notificacion).
 */
class Conversacion extends Model
{
    protected $table = 'conversaciones';

    protected $fillable = [
        'user_menor_id',
        'user_mayor_id',
        'ultimo_mensaje_at',
        'no_leidos_menor',
        'no_leidos_mayor',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_mensaje_at' => 'datetime',
            'no_leidos_menor' => 'integer',
            'no_leidos_mayor' => 'integer',
        ];
    }

    /**
     * LA conversacion entre dos usuarios: canonicaliza el par (menor id
     * primero) y crea la fila si no existe. El unique compuesto es la red
     * final ante una carrera (doctrina vehiculo_avisos).
     */
    public static function entre(User|int $a, User|int $b): self
    {
        $idA = $a instanceof User ? $a->id : $a;
        $idB = $b instanceof User ? $b->id : $b;

        if ($idA === $idB) {
            throw new InvalidArgumentException('Una conversación necesita dos usuarios distintos.');
        }

        return static::firstOrCreate([
            'user_menor_id' => min($idA, $idB),
            'user_mayor_id' => max($idA, $idB),
        ]);
    }

    public function menor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_menor_id');
    }

    public function mayor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_mayor_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class, 'conversacion_id');
    }

    /**
     * El ultimo mensaje del hilo, eager-loadable para la lista (MSG-2). Va
     * por latestOfMany a proposito: un limit() dentro de un eager load es
     * GLOBAL, no por padre — la trampa clasica del preview de listas.
     */
    public function ultimoMensaje(): HasOne
    {
        return $this->hasOne(Mensaje::class, 'conversacion_id')->latestOfMany();
    }

    public function esParticipante(User $user): bool
    {
        return $this->user_menor_id === $user->id || $this->user_mayor_id === $user->id;
    }

    /**
     * El OTRO lado del hilo (null si el usuario fue eliminado — el cascade
     * normalmente se lleva la conversacion antes, pero el render no revienta).
     */
    public function otroLado(User $user): ?User
    {
        return $this->user_menor_id === $user->id ? $this->mayor : $this->menor;
    }

    /**
     * Columna del contador de no-leidos DEL LADO de este usuario.
     */
    public function columnaContadorDe(User $user): string
    {
        return $this->user_menor_id === $user->id ? 'no_leidos_menor' : 'no_leidos_mayor';
    }

    public function noLeidosDe(User $user): int
    {
        return (int) $this->{$this->columnaContadorDe($user)};
    }

    /**
     * Abrir el hilo: MI contador a 0. Idempotente — dos lecturas seguidas
     * dejan 0 sin efectos; el proximo mensaje vuelve a avisar (ráfaga).
     */
    public function marcarLeida(User $user): void
    {
        abort_unless($this->esParticipante($user), 403);

        $columna = $this->columnaContadorDe($user);

        if ((int) $this->{$columna} !== 0) {
            $this->update([$columna => 0]);
        }
    }

    /**
     * Las conversaciones donde participa el usuario. El orWhere va AGRUPADO
     * en su closure para no fugarse del resto del query.
     */
    public function scopeParaUsuario($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_menor_id', $userId)->orWhere('user_mayor_id', $userId);
        });
    }
}
