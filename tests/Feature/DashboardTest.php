<?php

namespace Tests\Feature;

use App\Models\Aprobacion;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

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

    /** Todas las cards del tablero (aplana viewData 'secciones'), indexadas por label. */
    private function cards(TestResponse $res): Collection
    {
        return collect($res->viewData('secciones'))
            ->flatMap(fn (array $s) => $s['cards'])
            ->keyBy('label');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_sees_indicadores_and_all_quick_access_groups(): void
    {
        Producto::factory()->create(['activo' => true, 'peso_kg' => null]); // sin medidas

        $res = $this->actingAs($this->userWithRole('admin'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Reportes por revisar')
            ->assertSee('Productos sin medidas')
            ->assertSee('Comercial')
            ->assertSee('Operación')
            ->assertSee('Administración')
            ->assertSee('Auditoría');
    }

    public function test_soplador_sees_cta_but_no_admin_blocks(): void
    {
        $res = $this->actingAs($this->userWithRole('soplador'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Tu reporte de producción')
            ->assertSee('Mi producción')
            ->assertDontSee('Reportes por revisar')
            ->assertDontSee('Administración')
            ->assertDontSee('Usuarios');
    }

    public function test_tecnico_ve_cards_por_estado_con_conteos_y_enlaces(): void
    {
        OrdenServicio::factory()->count(2)->create(['estado' => 'cotizacion']);
        OrdenServicio::factory()->create(['estado' => 'reparado']);
        OrdenServicio::factory()->count(3)->create(['estado' => 'entregado']);
        OrdenServicio::factory()->create(['estado' => 'sin_solucion']);
        OrdenServicio::factory()->create(['estado' => 'recibido']);

        $res = $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Equipos en taller')
            ->assertSee('Cotización (espera cliente)')
            ->assertSee('Reparado')
            ->assertSee('Entregado')
            ->assertSee('Sin solución')
            // enlaces al listado filtrado por estado
            ->assertSee(route('admin.servicio-tecnico.index', ['estado' => 'cotizacion']), false)
            ->assertSee(route('admin.servicio-tecnico.index', ['estado' => 'sin_solucion']), false);

        // "Equipos en taller" = todo lo que no está entregado (2+1+1+1 = 5).
        $indicadores = $this->cards($res);
        $this->assertSame(5, $indicadores['Equipos en taller']['valor']);
        $this->assertSame(2, $indicadores['Cotización (espera cliente)']['valor']);
        $this->assertSame(3, $indicadores['Entregado']['valor']);
    }

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

    public function test_member_sees_only_greeting(): void
    {
        $res = $this->actingAs($this->userWithRole('member'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Bienvenido')
            ->assertDontSee('Tu reporte de producción')
            ->assertDontSee('Comercial')
            ->assertDontSee('Administración');
    }

    public function test_jefe_bodega_sees_pending_reports_count(): void
    {
        $soplador = $this->userWithRole('soplador');
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 100,
        ]);
        ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 100,
            'estado' => ProduccionReporte::ENVIADO,
        ]);

        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $res->assertOk()->assertSee('Reportes por revisar');
        $this->assertSame(1, ProduccionReporte::pendientes()->count());
    }

    // --- M16-v0: tablero ejecutivo (cards nuevas + visibilidad por rol) ------

    /** Asignación + reporte mínimos para las cards de producción. */
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

    private function aprobacionPendiente(string $rol): Aprobacion
    {
        return Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'motivo' => 'm',
            'descripcion' => 'd',
            'rol_aprobador' => $rol,
        ]);
    }

    public function test_cards_de_produccion_cuentan_solo_hoy(): void
    {
        // Hoy: 150+30 producido, 15+5 merma, 200 asignadas → avance 90%, merma 10%.
        $this->produccionDe(now()->toDateString(), [
            'primera' => 150, 'segunda' => 30, 'malo' => 15, 'danada' => 5,
            'estado' => ProduccionReporte::ENVIADO,
        ]);
        // Ayer: números grandes que NO deben contaminar las cards de hoy.
        $this->produccionDe(now()->subDay()->toDateString(), ['primera' => 999], 1000);

        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $cards = $this->cards($res);
        $this->assertSame(180, $cards['Producido hoy']['valor']);
        $this->assertSame(90, $cards['Avance de hoy (%)']['valor']);
        $this->assertSame(10, $cards['Merma de hoy (%)']['valor']);
        $this->assertSame(1, $cards['Reportes por revisar']['valor']); // solo el enviado
    }

    public function test_cards_de_produccion_en_cero_sin_division_por_cero(): void
    {
        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $cards = $this->cards($res);
        $this->assertSame(0, $cards['Producido hoy']['valor']);
        $this->assertSame(0, $cards['Avance de hoy (%)']['valor']);
        $this->assertSame(0, $cards['Merma de hoy (%)']['valor']);
    }

    public function test_recepciones_por_confirmar_cuenta_qr_y_ruta_sin_confirmar(): void
    {
        OrdenServicio::factory()->count(2)->create(['fuente' => 'qr', 'confirmada_at' => null]);
        OrdenServicio::factory()->create(['fuente' => 'qr', 'confirmada_at' => now()]);   // ya confirmada
        OrdenServicio::factory()->create(['fuente' => 'ruta', 'confirmada_at' => null]);
        OrdenServicio::factory()->create(['fuente' => 'mostrador']);                      // no requiere

        // jefe_bodega: confirma recepciones pero NO gestiona el taller → su
        // sección de Servicio Técnico trae SOLO esta card (semilla intacta).
        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $cards = $this->cards($res);
        $this->assertSame(3, $cards['Recepciones por confirmar']['valor']);
        $this->assertArrayNotHasKey('Equipos en taller', $cards->all());

        $st = collect($res->viewData('secciones'))->firstWhere('label', 'Servicio Técnico');
        $this->assertCount(1, $st['cards']);
    }

    public function test_aprobaciones_pendientes_es_espejo_de_la_bandeja(): void
    {
        $this->aprobacionPendiente('jefe_bodega');
        $this->aprobacionPendiente('admin');
        $this->aprobacionPendiente('jefe_bodega')
            ->update(['estado' => Aprobacion::ESTADO_APROBADA, 'resuelta_at' => now()]);

        // El jefe ve solo las pendientes de SU rol.
        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');
        $cards = $this->cards($res);
        $this->assertSame(1, $cards['Aprobaciones pendientes']['valor']);
        $this->assertSame(route('aprobaciones.index'), $cards['Aprobaciones pendientes']['href']);

        // El admin ve TODAS las pendientes (espejo de su bandeja).
        $res = $this->actingAs($this->userWithRole('admin'))->get('/dashboard');
        $cards = $this->cards($res);
        $this->assertSame(2, $cards['Aprobaciones pendientes']['valor']);
    }

    public function test_notificaciones_fallidas_cuenta_solo_fallidas_y_linkea_filtrado(): void
    {
        $base = ['evento' => 'sistema.prueba', 'canal' => 'mail', 'titulo' => 't', 'cuerpo' => 'c'];
        Notificacion::create($base + ['estado' => Notificacion::FALLIDA]);
        Notificacion::create($base + ['estado' => Notificacion::FALLIDA]);
        Notificacion::create($base + ['estado' => Notificacion::ENVIADA]);

        $res = $this->actingAs($this->userWithRole('admin'))->get('/dashboard');

        $cards = $this->cards($res);
        $this->assertSame(2, $cards['Notificaciones fallidas']['valor']);
        $this->assertSame(
            route('admin.notificaciones.index', ['estado' => 'fallida']),
            $cards['Notificaciones fallidas']['href'],
        );
    }

    public function test_jefe_ventas_ve_aprobaciones_pero_no_produccion_ni_taller(): void
    {
        $res = $this->actingAs($this->userWithRole('jefe_ventas'))->get('/dashboard');

        $labels = $this->cards($res)->keys();
        $this->assertEqualsCanonicalizing(
            ['Aprobaciones pendientes', 'Clientes', 'Usuarios'],
            $labels->all(),
        );
    }

    public function test_tecnico_ve_taller_pero_no_aprobaciones_ni_produccion(): void
    {
        $res = $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard');

        $labels = $this->cards($res)->keys();
        $this->assertContains('Equipos en taller', $labels);
        $this->assertContains('Recepciones por confirmar', $labels);
        $this->assertNotContains('Aprobaciones pendientes', $labels);
        $this->assertNotContains('Producido hoy', $labels);
        $this->assertNotContains('Notificaciones fallidas', $labels);
    }

    public function test_vendedor_solo_ve_la_card_de_clientes(): void
    {
        $res = $this->actingAs($this->userWithRole('vendedor'))->get('/dashboard');

        $this->assertSame(['Clientes'], $this->cards($res)->keys()->all());
    }

    public function test_soplador_y_member_no_tienen_cards(): void
    {
        foreach (['soplador', 'member'] as $rol) {
            $res = $this->actingAs($this->userWithRole($rol))->get('/dashboard');
            $this->assertSame([], $res->viewData('secciones'), "El rol {$rol} no debe ver secciones.");
        }
    }

    public function test_las_secciones_agrupan_por_modulo_en_orden(): void
    {
        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $this->assertSame(
            ['Producción · hoy', 'Servicio Técnico', 'Aprobaciones', 'Administración'],
            collect($res->viewData('secciones'))->pluck('label')->all(),
        );
    }

    public function test_solo_confirmar_sin_acceso_al_listado_no_ve_la_card(): void
    {
        OrdenServicio::factory()->create(['fuente' => 'qr', 'confirmada_at' => null]);

        // Permiso directo, sin rol: puede confirmar pero NO abrir el listado
        // ST (el href exige view|manage servicio tecnico) → la card se oculta
        // en vez de invitar a un 403.
        $user = User::factory()->create();
        $user->givePermissionTo('confirmar servicio tecnico');

        $res = $this->actingAs($user)->get('/dashboard');

        $this->assertArrayNotHasKey('Recepciones por confirmar', $this->cards($res)->all());
    }
}
