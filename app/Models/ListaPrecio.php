<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lista de precios espejada desde Bsale (M02.2).
 *
 * Bsale manda nombre/descripcion/moneda/estado; DaliGo agrega el canal
 * (convencion local: en Bsale no existe "una lista = un canal"). Auditable
 * porque el canal se edita desde la UI; los valores (precios) no se auditan:
 * son espejo masivo de solo lectura.
 */
class ListaPrecio extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\ListaPrecioFactory> */
    use HasFactory, AuditableTrait;

    protected $table = 'listas_precios';

    public const COIN_CLP = 1;

    protected $fillable = [
        'nombre',
        'descripcion',
        'bsale_coin_id',
        'activa',
        'canal',
        'bsale_price_list_id',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    /** @return HasMany<Precio, $this> */
    public function precios(): HasMany
    {
        return $this->hasMany(Precio::class, 'lista_precio_id');
    }
}
