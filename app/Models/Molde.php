<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * El molde como entidad (P-M11-12): ficha estilo M18 con contador de ciclos
 * que se alimenta solo al aprobar reportes (producción / cavidades activas),
 * umbral de mantención con aviso M15 «una vez por cruce» y su historial.
 *
 * El ciclo ideal NO vive acá: sigue en la fila ROL_PREFORMA de la receta
 * del producto del tipo (un solo lugar escribible); la ficha lo MUESTRA con
 * enlace a la receta — ver `cicloIdealDeReceta()`.
 */
class Molde extends Model implements AuditableContract
{
    use AuditableTrait;

    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_EN_MANTENCION = 'en_mantencion';
    public const ESTADO_RETIRADO = 'retirado';

    public const ESTADOS = [
        self::ESTADO_ACTIVO => 'Activo',
        self::ESTADO_EN_MANTENCION => 'En mantención',
        self::ESTADO_RETIRADO => 'Retirado',
    ];

    protected $table = 'moldes';

    protected $fillable = [
        'nombre',
        'tipo_botellon_id',
        'cavidades',
        'ciclos_acumulados',
        'umbral_mantencion',
        'estado',
        'notas',
        'aviso_umbral_at',
    ];

    protected function casts(): array
    {
        return [
            'cavidades' => 'integer',
            'ciclos_acumulados' => 'integer',
            'umbral_mantencion' => 'integer',
            'aviso_umbral_at' => 'datetime',
        ];
    }

    public function tipoBotellon(): BelongsTo
    {
        return $this->belongsTo(TipoBotellon::class, 'tipo_botellon_id');
    }

    public function mantenciones(): HasMany
    {
        return $this->hasMany(MoldeMantencion::class, 'molde_id');
    }

    /** Solo los ACTIVOS trabajan: la inferencia y los selectores parten acá. */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** Variante del badge (paleta de 4): requiere acción → brand; reposo → neutral. */
    public function varianteBadge(): string
    {
        return match (true) {
            $this->estado === self::ESTADO_EN_MANTENCION => 'brand',
            $this->correctivaPendiente() !== null => 'brand',
            $this->umbralCruzado() => 'brand',
            default => 'neutral',
        };
    }

    public function correctivaPendiente(): ?MoldeMantencion
    {
        return $this->mantenciones
            ->where('tipo', MoldeMantencion::TIPO_CORRECTIVA)
            ->whereNull('realizada_at')
            ->first();
    }

    public function umbralCruzado(): bool
    {
        return $this->umbral_mantencion !== null
            && $this->ciclos_acumulados >= $this->umbral_mantencion;
    }

    /**
     * El ciclo ideal del botellón de este molde, leído de la RECETA (la única
     * portadora del dato — decisión P-M11-12). Null = sin ciclo cargado.
     */
    public function cicloIdealDeReceta(): ?int
    {
        $productoId = $this->tipoBotellon?->producto_id;

        if ($productoId === null) {
            return null;
        }

        $ciclo = Receta::where('producto_id', $productoId)
            ->where('rol', Receta::ROL_PREFORMA)
            ->value('ciclo_ideal_seg');

        return $ciclo !== null ? (int) $ciclo : null;
    }

    /** «Faltan N ciclos para mantención» en palabras — nunca un número pelado. */
    public function umbralLabel(): ?string
    {
        if ($this->umbral_mantencion === null) {
            return null;
        }

        $faltan = $this->umbral_mantencion - $this->ciclos_acumulados;

        return $faltan > 0
            ? 'Faltan '.number_format($faltan, 0, ',', '.').' ciclos para la mantención'
            : 'Umbral cruzado: le toca mantención';
    }
}
