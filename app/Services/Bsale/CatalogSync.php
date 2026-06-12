<?php

namespace App\Services\Bsale;

use App\Models\Producto;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza el catalogo de Bsale hacia la tabla local `productos`.
 *
 * Bsale manda la identidad (sku, nombre, categoria, barcode, activo, ids); DaliGo
 * conserva el enriquecimiento (peso/dimensiones, marca, atributos, descripcion):
 * esos campos NO entran en el upsert, asi que jamas se pisan.
 *
 * El match se resuelve contra mapas precargados en memoria (id por variante /
 * id por sku sin enlazar): sin consultas de busqueda por fila (el barrido real
 * puede superar los miles de variantes).
 */
class CatalogSync
{
    /** @var array<int, int> bsale_variant_id => id local */
    private array $idPorVariante = [];

    /** @var array<string, int> sku => id local (solo filas SIN enlace a Bsale) */
    private array $idSinEnlacePorSku = [];

    public function __construct(private BsaleClient $client) {}

    /**
     * @return array{creados:int,actualizados:int,adoptados:int,desactivados:int,omitidos:int,errores:array<int,array>}
     */
    public function run(): array
    {
        $stats = ['creados' => 0, 'actualizados' => 0, 'adoptados' => 0, 'desactivados' => 0, 'omitidos' => 0, 'errores' => []];

        $typeMap = $this->fetchProductTypeMap();

        $this->idPorVariante = Producto::whereNotNull('bsale_variant_id')
            ->pluck('id', 'bsale_variant_id')->all();
        $this->idSinEnlacePorSku = Producto::whereNull('bsale_variant_id')
            ->pluck('id', 'sku')->all();

        // Solo los enlaces que existian ANTES del barrido son candidatos a
        // desactivar; lo creado/adoptado durante la corrida queda en $vistos.
        $candidatosDesactivar = $this->idPorVariante;

        $vistos = [];
        $recorridas = 0;

        // Carga masiva: sin audit por fila (igual que el import CSV); resumen al log.
        Producto::withoutAuditing(function () use (&$stats, &$vistos, &$recorridas, $candidatosDesactivar, $typeMap) {
            foreach ($this->client->each('variants.json', ['expand' => '[product]', 'state' => 0]) as $variant) {
                $recorridas++;

                try {
                    $this->upsertOne($variant, $typeMap, $stats, $vistos);
                } catch (Throwable $e) {
                    $stats['omitidos']++;
                    $stats['errores'][] = [
                        'variant_id' => $variant['id'] ?? null,
                        'sku' => $variant['code'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->desactivarNoVistos($candidatosDesactivar, $vistos, $recorridas, $stats);
        });

        Log::info(sprintf(
            'bsale:sync-catalog → %d creados, %d actualizados, %d adoptados, %d desactivados, %d omitidos, %d errores.',
            $stats['creados'], $stats['actualizados'], $stats['adoptados'], $stats['desactivados'], $stats['omitidos'], count($stats['errores']),
        ));

        return $stats;
    }

    /**
     * Bsale solo lista variantes ACTIVAS (state=0): una variante desactivada o
     * eliminada alla simplemente deja de venir, y sin esta fase la fila local
     * quedaria `activo=true` para siempre (catalogo fantasma).
     *
     * Guard anti-apagado-masivo (mismo criterio que PriceListSync): si el barrido
     * trajo variantes pero NINGUNA se reconocio ($vistos vacio), desactivar todo
     * lo enlazado nunca es un espejo fiel — se salta la fase y se reporta.
     *
     * @param  array<int, int>  $candidatos  bsale_variant_id => id local (pre-barrido)
     * @param  array<int, true>  $vistos
     */
    private function desactivarNoVistos(array $candidatos, array $vistos, int $recorridas, array &$stats): void
    {
        if ($vistos === []) {
            if ($recorridas > 0) {
                $stats['errores'][] = [
                    'variant_id' => null,
                    'sku' => null,
                    'error' => "{$recorridas} variantes recorridas y 0 reconocidas; se omite la desactivación de no-vistos (¿catálogo desincronizado?).",
                ];
            }

            return;
        }

        $ids = [];
        foreach ($candidatos as $variantId => $id) {
            if (! isset($vistos[$variantId])) {
                $ids[] = $id;
            }
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            $stats['desactivados'] += Producto::whereIn('id', $chunk)
                ->where('activo', true)
                ->update(['activo' => false]);
        }
    }

    /**
     * @return array<int, string> id => nombre
     */
    private function fetchProductTypeMap(): array
    {
        $map = [];

        foreach ($this->client->each('product_types.json', ['state' => 0]) as $type) {
            if (isset($type['id'])) {
                $map[(int) $type['id']] = trim((string) ($type['name'] ?? ''));
            }
        }

        return $map;
    }

    /**
     * @param  array<int, true>  $vistos
     */
    private function upsertOne(array $variant, array $typeMap, array &$stats, array &$vistos): void
    {
        $variantId = isset($variant['id']) ? (int) $variant['id'] : null;
        if ($variantId === null) {
            throw new \RuntimeException('Variante sin id.');
        }

        $sku = trim((string) ($variant['code'] ?? ''));
        if ($sku === '') {
            throw new \RuntimeException("Variante {$variantId} sin code (SKU).");
        }

        $product = is_array($variant['product'] ?? null) ? $variant['product'] : [];
        $productId = isset($product['id']) ? (int) $product['id'] : null;
        $ptId = isset($product['product_type']['id']) ? (int) $product['product_type']['id'] : null;
        $barcode = trim((string) ($variant['barCode'] ?? ''));

        // Solo campos que MANDA Bsale. Los locales (peso/dims/marca/atributos/descripcion)
        // se omiten a proposito => no se tocan en update y quedan null en create.
        $bsaleFields = [
            'sku' => $sku,
            'barcode' => $barcode !== '' ? $barcode : null,
            'nombre' => trim((string) ($product['name'] ?? $variant['description'] ?? '')) ?: $sku,
            'categoria' => $ptId !== null ? ($typeMap[$ptId] ?? null) : null,
            'bsale_product_type_id' => $ptId,
            'activo' => (int) ($variant['state'] ?? 0) === 0,
            'bsale_variant_id' => $variantId,
            'bsale_product_id' => $productId,
        ];

        // 1) Match por bsale_variant_id (maneja RENOMBRE: el sku puede cambiar aqui).
        // Se marca visto ANTES de guardar: la variante SI existe en Bsale aunque el
        // update falle (p. ej. colision de sku) — no debe desactivarse por eso.
        if (isset($this->idPorVariante[$variantId])) {
            $vistos[$variantId] = true;
            $row = Producto::find($this->idPorVariante[$variantId]);

            try {
                $row->fill($bsaleFields)->save();
                $stats['actualizados']++;
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    throw new \RuntimeException("Renombrar variante {$variantId} a SKU '{$sku}' colisiona; omitida.");
                }
                throw $e;
            }

            return;
        }

        // 2) Adopcion: fila sin enlazar (creada por CSV/UI) con el mismo SKU.
        if (isset($this->idSinEnlacePorSku[$sku])) {
            $id = $this->idSinEnlacePorSku[$sku];
            Producto::find($id)->fill($bsaleFields)->save();

            unset($this->idSinEnlacePorSku[$sku]);
            $this->idPorVariante[$variantId] = $id;
            $vistos[$variantId] = true;
            $stats['adoptados']++;

            return;
        }

        // 3) Nuevo producto.
        try {
            $nuevo = Producto::create($bsaleFields);
            $this->idPorVariante[$variantId] = $nuevo->id;
            $vistos[$variantId] = true;
            $stats['creados']++;
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new \RuntimeException("SKU '{$sku}' ya existe en otra fila; variante {$variantId} omitida.");
            }
            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');

        return $code === '1062' || $code === '19'
            || str_contains($e->getMessage(), 'UNIQUE')
            || str_contains($e->getMessage(), 'Duplicate');
    }
}
