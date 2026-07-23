<?php

namespace Tests\Feature;

use App\Models\Aprobacion;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Inicio M16-v1 «Pulso del día» (PLAN-M16-V1 opción A, visto bueno del dueño
 * 14-07): franja de excepciones con edad (solo lo desviado), pulso de
 * producción/taller con medida directa + contexto, y zócalo de accesos.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Excepciones de la franja ①, indexadas por label. */
    private function excepciones(TestResponse $res): Collection
    {
        return collect($res->viewData('excepciones'))->keyBy('label');
    }

    /** Asignación + reporte mínimos para el pulso de producción. */
    private function produccionDe(string $fecha, array $reporte = [], int $asignadas = 200): ProduccionReporte
    {
        $soplador = User::factory()->create();
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $asignadas,
        ]);

        return ProduccionReporte::create($reporte + [
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $asignadas,
            'estado' => ProduccionReporte::APROBADO,
        ]);
    }

    private function aprobacionPendiente(string $rol, int $horasAtras = 0): Aprobacion
    {
        $aprobacion = Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'motivo' => 'm',
            'descripcion' => 'd',
            'rol_aprobador' => $rol,
        ]);

        if ($horasAtras > 0) {
            $aprobacion->created_at = now()->subHours($horasAtras);
            $aprobacion->save();
        }

        return $aprobacion;
    }

    // --- Acceso y bloques base -----------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_ve_operacion_al_dia_y_zocalo_completo(): void
    {
        $res = $this->actingAs($this->userWithRole('admin'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Requiere tu atención')
            ->assertSee('Operación al día')
            ->assertSee('Accesos directos')
            ->assertSee('Comercial')
            ->assertSee('Administración')
            ->assertSee('Auditoría')
            ->assertSee('Aprobaciones') // historial del motor, ahora en el zócalo
            ->assertSee('Personalizar'); // modo de color de las cards (D-013)

        $this->assertSame([], $res->viewData('excepciones'));
    }

    public function test_soplador_sees_cta_but_no_admin_blocks(): void
    {
        $res = $this->actingAs($this->userWithRole('soplador'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Tu reporte de producción')
            ->assertSee('Mi producción')
            ->assertDontSee('Requiere tu atención')
            ->assertDontSee('Accesos directos')
            ->assertDontSee('Administración')
            ->assertDontSee('Usuarios')
            ->assertDontSee('Personalizar'); // sin accesos no hay modo de color
    }

    public function test_member_sees_only_greeting(): void
    {
        $res = $this->actingAs($this->userWithRole('member'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Bienvenido')
            ->assertDontSee('Tu reporte de producción')
            ->assertDontSee('Requiere tu atención')
            ->assertDontSee('Operación al día')
            ->assertDontSee('Comercial');

        $this->assertFalse($res->viewData('puedeVerExcepciones'));
    }

    // --- Franja ① Excepciones -------------------------------------------------

    public function test_excepcion_reportes_por_aprobar_con_edad_del_mas_viejo(): void
    {
        $this->freezeTime();
        $this->produccionDe(now()->toDateString(), [
            'estado' => ProduccionReporte::ENVIADO,
            'enviado_at' => now()->subDays(2),
        ]);

        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $ex = $this->excepciones($res)['Reportes por aprobar'];
        $this->assertSame(1, $ex['cantidad']);
        $this->assertSame('hace 2 días', $ex['edad']);
        $this->assertSame(route('admin.produccion.index'), $ex['href']);
        $res->assertSee('Reportes por aprobar')->assertSee('hace 2 días');
    }

    public function test_excepcion_asignacion_de_hoy_sin_reporte(): void
    {
        $soplador = User::factory()->create();
        ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 100,
        ]);

        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $this->assertSame(1, $this->excepciones($res)['Asignaciones de hoy sin reporte']['cantidad']);
    }

    public function test_operacion_al_dia_solo_para_quien_puede_tener_excepciones(): void
    {
        // jefe_bodega sin desviaciones: franja visible, quieta.
        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');
        $res->assertOk()->assertSee('Operación al día');
        $this->assertSame([], $res->viewData('excepciones'));
        $this->assertTrue($res->viewData('puedeVerExcepciones'));

        // vendedor (sin permisos que generen excepciones): ni la franja quieta.
        $res = $this->actingAs($this->userWithRole('vendedor'))->get('/dashboard');
        $res->assertOk()->assertDontSee('Operación al día');
        $this->assertFalse($res->viewData('puedeVerExcepciones'));
    }

    public function test_excepcion_aprobaciones_respeta_rol_y_muestra_espera(): void
    {
        $this->freezeTime();
        $this->aprobacionPendiente('jefe_bodega', horasAtras: 5);
        $this->aprobacionPendiente('admin');

        // El jefe ve solo lo de su rol, con la espera del más viejo.
        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');
        $ex = $this->excepciones($res)['Aprobaciones pendientes'];
        $this->assertSame(1, $ex['cantidad']);
        $this->assertSame('hace 5 h', $ex['edad']);

        // El admin ve todas (espejo de su bandeja).
        $res = $this->actingAs($this->userWithRole('admin'))->get('/dashboard');
        $this->assertSame(2, $this->excepciones($res)['Aprobaciones pendientes']['cantidad']);
    }

    public function test_excepcion_recepciones_exige_poder_abrir_el_destino(): void
    {
        OrdenServicio::factory()->count(2)->create(['fuente' => 'qr', 'confirmada_at' => null, 'estado' => 'recibido']);

        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');
        $this->assertSame(2, $this->excepciones($res)['Recepciones por confirmar']['cantidad']);

        // Permiso directo de confirmar SIN view/manage: el href daría 403 → no se ofrece.
        $user = User::factory()->create();
        $user->givePermissionTo('confirmar servicio tecnico');
        $res = $this->actingAs($user)->get('/dashboard');
        $this->assertArrayNotHasKey('Recepciones por confirmar', $this->excepciones($res)->all());
        $this->assertFalse($res->viewData('puedeVerExcepciones'));
    }

    public function test_excepcion_notificaciones_cuenta_solo_fallidas_terminales(): void
    {
        $base = ['evento' => 'sistema.prueba', 'canal' => 'mail', 'titulo' => 't', 'cuerpo' => 'c', 'estado' => Notificacion::FALLIDA];
        Notificacion::create($base + ['programada_para' => null]);           // terminal: cuenta
        Notificacion::create($base + ['programada_para' => now()->addMinutes(5)]); // en reintento: se resuelve sola

        $res = $this->actingAs($this->userWithRole('admin'))->get('/dashboard');

        $ex = $this->excepciones($res)['Notificaciones caídas (sin reintento)'];
        $this->assertSame(1, $ex['cantidad']);
        $this->assertSame(route('admin.notificaciones.index', ['estado' => 'fallida']), $ex['href']);
    }

    // --- Franja ② Pulso --------------------------------------------------------

    public function test_pulso_produccion_medida_directa_serie_y_referencia(): void
    {
        $this->freezeTime();
        // Hoy: 180 producido de 200 asignadas (90%), merma 20 de 200 (10%).
        $this->produccionDe(now()->toDateString(), [
            'primera' => 150, 'segunda' => 30, 'malo' => 15, 'danada' => 5,
        ]);
        // Ayer (la referencia de los 7 días previos): merma 10 de 100 (10%).
        $this->produccionDe(now()->subDay()->toDateString(), ['primera' => 90, 'malo' => 10], 100);

        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $pulso = $res->viewData('pulsoProduccion');
        $this->assertSame(180, $pulso['producido']);
        $this->assertSame(200, $pulso['asignadas']);
        $this->assertSame(90, $pulso['avance']);
        $this->assertSame(10, $pulso['merma_pct']);
        $this->assertSame(10, $pulso['mermaProm7']); // solo mira los días ANTERIORES

        // Serie: 7 días con ceros rellenados; hoy (último) es el máximo.
        $this->assertCount(7, $pulso['serie']);
        $this->assertSame(180, $pulso['serie'][6]['producido']);
        $this->assertSame(100, $pulso['serie'][6]['pct']);
        $this->assertSame(90, $pulso['serie'][5]['producido']);
        $this->assertSame(50, $pulso['serie'][5]['pct']);
        $this->assertSame(0, $pulso['serie'][0]['producido']);

        $res->assertSee('Producción · hoy')->assertSee('prom. 7 días 10%');
    }

    public function test_pulso_produccion_sin_dias_previos_no_inventa_referencia(): void
    {
        $this->produccionDe(now()->toDateString(), ['primera' => 100]);

        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $this->assertNull($res->viewData('pulsoProduccion')['mermaProm7']);
        $res->assertDontSee('prom. 7 días');
    }

    public function test_pulso_taller_aging_por_antiguedad_y_flujo_semanal(): void
    {
        $this->freezeTime();
        // Activas: una fresca, una mediana, una estancada (estado FIJO — la
        // factory lo sortea y aquí se asserta sobre él).
        OrdenServicio::factory()->create(['estado' => 'en_revision', 'fecha_ingreso' => now()->subDays(3)->toDateString()]);
        OrdenServicio::factory()->create(['estado' => 'recibido', 'fecha_ingreso' => now()->subDays(10)->toDateString()]);
        OrdenServicio::factory()->create(['estado' => 'cotizacion', 'fecha_ingreso' => now()->subDays(40)->toDateString()]);
        // Terminada esta semana: sale de activos, cuenta como entrada y salida.
        OrdenServicio::factory()->create([
            'estado' => 'entregado',
            'fecha_ingreso' => now()->subDays(2)->toDateString(),
            'fecha_entrega' => now()->subDay()->toDateString(),
        ]);

        $res = $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard');

        $pulso = $res->viewData('pulsoTaller');
        $this->assertSame(3, $pulso['activos']);
        $this->assertSame(['d0_7' => 1, 'd8_30' => 1, 'd30' => 1], $pulso['aging']);
        $this->assertSame(2, $pulso['entradasSemana']); // ingresos de los últimos 7 días
        $this->assertSame(1, $pulso['salidasSemana']);  // por fecha_entrega

        $res->assertSee('Taller · equipos activos')->assertSee('30+');
    }

    // --- Visibilidad por rol y zócalo ------------------------------------------

    public function test_visibilidad_de_pulsos_por_rol(): void
    {
        // jefe_bodega: producción sí, taller no (no gestiona el taller).
        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');
        $this->assertNotNull($res->viewData('pulsoProduccion'));
        $this->assertNull($res->viewData('pulsoTaller'));

        // técnico: taller sí, producción no; sin excepciones de aprobaciones.
        $res = $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard');
        $this->assertNull($res->viewData('pulsoProduccion'));
        $this->assertNotNull($res->viewData('pulsoTaller'));

        // jefe_ventas: sin pulsos, pero SÍ puede tener excepciones (aprobaciones).
        $res = $this->actingAs($this->userWithRole('jefe_ventas'))->get('/dashboard');
        $this->assertNull($res->viewData('pulsoProduccion'));
        $this->assertNull($res->viewData('pulsoTaller'));
        $this->assertTrue($res->viewData('puedeVerExcepciones'));
    }

    public function test_tarjetas_de_taller_agrupan_estados_y_entregadas_es_del_mes(): void
    {
        // Pendientes (ciclo abierto): cuentan por estado actual, sin acotar al mes.
        OrdenServicio::factory()->count(2)->create(['estado' => 'recibido']);
        OrdenServicio::factory()->create(['estado' => 'cotizacion']);
        OrdenServicio::factory()->create(['estado' => 'reparado']);
        // Entregadas: solo las retiradas ESTE mes cuentan en la tarjeta.
        OrdenServicio::factory()->create(['estado' => 'entregado', 'fecha_retiro' => now()->toDateString()]);
        OrdenServicio::factory()->create(['estado' => 'entregado', 'fecha_retiro' => now()->subMonth()->toDateString()]); // mes pasado → no
        OrdenServicio::factory()->create(['estado' => 'entregado', 'fecha_retiro' => null]);                              // histórica → no

        $res = $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Recibido / Cotización')
            ->assertSee('Reparadas')
            ->assertSee('Entregadas (mes)')
            ->assertSee('Total del mes')
            // La card combinada enlaza al listado filtrado por ambos estados.
            ->assertSee(route('admin.servicio-tecnico.index', ['estados' => 'recibido,cotizacion']), false);

        $cards = collect($res->viewData('tallerCards'))->keyBy('label');
        $this->assertSame(3, $cards['Recibido / Cotización']['cantidad']);   // 2 + 1
        $this->assertSame(1, $cards['Reparadas']['cantidad']);
        $this->assertSame(1, $cards['Entregadas (mes)']['cantidad']);         // solo la de este mes
    }

    public function test_sin_permiso_de_servicio_tecnico_no_hay_tarjetas_de_taller(): void
    {
        $res = $this->actingAs($this->userWithRole('soplador'))->get('/dashboard');
        $this->assertNull($res->viewData('tallerCards'));
    }

    public function test_zocalo_filtra_accesos_por_permiso(): void
    {
        // vendedor: solo Comercial (Clientes); nada de Operación/Administración.
        $res = $this->actingAs($this->userWithRole('vendedor'))->get('/dashboard');
        $res->assertOk()->assertSee('Accesos directos')->assertSee('Clientes')
            ->assertDontSee('Auditoría')
            ->assertDontSee('Inventario');

        $grupos = $res->viewData('accesos');
        $this->assertSame(['Comercial'], $grupos->keys()->all());
    }

    // --- Badge del nav (sin cambios en M16-v1) ---------------------------------

    public function test_barra_muestra_contador_de_pendientes_de_servicio_tecnico(): void
    {
        // Pendientes = todos los estados activos (recibido, en_revision, cotizacion,
        // esperando_repuesto, reparado). Solo entregado y sin_solucion NO cuentan.
        OrdenServicio::factory()->count(2)->create(['estado' => 'recibido']);
        OrdenServicio::factory()->create(['estado' => 'cotizacion']);
        OrdenServicio::factory()->create(['estado' => 'reparado']);       // ahora SÍ cuenta
        OrdenServicio::factory()->create(['estado' => 'entregado']);      // no cuenta
        OrdenServicio::factory()->create(['estado' => 'sin_solucion']);   // no cuenta

        $this->assertSame(4, OrdenServicio::pendientesTecnico()->count());

        // El técnico ve el badge con el número en la barra.
        $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard')
            ->assertOk()
            ->assertSee('4 equipo(s) por atender');
    }

    public function test_barra_no_muestra_contador_a_rol_sin_acceso_a_servicio_tecnico(): void
    {
        OrdenServicio::factory()->count(2)->create(['estado' => 'recibido']);

        // Un rol sin permiso de servicio técnico no ve el link ni el contador.
        $this->actingAs($this->userWithRole('soplador'))->get('/dashboard')
            ->assertOk()
            ->assertDontSee('equipo(s) por atender');
    }

    public function test_barra_no_muestra_badge_si_no_hay_pendientes(): void
    {
        OrdenServicio::factory()->create(['estado' => 'entregado']);   // no pendiente

        $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard')
            ->assertOk()
            ->assertDontSee('equipo(s) por atender');
    }
}
