<?php

namespace App\Models;

use Database\Factories\ServicioTerrenoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Servicio de terreno del catálogo (técnico industrial): mantención/reparación/
 * instalación de plantas de osmosis, llenadoras y lavadoras, con tarifa en UF,
 * duración y detalle. Editable desde la app; el seeder solo crea lo que falte.
 */
class ServicioTerreno extends Model implements AuditableContract
{
    /** @use HasFactory<ServicioTerrenoFactory> */
    use AuditableTrait, HasFactory;

    protected $table = 'servicios_terreno';

    protected $fillable = [
        'nombre',
        'valor_uf',
        'duracion',
        'incluye',
        'observaciones',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'valor_uf' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Solo los servicios ofrecibles (para el selector de la agenda).
     *
     * @param  Builder<ServicioTerreno>  $query
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('nombre');
    }

    /** @return HasMany<AgendaTrabajo, $this> */
    public function trabajos(): HasMany
    {
        return $this->hasMany(AgendaTrabajo::class, 'servicio_terreno_id');
    }
}
