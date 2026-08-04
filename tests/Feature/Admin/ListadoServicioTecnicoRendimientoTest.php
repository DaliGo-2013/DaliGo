<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Candados de RENDIMIENTO del listado de servicio tecnico (reporte del dueño
 * 03-08-2026: "al hacer clic en cada mes tarda en cargar las ordenes" en el
 * celular).
 *
 * El diagnostico, medido con 9.940 ordenes: las consultas del periodo envolvian
 * la columna de fecha en una funcion (`whereDate` -> `date(fecha_ingreso)`;
 * `SUBSTR(fecha_ingreso,1,4)` en el filtro del historial), y una columna dentro
 * de una funcion deja el INDICE fuera de juego -> cada clic de mes leia la tabla
 * completa, dos veces (el count del paginador + la pagina). 15 ms de SQL que
 * crecian con la tabla; con los fixes, 1 ms constante.
 *
 * Estos candados fijan la FORMA de las consultas (el SQL que se emite), porque el
 * tiempo no se puede asertar (varia por maquina) y el resultado es identico por
 * ambos caminos — un refactor bien intencionado a whereDate pasaria toda la suite
 * y devolveria la lentitud.
 */
class ListadoServicioTecnicoRendimientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    /** El SQL emitido al pedir un mes del historial. */
    private function sqlDe(string $url): array
    {
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-07-15']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin())->get($url)->assertOk();
        $log = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $log;
    }

    public function test_el_filtro_de_periodo_no_envuelve_la_fecha_en_una_funcion(): void
    {
        $sql = collect($this->sqlDe('/admin/servicio-tecnico?anio=2026&mes=7'))
            ->filter(fn ($q) => str_contains($q, 'ordenes_servicio'));

        // El rango va sobre la columna cruda (BETWEEN usa el indice)...
        $this->assertTrue(
            $sql->contains(fn ($q) => preg_match('/[`"]fecha_ingreso[`"] (>=|between)/', $q) === 1),
            'El periodo ya no filtra por rango sobre la columna cruda.'
        );
        // ...y NINGUNA consulta del listado la envuelve en date()/strftime(), que
        // es lo que anula el indice (whereDate compila a eso).
        $conFuncion = $sql->filter(fn ($q) => preg_match('/(date|strftime)\s*\([^)]*fecha_ingreso/i', $q));
        $this->assertSame(0, $conFuncion->count(),
            "Una consulta del listado envuelve fecha_ingreso en una función (índice anulado):\n".$conFuncion->implode("\n"));
    }

    public function test_el_periodo_por_retiro_tampoco(): void
    {
        $conFuncion = collect($this->sqlDe('/admin/servicio-tecnico?anio=2026&mes=7&por=retiro&estado=entregado'))
            ->filter(fn ($q) => preg_match('/(date|strftime)\s*\([^)]*fecha_retiro/i', $q));

        $this->assertSame(0, $conFuncion->count(),
            "El período por retiro envuelve fecha_retiro en una función:\n".$conFuncion->implode("\n"));
    }

    /** El filtro de periodo con BETWEEN devuelve lo mismo que devolvia whereDate. */
    public function test_el_between_filtra_igual_que_antes(): void
    {
        OrdenServicio::factory()->create(['cliente_nombre' => 'Primero De Julio', 'fecha_ingreso' => '2026-07-01']);
        OrdenServicio::factory()->create(['cliente_nombre' => 'Ultimo De Julio', 'fecha_ingreso' => '2026-07-31']);
        OrdenServicio::factory()->create(['cliente_nombre' => 'Fuera Junio', 'fecha_ingreso' => '2026-06-30']);
        OrdenServicio::factory()->create(['cliente_nombre' => 'Fuera Agosto', 'fecha_ingreso' => '2026-08-01']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?anio=2026&mes=7')
            ->assertOk()
            ->assertSee('Primero De Julio')   // borde inferior INCLUIDO
            ->assertSee('Ultimo De Julio')    // borde superior INCLUIDO
            ->assertDontSee('Fuera Junio')
            ->assertDontSee('Fuera Agosto');
    }

    // --- La cache del historial y su invalidacion ---

    public function test_los_conteos_del_historial_se_cachean_entre_visitas(): void
    {
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-07-15']);
        $admin = $this->admin();

        // Primera visita: calcula y guarda.
        $this->actingAs($admin)->get('/admin/servicio-tecnico?anio=2026')->assertOk();

        // Segunda visita: los conteos salen del cache — ninguna consulta agrupa
        // por SUBSTR de la fecha.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get('/admin/servicio-tecnico?anio=2026')->assertOk();
        $agregados = collect(array_column(DB::getQueryLog(), 'query'))
            ->filter(fn ($q) => stripos($q, 'SUBSTR(fecha_ingreso') !== false);
        DB::disableQueryLog();

        $this->assertSame(0, $agregados->count(),
            'La segunda visita volvió a barrer la tabla para los conteos del historial (el caché no está funcionando).');
    }

    /**
     * La parte que NO puede fallar hacia el otro lado: el cache no puede dejar el
     * historial PEGADO. Un ingreso nuevo tiene que verse al instante — este es el
     * sintoma exacto que reporto el dueño ("como pegada la info").
     */
    public function test_un_ingreso_nuevo_invalida_el_cache_y_se_ve_al_instante(): void
    {
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-07-15']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/servicio-tecnico?anio=2026')
            ->assertViewHas('historial', fn ($h) => $h['meses'][7] === 1);

        // Entra una orden nueva (evento saved -> invalidarHistorial).
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-07-20']);

        $this->actingAs($admin)->get('/admin/servicio-tecnico?anio=2026')
            ->assertViewHas('historial', fn ($h) => $h['meses'][7] === 2);
    }

    public function test_borrar_una_orden_tambien_invalida(): void
    {
        $orden = OrdenServicio::factory()->create(['fecha_ingreso' => '2026-07-15']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/servicio-tecnico?anio=2026')
            ->assertViewHas('historial', fn ($h) => $h['meses'][7] === 1);

        $orden->delete();

        $this->actingAs($admin)->get('/admin/servicio-tecnico?anio=2026')
            ->assertViewHas('historial', fn ($h) => $h['meses'][7] === 0);
    }

    // --- La señal de carga al tocar un mes (celular) ---

    public function test_las_tarjetas_de_mes_marcan_cargando_al_toque(): void
    {
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-07-15']);

        $html = $this->actingAs($this->admin())->get('/admin/servicio-tecnico?anio=2026')
            ->assertOk()->getContent();

        // El clic marca la tarjeta al instante (la espera deja de parecer cuelgue)...
        $this->assertStringContainsString('cargando = 7', $html);
        $this->assertStringContainsString('Cargando…', $html);
        // ...y el bloque bloquea un segundo toque mientras navega.
        $this->assertStringContainsString('pointer-events-none', $html);
    }
}
