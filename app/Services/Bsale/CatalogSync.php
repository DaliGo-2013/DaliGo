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
 */
class CatalogSync
{
    public function __construct(private BsaleClient $client) {}

    /**
     * @return array{creados:int,actualizados:int,adoptados:int,omitidos:int,errores:array<int,array>}
     */
    public function run(): array
    {
        $stats = ['creados' => 0, 'actualizados' => 0, 'adoptados' => 0, 'omitidos' => 0, 'errores' => []];

        $typeMap = $this->fetchProductTypeMap();

        // Carga masiva: sin audit por fila (igual que el import CSV); resumen al log.
        Producto::withoutAuditing(function () use (&$stats, $typeMap) {
            foreach ($this->client->each('variants.json', ['expand' => '[product]', 'state' => 0]) as $variant) {
                try {
                    $this->upsertOne($variant, $typeMap, $stats);
                } catch (Throwable $e) {
                    $stats['omitidos']++;
                    $stats['errores'][] = [
                        'variant_id' => $variant['id'] ?? null,
                        'sku' => $variant['code'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        Log::info(sprintf(
            'bsale:sync-catalog → %d creados, %d actualizados, %d adoptados, %d omitidos, %d errores.',
            $stats['creados'], $stats['actualizados'], $stats['adoptados'], $stats['omitidos'], count($stats['errores']),
        ));

        return $stats;
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

    private function upsertOne(array $variant, array $typeMap, array &$stats): void
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
        $row = Producto::where('bsale_variant_id', $variantId)->first();

        if ($row !== null) {
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
        $unlinked = Producto::whereNull('bsale_variant_id')->where('sku', $sku)->first();

        if ($unlinked !== null) {
            $unlinked->fill($bsaleFields)->save();
            $stats['adoptados']++;

            return;
        }

        // 3) Nuevo producto.
        try {
            Producto::create($bsaleFields);
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
