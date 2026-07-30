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
        // El usuario DEBE poder llegar al destino: la fila ya no enlaza a ciegas
        // (antes un vendedor caia en 403 al tocar la notificacion de una
        // cotizacion de otra cartera). Con el permiso, el nav tambien pinta ese
        // href, asi que el assert de la FILA es su clase de fila-enlazada, que en
        // esta pagina solo existe cuando hay destino (verde-engañoso 2026-07-20).
        $user = tap(User::factory()->create())->assignRole('jefe_bodega');
        $this->notifDe($user, 'aprobacion.solicitada');

        $this->actingAs($user)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee(route('aprobaciones.index'), false)
            ->assertSee('active:scale-[0.98]', false);
    }

    /**
     * EL bug que motivó urlDestinoPara(): las notificaciones de cotización se
     * despachan a ROLES_AVISO (tecnico/jefe_ventas/vendedor/admin), así que un
     * vendedor recibía la de un cliente de OTRA cartera y al tocarla caía en 403
     * (ServicioTecnicoController::show → OrdenServicio::esVisiblePara).
     *
     * Es la rama con más lógica (canAny + instanceof + esVisiblePara) y el gate
     * R-31 probó que sin estos dos tests se puede borrar el guard completo y la
     * suite queda verde.
     */
    private function notifDeCotizacion(\App\Models\User $user, string $rutCliente): \App\Models\Notificacion
    {
        $orden = \App\Models\OrdenServicio::factory()->create(['cliente_rut' => $rutCliente]);

        return \App\Models\Notificacion::create([
            'evento' => 'cotizacion.enviada', 'user_id' => $user->id,
            'canal' => \App\Models\Notificacion::CANAL_DATABASE,
            'titulo' => 'Cotización enviada', 'cuerpo' => 'C',
            'estado' => \App\Models\Notificacion::ENVIADA,
            'notificable_type' => $orden->getMorphClass(), 'notificable_id' => $orden->id,
        ]);
    }

    public function test_la_cotizacion_de_su_cartera_si_enlaza(): void
    {
        $vendedor = tap(\App\Models\User::factory()->create())->assignRole('vendedor');
        \App\Models\Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => $vendedor->id]);
        $notif = $this->notifDeCotizacion($vendedor, '11111111-1');

        $this->assertSame(
            route('admin.servicio-tecnico.show', $notif->notificable_id),
            $notif->urlDestinoPara($vendedor),
        );

        $this->actingAs($vendedor)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee(route('admin.servicio-tecnico.show', $notif->notificable_id), false);
    }

    public function test_la_cotizacion_de_OTRA_cartera_no_enlaza(): void
    {
        $vendedor = tap(\App\Models\User::factory()->create())->assignRole('vendedor');
        \App\Models\Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => $vendedor->id]);
        // Cliente de otro vendedor: la orden NO es de su cartera.
        $otro = tap(\App\Models\User::factory()->create())->assignRole('vendedor');
        \App\Models\Cliente::factory()->create(['rut' => '99999999-9', 'vendedor_id' => $otro->id]);
        $notif = $this->notifDeCotizacion($vendedor, '99999999-9');

        $this->assertNull($notif->urlDestinoPara($vendedor));
        // El mapeo crudo sigue apuntando ahi: lo que corta es el scope de cartera.
        $this->assertNotNull($notif->urlDestino());

        $this->actingAs($vendedor)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee('Cotización enviada')
            ->assertDontSee(route('admin.servicio-tecnico.show', $notif->notificable_id), false);
    }

    public function test_sin_permiso_de_servicio_tecnico_la_cotizacion_no_enlaza(): void
    {
        // Un soplador no tiene view/manage servicio tecnico: ni con cartera.
        $soplador = tap(\App\Models\User::factory()->create())->assignRole('soplador');
        $notif = $this->notifDeCotizacion($soplador, '11111111-1');

        $this->assertNull($notif->urlDestinoPara($soplador));
    }

    public function test_la_fila_no_enlaza_si_el_usuario_no_puede_llegar(): void
    {
        // Sin 'aprobar solicitudes', la bandeja de aprobaciones le daria 403:
        // la fila se muestra pero NO es un link (ni href ni estilo de enlazada).
        $user = tap(User::factory()->create())->assignRole('soplador');
        $notif = $this->notifDe($user, 'aprobacion.solicitada');

        $this->assertNull($notif->urlDestinoPara($user));
        $this->assertSame(route('aprobaciones.index'), $notif->urlDestino()); // el mapeo sigue intacto

        $this->actingAs($user)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee($notif->titulo)
            ->assertDontSee(route('aprobaciones.index'), false)
            ->assertDontSee('active:scale-[0.98]', false);
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

        // Por marcador, no string pegada (los textos exactos viven en el seed;
        // lote NOTIF-1 movió la magnitud al título y la url al payload).
        $this->assertStringContainsString('Aprobación pendiente: '.$aprobacion->descripcion, $notif->titulo);
        $this->assertStringContainsString('100', $notif->titulo);              // magnitud
        $this->assertStringContainsString('Conteo corregido', $notif->cuerpo); // motivo
        // La url del payload (el botón del correo) llega ANCLADA a la tarjeta,
        // igual que la fila de la campanita (deuda de NOTIF-1 cerrada el 28-07).
        $this->assertSame(
            route('aprobaciones.index').'#aprobacion-'.$aprobacion->id,
            $notif->payload['url'],
        );
    }

    public function test_la_resolucion_distingue_el_titulo_por_resultado(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');

        [, , $paraAprobar] = $this->pendienteReal();
        app(Aprobaciones::class)->aprobar($paraAprobar, $admin);
        $notif = Notificacion::where('evento', 'aprobacion.resuelta')->latest('id')->firstOrFail();
        // El marcador es el PREFIJO con el resultado (lote NOTIF-1 sumó
        // «— {magnitud}» al final y movió la url al payload).
        $this->assertStringContainsString('Aprobada: '.$paraAprobar->descripcion, $notif->titulo);
        $this->assertSame(
            route('aprobaciones.mias').'#aprobacion-'.$paraAprobar->id,
            $notif->payload['url'],
        );

        [, , $paraRechazar] = $this->pendienteReal();
        app(Aprobaciones::class)->rechazar($paraRechazar, $admin, 'Los datos no cuadran');
        $notif = Notificacion::where('evento', 'aprobacion.resuelta')->latest('id')->firstOrFail();
        $this->assertStringContainsString('Rechazada: '.$paraRechazar->descripcion, $notif->titulo);
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
