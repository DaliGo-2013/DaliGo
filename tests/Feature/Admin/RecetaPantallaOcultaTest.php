<?php

namespace Tests\Feature\Admin;

use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionMovimiento;
use App\Models\ProduccionReporte;
use App\Models\Receta;
use App\Models\TipoBotellon;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La pantalla de Recetas está OCULTA por decisión del dueño (31-08-2026:
 * «quiero mantener la lógica pero ocultar la vista por hoy», pendiente de
 * una reunión). Estos candados fijan LAS DOS mitades de esa frase: la vista
 * no se ofrece ni se alcanza, y el backflush del kardex sigue vivo. El flag
 * es config/produccion.php `pantalla_recetas` (nivel 2 — reencender = true
 * + deploy); los candados del CRUD siguen en RecetaCrudTest, encendiendo el
 * flag en su setUp.
 */
class RecetaPantallaOcultaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // A propósito SIN tocar el flag: acá se prueba el DEFAULT del deploy.
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    public function test_el_default_del_deploy_es_oculta(): void
    {
        // Si alguien enciende el flag, tiene que ser una decisión (la reunión
        // del dueño), no un default que volvió solo en un merge.
        $this->assertFalse((bool) config('produccion.pantalla_recetas'));
    }

    public function test_la_pestana_no_se_dibuja_y_las_demas_siguen(): void
    {
        $html = $this->actingAs($this->jefe())
            ->get(route('admin.maquinas.index'))
            ->assertOk()
            ->getContent();

        // La URL de recetas no puede estar en el tab-nav (una URL sin query
        // string se busca tal cual). Control positivo: las otras tres
        // pestañas siguen — sin él, un tab-nav roto entero pasaría en verde.
        $this->assertStringNotContainsString(route('admin.recetas.index'), $html);
        $this->assertStringContainsString(route('admin.tipos-botellon.index'), $html);
        $this->assertStringContainsString(route('admin.moldes.index'), $html);
        $this->assertStringContainsString('Tipos de botellón', $html);
    }

    public function test_las_rutas_redirigen_al_anfitrion_sin_puerta_trasera(): void
    {
        $botellon = Producto::create(['sku' => 'BOT-X', 'nombre' => 'BOT-X', 'categoria' => 'Botellones', 'activo' => true]);
        TipoBotellon::create(['codigo' => 'TX', 'nombre' => 'Tipo X', 'producto_id' => $botellon->id, 'activo' => true]);

        $this->actingAs($this->jefe())->get(route('admin.recetas.index'))
            ->assertRedirect(route('admin.maquinas.index'));
        $this->actingAs($this->jefe())->get(route('admin.recetas.edit', $botellon))
            ->assertRedirect(route('admin.maquinas.index'));
        // El PUT también: una pantalla oculta no puede seguir GUARDANDO.
        $this->actingAs($this->jefe())->put(route('admin.recetas.update', $botellon), ['cantidad_preforma' => 3])
            ->assertRedirect(route('admin.maquinas.index'));
        $this->assertDatabaseMissing('recetas', ['producto_id' => $botellon->id, 'cantidad' => 3]);
    }

    public function test_el_backflush_sigue_vivo_con_la_pantalla_oculta(): void
    {
        // La mitad «mantener la lógica»: con el flag APAGADO, aprobar un
        // reporte sigue descontando por receta — (buenos + merma) × cantidad.
        $preforma = Producto::create(['sku' => 'PRE-1', 'nombre' => 'PRE-1', 'categoria' => 'Preformas', 'activo' => true]);
        $botellon = Producto::create(['sku' => 'BOT-1', 'nombre' => 'BOT-1', 'categoria' => 'Botellones', 'activo' => true]);
        $tipo = TipoBotellon::create(['codigo' => 'T1', 'nombre' => 'Tipo 1', 'producto_id' => $botellon->id, 'activo' => true]);
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'cantidad' => 2, 'confirmada' => false]);

        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => now()->toDateString(),
            'turno' => 'dia', 'asignadas' => 10, 'preforma_id' => $preforma->id,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 10,
            'estado' => ProduccionReporte::ENVIADO, 'enviado_at' => now(),
        ]);
        $reporte->registros()->create(['tipo_botellon_id' => $tipo->id, 'primera' => 8, 'segunda' => 0, 'malo' => 1, 'danada' => 1]);
        $reporte->recalcularDesdeRegistros();

        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.reporte.aprobar', $reporte->refresh()))
            ->assertRedirect(route('admin.produccion.index'));

        // 10 unidades × 2 preformas de la receta = 20 (no 10 de la implícita).
        $this->assertDatabaseHas('produccion_movimientos', [
            'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA,
            'producto_id' => $preforma->id,
            'cantidad' => 20,
        ]);
    }

    public function test_encendida_la_pestana_y_la_pantalla_vuelven(): void
    {
        // La reversibilidad que promete el flag: true + deploy y todo vuelve.
        config(['produccion.pantalla_recetas' => true]);

        $html = $this->actingAs($this->jefe())
            ->get(route('admin.maquinas.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(route('admin.recetas.index'), $html);

        $this->actingAs($this->jefe())->get(route('admin.recetas.index'))->assertOk();
    }
}
