<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Bodega espejada desde las offices de Bsale (M04). Solo lectura: el alta y la
 * gestión viven en Bsale. Auditable por si se agregan campos locales editables
 * más adelante (hoy ninguno); los stocks no se auditan (espejo masivo).
 */
class Bodega extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\BodegaFactory> */
    use HasFactory, AuditableTrait;

    protected $table = 'bodegas';

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
    ];

    protected function casts(): array
    {
        return [
            'es_virtual' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    /** @return HasMany<Stock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'bodega_id');
    }
}
