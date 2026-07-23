<?php

namespace Tests\Feature\Notificaciones;

use App\Mail\NotificacionMail;
use App\Models\Aprobacion;
use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use App\Services\Aprobaciones\Aprobaciones;
use App\Services\Notificaciones\NotificacionDispatcher;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Lote NOTIF-1 (dictado v24, directiva del dueño 22-07): notificaciones
 * ESPECÍFICAS. La campanita gana cuerpo y navegación, el aterrizaje de
 * aprobaciones es puntual (#aprobacion-{id}), el payload lleva objeto/cambio,
 * el fallback sin plantilla degrada a legible (no a mudo) y los textos nuevos
 * LLEGAN a prod vía migración one-shot que respeta ediciones manuales.
 */
class NotificacionEspecificaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_07_22_100000_actualiza_plantillas_aprobacion_notif1.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
    }

    private function notifCon(User $user, array $extra = []): Notificacion
    {
        return Notificacion::create($extra + [
            'evento' => 'aprobacion.solicitada',
            'user_id' => $user->id,
            'canal' => Notificacion::CANAL_DATABASE,
            'titulo' => 'T',
            'cuerpo' => 'C',
            'estado' => Notificacion::ENVIADA,
        ]);
    }

    // --- D.1 · campanita con sustancia y navegación -------------------------

    public function test_la_campanita_muestra_el_cuerpo_de_la_notificacion(): void
    {
        $user = User::factory()->create();
        // Marcador único: en el dashboard SOLO la campanita puede aportarlo
        // (lección verde-engañoso 2026-07-20).
        $this->notifCon($user, ['cuerpo' => "Sobre: Reporte CTX-CAMPANITA-9d\nCambio: Asignadas: 450 → 500"]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('CTX-CAMPANITA-9d')
            // El CABLEADO de la navegación (gate R-31): la campanita es el único
            // emisor de name="ir" en resources/ — sin este assert, quitar el
            // hidden dejaría la fila sin navegar con la suite verde.
            ->assertSee('name="ir"', false);
    }

    public function test_la_fila_de_la_campanita_marca_leida_y_navega_al_destino(): void
    {
        $user = User::factory()->create();
        $n = $this->notifCon($user, ['notificable_id' => 77]);

        $this->actingAs($user)->post(route('notificaciones.leer', $n), ['ir' => 1])
            ->assertRedirect(route('aprobaciones.index').'#aprobacion-77');

        $this->assertSame(Notificacion::LEIDA, $n->fresh()->estado);
    }

    public function test_el_boton_leida_de_la_bandeja_no_navega(): void
    {
        $user = User::factory()->create();
        $n = $this->notifCon($user, ['notificable_id' => 77]);

        // Sin ir=1 (el botón "Leída"): back() a la bandeja, como siempre.
        $this->actingAs($user)->from(route('notificaciones.index'))
            ->post(route('notificaciones.leer', $n))
            ->assertRedirect(route('notificaciones.index'));
    }

    public function test_la_fila_sin_destino_cae_al_comportamiento_actual(): void
    {
        $user = User::factory()->create();
        $n = $this->notifCon($user, ['evento' => 'sistema.prueba']);

        $this->actingAs($user)->from(route('notificaciones.index'))
            ->post(route('notificaciones.leer', $n), ['ir' => 1])
            ->assertRedirect(route('notificaciones.index'));

        $this->assertSame(Notificacion::LEIDA, $n->fresh()->estado);
    }

    // --- D.3 · aterrizaje puntual --------------------------------------------

    public function test_url_destino_de_aprobaciones_ancla_al_item(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            route('aprobaciones.index').'#aprobacion-31',
            $this->notifCon($user, ['notificable_id' => 31])->urlDestino(),
        );
        $this->assertSame(
            route('aprobaciones.mias').'#aprobacion-32',
            $this->notifCon($user, ['evento' => 'aprobacion.resuelta', 'notificable_id' => 32])->urlDestino(),
        );
        // Sin morph (compat con filas antiguas): la lista, sin ancla.
        $this->assertSame(route('aprobaciones.index'), $this->notifCon($user)->urlDestino());
    }

    public function test_la_bandeja_y_mis_solicitudes_emiten_el_ancla(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        [$jefe, , $aprobacion] = $this->pendienteReal();

        $aprobador = tap(User::factory()->create())->assignRole('admin');
        $this->actingAs($aprobador)->get(route('aprobaciones.index'))
            ->assertOk()
            ->assertSee('id="aprobacion-'.$aprobacion->id.'"', false);

        $this->actingAs($jefe)->get(route('aprobaciones.mias'))
            ->assertOk()
            ->assertSee('id="aprobacion-'.$aprobacion->id.'"', false);
    }

    // --- D.4 · fallback nunca-mudo -------------------------------------------

    public function test_sin_plantilla_el_cuerpo_interpola_los_escalares(): void
    {
        // sistema.prueba SIN sembrar plantilla; 'url' y los no-escalares se omiten.
        $creadas = app(NotificacionDispatcher::class)->despachar('sistema.prueba', null, 'x@example.com', [
            'nombre' => 'Ana',
            'detalle' => 'Bomba 3',
            'url' => 'https://daligo.test/x',
            'lista' => ['no', 'escalar'],
        ]);

        $this->assertSame("nombre: Ana\ndetalle: Bomba 3", $creadas->first()->cuerpo);
    }

    // --- B + A · payload con contexto y plantillas nuevas ---------------------

    public function test_la_solicitud_notifica_objeto_y_cambio(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        tap(User::factory()->create())->assignRole('admin');
        [, $reporte, $aprobacion] = $this->pendienteReal();

        $notif = Notificacion::where('evento', 'aprobacion.solicitada')
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();

        // Título específico con magnitud; cuerpo con el objeto y el cambio.
        $this->assertSame('Aprobación pendiente: '.$aprobacion->descripcion.' (100)', $notif->titulo);
        $this->assertStringContainsString('Sobre: Reporte de producción '.$reporte->fecha->format('d-m-Y'), $notif->cuerpo);
        $this->assertStringContainsString('Cambio: Asignadas: 450 → 500', $notif->cuerpo);
        // La URL ya no viaja cruda en el cuerpo; sigue en el payload (correo/fila).
        $this->assertStringNotContainsString('http', $notif->cuerpo);
        $this->assertSame(route('aprobaciones.index'), $notif->payload['url']);
    }

    public function test_la_resolucion_notifica_quien_resolvio_y_defaults_sin_dato(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');
        [, , $aprobacion] = $this->pendienteReal(datos: []); // sin anterior/nuevo

        app(Aprobaciones::class)->aprobar($aprobacion, $admin);
        $notif = Notificacion::where('evento', 'aprobacion.resuelta')->latest('id')->firstOrFail();

        $this->assertSame('Aprobada: '.$aprobacion->descripcion.' — 100', $notif->titulo);
        $this->assertStringContainsString('por '.$admin->name, $notif->cuerpo);
        // Sin anterior/nuevo en datos, el placeholder degrada a '—' (no a {cambio}).
        $this->assertSame('—', $notif->payload['cambio']);
        $this->assertStringNotContainsString('{', $notif->cuerpo);
    }

    // --- Correo: el link rápido sobrevive como botón (hallazgo #8) -----------

    public function test_el_correo_pone_boton_cuando_el_payload_trae_url(): void
    {
        $user = User::factory()->create();

        $conUrl = $this->notifCon($user, ['canal' => Notificacion::CANAL_MAIL, 'payload' => ['url' => route('aprobaciones.index')]]);
        $html = (new NotificacionMail($conUrl))->render();
        $this->assertStringContainsString('Abrir en DaliGo', $html);
        $this->assertStringContainsString(route('aprobaciones.index'), $html);

        $sinUrl = $this->notifCon($user, ['canal' => Notificacion::CANAL_MAIL]);
        $this->assertStringNotContainsString('Abrir en DaliGo', (new NotificacionMail($sinUrl))->render());
    }

    // --- A · entrega a prod: la migración one-shot ----------------------------

    public function test_la_migracion_actualiza_solo_la_plantilla_intacta(): void
    {
        // Fila con el texto del seed ANTERIOR (lo que hay hoy en prod).
        Configuracion::create([
            'clave' => 'notif_plantilla_aprobacion_solicitada',
            'valor' => json_encode([
                'asunto' => 'Aprobación pendiente: {descripcion}',
                'cuerpo' => "{solicitante} pide: {tipo}.\nMotivo: {motivo}\nMagnitud: {magnitud}\n\nResuélvela aquí: {url}",
            ], JSON_UNESCAPED_UNICODE),
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => 'notificaciones',
            'descripcion' => 'x',
        ]);
        // Fila EDITADA a mano desde la UI: la migración debe respetarla.
        $editada = ['asunto' => 'Mi asunto editado', 'cuerpo' => 'Mi cuerpo editado'];
        Configuracion::create([
            'clave' => 'notif_plantilla_aprobacion_resuelta',
            'valor' => json_encode($editada, JSON_UNESCAPED_UNICODE),
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => 'notificaciones',
            'descripcion' => 'x',
        ]);

        $migracion = require database_path(self::MIGRACION);
        $migracion->up();

        // La intacta se actualizó al texto nuevo (y el caché quedó limpio)…
        $solicitada = Configuracion::get('notif_plantilla_aprobacion_solicitada');
        $this->assertSame('Aprobación pendiente: {descripcion} ({magnitud})', $solicitada['asunto']);
        $this->assertStringContainsString('{objeto}', $solicitada['cuerpo']);
        $this->assertStringNotContainsString('{url}', $solicitada['cuerpo']);

        // …y la editada quedó tal cual.
        $this->assertSame($editada, Configuracion::get('notif_plantilla_aprobacion_resuelta'));
    }

    /**
     * Pendiente real 450→500 vía el servicio (mismo arnés que el lote S2).
     *
     * @return array{0: User, 1: ProduccionReporte, 2: Aprobacion}
     */
    private function pendienteReal(?array $datos = null): array
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
            datos: $datos ?? [
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
