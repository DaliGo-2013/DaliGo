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
 * Despacho de un documento de venta espejado: el retiro en bodega se valida
 * con el QR del código (anti-fraude M07) y la entrega la confirma el conductor
 * con firma+foto+hora (M08-MVP). Auditable: queda registro de quién lo creó y
 * de cada transición.
 */
class Despacho extends Model implements AuditableContract
{
    use AuditableTrait;

    // Pluralizador inglés fallaría; fijado a mano como ordenes_servicio.
    protected $table = 'despachos';

    public const PREPARADO = 'preparado';
    public const RETIRADO = 'retirado';
    public const EN_RUTA = 'en_ruta';
    public const ENTREGADO = 'entregado';
    public const ENTREGA_PARCIAL = 'entrega_parcial';

    public const ESTADOS = [
        self::PREPARADO,
        self::RETIRADO,
        self::EN_RUTA,
        self::ENTREGADO,
        self::ENTREGA_PARCIAL,
    ];

    protected $fillable = [
        'codigo',
        'documento_venta_id',
        'zona_id',
        'estado',
        'transportista',
        'conductor_id',
        'retirado_at',
        'entregado_at',
        'capturado_at',
        'entrega_uuid',
        'firma_path',
        'foto_path',
    ];

    protected function casts(): array
    {
        return [
            'retirado_at' => 'datetime',
            'entregado_at' => 'datetime',
            'capturado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Código único e impredecible al crear (el QR no es enumerable).
        static::creating(function (self $despacho) {
            if (blank($despacho->codigo)) {
                $despacho->codigo = self::generarCodigoUnico();
            }
            if (blank($despacho->estado)) {
                $despacho->estado = self::PREPARADO;
            }
        });
    }

    /** Código único e impredecible (ej. DSP-K7QM2X9P). Reintenta ante colisión. */
    public static function generarCodigoUnico(): string
    {
        do {
            $codigo = 'DSP-'.Str::upper(Str::random(8));
        } while (static::where('codigo', $codigo)->exists());

        return $codigo;
    }

    /** @return BelongsTo<DocumentoVenta, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_venta_id');
    }

    /** @return BelongsTo<Zona, $this> */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /** @return BelongsTo<User, $this> */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conductor_id');
    }

    /** @return HasMany<EscaneoDespacho, $this> */
    public function escaneos(): HasMany
    {
        return $this->hasMany(EscaneoDespacho::class, 'despacho_id');
    }

    /** Aún no retirado de bodega (la cola "McDonald's" muestra estos). */
    public function scopePendienteDeRetiro(Builder $query): Builder
    {
        return $query->where('estado', self::PREPARADO);
    }

    /** En manos del conductor (hoja de ruta). */
    public function scopeEnReparto(Builder $query): Builder
    {
        return $query->whereIn('estado', [self::RETIRADO, self::EN_RUTA]);
    }
}
