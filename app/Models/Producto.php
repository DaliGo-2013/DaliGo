<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Producto del catalogo maestro local (nivel SKU).
 *
 * Peso y dimensiones viven aqui porque Bsale no los guarda. Enlace a Bsale por
 * bsale_variant_id (el SKU es la variante) / bsale_product_id, nullable hasta
 * que exista la sincronizacion con la API.
 */
class Producto extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\ProductoFactory> */
    use HasFactory, AuditableTrait;

    protected $table = 'productos';

    protected $fillable = [
        'sku',
        'barcode',
        'nombre',
        'descripcion',
        'categoria',
        'marca',
        'peso_kg',
        'alto_cm',
        'ancho_cm',
        'largo_cm',
        'atributos',
        'activo',
        'bsale_variant_id',
        'bsale_product_id',
        'bsale_product_type_id',
    ];

    protected function casts(): array
    {
        return [
            'atributos' => 'array',
            'activo' => 'boolean',
            'peso_kg' => 'decimal:3',
            'alto_cm' => 'decimal:2',
            'ancho_cm' => 'decimal:2',
            'largo_cm' => 'decimal:2',
        ];
    }

    /** @return HasMany<Precio, $this> */
    public function precios(): HasMany
    {
        return $this->hasMany(Precio::class, 'producto_id');
    }

    /** @return HasMany<Stock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'producto_id');
    }
}
