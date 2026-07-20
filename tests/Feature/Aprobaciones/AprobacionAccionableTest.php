<?php

namespace Tests\Feature\Aprobaciones;

use App\Models\Aprobacion;
use App\Models\Configuracion;
use App\Models\Notificacion;
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
 * Lote S2 del QA 15-07: #5 notificaciones ACCIONABLES (la fila lleva al destino
 * segun su evento), #8 plantillas ricas de aprobaciones (titulo que distingue
 * el resultado + motivo/magnitud/url en el cuerpo) y #9b traza del reporte
 * enlazada a su auditoria (la historia ya existia; faltaba el puente).
 */
class AprobacionAccionableTest extends TestCase
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

    private function notifDe(User $user, string $evento): Notificacion
    {
        return Notificacion::create([
            'evento' => $evento, 'user_id' => $user->id,
            'canal' => Notificacion::CANAL_DATABASE, 'titulo' => 'T', 'cuerpo' => 'C',
            'estado' => Notificacion::ENVIADA,
        ]);
    }

    // --- #5 · filas accionables --------------------------------------------

    public function test_url_destino_mapea_los_eventos_de_aprobacion(): void
    {
        $user = User::factory()->create();

        $this->assertSame(route('aprobaciones.index'), $this->notifDe($user, 'aprobacion.solicitada')->urlDestino());
        $this->assertSame(route('aprobaciones.index'), $this->notifDe($user, 'aprobacion.escalada')->urlDestino());
        $this->assertSame(route('aprobaciones.mias'), $this->notifDe($user, 'aprobacion.resuelta')->urlDestino());
        $this->assertNull($this->notifDe($user, 'sistema.prueba')->urlDestino());
    }

    public function test_la_fila_de_la_bandeja_lleva_a_su_destino(): void
    {
        // Usuario SIN rol: ni el nav ni el zocalo aportan este href — si la
        // ruta aparece, la trae la FILA (leccion del verde-engañoso 2026-07-20:
        // asertar donde ninguna otra superficie pueda satisfacer la cadena).
        $user = User::factory()->create();
        $this->notifDe($user, 'aprobacion.solicitada');

        $this->actingAs($user)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee(route('aprobaciones.index'), false);
    }

    public function test_la_fila_sin_destino_no_es_link(): void
    {
        $user = User::factory()->create();
        $this->notifDe($user, 'sistema.prueba');

        $this->actingAs($user)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertDontSee(route('aprobaciones.index'), false)
            ->assertDontSee(route('aprobaciones.mias'), false);
    }

    // --- #8 · plantillas ricas ----------------------------------------------

    public function test_solicitud_pendiente_notifica_con_titulo_motivo_magnitud_y_link(): void
    {
        tap(User::factory()->create())->assignRole('admin'); // destinatario del rol aprobador
        [, , $aprobacion] = $this->pendienteReal();

        $notif = Notificacion::where('evento', 'aprobacion.solicitada')
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();

        $this->assertSame('Aprobación pendiente: '.$aprobacion->descripcion, $notif->titulo);
        $this->assertStringContainsString('Conteo corregido', $notif->cuerpo); // motivo
        $this->assertStringContainsString('100', $notif->cuerpo);              // magnitud
        $this->assertStringContainsString(route('aprobaciones.index'), $notif->cuerpo);
    }

    public function test_la_resolucion_distingue_el_titulo_por_resultado(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');

        [, , $paraAprobar] = $this->pendienteReal();
        app(Aprobaciones::class)->aprobar($paraAprobar, $admin);
        $notif = Notificacion::where('evento', 'aprobacion.resuelta')->latest('id')->firstOrFail();
        $this->assertSame('Aprobada: '.$paraAprobar->descripcion, $notif->titulo);
        $this->assertStringContainsString(route('aprobaciones.mias'), $notif->cuerpo);

        [, , $paraRechazar] = $this->pendienteReal();
        app(Aprobaciones::class)->rechazar($paraRechazar, $admin, 'Los datos no cuadran');
        $notif = Notificacion::where('evento', 'aprobacion.resuelta')->latest('id')->firstOrFail();
        $this->assertSame('Rechazada: '.$paraRechazar->descripcion, $notif->titulo);
        $this->assertStringContainsString('Los datos no cuadran', $notif->cuerpo);
    }

    public function test_las_plantillas_sembradas_son_idempotentes(): void
    {
        $this->seed(ConfiguracionSeeder::class); // segunda corrida (patron del deploy)

        foreach (['solicitada', 'escalada', 'resuelta'] as $sufijo) {
            $this->assertSame(
                1,
                Configuracion::where('clave', "notif_plantilla_aprobacion_{$sufijo}")->count(),
                "La plantilla {$sufijo} se duplico o no se sembro.",
            );
        }
    }

    // --- #9b · traza del reporte --------------------------------------------

    public function test_la_ficha_del_reporte_enlaza_su_auditoria_solo_con_permiso(): void
    {
        [, $reporte] = $this->pendienteReal();

        $admin = tap(User::factory()->create())->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            ->assertSee('Ver historial de cambios');

        // jefe_bodega gestiona produccion pero NO tiene 'view audit': el enlace
        // no se le ofrece (su destino le daria 403).
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $this->actingAs($jefe)->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            ->assertDontSee('Ver historial de cambios');
    }

    public function test_la_auditoria_filtra_por_registro(): void
    {
        [, $reporteA] = $this->pendienteReal();
        [, $reporteB] = $this->pendienteReal();
        $reporteA->update(['asignadas' => 999]);
        $reporteB->update(['asignadas' => 888]);

        $admin = tap(User::factory()->create())->assignRole('admin');
        $res = $this->actingAs($admin)->get(route('admin.audits.index', [
            'auditable_type' => ProduccionReporte::class,
            'auditable_id' => $reporteA->id,
        ]));

        $res->assertOk();
        $audits = $res->viewData('audits');
        $this->assertTrue($audits->isNotEmpty(), 'El filtro dejo la traza vacia.');
        $this->assertTrue(
            $audits->getCollection()->every(fn ($a) => (int) $a->auditable_id === $reporteA->id),
            'El filtro por registro dejo pasar audits de otro registro.',
        );
    }

    /**
     * Pendiente real 450→500 via el servicio (mismo idioma que la bandeja).
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
