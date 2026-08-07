<?php

namespace Tests\Feature\Admin;

use App\Models\Bodega;
use App\Models\BodegaTraslado;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M04-F2 (P-M04-20): el wizard de baja. La regla que protege TODO el paso:
 * «eliminar» una bodega jamás pierde stock ni borra la fila — o está vacía y
 * muere al tiro, o el sistema OBLIGA a decidir a dónde va lo que contiene.
 */
class BajaBodegaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** Ve el inventario (manage productos) pero NO administra la estructura. */
    private function gestorCatalogo(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage productos');

        return $user;
    }

    /** Bodega clasificada con UN producto y su stock espejado. */
    private function bodegaConStock(float $cantidad = 40): array
    {
        $bodega = Bodega::factory()->clasificada()->create(['nombre' => 'SERAFIN ZAMORA']);
        $producto = Producto::factory()->create(['nombre' => 'Botellón 20L', 'sku' => 'BOT-20']);
        $stock = Stock::factory()->create([
            'bodega_id' => $bodega->id,
            'producto_id' => $producto->id,
            'stock_real' => $cantidad,
            'stock_reservado' => 0,
            'stock_disponible' => $cantidad,
        ]);

        return [$bodega, $producto, $stock];
    }

    // ── Candado 2 · vacía = baja al tiro, sin tocar stocks ni la fila ──────

    public function test_bodega_vacia_se_da_de_baja_al_tiro_sin_tocar_stocks(): void
    {
        [$bodega] = $this->bodegaConStock(0); // fila de stock EN CERO: vacía igual
        $fotoStocks = Stock::orderBy('id')->get()->toArray();

        $respuesta = $this->actingAs($this->admin())->post(route('admin.bodegas.baja.store', $bodega));

        $respuesta->assertRedirect(route('admin.bodegas.show', $bodega));
        $bodega->refresh();
        $this->assertSame(Bodega::BAJA_DADA_DE_BAJA, $bodega->estado_baja);
        $this->assertFalse($bodega->en_operacion, 'La baja es FINAL: apaga la operación (PLAN §F2).');
        $this->assertSame(0, BodegaTraslado::count(), 'Vacía = sin orden de traslado.');
        // La regla madre: ni una fila de stock se toca, y la bodega NO se borra.
        $this->assertSame($fotoStocks, Stock::orderBy('id')->get()->toArray());
        $this->assertDatabaseHas('bodegas', ['id' => $bodega->id]);
    }

    // ── Candado 1 · con stock NO se puede saltar el wizard (MUTADO) ────────

    public function test_con_stock_no_puede_saltarse_el_wizard(): void
    {
        [$bodega] = $this->bodegaConStock(40);

        $respuesta = $this->actingAs($this->admin())->post(route('admin.bodegas.baja.store', $bodega));

        $respuesta->assertSessionHas('status', fn (string $s) => str_contains($s, 'existencias'));
        $bodega->refresh();
        $this->assertNull($bodega->estado_baja, 'Con stock y sin destino NO hay baja: el wizard es obligatorio.');
        $this->assertSame(0, BodegaTraslado::count());
    }

    public function test_con_stock_y_destino_crea_la_orden_con_la_foto(): void
    {
        [$bodega, $producto] = $this->bodegaConStock(40);
        // Un segundo producto EN CERO: no entra a la foto (no hay nada que trasladar).
        Stock::factory()->create(['bodega_id' => $bodega->id, 'stock_real' => 0, 'stock_disponible' => 0]);
        $destino = Bodega::factory()->clasificada()->create(['nombre' => 'MIRADOR']);

        $respuesta = $this->actingAs($this->admin())->post(route('admin.bodegas.baja.store', $bodega), [
            'bodega_destino_id' => $destino->id,
        ]);

        $orden = BodegaTraslado::firstOrFail();
        $respuesta->assertRedirect(route('admin.bodegas.traslados.show', $orden));

        $this->assertSame($bodega->id, $orden->bodega_id);
        $this->assertSame($destino->id, $orden->bodega_destino_id);
        $this->assertSame(BodegaTraslado::PENDIENTE, $orden->estado);
        $this->assertSame(1, $orden->items()->count(), 'Solo lo que TIENE stock entra a la foto.');
        $item = $orden->items->first();
        $this->assertSame($producto->id, $item->producto_id);
        $this->assertSame('Botellón 20L', $item->nombre);
        $this->assertSame('BOT-20', $item->sku);
        $this->assertSame('40.0000', $item->cantidad);

        $bodega->refresh();
        $this->assertSame(Bodega::BAJA_PENDIENTE_TRASLADO, $bodega->estado_baja);
        $this->assertTrue($bodega->en_operacion, 'pendiente_traslado NO toca en_operacion: si la orden se anula, vuelve sola.');
        $this->assertFalse(
            Bodega::enOperacion()->whereKey($bodega->id)->exists(),
            'El scope operativo la excluye vía estado_baja: fuera de todo selector futuro.'
        );
    }

    // ── Candado 6 · la orden es una FOTO ───────────────────────────────────

    public function test_la_foto_no_cambia_aunque_el_stock_siga_moviendose(): void
    {
        [$bodega, , $stock] = $this->bodegaConStock(40);
        $destino = Bodega::factory()->clasificada()->create();
        $this->actingAs($this->admin())->post(route('admin.bodegas.baja.store', $bodega), [
            'bodega_destino_id' => $destino->id,
        ]);

        // El stock sigue drenando DESPUÉS de emitida la orden.
        $stock->update(['stock_real' => 15, 'stock_disponible' => 15]);

        $this->assertSame('40.0000', BodegaTraslado::firstOrFail()->items()->first()->cantidad,
            'La orden imprime lo que se decidió trasladar, no lo que haya ahora.');
    }

    // ── Candado 7 · destinos inválidos ─────────────────────────────────────

    public function test_el_destino_no_puede_ser_invalido(): void
    {
        [$bodega] = $this->bodegaConStock(40);
        $muerta = Bodega::factory()->fueraDeOperacion()->create();
        $enBaja = Bodega::factory()->clasificada()->create(['estado_baja' => Bodega::BAJA_PENDIENTE_TRASLADO]);

        $admin = $this->admin();
        $casos = [
            'ella misma' => $bodega->id,
            'una muerta' => $muerta->id,
            'una en baja' => $enBaja->id,
            'inexistente' => 99999,
        ];

        foreach ($casos as $caso => $destinoId) {
            $this->actingAs($admin)->post(route('admin.bodegas.baja.store', $bodega), [
                'bodega_destino_id' => $destinoId,
            ])->assertSessionHas('status', fn (string $s) => str_contains($s, 'existencias'));

            $this->assertNull($bodega->fresh()->estado_baja, "Destino [{$caso}] no puede iniciar la baja.");
            $this->assertSame(0, BodegaTraslado::count(), "Destino [{$caso}] no puede crear orden.");
        }
    }

    // ── Anular: pendiente_traslado no es un callejón sin salida ───────────

    public function test_anular_devuelve_la_bodega_a_operacion(): void
    {
        [$bodega] = $this->bodegaConStock(40);
        $destino = Bodega::factory()->clasificada()->create();
        $this->actingAs($this->admin())->post(route('admin.bodegas.baja.store', $bodega), [
            'bodega_destino_id' => $destino->id,
        ]);
        $orden = BodegaTraslado::firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.bodegas.traslados.anular', $orden))
            ->assertRedirect(route('admin.bodegas.show', $bodega->id));

        $orden->refresh();
        $this->assertSame(BodegaTraslado::ANULADO, $orden->estado);
        $this->assertNotNull($orden->anulado_at);
        $bodega->refresh();
        $this->assertNull($bodega->estado_baja);
        $this->assertTrue(Bodega::enOperacion()->whereKey($bodega->id)->exists(), 'Anulada la orden, la bodega vuelve sola.');
    }

    public function test_una_orden_completada_no_se_puede_anular(): void
    {
        $orden = BodegaTraslado::factory()->create([
            'estado' => BodegaTraslado::COMPLETADO,
            'completado_at' => now(),
        ]);

        $this->actingAs($this->admin())->post(route('admin.bodegas.traslados.anular', $orden))
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'no se puede anular'));

        $this->assertSame(BodegaTraslado::COMPLETADO, $orden->fresh()->estado);
    }

    // ── Candado 5 · 403 sin manage sucursales en TODO el flujo ────────────

    public function test_ver_inventario_no_habilita_el_flujo_de_baja(): void
    {
        [$bodega] = $this->bodegaConStock(40);
        $orden = BodegaTraslado::factory()->create(['bodega_id' => $bodega->id]);
        $gestor = $this->gestorCatalogo();

        // GET navegable → Inicio con aviso; mutación → 403 crudo (contrato de la casa).
        foreach ([route('admin.bodegas.baja', $bodega), route('admin.bodegas.traslados.show', $orden), route('admin.bodegas.traslados.excel', $orden)] as $url) {
            $this->actingAs($gestor)->get($url)
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
        }
        $this->actingAs($gestor)->post(route('admin.bodegas.baja.store', $bodega))->assertForbidden();
        $this->actingAs($gestor)->post(route('admin.bodegas.traslados.anular', $orden))->assertForbidden();

        $this->assertNull($bodega->fresh()->estado_baja);
    }

    public function test_el_boton_dar_de_baja_aparece_solo_a_quien_corresponde(): void
    {
        $bodega = Bodega::factory()->clasificada()->create();

        $deAdmin = $this->actingAs($this->admin())->get(route('admin.bodegas.show', $bodega))->getContent();
        $this->assertStringContainsString('Dar de baja', $deAdmin);

        $deGestor = $this->actingAs($this->gestorCatalogo())->get(route('admin.bodegas.show', $bodega))->getContent();
        $this->assertStringNotContainsString('Dar de baja', $deGestor);

        // Ya en baja: el botón desaparece también para el admin (no hay doble baja).
        $bodega->update(['estado_baja' => Bodega::BAJA_PENDIENTE_TRASLADO]);
        $enBaja = $this->actingAs($this->admin())->get(route('admin.bodegas.show', $bodega))->getContent();
        $this->assertStringNotContainsString('Dar de baja', $enBaja);
    }

    // ── El wizard en pantalla ───────────────────────────────────────────────

    public function test_el_wizard_pinta_sus_dos_estados(): void
    {
        [$bodega] = $this->bodegaConStock(40);
        $vacia = Bodega::factory()->clasificada()->create();
        $admin = $this->admin();

        $conStock = $this->actingAs($admin)->get(route('admin.bodegas.baja', $bodega))->assertOk()->getContent();
        $this->assertStringContainsString('Botellón 20L', $conStock);
        $this->assertStringContainsString('Bodega de destino', $conStock);

        $sinStock = $this->actingAs($admin)->get(route('admin.bodegas.baja', $vacia))->assertOk()->getContent();
        $this->assertStringContainsString('La bodega está vacía', $sinStock);
        $this->assertStringNotContainsString('Bodega de destino', $sinStock);

        // Ya en baja → de vuelta a la ficha, sin wizard.
        $bodega->update(['estado_baja' => Bodega::BAJA_PENDIENTE_TRASLADO]);
        $this->actingAs($admin)->get(route('admin.bodegas.baja', $bodega))
            ->assertRedirect(route('admin.bodegas.show', $bodega));
    }

    public function test_el_selector_de_destino_solo_ofrece_bodegas_en_operacion(): void
    {
        [$bodega] = $this->bodegaConStock(40);
        Bodega::factory()->clasificada()->create(['nombre' => 'DESTINO VIVO']);
        Bodega::factory()->fueraDeOperacion()->create(['nombre' => 'DESTINO MUERTO']);

        $html = $this->actingAs($this->admin())->get(route('admin.bodegas.baja', $bodega))->getContent();

        $this->assertStringContainsString('DESTINO VIVO', $html);
        $this->assertStringNotContainsString('DESTINO MUERTO', $html);
        $this->assertStringNotContainsString('value="'.$bodega->id.'"', $html); // ella misma tampoco
    }
}
