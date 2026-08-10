<?php

namespace Tests\Feature\Admin;

use App\Models\Producto;
use App\Models\Receta;
use Database\Seeders\ProduccionTesteoSeeder;
use Database\Seeders\RecetaSeeder;
use Database\Seeders\TipoBotellonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La hipótesis [B] del seeder de recetas (candado 4 del dictado v40):
 * idempotente, sin productos fantasma, y JAMÁS pisa lo editado desde la UI.
 */
class RecetaSeederTest extends TestCase
{
    use RefreshDatabase;

    private function sembrarCatalogo(): void
    {
        $this->seed(TipoBotellonSeeder::class);
        $this->seed(ProduccionTesteoSeeder::class);
    }

    public function test_seeder_dos_veces_es_idempotente_y_no_crea_productos(): void
    {
        $this->sembrarCatalogo();
        $productosAntes = Producto::count();

        $this->seed(RecetaSeeder::class);
        // 4 botellones TEST- enlazados × 2 roles = 8 filas hipótesis.
        $this->assertSame(8, Receta::count());
        $this->assertSame(8, Receta::where('confirmada', false)->count());
        $this->assertSame(8, Receta::whereNull('componente_id')->count());

        $this->seed(RecetaSeeder::class);
        $this->assertSame(8, Receta::count());

        // Nada de TEST-TAPA fantasma: el seeder no toca el catálogo (el
        // candado de los 6 TEST-% vive en ProduccionKardexTest).
        $this->assertSame($productosAntes, Producto::count());
    }

    public function test_seeder_no_pisa_lo_editado_desde_la_ui(): void
    {
        $this->sembrarCatalogo();
        $this->seed(RecetaSeeder::class);

        // Luis confirma una fila con otra cantidad… y otra queda editada sin
        // confirmar. firstOrCreate no actualiza: NINGUNA se pisa.
        $confirmada = Receta::where('rol', Receta::ROL_PREFORMA)->first();
        $confirmada->update(['cantidad' => 3, 'confirmada' => true]);
        $borrador = Receta::where('rol', Receta::ROL_TAPA)->first();
        $borrador->update(['cantidad' => 2.5]);

        $this->seed(RecetaSeeder::class);

        $this->assertSame('3.0000', $confirmada->fresh()->cantidad);
        $this->assertTrue($confirmada->fresh()->confirmada);
        $this->assertSame('2.5000', $borrador->fresh()->cantidad);
    }
}
