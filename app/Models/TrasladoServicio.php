<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Traslado de maquinas a reparar de una sucursal a la casa matriz.
 *
 * Cierra el tramo que no tenia dueño: una maquina recibida en Abate o Coquimbo
 * se repara en Mirador, y entre una cosa y la otra no quedaba registro de quien
 * la entrego ni quien la recibio (pedido del dueño 03-08-2026: «eliminar las
 * excusas, todo transparente»).
 *
 * Reglas del dueño que este modelo hace cumplir:
 *  - Despacha el jefe de sucursal o un administrativo de esa sucursal
 *    (permiso 'despachar traslado servicio', asignable por rol desde la UI).
 *  - Recibe el tecnico, el jefe de bodega o el jefe de ventas
 *    (permiso 'recibir traslado servicio').
 *  - Una maquina NO se puede reparar si no fue recepcionada en la matriz
 *    (ver OrdenServicio::getEnTransitoAttribute y el candado de la reparacion).
 */
class TrasladoServicio extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'traslados_servicio';

    public const EN_TRANSITO = 'en_transito';

    public const RECIBIDO = 'recibido';

    protected static function booted(): void
    {
        static::creating(function (self $traslado) {
            if (blank($traslado->codigo)) {
                $traslado->codigo = self::generarCodigoUnico();
            }
        });
    }

    /** Codigo unico e impredecible (ej. TR-K7QM2X9P), como ordenes y lotes. */
    public static function generarCodigoUnico(): string
    {
        do {
            $codigo = 'TR-'.Str::upper(Str::random(8));
        } while (static::where('codigo', $codigo)->exists());

        return $codigo;
    }

    protected $fillable = [
        'codigo',
        'sucursal_origen_id',
        'sucursal_destino_id',
        'estado',
        'emisor_id',
        'emisor_nombre',
        'conductor',
        'despachado_at',
        'observaciones_envio',
        'receptor_id',
        'receptor_nombre',
        'recibido_at',
        'observaciones_recepcion',
        'total_enviado',
        'total_recibido',
    ];

    protected function casts(): array
    {
        return [
            'despachado_at' => 'datetime',
            'recibido_at' => 'datetime',
            'total_enviado' => 'integer',
            'total_recibido' => 'integer',
        ];
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emisor_id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptor_id');
    }

    /** @return HasMany<OrdenServicio, $this> */
    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenServicio::class, 'traslado_id');
    }

    public function scopeEnTransito(Builder $query): Builder
    {
        return $query->where('estado', self::EN_TRANSITO);
    }

    public function getRecibidoAttribute(): bool
    {
        return $this->estado === self::RECIBIDO;
    }

    /**
     * ¿Llegaron menos maquinas de las que salieron? Es el dato que convierte una
     * discusion en un hecho: se compara el conteo CONGELADO del despacho contra
     * lo que el receptor confirmo.
     */
    public function getTieneDiferenciaAttribute(): bool
    {
        return $this->recibido
            && $this->total_recibido !== null
            && $this->total_recibido < $this->total_enviado;
    }

    public function getFaltantesAttribute(): int
    {
        return $this->tiene_diferencia
            ? $this->total_enviado - (int) $this->total_recibido
            : 0;
    }

    /** Etiqueta de estado para la UI, con la diferencia incorporada. */
    public function getEstadoLabelAttribute(): string
    {
        if (! $this->recibido) {
            return 'En tránsito';
        }

        return $this->tiene_diferencia
            ? 'Recibido con diferencias'
            : 'Recibido';
    }

    /** Variante de <x-badge> del estado (rojo si falta alguna maquina). */
    public function getEstadoVarianteAttribute(): string
    {
        return match (true) {
            $this->tiene_diferencia => 'danger',
            $this->recibido => 'success',
            default => 'warning',
        };
    }

    /**
     * Las maquinas que el receptor NO confirmo. Se listan con nombre y folio: sin
     * eso, «falta una» no le sirve a nadie.
     *
     * @return \Illuminate\Support\Collection<int, OrdenServicio>
     */
    public function ordenesFaltantes()
    {
        return $this->ordenes->whereNull('traslado_recibida_at');
    }
}
