<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        // Clasificación propia de DaliGo (curada a mano); Bsale nunca la toca.
        'categoria_interna',
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

    /**
     * Categoría EFECTIVA: la corregida en DaliGo (`categoria_interna`) MANDA; si
     * no hay corrección, se usa la de Bsale (`categoria`). Bsale nunca pisa
     * `categoria_interna`, así que la corrección es duradera. Es lo que el
     * catálogo muestra y por lo que filtra.
     */
    public function getCategoriaEfectivaAttribute(): ?string
    {
        return $this->categoria_interna ?? $this->categoria;
    }

    /**
     * Normaliza un nombre de categoría para comparar de forma tolerante:
     * minúsculas, sin acentos, sin puntuación (el punto de "disp." u otros) y
     * espacios colapsados. Así "AGUA DISP. PEDESTAL…" (Bsale) calza con
     * "agua disp pedestal…" (config) aunque difieran en puntuación/mayúsculas.
     */
    public static function normalizarCategoria(?string $s): string
    {
        $s = \Illuminate\Support\Str::ascii(mb_strtolower(trim((string) $s)));
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Solo "equipos de taller" (dispensadores, lavadoras, bombas, herramientas):
     * productos cuya `categoria` (el product_type espejado de Bsale), NORMALIZADA,
     * calce con alguna de `config('servicio_tecnico.categorias_equipo')`. Excluye
     * accesorios/repuestos del buscador público del QR. El match es tolerante a
     * mayúsculas/acentos/puntuación (ver normalizarCategoria) para no depender de
     * que el nombre en Bsale coincida carácter a carácter con el config. Lista
     * vacía = no filtra (evita dejar el buscador sin resultados por config faltante).
     */
    public function scopeEquipoTaller(Builder $query): Builder
    {
        $objetivo = collect(config('servicio_tecnico.categorias_equipo', []))
            ->map(fn ($c) => self::normalizarCategoria($c))
            ->filter()
            ->unique()
            ->values();

        if ($objetivo->isEmpty()) {
            return $query;
        }

        // Categorías reales del catálogo (los product_types son pocas decenas)
        // cuya forma normalizada calza con el allowlist. Se filtra por el valor
        // ORIGINAL para no depender de normalización en SQL (portable 5.7/SQLite).
        $categoriasOk = static::query()
            ->whereNotNull('categoria')
            ->distinct()
            ->pluck('categoria')
            ->filter(fn ($c) => $objetivo->contains(self::normalizarCategoria($c)))
            ->values()
            ->all();

        return $query->whereIn('categoria', $categoriasOk);
    }

    /** @return HasMany<Precio, $this> */
    public function precios(): HasMany
    {
        return $this->hasMany(Precio::class, 'producto_id');
    }

    /**
     * Precio de venta CON IVA, tomado de la lista OFICIAL de ventas
     * (`config('daligo.lista_precios_ventas')`, hoy GENERAL). Null si el
     * producto no tiene precio ahí.
     *
     * Fuente única: antes esta misma consulta estaba copiada en 4 lugares
     * (valor hora, buscador de repuestos, precio del equipo, tiempos de
     * reparación) y todas decían «la primera lista activa que aparezca». Con
     * 5 listas activas espejadas de Bsale eso devolvía una lista arbitraria.
     * El null NO es un fallback: es la respuesta correcta cuando el producto
     * no está en la lista oficial (quien atiende escribe el precio a mano),
     * porque tomarlo de otra lista sería mostrar un precio de origen incierto.
     */
    public function precioVentaConIva(): ?int
    {
        $this->loadMissing('precios.lista');

        $oficial = config('daligo.lista_precios_ventas');

        $precio = $oficial
            ? $this->precios->first(fn (Precio $p) => $p->lista
                && mb_strtoupper(trim((string) $p->lista->nombre)) === mb_strtoupper(trim((string) $oficial)))
            // Sin lista oficial configurada se vuelve al criterio antiguo (solo
            // entornos de prueba sin catálogo espejado).
            : $this->precios->first(fn (Precio $p) => (bool) $p->lista?->activa) ?? $this->precios->first();

        return $precio ? (int) round((float) $precio->precio_con_iva) : null;
    }

    /** @return HasMany<Stock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'producto_id');
    }
}
