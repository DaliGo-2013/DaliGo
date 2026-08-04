<?php

namespace Tests\Feature\Devoluciones;

use App\Models\Devolucion;
use App\Models\User;
use App\Support\AvisosError;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-M13-04 · informe por causa y canal (el cierre de E6). Molde de
 * ServicioTecnicoInformeTest: acceso primero, agregaciones por assertViewHas
 * (nunca assertSee de un número computado), fixtures con fechas LITERALES y
 * siempre un registro fuera del período que no debe contarse.
 */
class DevolucionInformeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    private function jefeBodega(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    /** Una devolución con fecha de creación LITERAL (created_at controlado). */
    private function devolucion(string $fecha, array $attrs = []): Devolucion
    {
        $devolucion = Devolucion::factory()->create($attrs);
        // created_at fuera del guarded-touch de la factory: fecha exacta.
        $devolucion->forceFill(['created_at' => $fecha.' 12:00:00'])->saveQuietly();

        return $devolucion;
    }

    public function test_acceso_por_permiso(): void
    {
        // Invitado → login.
        $this->get(route('admin.devoluciones.informe'))->assertRedirect('/login');

        // Sin permiso → rebote amable al Inicio.
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $this->actingAs($soplador)->get(route('admin.devoluciones.informe'))
            ->assertRedirect(route('dashboard'));

        // `view devoluciones` BASTA (el informe no exige manage): rol custom
        // con solo ese permiso.
        $mirón = User::factory()->create();
        $mirón->givePermissionTo('view devoluciones');
        $this->actingAs($mirón)->get(route('admin.devoluciones.informe'))->assertOk();
    }

    public function test_agrega_por_causa_y_por_canal_dentro_del_periodo(): void
    {
        // Fixtures con fechas LITERALES (jamás subMonth() relativo a hoy: un
        // día 31 desborda al mismo mes — bitácora 2026-07-31). Período pedido:
        // junio 2026. El de julio NO debe contarse.
        $this->devolucion('2026-06-05', ['canal' => 'mercado_libre']);
        $this->devolucion('2026-06-10', ['canal' => 'mercado_libre', 'causa' => 'transporte', 'estado' => Devolucion::EVALUADA]);
        $this->devolucion('2026-06-30', ['canal' => 'falabella', 'causa' => 'fabrica', 'estado' => Devolucion::REEMBOLSADA, 'monto_reembolso' => 45000]);
        $this->devolucion('2026-07-01', ['canal' => 'mostrador']); // FUERA (borde superior + 1)

        $respuesta = $this->actingAs($this->jefeBodega())
            ->get(route('admin.devoluciones.informe', ['anio' => 2026, 'mes' => 6]))
            ->assertOk()
            ->assertViewHas('kpis.total', 3)
            ->assertViewHas('kpis.por_recibir', 1)
            ->assertViewHas('kpis.resueltas', 1)
            ->assertViewHas('kpis.reembolsado', 45000)
            ->assertViewHas('periodoLabel', 'Junio 2026');

        // Por causa: la sin evaluar NO se esconde (es la cola de trabajo).
        $respuesta->assertViewHas('porCausa', function ($causas) {
            $porNombre = $causas->keyBy('nombre');

            return $porNombre['Sin evaluar']->cantidad === 1
                && $porNombre['Daño en transporte']->cantidad === 1
                && $porNombre['Defecto de fábrica']->cantidad === 1;
        });

        // Por canal: 2 de Mercado Libre, 1 de Falabella, y el de mostrador
        // (julio) NO aparece.
        $respuesta->assertViewHas('porCanal', function ($canales) {
            $porNombre = $canales->keyBy('nombre');

            return $porNombre['Mercado Libre']->cantidad === 2
                && $porNombre['Falabella']->cantidad === 1
                && ! $porNombre->has('Mostrador');
        });
    }

    public function test_los_dos_bordes_del_mes_cuentan_y_los_de_afuera_no(): void
    {
        // El candado del borde (bitácora 2026-07-01: la fecha casteada guarda
        // hora 00:00:00 y un whereBetween deja el borde superior FUERA):
        // primer y último día DENTRO; el día anterior y el siguiente FUERA.
        $this->devolucion('2026-06-01'); // primer día → DENTRO
        $this->devolucion('2026-06-30'); // último día → DENTRO
        $this->devolucion('2026-05-31'); // víspera → FUERA
        $this->devolucion('2026-07-01'); // siguiente → FUERA

        $this->actingAs($this->jefeBodega())
            ->get(route('admin.devoluciones.informe', ['anio' => 2026, 'mes' => 6]))
            ->assertOk()
            ->assertViewHas('kpis.total', 2);
    }

    public function test_solo_anio_cubre_el_anio_completo(): void
    {
        $this->devolucion('2026-01-15');
        $this->devolucion('2026-12-31');
        $this->devolucion('2025-12-31'); // FUERA

        $this->actingAs($this->jefeBodega())
            ->get(route('admin.devoluciones.informe', ['anio' => 2026]))
            ->assertOk()
            ->assertViewHas('kpis.total', 2)
            ->assertViewHas('periodoLabel', 'Año 2026');
    }

    public function test_parametros_invalidos_se_rechazan(): void
    {
        $jefe = $this->jefeBodega();

        $this->actingAs($jefe)->get(route('admin.devoluciones.informe', ['mes' => 13]))
            ->assertSessionHasErrors('mes');
        $this->actingAs($jefe)->get(route('admin.devoluciones.informe', ['anio' => 19999]))
            ->assertSessionHasErrors('anio');
    }

    public function test_el_badge_del_menu_cuenta_solo_para_quien_puede_recibir(): void
    {
        // 2 por recibir + 1 ya recibida (no cuenta): la ACCIÓN pendiente,
        // doctrina de badges.
        Devolucion::factory()->count(2)->create();
        Devolucion::factory()->recibida()->create();

        $badgesJefe = \App\Support\MenuPrincipal::badges($this->jefeBodega());
        $this->assertSame(2, $badgesJefe['devoluciones_por_recibir']);

        // Sin `manage devoluciones` (aunque tenga view): 0 — el badge es de
        // quien puede ACTUAR recibiéndola.
        $mirón = User::factory()->create();
        $mirón->givePermissionTo('view devoluciones');
        $badgesMirón = \App\Support\MenuPrincipal::badges($mirón);
        $this->assertSame(0, $badgesMirón['devoluciones_por_recibir']);
    }
}
