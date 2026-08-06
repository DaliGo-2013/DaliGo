<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Bodega espejada desde las offices de Bsale (M04) + su capa LOCAL de
 * clasificación (PLAN-M04 F1): la existencia física y el stock siguen
 * viniendo de Bsale (StockSync pisa esos campos en cada corrida), pero
 * sucursal, propósito, operación y alias son locales y editables desde la
 * app — el sync no los conoce. Auditable: los cambios de clasificación
 * quedan en /admin/audits (el sync corre withoutAuditing).
 */
class Bodega extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\BodegaFactory> */
    use HasFactory, AuditableTrait;

    protected $table = 'bodegas';

    /**
     * Propósito local (clave => etiqueta; patrón Devolucion::CANALES).
     * Fuente única para validación, seeder y vistas.
     */
    public const PROPOSITOS = [
        'fisica' => 'Física',
        'virtual_operativa' => 'Virtual operativa',
        'transito' => 'En tránsito (importaciones)',
        'insumos' => 'Insumos',
        'taller' => 'Taller',
        'cerrada' => 'Cerrada',
    ];

    /** Estados del ciclo de baja (los escribe F2; F1 solo los muestra). */
    public const BAJA_PENDIENTE_TRASLADO = 'pendiente_traslado';
    public const BAJA_DADA_DE_BAJA = 'dada_de_baja';

    protected $fillable = [
        'nombre',
        'direccion',
        'comuna',
        'ciudad',
        'email',
        'es_virtual',
        'activa',
        'bsale_default_price_list_id',
        'bsale_office_id',
        // Capa local (el sync NO las toca).
        'sucursal_id',
        'proposito',
        'en_operacion',
        'clasificacion_confirmada',
        'estado_baja',
        'alias',
    ];

    protected function casts(): array
    {
        return [
            'es_virtual' => 'boolean',
            'activa' => 'boolean',
            'en_operacion' => 'boolean',
            'clasificacion_confirmada' => 'boolean',
        ];
    }

    /**
     * El contrato para todo selector/pantalla OPERATIVA futura: las bodegas
     * dadas de baja o fuera de operación (las 6 muertas de D-003) no aparecen.
     * La pantalla admin de bodegas NO lo usa: ahí se administran todas.
     */
    public function scopeEnOperacion($query)
    {
        return $query->where('en_operacion', true);
    }

    public function scopeDeSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /** @return HasMany<Stock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'bodega_id');
    }
}
