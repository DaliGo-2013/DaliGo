<?php

namespace Tests\Feature\Aprobaciones;

use App\Models\Aprobacion;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use App\Services\Aprobaciones\Aprobaciones;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P-M14-03: bandeja movil /aprobaciones + "mis solicitudes" por HTTP.
 * Permisos, filtro por rol vigente, resolver con doble-tap absorbido y
 * rechazo con motivo obligatorio (chips + "Otro").
 */
class AprobacionBandejaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        Queue::fake();
    }

    public function test_la_bandeja_exige_el_permiso(): void
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        $this->actingAs($soplador)->get(route('aprobaciones.index'))->assertForbidden();
    }

    public function test_la_bandeja_muestra_solo_las_pendientes_del_rol_vigente(): void
    {
        $base = ['tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE, 'motivo' => 'm'];
        Aprobacion::create($base + ['descripcion' => 'Para admin', 'rol_aprobador' => 'admin']);
        Aprobacion::create($base + ['descripcion' => 'Para bodega', 'rol_aprobador' => 'jefe_bodega']);

        // jefe_ventas: tiene el permiso (bandeja abre) pero NINGUNA es de su rol.
        $ventas = tap(User::factory()->create())->assignRole('jefe_ventas');
        $this->actingAs($ventas)->get(route('aprobaciones.index'))
            ->assertOk()
            ->assertSee('Todo al día')
            ->assertDontSee('Para admin')
            ->assertDontSee('Para bodega');

        // jefe_bodega ve la suya, no la del admin.
        $bodega = tap(User::factory()->create())->assignRole('jefe_bodega');
        $this->actingAs($bodega)->get(route('aprobaciones.index'))
            ->assertOk()
            ->assertSee('Para bodega')
            ->assertDontSee('Para admin');

        // El admin ve TODAS (puede resolver cualquiera).
        $admin = tap(User::factory()->create())->assignRole('admin');
        $this->actingAs($admin)->get(route('aprobaciones.index'))
            ->assertOk()
            ->assertSee('Para admin')
            ->assertSee('Para bodega');
    }

    public function test_aprobar_por_http_aplica_y_redirige_con_estado(): void
    {
        [$jefe, $reporte, $aprobacion] = $this->pendienteReal();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('aprobaciones.aprobar', $aprobacion))
            ->assertRedirect(route('aprobaciones.index'))
            ->assertSessionHas('status', 'Solicitud aprobada y aplicada.');

        $this->assertSame(500, $reporte->fresh()->asignadas);
    }

    public function test_doble_tap_el_segundo_recibe_ya_fue_resuelta(): void
    {
        [, $reporte, $aprobacion] = $this->pendienteReal();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($admin)->post(route('aprobaciones.aprobar', $aprobacion));
        // Segundo tap: flash amable, sin doble aplicacion ni pagina de error.
        $this->actingAs($admin)
            ->post(route('aprobaciones.aprobar', $aprobacion))
            ->assertRedirect(route('aprobaciones.index'))
            ->assertSessionHas('status', 'Esa solicitud ya fue resuelta.');

        $this->assertSame(500, $reporte->fresh()->asignadas);
        $this->assertSame(Aprobacion::ESTADO_APROBADA, $aprobacion->fresh()->estado);
    }

    public function test_rechazar_exige_motivo(): void
    {
        [, , $aprobacion] = $this->pendienteReal();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('aprobaciones.rechazar', $aprobacion), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertTrue($aprobacion->fresh()->esPendiente());
    }

    public function test_rechazar_con_chip_y_con_otro_texto_libre(): void
    {
        [, $reporte, $aprobacion] = $this->pendienteReal();
        $admin = tap(User::factory()->create())->assignRole('admin');

        // Chip de la lista.
        $this->actingAs($admin)
            ->post(route('aprobaciones.rechazar', $aprobacion), [
                'motivo' => Aprobacion::MOTIVOS_RECHAZO[0],
            ])
            ->assertRedirect(route('aprobaciones.index'));
        $this->assertSame(Aprobacion::MOTIVOS_RECHAZO[0], $aprobacion->fresh()->resultado_motivo);
        $this->assertSame(450, $reporte->fresh()->asignadas); // objetivo intacto

        // "Otro" con texto libre (centinela __otro__ + motivo_otro).
        [, , $otra] = $this->pendienteReal();
        $this->actingAs($admin)
            ->post(route('aprobaciones.rechazar', $otra), [
                'motivo' => ProduccionReporte::MOTIVO_OTRO,
                'motivo_otro' => 'El kardex de ese día ya se cerró',
            ])
            ->assertRedirect(route('aprobaciones.index'));
        $this->assertSame('El kardex de ese día ya se cerró', $otra->fresh()->resultado_motivo);
    }

    public function test_mis_solicitudes_muestra_lo_propio_y_solo_exige_auth(): void
    {
        // Invitado: redirige a login (ANTES de cualquier actingAs — persiste
        // en la instancia del test).
        $this->get(route('aprobaciones.mias'))->assertRedirect(route('login'));

        [$jefe, , $aprobacion] = $this->pendienteReal();

        // El jefe (solicitante) la ve en su historial, con su estado Y con lo
        // que pidió (motivo + magnitud — hallazgo #3 del QA 15-07: sin esto no
        // distinguía sus solicitudes entre sí).
        $this->actingAs($jefe)->get(route('aprobaciones.mias'))
            ->assertOk()
            ->assertSee($aprobacion->descripcion)
            ->assertSee('Pendiente')
            ->assertSee('Conteo corregido')
            ->assertSee('magnitud');

        // Otro usuario cualquiera (sin permiso de bandeja) entra pero no la ve.
        $otro = tap(User::factory()->create())->assignRole('soplador');
        $this->actingAs($otro)->get(route('aprobaciones.mias'))
            ->assertOk()
            ->assertDontSee($aprobacion->descripcion);
    }

    public function test_la_tarjeta_muestra_el_detalle_del_cambio(): void
    {
        // Hallazgo #7 del QA 15-07: el aprobador decidía sin ver QUÉ cambia.
        // El payload ya trae anterior/nuevo → la tarjeta pinta solo lo que difiere.
        $this->pendienteReal(); // anterior asignadas 450 → nuevo 500
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($admin)->get(route('aprobaciones.index'))
            ->assertOk()
            ->assertSee('Asignadas: 450 → 500');
    }

    public function test_el_nav_distingue_bandeja_de_historial(): void
    {
        // Hallazgo #1 del QA 15-07: dos entradas llamadas "Aprobaciones"
        // confundieron al primer usuario real. El dropdown ahora se llama
        // "Historial de aprobaciones"; la bandeja conserva su nombre y se
        // verifica por su RUTA — asertar '>Aprobaciones<' es frágil: x-nav-link
        // renderiza el slot con saltos de línea, y la cadena pegada solo existía
        // por el chip del zócalo del dashboard (pasaba por la razón equivocada).
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Historial de aprobaciones')
            ->assertSee(route('aprobaciones.index'), false)         // la bandeja, por su href
            ->assertSee(route('admin.aprobaciones.index'), false);  // el historial, por el suyo
    }

    public function test_el_nav_muestra_aprobaciones_solo_con_permiso(): void
    {
        $bodega = tap(User::factory()->create())->assignRole('jefe_bodega');
        $this->actingAs($bodega)->get('/dashboard')->assertSee('Aprobaciones');

        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $this->actingAs($soplador)->get('/dashboard')->assertDontSee('>Aprobaciones<', false);
    }

    /**
     * Solicitud PENDIENTE real creada via el servicio (jefe_bodega pide un
     * ajuste 450→500 sobre el umbral).
     *
     * @return array{0: User, 1: ProduccionReporte, 2: Aprobacion}
     */
    private function pendienteReal(): array
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 450,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $asignacion->fecha,
            'turno' => 'dia',
            'asignadas' => 450,
            'estado' => ProduccionReporte::BORRADOR,
        ]);

        $aprobacion = app(Aprobaciones::class)->solicitar(
            tipoAccion: Aprobacion::ACCION_AJUSTE_REPORTE,
            aprobable: $reporte,
            solicitante: $jefe,
            motivo: 'Conteo corregido',
            datos: [
                'nuevo' => ['asignadas' => 500],
                'anterior' => ['asignadas' => 450],
                'objetivo_updated_at' => $reporte->updated_at?->toJSON(),
            ],
            monto: 100,
            descripcion: "Ajuste reporte #{$reporte->id}",
        );

        return [$jefe, $reporte, $aprobacion];
    }
}
