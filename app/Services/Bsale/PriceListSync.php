<?php

namespace App\Services\Bsale;

use App\Models\ListaPrecio;
use App\Models\Precio;
use App\Models\Producto;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza las listas de precios de Bsale hacia `listas_precios` + `precios`.
 *
 * Bsale manda cabeceras (nombre, moneda, estado) y valores por variante
 * (variantValue neto / variantValueWithTaxes con IVA); DaliGo conserva el campo
 * local `canal` (no entra en el upsert). Detalles solo de listas ACTIVAS (las
 * inactivas son promos muertas; espejar sus valores es trabajo inutil). A
 * diferencia del catalogo, aqui los precios que Bsale ya no manda SE BORRAN:
 * un precio obsoleto induce a cotizar mal (el catalogo viejo solo desinforma).
 *
 * Shape real verificado (cuenta DALI): ids llegan como STRING ("15", "979");
 * coin es {href,id} sin codigo; variantValue trae floats largos (bruto/1.19).
 */
class PriceListSync
{
    public function __construct(private BsaleClient $client) {}

    /**
     * @return array{listas:int,creados:int,actualizados:int,eliminados:int,omitidos:int,errores:array<int,array>}
     */
    public function run(): array
    {
        $stats = ['listas' => 0, 'creados' => 0, 'actualizados' => 0, 'eliminados' => 0, 'omitidos' => 0, 'errores' => []];

        // Cabeceras: TODAS (el flag activa tambien se espeja). Sin audit por fila.
        ListaPrecio::withoutAuditing(function () use (&$stats) {
            foreach ($this->client->each('price_lists.json') as $pl) {
                try {
                    $this->upsertLista($pl);
                    $stats['listas']++;
                } catch (Throwable $e) {
                    $stats['errores'][] = [
                        'lista_id' => $pl['id'] ?? null,
                        'detalle' => null,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        // Detalles: solo listas activas. Match al catalogo local por bsale_variant_id.
        $productoPorVariante = Producto::whereNotNull('bsale_variant_id')
            ->pluck('id', 'bsale_variant_id');

        foreach (ListaPrecio::where('activa', true)->get() as $lista) {
            try {
                $this->syncDetalles($lista, $productoPorVariante, $stats);
            } catch (Throwable $e) {
                $stats['errores'][] = [
                    'lista_id' => $lista->bsale_price_list_id,
                    'detalle' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info(sprintf(
            'bsale:sync-prices → %d listas, %d precios creados, %d actualizados, %d eliminados, %d omitidos, %d errores.',
            $stats['listas'], $stats['creados'], $stats['actualizados'], $stats['eliminados'], $stats['omitidos'], count($stats['errores']),
        ));

        return $stats;
    }

    private function upsertLista(array $pl): void
    {
        $bsaleId = isset($pl['id']) ? (int) $pl['id'] : null;
        if ($bsaleId === null || $bsaleId === 0) {
            throw new \RuntimeException('Lista sin id.');
        }

        $nombre = trim((string) ($pl['name'] ?? ''));

        // Solo campos que MANDA Bsale; `canal` (local) se omite => no se pisa.
        ListaPrecio::updateOrCreate(
            ['bsale_price_list_id' => $bsaleId],
            [
                'nombre' => $nombre !== '' ? mb_substr($nombre, 0, 191) : "Lista {$bsaleId}",
                'descripcion' => $this->limpiar($pl['description'] ?? null),
                'bsale_coin_id' => isset($pl['coin']['id']) ? (int) $pl['coin']['id'] : null,
                'activa' => (int) ($pl['state'] ?? 0) === 0,
            ],
        );
    }

    /**
     * Espeja los valores de una lista activa. Al final BORRA los precios de esa
     * lista cuyo producto Bsale ya no incluye (espejo fiel: precio que no esta
     * en Bsale no debe poder cotizarse).
     *
     * @param  \Illuminate\Support\Collection<int|string, int>  $productoPorVariante
     */
    private function syncDetalles(ListaPrecio $lista, $productoPorVariante, array &$stats): void
    {
        $vistos = [];
        $detalles = 0;

        foreach ($this->client->each("price_lists/{$lista->bsale_price_list_id}/details.json") as $det) {
            $detalles++;
            $variantId = isset($det['variant']['id']) ? (int) $det['variant']['id'] : null;
            $productoId = $variantId !== null ? ($productoPorVariante[$variantId] ?? null) : null;

            if ($productoId === null) {
                // Variante sin espejo en el catalogo local (producto inactivo o
                // catalogo desactualizado): se omite, no se inventan productos.
                $stats['omitidos']++;

                continue;
            }

            $precio = Precio::updateOrCreate(
                ['lista_precio_id' => $lista->id, 'producto_id' => $productoId],
                [
                    'precio_neto' => isset($det['variantValue']) ? round((float) $det['variantValue'], 4) : null,
                    'precio_con_iva' => isset($det['variantValueWithTaxes']) ? round((float) $det['variantValueWithTaxes'], 4) : null,
                    'bsale_detail_id' => isset($det['id']) ? (int) $det['id'] : null,
                ],
            );

            $precio->wasRecentlyCreated ? $stats['creados']++ : $stats['actualizados']++;
            $vistos[] = $productoId;
        }

        // Guard anti-borrado-masivo: si la lista TRAE details pero ninguno matchea
        // el catalogo local ($vistos vacio), whereNotIn([]) compilaria como 1=1 y
        // borraria TODOS los precios de la lista. Eso nunca es un espejo fiel: es
        // sintoma de catalogo desincronizado (productos sin bsale_variant_id).
        // Se salta el delete y se reporta. (Lista legitimamente vacia en Bsale,
        // $detalles === 0, si borra: espejo fiel de "sin precios".)
        if ($detalles > 0 && $vistos === []) {
            $stats['errores'][] = [
                'lista_id' => $lista->bsale_price_list_id,
                'detalle' => null,
                'error' => "Lista {$lista->bsale_price_list_id}: {$detalles} details y 0 matches con el catalogo local; se omite el borrado de precios (catalogo desincronizado?).",
            ];

            return;
        }

        $stats['eliminados'] += Precio::where('lista_precio_id', $lista->id)
            ->whereNotIn('producto_id', $vistos)
            ->delete();
    }

    private function limpiar(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, 191);
    }
}
