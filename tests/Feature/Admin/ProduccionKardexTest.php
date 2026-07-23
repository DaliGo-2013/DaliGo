<?php

namespace Tests\Feature\Admin;

use App\Models\Maquina;
use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionMovimiento;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use Database\Seeders\ProduccionTesteoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SucursalSeeder;
use Database\Seeders\TipoBotellonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduccionKardexTest extends TestCase
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

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('soplador');
    }

    private function producto(string $sku, string $categoria = 'Botellones'): Producto
    {
        return Producto::create(['sku' => $sku, 'nombre' => $sku, 'categoria' => $categoria, 'activo' => true]);
    }

    /**
     * Reporte ENVIADO con una tanda y preforma asignada, listo para aprobar.
     */
    private function reporteEnviadoConTanda(User $soplador, Producto $preforma, TipoBotellon $tipo, array $c): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => array_sum($c),
            'preforma_id' => $preforma->id,
        ]);

        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => array_sum($c),
            'estado' => ProduccionReporte::ENVIADO,
            'enviado_at' => now(),
        ]);

        $reporte->registros()->create([
            'tipo_botellon_id' => $tipo->id,
            'primera' => $c['primera'], 'segunda' => $c['segunda'],
            'malo' => $c['malo'], 'danada' => $c['danada'],
        ]);
        $reporte->recalcularDesdeRegistros();

        return $reporte->refresh();
    }

    // --- Generación del kardex al aprobar ---

    public function test_aprobar_genera_movimientos_exactos(): void
    {
        $preforma = $this->producto('PRE-1', 'Preformas');
        $botellon = $this->producto('BOT-1');
        $tipo = TipoBotellon::create(['codigo' => 'T1', 'nombre' => 'Tipo 1', 'producto_id' => $botellon->id, 'activo' => true]);
        $soplador = $this->soplador();

        $reporte = $this->reporteEnviadoConTanda($soplador, $preforma, $tipo, [
            'primera' => 80, 'segunda' => 10, 'malo' => 5, 'danada' => 5,
        ]);

        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.reporte.aprobar', $reporte))
            ->assertRedirect(route('admin.produccion.index'));

        $this->assertSame(ProduccionReporte::APROBADO, $reporte->fresh()->estado);

        // 4 movimientos: consumo (total), 1ª, 2ª, merma (malo+danada).
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $preforma->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 100]);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $botellon->id, 'tipo' => ProduccionMovimiento::TIPO_PRODUCCION_PRIMERA, 'cantidad' => 80]);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $botellon->id, 'tipo' => ProduccionMovimiento::TIPO_PRODUCCION_SEGUNDA, 'cantidad' => 10]);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $botellon->id, 'tipo' => ProduccionMovimiento::TIPO_MERMA, 'cantidad' => 10]);
        $this->assertSame(4, $reporte->movimientos()->count());
    }

    public function test_kardex_usa_las_tandas_no_los_totales_ajustados(): void
    {
        // M-1: el consumo del kardex se deriva de las TANDAS, no del total
        // denormalizado del reporte. Si el admin ajusta los totales (ajustar())
        // sin tocar las tandas, el kardex sigue siendo internamente consistente.
        $preforma = $this->producto('PRE-AJ', 'Preformas');
        $botellon = $this->producto('BOT-AJ');
        $tipo = TipoBotellon::create(['codigo' => 'TAJ', 'nombre' => 'Tipo Aj', 'producto_id' => $botellon->id, 'activo' => true]);

        // Tandas reales suman 100 (80 + 10 + 5 + 5).
        $reporte = $this->reporteEnviadoConTanda($this->soplador(), $preforma, $tipo, [
            'primera' => 80, 'segunda' => 10, 'malo' => 5, 'danada' => 5,
        ]);

        // El admin edita los totales denormalizados a 500 SIN tocar las tandas (ajustar()).
        $reporte->update(['primera' => 500, 'segunda' => 0, 'malo' => 0, 'danada' => 0]);
        $this->assertSame(500, $reporte->fresh()->total);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        // El consumo sigue las tandas (100), no el total ajustado (500).
        $this->assertDatabaseHas('produccion_movimientos', [
            'reporte_id' => $reporte->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 100,
        ]);
        $this->assertDatabaseMissing('produccion_movimientos', [
            'reporte_id' => $reporte->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 500,
        ]);

        // Internamente consistente: consumo == producción (1ª+2ª) + merma.
        $movs = $reporte->movimientos()->get();
        $consumo = (int) $movs->where('tipo', ProduccionMovimiento::TIPO_CONSUMO_PREFORMA)->sum('cantidad');
        $prodYmerma = (int) $movs->whereIn('tipo', [
            ProduccionMovimiento::TIPO_PRODUCCION_PRIMERA,
            ProduccionMovimiento::TIPO_PRODUCCION_SEGUNDA,
            ProduccionMovimiento::TIPO_MERMA,
        ])->sum('cantidad');
        $this->assertSame(100, $consumo);
        $this->assertSame($consumo, $prodYmerma);
    }

    public function test_aprobar_dos_veces_no_duplica_movimientos(): void
    {
        $preforma = $this->producto('PRE-2', 'Preformas');
        $tipo = TipoBotellon::create(['codigo' => 'T2', 'nombre' => 'Tipo 2', 'producto_id' => $this->producto('BOT-2')->id, 'activo' => true]);
        $reporte = $this->reporteEnviadoConTanda($this->soplador(), $preforma, $tipo, ['primera' => 50, 'segunda' => 0, 'malo' => 0, 'danada' => 0]);
        $jefe = $this->jefe();

        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));
        $antes = $reporte->movimientos()->count();

        // Segundo submit: el guard de estado (ya aprobado) lo bloquea.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));

        $this->assertSame($antes, $reporte->movimientos()->count());
    }

    public function test_sin_preforma_el_consumo_queda_sin_producto(): void
    {
        $tipo = TipoBotellon::create(['codigo' => 'T3', 'nombre' => 'Tipo 3', 'producto_id' => $this->producto('BOT-3')->id, 'activo' => true]);

        // Asignación sin preforma_id.
        $asignacion = ProduccionAsignacion::create(['soplador_id' => $this->soplador()->id, 'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 30]);
        $reporte = ProduccionReporte::create(['asignacion_id' => $asignacion->id, 'soplador_id' => $asignacion->soplador_id, 'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 30, 'estado' => ProduccionReporte::ENVIADO, 'enviado_at' => now()]);
        $reporte->registros()->create(['tipo_botellon_id' => $tipo->id, 'primera' => 30, 'segunda' => 0, 'malo' => 0, 'danada' => 0]);
        $reporte->recalcularDesdeRegistros();

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte->refresh()));

        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => null, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 30]);
    }

    public function test_devolver_no_genera_movimientos(): void
    {
        $tipo = TipoBotellon::create(['codigo' => 'T4', 'nombre' => 'Tipo 4', 'producto_id' => $this->producto('BOT-4')->id, 'activo' => true]);
        $reporte = $this->reporteEnviadoConTanda($this->soplador(), $this->producto('PRE-4', 'Preformas'), $tipo, ['primera' => 10, 'segunda' => 0, 'malo' => 0, 'danada' => 0]);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.devolver', $reporte), ['devuelto_motivo' => 'Revisar conteo']);

        $this->assertSame(0, ProduccionMovimiento::where('reporte_id', $reporte->id)->count());
    }

    // --- Vista kardex ---

    public function test_kardex_requiere_permiso(): void
    {
        $this->actingAs($this->soplador())->get(route('admin.produccion.movimientos'))->assertForbidden();
    }

    public function test_kardex_filtra_por_tipo(): void
    {
        $tipo = TipoBotellon::create(['codigo' => 'T5', 'nombre' => 'Tipo 5', 'producto_id' => $this->producto('BOT-5')->id, 'activo' => true]);
        $reporte = $this->reporteEnviadoConTanda($this->soplador(), $this->producto('PRE-5', 'Preformas'), $tipo, ['primera' => 7, 'segunda' => 0, 'malo' => 0, 'danada' => 3]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.movimientos', ['tipo' => ProduccionMovimiento::TIPO_PRODUCCION_PRIMERA]))
            ->assertOk()
            ->assertSee('Producción 1ª');
    }

    // --- Accessors ---

    public function test_producido_y_merma_separan_vendible_de_desperdicio(): void
    {
        $reporte = new ProduccionReporte(['primera' => 80, 'segunda' => 10, 'malo' => 5, 'danada' => 5]);

        $this->assertSame(100, $reporte->total);
        $this->assertSame(90, $reporte->producido);  // 1ª + 2ª
        $this->assertSame(10, $reporte->merma);       // malo + danada
    }

    // --- Auditoría visible ---

    public function test_auditoria_acepta_filtro_de_reporte_de_produccion(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.audits.index', ['auditable_type' => ProduccionReporte::class]))
            ->assertOk();
    }

    // --- Seeder de testeo ---

    public function test_seeder_testeo_es_idempotente_y_enlaza_tipos(): void
    {
        $this->seed(SucursalSeeder::class);
        $this->seed(TipoBotellonSeeder::class);
        $this->seed(ProduccionTesteoSeeder::class);
        $this->seed(ProduccionTesteoSeeder::class); // 2ª vez: no duplica

        // 2 preformas + 4 botellones (uno por tipo base).
        $this->assertSame(6, Producto::where('sku', 'like', 'TEST-%')->count());
        $this->assertNotNull(TipoBotellon::where('codigo', 'AZUL-20L')->first()->producto_id);
    }

    // --- Scope de preforma_id al asignar ---

    public function test_asignar_rechaza_preforma_inactiva_y_acepta_activa(): void
    {
        $soplador = $this->soplador();
        $base = ['soplador_id' => $soplador->id, 'turno' => 'dia', 'fecha' => now()->toDateString(), 'asignadas' => 100];

        $inactiva = Producto::create(['sku' => 'PREF-OFF', 'nombre' => 'Preforma inactiva', 'categoria' => 'Preformas', 'activo' => false]);
        $activa = $this->producto('PREF-ON', 'Preformas');

        // Un producto inactivo no puede entrar al kardex como preforma del turno.
        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.asignar.store'), $base + ['preforma_id' => $inactiva->id])
            ->assertSessionHasErrors('preforma_id');
        $this->assertDatabaseMissing('produccion_asignaciones', ['soplador_id' => $soplador->id]);

        // Un producto activo sí se acepta y queda enlazado a la asignación.
        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.asignar.store'), $base + ['preforma_id' => $activa->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('produccion_asignaciones', [
            'soplador_id' => $soplador->id, 'preforma_id' => $activa->id,
        ]);
    }

    public function test_selector_de_preformas_excluye_las_danadas(): void
    {
        $sana = Producto::create(['sku' => 'PREF-SANA', 'nombre' => 'PREFORMA AZUL 700 GR', 'categoria' => 'Preformas', 'activo' => true]);
        $danada = Producto::create(['sku' => 'PREF-DAN', 'nombre' => 'PREFORMA DAÑADA AZUL 10 LT', 'categoria' => 'Preformas', 'activo' => true]);
        $danadaMinuscula = Producto::create(['sku' => 'PREF-DAN-MIN', 'nombre' => 'Preforma dañada lila', 'categoria' => 'Preformas', 'activo' => true]);

        $ids = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))
            ->assertOk()
            ->viewData('preformas')
            ->pluck('id');

        $this->assertTrue($ids->contains($sana->id));
        $this->assertFalse($ids->contains($danada->id), 'La preforma DAÑADA (mayúsculas) no debe ofrecerse en el selector.');
        $this->assertFalse($ids->contains($danadaMinuscula->id), 'La preforma dañada (minúsculas) no debe ofrecerse en el selector.');
    }

    public function test_selector_fallback_sin_categoria_preforma_tambien_excluye_danadas(): void
    {
        // Sin ningún producto de categoría "preforma" rige el fallback (todos
        // los activos): las dañadas también deben quedar fuera ahí.
        $normal = Producto::create(['sku' => 'GEN-1', 'nombre' => 'PRODUCTO GENERICO', 'categoria' => 'Otros', 'activo' => true]);
        $danada = Producto::create(['sku' => 'GEN-DAN', 'nombre' => 'PREFORMA DAÑADA VERDE', 'categoria' => 'Otros', 'activo' => true]);

        $ids = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))
            ->assertOk()
            ->viewData('preformas')
            ->pluck('id');

        $this->assertTrue($ids->contains($normal->id));
        $this->assertFalse($ids->contains($danada->id), 'El fallback del selector tampoco debe ofrecer preformas dañadas.');
    }

    public function test_asignar_rechaza_preforma_danada(): void
    {
        // Mismo universo que el selector: un id de preforma dañada posteado a
        // mano no debe entrar al kardex.
        $soplador = $this->soplador();
        $base = ['soplador_id' => $soplador->id, 'turno' => 'dia', 'fecha' => now()->toDateString(), 'asignadas' => 100];

        $danada = Producto::create(['sku' => 'PREF-DAN-POST', 'nombre' => 'PREFORMA DAÑADA AZUL 20 L 750 GR', 'categoria' => 'Preformas', 'activo' => true]);

        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.asignar.store'), $base + ['preforma_id' => $danada->id])
            ->assertSessionHasErrors('preforma_id');
        $this->assertDatabaseMissing('produccion_asignaciones', ['soplador_id' => $soplador->id]);
    }
}
