<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Configuracion;
use App\Models\Maquina;
use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionParada;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use App\Services\Produccion\Oee;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de OPE-1 (PLAN-PARAMETRICOS §5.3 #1): las ventanas por defecto del
 * panel del jefe y de los dos informes de rendimiento como parámetros nivel 1,
 * con el molde DASH-1 (default idéntico con BD virgen · mover cada clave mueve
 * SU pantalla —serie, cifra y rótulo— y NO las hermanas · la UI valida por los
 * dos bordes). Lo propio de este módulo: acá también se fija el contrato del
 * helper `rango()` — la clave es el DEFAULT de la ventana, y un rango pedido
 * por URL (?desde/?hasta) SIEMPRE le gana a la clave.
 */
class ParametrosOperacionTest extends TestCase
{
    use RefreshDatabase;

    private Maquina $maquina;

    private TipoBotellon $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SucursalSeeder::class);
        $this->freezeTime();

        $this->maquina = Maquina::create(['nombre' => 'SOPLA-1', 'sucursal_id' => Sucursal::first()->id, 'activa' => true]);
        $this->tipo = TipoBotellon::create(['codigo' => 'B20', 'nombre' => 'Botellón 20L', 'activo' => true]);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    /**
     * Reporte APROBADO con UNA tanda (la máquina/tipo del setUp): el panel
     * agrega desde los totales del reporte y los informes desde las tandas —
     * la misma producción alimenta las tres pantallas.
     */
    private function produccionDe(string $fecha, int $primera): void
    {
        $soplador = User::factory()->create();
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $primera,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $primera,
            'estado' => ProduccionReporte::APROBADO,
        ]);
        ProduccionRegistro::create([
            'reporte_id' => $reporte->id,
            'maquina_id' => $this->maquina->id,
            'tipo_botellon_id' => $this->tipo->id,
            'primera' => $primera,
            'segunda' => 0,
            'malo' => 0,
            'danada' => 0,
        ]);
        $reporte->recalcularDesdeRegistros();
    }

    /**
     * Tres producciones en los BORDES de las ventanas: hoy (dentro de todas),
     * hace 7 días (fuera del panel de 7, dentro de 14 y de los informes de 30)
     * y hace 34 días (fuera de los informes de 30, dentro de 45 y de 60).
     */
    private function produccionEnLosBordes(): void
    {
        $this->produccionDe(now()->toDateString(), 100);
        $this->produccionDe(now()->subDays(7)->toDateString(), 40);
        $this->produccionDe(now()->subDays(34)->toDateString(), 50);
    }

    public function test_sin_claves_en_bd_las_tres_pantallas_rinden_identico_al_historico(): void
    {
        // A propósito SIN ConfiguracionSeeder: la BD virgen es el escenario de
        // la regla de oro — rigen los fallbacks del controller (7 y 30/30).
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk();
        $this->assertSame(7, $panel->viewData('diasPanel'));
        $this->assertCount(7, $panel->viewData('periodo')['dias']);
        $this->assertSame(100, $panel->viewData('periodo')['totales']['producido']); // el día -7 queda FUERA
        // El rótulo grande Y el info-tip (v77.1): la forma «por defecto,
        // últimos N» es ÚNICA del tip — el rótulo dice «Últimos N días».
        $panel->assertSee('Últimos 7 días')->assertSee('por defecto, últimos 7');

        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', $this->maquina))->assertOk();
        $this->assertSame(30, $maquina->viewData('diasInforme'));
        $this->assertCount(30, $maquina->viewData('tendencia')['dias']);
        $this->assertSame(140, $maquina->viewData('tendencia')['totales']['producido']); // el día -34 queda FUERA
        $maquina->assertSee('· últimos 30 días');

        $tipo = $this->actingAs($jefe)->get(route('admin.produccion.tipo', $this->tipo))->assertOk();
        $this->assertSame(30, $tipo->viewData('diasInforme'));
        $this->assertCount(30, $tipo->viewData('tendencia')['dias']);
        $this->assertSame(140, $tipo->viewData('tendencia')['totales']['producido']);
        $tipo->assertSee('· últimos 30 días');
    }

    public function test_mover_la_ventana_del_panel_mueve_su_serie_y_cifra_y_no_los_informes(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        Configuracion::set('produccion_dias_panel', 14);

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk();
        $this->assertCount(14, $panel->viewData('periodo')['dias']);
        $this->assertSame(140, $panel->viewData('periodo')['totales']['producido']); // el día -7 ENTRA
        $panel->assertSee('Últimos 14 días')->assertDontSee('Últimos 7 días');
        // El info-tip también deriva (rebote v77.1): con la perilla en 14 la
        // ayudita no puede seguir jurando 7.
        $panel->assertSee('por defecto, últimos 14')->assertDontSee('por defecto, últimos 7');

        // Las ventanas hermanas NO se movieron.
        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', $this->maquina))->assertOk();
        $this->assertCount(30, $maquina->viewData('tendencia')['dias']);
        $maquina->assertSee('· últimos 30 días');

        $tipo = $this->actingAs($jefe)->get(route('admin.produccion.tipo', $this->tipo))->assertOk();
        $this->assertCount(30, $tipo->viewData('tendencia')['dias']);
        $tipo->assertSee('· últimos 30 días');
    }

    public function test_mover_el_informe_de_maquina_mueve_su_ventana_y_no_el_panel_ni_el_de_tipo(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        Configuracion::set('produccion_dias_informe_maquina', 45);

        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', $this->maquina))->assertOk();
        $this->assertCount(45, $maquina->viewData('tendencia')['dias']);
        $this->assertSame(190, $maquina->viewData('tendencia')['totales']['producido']); // el día -34 ENTRA
        $maquina->assertSee('· últimos 45 días')->assertDontSee('· últimos 30 días');

        $tipo = $this->actingAs($jefe)->get(route('admin.produccion.tipo', $this->tipo))->assertOk();
        $this->assertCount(30, $tipo->viewData('tendencia')['dias']);
        $this->assertSame(140, $tipo->viewData('tendencia')['totales']['producido']);
        $tipo->assertSee('· últimos 30 días');

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk();
        $this->assertCount(7, $panel->viewData('periodo')['dias']);
        $panel->assertSee('Últimos 7 días');
    }

    public function test_mover_el_informe_de_tipo_mueve_su_ventana_y_no_el_panel_ni_el_de_maquina(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        Configuracion::set('produccion_dias_informe_tipo', 60);

        $tipo = $this->actingAs($jefe)->get(route('admin.produccion.tipo', $this->tipo))->assertOk();
        $this->assertCount(60, $tipo->viewData('tendencia')['dias']);
        $this->assertSame(190, $tipo->viewData('tendencia')['totales']['producido']); // el día -34 ENTRA
        $tipo->assertSee('· últimos 60 días')->assertDontSee('· últimos 30 días');

        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', $this->maquina))->assertOk();
        $this->assertCount(30, $maquina->viewData('tendencia')['dias']);
        $this->assertSame(140, $maquina->viewData('tendencia')['totales']['producido']);
        $maquina->assertSee('· últimos 30 días');

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk();
        $this->assertCount(7, $panel->viewData('periodo')['dias']);
        $panel->assertSee('Últimos 7 días');
    }

    public function test_el_rango_pedido_por_url_le_gana_a_la_clave(): void
    {
        // El OJO del dictado v77: la clave es el DEFAULT del rango, no un tope.
        // Con las claves movidas, un ?desde/?hasta explícito manda igual.
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        Configuracion::set('produccion_dias_panel', 14);
        Configuracion::set('produccion_dias_informe_maquina', 45);

        $query = ['desde' => now()->subDays(2)->toDateString(), 'hasta' => now()->toDateString()];

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index', $query))->assertOk();
        $this->assertFalse($panel->viewData('periodo')['esDefault']);
        $this->assertCount(3, $panel->viewData('periodo')['dias']); // los 3 pedidos, no los 14 de la clave
        $this->assertSame(100, $panel->viewData('periodo')['totales']['producido']);
        $panel->assertDontSee('Últimos 14 días');

        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', array_merge(['maquina' => $this->maquina->id], $query)))->assertOk();
        $this->assertFalse($maquina->viewData('esDefault'));
        $this->assertCount(3, $maquina->viewData('tendencia')['dias']);
        $maquina->assertDontSee('· últimos 45 días');
    }

    public function test_la_ui_de_configuracion_valida_el_rango_del_panel_2_a_31(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');
        $config = Configuracion::where('clave', 'produccion_dias_panel')->firstOrFail();

        foreach ([1, 0, -5, 32, 'abc'] as $malo) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => $malo])
                ->assertSessionHasErrors('valor');
        }

        foreach ([2, 31] as $bueno) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => $bueno])
                ->assertSessionHasNoErrors();
            $this->assertSame($bueno, Configuracion::get('produccion_dias_panel'));
        }
    }

    public function test_la_ui_de_configuracion_valida_el_rango_de_los_informes_7_a_90(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');

        foreach (['produccion_dias_informe_maquina', 'produccion_dias_informe_tipo'] as $clave) {
            $config = Configuracion::where('clave', $clave)->firstOrFail();

            foreach ([6, 0, -5, 91, 'abc'] as $malo) {
                $this->actingAs($admin)
                    ->put(route('admin.configuracion.update', $config), ['valor' => $malo])
                    ->assertSessionHasErrors('valor');
            }

            foreach ([7, 90] as $bueno) {
                $this->actingAs($admin)
                    ->put(route('admin.configuracion.update', $config), ['valor' => $bueno])
                    ->assertSessionHasNoErrors();
                $this->assertSame($bueno, Configuracion::get($clave));
            }
        }
    }

    // =====================================================================
    //  OPE-2: motivos de parada + subconjunto planificado + procedencias
    //  (hallazgos #9 y #13 del mapa §5.3; molde COM-1/LISTAS_SIMPLES + el
    //  4º hermano declarativo PARES_SUBCONJUNTO)
    // =====================================================================

    private function soplador(): User
    {
        // Misma sucursal que la máquina del setUp: paraSoplador() la ofrece.
        return tap(User::factory()->create(['sucursal_id' => $this->maquina->sucursal_id]))->assignRole('soplador');
    }

    private function reporteBorradorDe(User $soplador): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 200,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 200,
            'estado' => ProduccionReporte::BORRADOR,
        ]);
    }

    /** Payload de parada CERRADA (mismo idioma que ProduccionParadasTest). */
    private function payloadParada(string $motivo): array
    {
        return [
            'parada_maquina_id' => $this->maquina->id,
            'parada_motivo' => $motivo,
            'parada_origen' => 'maquina',
            'parada_inicio' => '10:00',
            'parada_fin' => '11:00',
        ];
    }

    public function test_sin_claves_en_bd_motivos_y_procedencias_rinden_identico_al_historico(): void
    {
        // BD virgen (sin ConfiguracionSeeder): rigen las constantes.
        $soplador = $this->soplador();
        $reporte = $this->reporteBorradorDe($soplador);

        // Por la forma contigua del chip (name+value): varios motivos tienen
        // gemelos de texto en otros forms de la misma pantalla.
        $pantalla = $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))->assertOk();
        foreach (ProduccionParada::MOTIVOS as $motivo) {
            $pantalla->assertSee('name="parada_motivo" value="'.$motivo.'"', false);
        }

        $asignar = $this->actingAs($this->jefe())->get(route('admin.produccion.asignar'))->assertOk();
        $this->assertSame(['saco', 'caja'], $asignar->viewData('procedencias'));

        // La clase deriva del subconjunto histórico.
        $this->assertSame(ProduccionParada::CLASE_PLANIFICADA, ProduccionParada::claseDe('Mantención de máquina'));
        $this->assertSame(ProduccionParada::CLASE_NO_PLANIFICADA, ProduccionParada::claseDe('Corte de luz'));
    }

    public function test_agregar_un_motivo_lo_ofrece_al_operario_y_no_toca_las_procedencias(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        Configuracion::set('produccion_motivos_parada', array_merge(ProduccionParada::MOTIVOS, ['Robo de tapas']));

        $soplador = $this->soplador();
        $reporte = $this->reporteBorradorDe($soplador);

        // Forma contigua del chip (name+value): 'Corte de luz' y varios motivos
        // de parada tienen GEMELOS en otros forms de la misma pantalla
        // (MOTIVOS_DIFERENCIA/MOTIVOS_DEFECTO) — un assert del texto suelto
        // pasa/falla por la superficie equivocada (doctrina verde-engañoso).
        $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('name="parada_motivo" value="Robo de tapas"', false);

        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payloadParada('Robo de tapas'))
            ->assertSessionHasNoErrors();
        $parada = $reporte->paradas()->firstOrFail();
        $this->assertSame('Robo de tapas', $parada->motivo);
        $this->assertSame(ProduccionParada::CLASE_NO_PLANIFICADA, $parada->clase);

        // La lista hermana NO se movió.
        $asignar = $this->actingAs($this->jefe())->get(route('admin.produccion.asignar'))->assertOk();
        $this->assertSame(['saco', 'caja'], $asignar->viewData('procedencias'));
    }

    public function test_un_motivo_retirado_deja_de_valer_para_paradas_nuevas(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        Configuracion::set('produccion_motivos_parada', array_values(array_diff(ProduccionParada::MOTIVOS, ['Corte de luz'])));

        $soplador = $this->soplador();
        $reporte = $this->reporteBorradorDe($soplador);

        // El chip desaparece — por la forma contigua name+value: el texto
        // suelto 'Corte de luz' SIGUE en la página (es también motivo de
        // diferencia del reporte, otro form) y un assertDontSee ingenuo
        // fallaría por esa superficie ajena.
        $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertDontSee('name="parada_motivo" value="Corte de luz"', false);

        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payloadParada('Corte de luz'))
            ->assertSessionHasErrors('parada_motivo');
        $this->assertSame(0, $reporte->paradas()->count());
    }

    public function test_agregar_una_procedencia_la_ofrece_al_asignar_y_no_toca_los_motivos(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        Configuracion::set('produccion_procedencias_preforma', ['saco', 'caja', 'granel']);
        $jefe = $this->jefe();
        $soplador = $this->soplador();

        $asignar = $this->actingAs($jefe)->get(route('admin.produccion.asignar'))->assertOk();
        $this->assertSame(['saco', 'caja', 'granel'], $asignar->viewData('procedencias'));

        $this->actingAs($jefe)->post(route('admin.produccion.asignar.store'), [
            'soplador_id' => $soplador->id,
            'turno' => 'dia',
            'fecha' => now()->toDateString(),
            'asignadas' => 300,
            'procedencia' => 'granel',
        ])->assertSessionHasNoErrors();
        $this->assertSame('granel', ProduccionAsignacion::where('soplador_id', $soplador->id)->firstOrFail()->procedencia);

        // La lista hermana NO se movió.
        $this->assertSame(ProduccionParada::MOTIVOS, ProduccionParada::motivos());
    }

    public function test_mover_un_motivo_a_planificados_cambia_la_clase_de_paradas_nuevas(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        Configuracion::set('produccion_motivos_planificados', ['Mantención de máquina', 'Cambio de molde', 'Corte de luz']);

        $soplador = $this->soplador();
        $reporte = $this->reporteBorradorDe($soplador);

        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payloadParada('Corte de luz'))
            ->assertSessionHasNoErrors();

        $this->assertSame(ProduccionParada::CLASE_PLANIFICADA, $reporte->paradas()->firstOrFail()->clase);
    }

    public function test_el_oee_historico_no_se_reescribe_al_mover_las_listas(): void
    {
        // El candado 4 del dictado v78: motivo y clase quedan PERSISTIDOS en
        // la fila — editar las listas HOY no reescribe el OEE de AYER.
        $this->seed(ConfiguracionSeeder::class);
        $soplador = $this->soplador();
        $reporte = $this->reporteBorradorDe($soplador);

        // Tanda + parada cerrada, creadas por el flujo real (claseDe corre al crear).
        ProduccionRegistro::create([
            'reporte_id' => $reporte->id,
            'maquina_id' => $this->maquina->id,
            'tipo_botellon_id' => $this->tipo->id,
            'primera' => 100, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
        ]);
        $this->actingAs($soplador)
            ->post(route('produccion.mi.paradas.store', $reporte), $this->payloadParada('Corte de luz'))
            ->assertSessionHasNoErrors();
        $this->assertSame(ProduccionParada::CLASE_NO_PLANIFICADA, $reporte->paradas()->firstOrFail()->clase);

        $hoy = now()->toDateString();
        $antes = app(Oee::class)->paraMaquina($this->maquina, $hoy, $hoy);

        // (b) RECLASIFICADO hoy: el histórico lee la columna, no claseDe().
        Configuracion::set('produccion_motivos_planificados', ['Mantención de máquina', 'Cambio de molde', 'Corte de luz']);
        $this->assertEquals($antes, app(Oee::class)->paraMaquina($this->maquina, $hoy, $hoy));

        // (a) RETIRADO de la lista madre: el OEE no cambia y la parada sigue
        // visible con su motivo legado (el informe no lo esconde).
        Configuracion::set('produccion_motivos_planificados', ProduccionParada::MOTIVOS_PLANIFICADOS);
        Configuracion::set('produccion_motivos_parada', array_values(array_diff(ProduccionParada::MOTIVOS, ['Corte de luz'])));
        $this->assertEquals($antes, app(Oee::class)->paraMaquina($this->maquina, $hoy, $hoy));
        $this->actingAs($this->jefe())->get(route('admin.produccion.maquina', $this->maquina))
            ->assertOk()
            ->assertSee('Corte de luz');
    }

    public function test_la_ui_valida_el_par_planificados_subconjunto_de_motivos(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');
        $hijo = Configuracion::where('clave', 'produccion_motivos_planificados')->firstOrFail();
        $madre = Configuracion::where('clave', 'produccion_motivos_parada')->firstOrFail();

        // El hijo no puede traer un elemento fuera de la madre.
        $this->actingAs($admin)
            ->put(route('admin.configuracion.update', $hijo), ['valor' => "Mantención de máquina\nMotivo inventado"])
            ->assertSessionHasErrors('valor');

        // La madre no puede soltar un elemento que el hijo todavía nombra.
        $sinCambioDeMolde = implode("\n", array_diff(ProduccionParada::MOTIVOS, ['Cambio de molde']));
        $this->actingAs($admin)
            ->put(route('admin.configuracion.update', $madre), ['valor' => $sinCambioDeMolde])
            ->assertSessionHasErrors('valor');

        // Bordes sanos: subconjunto legítimo (case-insensitive, como getLista)…
        $this->actingAs($admin)
            ->put(route('admin.configuracion.update', $hijo), ['valor' => 'cambio de MOLDE'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['cambio de MOLDE'], Configuracion::get('produccion_motivos_planificados'));

        // …y quitar de la madre un motivo que ningún planificado nombra.
        $sinCorteDeLuz = implode("\n", array_diff(ProduccionParada::MOTIVOS, ['Corte de luz']));
        $this->actingAs($admin)
            ->put(route('admin.configuracion.update', $madre), ['valor' => $sinCorteDeLuz])
            ->assertSessionHasNoErrors();
    }

    // =====================================================================
    //  OPE-3: config de preforma (nivel 2, config/produccion.php) + higiene
    //  (hallazgo #3 del mapa §5.3 + tope de rango + POR_PAGINA)
    // =====================================================================

    public function test_el_patron_de_preforma_vive_en_config_y_moverlo_mueve_el_selector(): void
    {
        $preforma = Producto::create(['sku' => 'PREF-1', 'nombre' => 'Preforma 20g', 'categoria' => 'Preformas PET', 'activo' => true]);
        Producto::create(['sku' => 'PREF-D', 'nombre' => 'Preforma dañada 20g', 'categoria' => 'Preformas PET', 'activo' => true]);
        $botellon = Producto::create(['sku' => 'BOT-1', 'nombre' => 'Botellon 20L', 'categoria' => 'Botellones', 'activo' => true]);
        $jefe = $this->jefe();

        // Config default = conducta de hoy (regla de oro): preformas sanas.
        $res = $this->actingAs($jefe)->get(route('admin.produccion.asignar'))->assertOk();
        $this->assertSame([$preforma->id], $res->viewData('preformas')->pluck('id')->all());

        // El criterio de QUÉ es preforma sigue a la config (deploy, nivel 2).
        config(['produccion.patron_preforma' => '%botellon%']);
        $res = $this->actingAs($jefe)->get(route('admin.produccion.asignar'))->assertOk();
        $this->assertSame([$botellon->id], $res->viewData('preformas')->pluck('id')->all());
    }

    public function test_el_patron_de_danada_mueve_la_exclusion_y_la_validacion_la_comparte(): void
    {
        $preforma = Producto::create(['sku' => 'PREF-1', 'nombre' => 'Preforma 20g', 'categoria' => 'Preformas PET', 'activo' => true]);
        $danada = Producto::create(['sku' => 'PREF-D', 'nombre' => 'Preforma dañada 20g', 'categoria' => 'Preformas PET', 'activo' => true]);
        $rota = Producto::create(['sku' => 'PREF-R', 'nombre' => 'Preforma rota 20g', 'categoria' => 'Preformas PET', 'activo' => true]);
        $jefe = $this->jefe();
        $base = fn () => ['soplador_id' => $this->soplador()->id, 'turno' => 'dia', 'fecha' => now()->toDateString(), 'asignadas' => 100];

        // Default: la dañada queda fuera del selector Y de la validación
        // (closure única: mismo universo en las dos puertas).
        $res = $this->actingAs($jefe)->get(route('admin.produccion.asignar'))->assertOk();
        $this->assertSame([$preforma->id, $rota->id], $res->viewData('preformas')->pluck('id')->all());
        $this->actingAs($jefe)->post(route('admin.produccion.asignar.store'), $base() + ['preforma_id' => $danada->id])
            ->assertSessionHasErrors('preforma_id');

        // Con el patrón movido, la exclusión cambia de producto en AMBAS puertas.
        config(['produccion.patron_danada' => '%rota%']);
        $res = $this->actingAs($jefe)->get(route('admin.produccion.asignar'))->assertOk();
        $this->assertSame([$preforma->id, $danada->id], $res->viewData('preformas')->pluck('id')->all());
        $this->actingAs($jefe)->post(route('admin.produccion.asignar.store'), $base() + ['preforma_id' => $danada->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($jefe)->post(route('admin.produccion.asignar.store'), $base() + ['preforma_id' => $rota->id])
            ->assertSessionHasErrors('preforma_id');
    }

    public function test_el_tope_de_92_dias_acota_el_rango_pedido(): void
    {
        // Límite de RENDER (nivel 3, ahora constante MAX_DIAS_RANGO): pedir
        // 200 días devuelve la tabla acotada a 92 hacia atrás + el día hasta.
        $res = $this->actingAs($this->jefe())->get(route('admin.produccion.maquina', [
            'maquina' => $this->maquina->id,
            'desde' => now()->subDays(200)->toDateString(),
            'hasta' => now()->toDateString(),
        ]))->assertOk();

        $this->assertCount(93, $res->viewData('tendencia')['dias']);
    }

    public function test_el_kardex_y_el_inventario_paginan_con_la_convencion_de_la_casa(): void
    {
        // Adopción de Controller::POR_PAGINA (molde COM-2): el valor vive UNA
        // vez en el padre; estos dos eran los paginate(25) del módulo.
        $kardex = $this->actingAs($this->jefe())->get(route('admin.produccion.movimientos'))->assertOk();
        $this->assertSame(\App\Http\Controllers\Controller::POR_PAGINA, $kardex->viewData('movimientos')->perPage());

        $bodega = Bodega::factory()->create();
        $gestor = tap(User::factory()->create())->givePermissionTo('manage productos');
        $inventario = $this->actingAs($gestor)->get(route('admin.bodegas.show', $bodega))->assertOk();
        $this->assertSame(\App\Http\Controllers\Controller::POR_PAGINA, $inventario->viewData('stocks')->perPage());
    }
}
