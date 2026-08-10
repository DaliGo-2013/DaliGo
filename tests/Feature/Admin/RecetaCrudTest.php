<?php

namespace Tests\Feature\Admin;

use App\Models\Producto;
use App\Models\ProduccionMovimiento;
use App\Models\ProduccionReporte;
use App\Models\Receta;
use App\Models\User;
use App\Support\AvisosError;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de recetas (P-M11-10): permiso `manage production` (candado 5 del
 * dictado), guardar = confirmar (D-003), validación con el MISMO scope del
 * selector (regla M-3), y NI UN costo a la vista (candado 6, regla Katana).
 */
class RecetaCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function botellon(): Producto
    {
        return Producto::create(['sku' => 'BOT-1', 'nombre' => 'Botellón 20L', 'categoria' => 'Botellones', 'activo' => true]);
    }

    private function tapa(string $sku = 'TAPA-1', string $nombre = 'Tapa rosca 3L', bool $activo = true): Producto
    {
        return Producto::create(['sku' => $sku, 'nombre' => $nombre, 'categoria' => 'Tapas', 'activo' => $activo]);
    }

    // --- Candado 5: 403/redirect sin `manage production` ---

    public function test_recetas_requieren_permiso_de_produccion(): void
    {
        $botellon = $this->botellon();

        // Soplador y alguien de catálogo (manage productos ≠ manage production
        // — la misma separación de M04-F1): GET navega → redirect al Inicio
        // con aviso; mutación → 403 crudo.
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $catalogo = tap(User::factory()->create())->givePermissionTo('manage productos');

        foreach ([$soplador, $catalogo] as $usuario) {

            $this->actingAs($usuario)->get(route('admin.recetas.index'))
                ->assertRedirect(route('dashboard'));
            $this->assertSame(AvisosError::SIN_PERMISO, session('aviso'));

            $this->actingAs($usuario)->get(route('admin.recetas.edit', $botellon))
                ->assertRedirect(route('dashboard'));

            $this->actingAs($usuario)->put(route('admin.recetas.update', $botellon), [
                'cantidad_preforma' => 1,
            ])->assertForbidden();
        }

        $this->assertSame(0, Receta::count());
    }

    // --- Guardar = confirmar (D-003) ---

    public function test_guardar_confirma_la_receta(): void
    {
        $botellon = $this->botellon();
        $tapa = $this->tapa();
        // La hipótesis del seeder, por confirmar.
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'cantidad' => 1, 'confirmada' => false]);
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'cantidad' => 1, 'confirmada' => false]);

        $this->actingAs($this->jefe())->put(route('admin.recetas.update', $botellon), [
            'cantidad_preforma' => 1,
            'cantidad_tapa' => 2,
            'componente_tapa' => $tapa->id,
        ])->assertRedirect(route('admin.recetas.index'));

        $this->assertDatabaseHas('recetas', ['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'confirmada' => true]);
        $this->assertDatabaseHas('recetas', ['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'componente_id' => $tapa->id, 'cantidad' => 2, 'confirmada' => true]);
    }

    public function test_sin_cantidad_de_tapa_la_fila_se_elimina(): void
    {
        $botellon = $this->botellon();
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'cantidad' => 1, 'confirmada' => false]);

        $this->actingAs($this->jefe())->put(route('admin.recetas.update', $botellon), [
            'cantidad_preforma' => 1,
        ])->assertRedirect(route('admin.recetas.index'));

        $this->assertDatabaseMissing('recetas', ['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA]);
        $this->assertDatabaseHas('recetas', ['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'confirmada' => true]);
    }

    // --- Validación con el MISMO scope del selector (regla M-3) ---

    public function test_tapa_fuera_del_scope_del_selector_es_rechazada(): void
    {
        $botellon = $this->botellon();
        $inactiva = $this->tapa('TAPA-X', 'Tapa vieja', activo: false);
        $danada = $this->tapa('TAPA-D', 'Tapa dañada 3L');

        foreach ([$inactiva, $danada] as $fueraDeScope) {
            $this->actingAs($this->jefe())->put(route('admin.recetas.update', $botellon), [
                'cantidad_preforma' => 1,
                'cantidad_tapa' => 1,
                'componente_tapa' => $fueraDeScope->id,
            ])->assertSessionHasErrors('componente_tapa');
        }

        $this->assertSame(0, Receta::count());
    }

    public function test_componente_sin_cantidad_es_rechazado(): void
    {
        $botellon = $this->botellon();
        $tapa = $this->tapa();

        $this->actingAs($this->jefe())->put(route('admin.recetas.update', $botellon), [
            'cantidad_preforma' => 1,
            'componente_tapa' => $tapa->id,
        ])->assertSessionHasErrors('cantidad_tapa');
    }

    // --- Candado 6: cantidades, jamás costos (regla Katana del PLAN §1.3) ---

    public function test_las_pantallas_de_recetas_no_muestran_costos(): void
    {
        $botellon = $this->botellon();
        $tapa = $this->tapa();
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'cantidad' => 1, 'confirmada' => false]);
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'componente_id' => $tapa->id, 'cantidad' => 2, 'confirmada' => true]);
        // Necesita un tipo enlazado para que el index liste el botellón.
        \App\Models\TipoBotellon::create(['codigo' => 'T1', 'nombre' => 'Tipo 1', 'producto_id' => $botellon->id, 'activo' => true]);

        $jefe = $this->jefe();

        foreach ([route('admin.recetas.index'), route('admin.recetas.edit', $botellon)] as $url) {
            $html = $this->actingAs($jefe)->get($url)->assertOk()->getContent();

            // Texto que el usuario percibe (sin tags ni atributos técnicos).
            $texto = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/si', ' ', $html);
            $texto = preg_replace('/<[^>]+>/', ' ', $texto);

            // Palabra completa, no substring (lección \brut\b: 'precio' dentro
            // de otra palabra no cuenta, y acá tampoco debe aparecer solo).
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\p{L}])(precio|precios|costo|costos|valor|CLP|UF)(?![\p{L}])/iu',
                $texto,
                "[{$url}] la pantalla de recetas menciona costos/precios: el operario y esta pantalla solo hablan de cantidades."
            );
            $this->assertDoesNotMatchRegularExpression('/\$\s?\d/', $texto, "[{$url}] hay un monto en pesos a la vista.");
        }
    }

    // --- El kardex muestra la etiqueta del tipo nuevo ---

    public function test_el_kardex_muestra_consumo_de_tapa(): void
    {
        $tapa = $this->tapa();
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $asignacion = \App\Models\ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 10,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 10,
            'estado' => ProduccionReporte::APROBADO,
        ]);
        ProduccionMovimiento::create([
            'reporte_id' => $reporte->id,
            'producto_id' => $tapa->id,
            'tipo' => ProduccionMovimiento::TIPO_CONSUMO_TAPA,
            'cantidad' => 40,
            'fecha' => now()->toDateString(),
        ]);

        $html = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.movimientos'))
            ->assertOk()
            ->getContent();

        // La fila (vía ETIQUETAS — mutable quitando la entrada) y el chip.
        $this->assertStringContainsString('Consumo de tapa', $html);
        $this->assertStringContainsString('Consumo tapa', $html);

        // Y el filtro por el tipo nuevo funciona.
        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.movimientos', ['tipo' => ProduccionMovimiento::TIPO_CONSUMO_TAPA]))
            ->assertOk()
            ->assertSee($tapa->nombre);
    }
}
