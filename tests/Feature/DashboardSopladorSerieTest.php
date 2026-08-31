<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Serie personal del operario en el Inicio (pedido del dueño 31-08): bajo la
 * tarjeta CTA, mini-barras con SUS últimos N días de soplado — solo sus
 * reportes, con la misma perilla de ventana que la serie del jefe
 * (`dashboard_dias_serie_produccion`) y el rótulo derivado de la ventana.
 *
 * Los asserts de pantalla usan el título COMPLETO («Mi producción · últimos
 * N días»): la sidebar del soplador ya dice «Mi producción» a secas, así que
 * la forma corta pasaría por el ítem del menú (doctrina verde-engañoso).
 */
class DashboardSopladorSerieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('soplador');
    }

    /** Asignación + reporte mínimos PARA un soplador dado (cualquier estado cuenta). */
    private function reporteDe(User $soplador, string $fecha, array $reporte = []): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => 200,
        ]);

        return ProduccionReporte::create($reporte + [
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => 200,
            'estado' => ProduccionReporte::APROBADO,
        ]);
    }

    public function test_el_soplador_ve_solo_su_propia_serie(): void
    {
        $this->freezeTime();
        $yo = $this->soplador();
        $otro = $this->soplador();

        // Míos: hoy 120 (100 de 1ª + 20 de 2ª — la merma NO cuenta como
        // producido) y ayer 50. Del OTRO soplador: 999 hoy, que no deben
        // aparecer en MI tarjeta.
        $this->reporteDe($yo, now()->toDateString(), ['primera' => 100, 'segunda' => 20, 'malo' => 7]);
        $this->reporteDe($yo, now()->subDay()->toDateString(), ['primera' => 50]);
        $this->reporteDe($otro, now()->toDateString(), ['primera' => 999]);

        $res = $this->actingAs($yo)->get('/dashboard')->assertOk();

        $tarjeta = $res->viewData('serieSoplador');
        $this->assertCount(7, $tarjeta['serie']); // default de la perilla
        $this->assertSame(120, $tarjeta['serie'][6]['producido']); // hoy, sin el 999 ajeno
        $this->assertSame(100, $tarjeta['serie'][6]['pct']);
        $this->assertSame(50, $tarjeta['serie'][5]['producido']);
        $this->assertSame(0, $tarjeta['serie'][0]['producido']); // día sin soplar = cero, no hueco
        $this->assertSame(170, $tarjeta['total']);

        $res->assertSee('Mi producción · últimos 7 días')
            ->assertSee('Ver historial')
            ->assertSee(route('produccion.mi.historial'));
    }

    public function test_sin_permiso_de_reporte_no_hay_serie_personal(): void
    {
        // El jefe ve el pulso GLOBAL, no una serie personal (no reporta).
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');

        $res = $this->actingAs($jefe)->get('/dashboard')->assertOk();

        $this->assertNull($res->viewData('serieSoplador'));
        $res->assertDontSee('Mi producción · últimos');
    }

    public function test_la_ventana_deriva_de_la_perilla_compartida(): void
    {
        // La MISMA perilla de la serie del jefe (DASH-1): una sola ventana
        // para las dos mini-barras del Inicio. La fila la define el seeder de
        // Configuración (set() exige fila existente), así que se siembra.
        $this->seed(\Database\Seeders\ConfiguracionSeeder::class);
        Configuracion::set('dashboard_dias_serie_produccion', 3);

        $res = $this->actingAs($this->soplador())->get('/dashboard')->assertOk();

        $this->assertCount(3, $res->viewData('serieSoplador')['serie']);
        $res->assertSee('Mi producción · últimos 3 días');
    }

    public function test_sin_soplado_la_tarjeta_igual_aparece_en_cero(): void
    {
        // Un soplador nuevo ve la tarjeta honesta en cero, no una pantalla
        // que cambia de forma según tenga o no datos.
        $res = $this->actingAs($this->soplador())->get('/dashboard')->assertOk();

        $tarjeta = $res->viewData('serieSoplador');
        $this->assertSame(0, $tarjeta['total']);
        $this->assertSame([0], array_values(array_unique(array_column($tarjeta['serie'], 'producido'))));
        $res->assertSee('Mi producción · últimos 7 días');
    }
}
