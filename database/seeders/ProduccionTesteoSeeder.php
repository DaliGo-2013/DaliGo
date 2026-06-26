<?php

namespace Database\Seeders;

use App\Models\Producto;
use App\Models\TipoBotellon;
use Illuminate\Database\Seeder;

/**
 * Material de catalogo de PRUEBA para el flujo del soplador (M11), para que el
 * kardex sea demoable sin depender de que el catalogo real de Bsale ya tenga
 * categorizadas las preformas/botellones.
 *
 * - Crea preformas y botellones con prefijo `TEST-` y `bsale_variant_id=null`
 *   (el CatalogSync solo "adopta" SKUs que matchean EXACTO una variante de
 *   Bsale; el prefijo TEST- no colisiona, asi que la sync nunca los pisa).
 * - Enlaza los tipos de botellon base a su producto SOLO si aun no tienen uno
 *   (no pisa un enlace real puesto por un admin desde la UI).
 *
 * Idempotente (firstOrCreate por sku). Seguro re-ejecutar en cada deploy.
 */
class ProduccionTesteoSeeder extends Seeder
{
    public function run(): void
    {
        // Preformas (insumo).
        $this->producto('TEST-PREFORMA-600G', 'Preforma 600 g (testeo)', 'Preformas (testeo)');
        $this->producto('TEST-PREFORMA-750G', 'Preforma 750 g (testeo)', 'Preformas (testeo)');

        // Botellones (producto terminado), uno por tipo base. Mapeo
        // codigo de tipo_botellon -> [sku producto, nombre producto].
        $botellones = [
            'AZUL-20L' => ['TEST-BOTELLON-AZUL-20L', 'Botellón Azul 20L s/manilla (testeo)'],
            'AZUL-20L-MANILLA' => ['TEST-BOTELLON-AZUL-20L-MANILLA', 'Botellón Azul 20L c/manilla (testeo)'],
            'AZUL-10L-RETORNABLE' => ['TEST-BOTELLON-AZUL-10L', 'Botellón Azul 10L retornable (testeo)'],
            'INCOLORO-10L-RETORNABLE' => ['TEST-BOTELLON-INCOLORO-10L', 'Botellón Incoloro 10L retornable (testeo)'],
        ];

        foreach ($botellones as $codigoTipo => [$sku, $nombre]) {
            $producto = $this->producto($sku, $nombre, 'Botellones (testeo)');

            // Enlaza el tipo a su producto solo si no tiene uno (no pisa enlaces
            // reales puestos desde la UI).
            $tipo = TipoBotellon::where('codigo', $codigoTipo)->first();
            if ($tipo && $tipo->producto_id === null) {
                $tipo->update(['producto_id' => $producto->id]);
            }
        }
    }

    private function producto(string $sku, string $nombre, string $categoria): Producto
    {
        return Producto::firstOrCreate(
            ['sku' => $sku],
            ['nombre' => $nombre, 'categoria' => $categoria, 'activo' => true],
        );
    }
}
