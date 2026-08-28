<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Cliente;
use App\Models\Instalacion;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Los ingresos de Servicio Técnico que NO avisaban a nadie (dueño 28-08-2026: «a
 * Héctor deben llegar todas las notificaciones de servicio técnico, sean
 * dispensadores o visitas técnicas, mantenciones o instalaciones»).
 *
 * El defecto que originó el lote: el jefe de ventas no recibió el aviso de una
 * máquina que su vendedor ingresó desde el mostrador. Y no era latencia — el
 * canal `database` se escribe ENVIADA en el acto (NotificacionDispatcher se
 * saltea la cola justo para eso), así que «Sin notificaciones nuevas» significa
 * que la fila nunca existió. `notificarIngresoInterno()` solo lo llamaba el
 * flujo PÚBLICO del QR: el mostrador y el lote en ruta creaban la orden y no
 * avisaban.
 *
 * Estos candados fijan las TRES puertas de entrada al taller y el registro de
 * instalaciones. Los avisos de terreno (solicitada/realizado/agendado…) tienen
 * sus propios archivos.
 */
class AvisosDeServicioTecnicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Mail::fake();
    }

    private function conRol(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    /** Filas de campanita de un usuario para un evento (lo que ve al tocar la campana). */
    private function campanita(User $user, string $evento)
    {
        return Notificacion::where('user_id', $user->id)
            ->where('evento', $evento)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->get();
    }

    /**
     * Payload del formulario del mostrador. Se construye contra el catálogo real
     * porque `producto_id` es obligatorio al crear.
     */
    private function ingresoDeMostrador(array $overrides = []): array
    {
        $sucursal = Sucursal::first() ?? Sucursal::factory()->create();
        $producto = Producto::factory()->create();

        return array_merge([
            'cliente_nombre' => 'AACUA SANAVITA E.I.R.L',
            'cliente_rut' => '77267378-7',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'cliente@example.com',
            'producto_id' => $producto->id,
            'sucursal_id' => $sucursal->id,
            'fecha_ingreso' => now()->toDateString(),
            'tipo_equipo' => 'dispensador',
            'modelo' => 'LB-16 WHITE',
            'numero_serie' => 'Est2018908070',
            'falla_reportada' => 'No enfría',
            'facturacion' => 'reparacion',
        ], $overrides);
    }

    // --- El ingreso desde el MOSTRADOR (el defecto reportado) ---

    public function test_el_ingreso_desde_el_mostrador_le_avisa_al_jefe_de_ventas(): void
    {
        $jefe = $this->conRol('jefe_ventas');
        // Quien atiende el mostrador: el rol necesita 'manage servicio tecnico'
        // (la ruta lo exige). El vendedor entra acá como DESTINATARIO.
        $mostrador = $this->conRol('tecnico');
        $vendedor = $this->conRol('vendedor');

        $this->actingAs($mostrador)
            ->post(route('admin.servicio-tecnico.store'), $this->ingresoDeMostrador())
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertCount(1, $this->campanita($vendedor, 'taller.ingresado'));

        $avisos = $this->campanita($jefe, 'taller.ingresado');
        $this->assertCount(1, $avisos, 'El jefe de ventas no se enteró de la máquina que ingresó su vendedor.');

        // La campanita muestra la fila AL TIRO: nace ENVIADA, sin pasar por la cola
        // de la grilla */15 (eso es solo para el correo). Si esto fuera 'pendiente',
        // el aviso tardaría hasta 15 minutos y el reporte «no llegó» volvería.
        $this->assertSame(Notificacion::ENVIADA, $avisos->first()->estado);
        $this->assertNotNull($avisos->first()->enviada_at);
    }

    public function test_el_aviso_del_mostrador_lleva_el_folio_y_aterriza_en_la_ficha(): void
    {
        $jefe = $this->conRol('jefe_ventas');

        $this->actingAs($this->conRol('tecnico'))
            ->post(route('admin.servicio-tecnico.store'), $this->ingresoDeMostrador());

        $orden = OrdenServicio::firstOrFail();
        $aviso = $this->campanita($jefe, 'taller.ingresado')->firstOrFail();

        // El folio es con lo que se busca la orden; sin él hay que buscar por nombre.
        $this->assertStringContainsString($orden->folio, $aviso->cuerpo);
        $this->assertSame(route('admin.servicio-tecnico.show', $orden->id), $aviso->urlDestino());
        // El destino tiene que ser ALCANZABLE para él, o la campanita enlaza a un 403.
        $this->assertNotNull($aviso->urlDestinoPara($jefe));
    }

    public function test_quien_registra_en_el_mostrador_no_se_autonotifica(): void
    {
        // Quien acaba de recibir la máquina no necesita que le avisen de su propia
        // acción (mismo criterio que el resto del módulo). Se usa un técnico como
        // actor y otro como testigo: los dos están en ROLES_AVISO_INGRESO, así que
        // si el reject no existiera el primero tendría su fila igual.
        $mostrador = $this->conRol('tecnico');
        $otroTecnico = $this->conRol('tecnico');

        $this->actingAs($mostrador)
            ->post(route('admin.servicio-tecnico.store'), $this->ingresoDeMostrador());

        $this->assertCount(0, $this->campanita($mostrador, 'taller.ingresado'));
        $this->assertCount(1, $this->campanita($otroTecnico, 'taller.ingresado'));
    }

    /**
     * El texto del aviso NO puede mandar a confirmar una recepción que ya ocurrió.
     *
     * `por_confirmar` es false para fuente 'mostrador' (FUENTES_POR_CONFIRMAR solo
     * trae 'qr' y 'ruta'), así que ahí no hay botón de confirmar: el «Falta
     * confirmar la recepción» que traía la plantilla fija era una instrucción
     * imposible, y eso hace desconfiar del resto del aviso.
     */
    public function test_el_aviso_del_mostrador_no_manda_a_confirmar_lo_ya_recibido(): void
    {
        $jefe = $this->conRol('jefe_ventas');
        $mostrador = $this->conRol('tecnico');

        $this->actingAs($mostrador)
            ->post(route('admin.servicio-tecnico.store'), $this->ingresoDeMostrador());

        $cuerpo = $this->campanita($jefe, 'taller.ingresado')->firstOrFail()->cuerpo;

        $this->assertStringNotContainsString('Falta confirmar', $cuerpo);
        $this->assertStringContainsString('mostrador', $cuerpo);
        // Y dice QUIÉN la recibió: es el dato que ventas necesita para preguntar.
        $this->assertStringContainsString($mostrador->name, $cuerpo);
        // Ningún placeholder crudo (un {x} sin dato queda literal en la campanita).
        $this->assertStringNotContainsString('{', $cuerpo);
    }

    /**
     * El caso simétrico, y la razón de que la frase se calcule en vez de fijarse:
     * por QR la máquina llega DESPUÉS, así que ahí sí falta confirmarla.
     */
    public function test_por_qr_el_aviso_sigue_pidiendo_confirmar_la_recepcion(): void
    {
        $jefe = $this->conRol('jefe_ventas');

        $orden = OrdenServicio::factory()->create([
            'fuente' => 'qr',
            'confirmada_at' => null,
            'facturacion' => 'reparacion',
        ]);
        $orden->notificarIngresoInterno();

        $cuerpo = $this->campanita($jefe, 'taller.ingresado')->firstOrFail()->cuerpo;

        $this->assertStringContainsString('Falta confirmar la recepción.', $cuerpo);
        $this->assertStringNotContainsString('mostrador', $cuerpo);
    }

    // --- El lote en ruta (la tercera puerta) ---

    public function test_el_lote_registrado_por_el_conductor_avisa_una_sola_vez(): void
    {
        // El selector de conductores sale de su seeder, y cada máquina exige foto:
        // mismo payload que LoteServicioTest, que es el que describe el formulario real.
        $this->seed(\Database\Seeders\ConductoresSeeder::class);
        Storage::fake('local');

        $jefe = $this->conRol('jefe_ventas');
        $conductor = $this->conRol('conductor');
        $sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'MIRADOR'],
            ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true],
        );
        $producto = Producto::firstOrCreate(
            ['sku' => '1030034'],
            ['nombre' => 'Equipo 1030034', 'categoria' => 'AGUA DISP. SOBREMESA COMPRESOR'],
        );

        $this->actingAs($conductor)->post(route('admin.servicio-tecnico.lote.store'), [
            'cliente_nombre' => 'AACUA SANAVITA E.I.R.L',
            'cliente_rut' => '77.267.378-7',
            'cliente_email' => 'cliente@example.com',
            'cliente_telefono' => '+56 9 1234 5678',
            'origen_ciudad' => 'Los Andes',
            'conductor' => 'Ariel Hernández',
            'sucursal_id' => $sucursal->id,
            'fecha_ingreso' => now()->toDateString(),
            'tipo_default' => 'dispensador',
            'facturacion_default' => 'reparacion',
            'falla_default' => 'No enfría',
            'maquinas' => [
                ['producto_id' => $producto->id, 'modelo' => 'LB-16', 'numero_serie' => 'SN-0001',
                    'foto' => UploadedFile::fake()->image('equipo1.jpg', 400, 300)],
                ['producto_id' => $producto->id, 'modelo' => 'LB-16', 'numero_serie' => 'SN-0002',
                    'foto' => UploadedFile::fake()->image('equipo2.jpg', 400, 300)],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        // UN aviso por LOTE, no uno por máquina: son 2 órdenes y 1 sola fila.
        $this->assertSame(2, OrdenServicio::count());
        $avisos = $this->campanita($jefe, 'taller.ingresado');
        $this->assertCount(1, $avisos, 'El lote del conductor no avisó a ventas.');
        // Un lote SIEMPRE espera confirmación: las máquinas llegan físicamente después.
        $this->assertStringContainsString('Falta confirmar la recepción.', $avisos->first()->cuerpo);
        $this->assertStringContainsString('2 equipos', $avisos->first()->cuerpo);
    }

    // --- Instalaciones (el módulo no emitía NADA) ---

    public function test_registrar_una_instalacion_le_avisa_a_jefatura(): void
    {
        $jefe = $this->conRol('jefe_ventas');
        $tecnico = $this->conRol('tecnico_industrial');

        $this->actingAs($tecnico)->post(route('admin.instalaciones.store'), [
            'fecha' => now()->toDateString(),
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '77267378-7',
            'comuna_region' => 'La Serena, Coquimbo',
            'categoria' => 'planta',
            'producto' => 'Planta de osmosis 500 L/h',
            'instalacion' => '1',
            'puesta_en_marcha' => '1',
            'dias' => 3,
            'vendedor' => 'Héctor Martínez',
        ])->assertRedirect(route('admin.instalaciones.index'));

        $avisos = $this->campanita($jefe, 'instalacion.registrada');
        $this->assertCount(1, $avisos, 'Instalaciones no avisó a jefatura.');

        $aviso = $avisos->first();
        $cuerpo = $aviso->cuerpo;
        $this->assertStringContainsString('Aguas Claras SpA', $cuerpo);
        $this->assertStringContainsString($tecnico->name, $cuerpo);
        // Los dos datos por los que jefatura pregunta después.
        $this->assertStringContainsString('3', $cuerpo);          // días trabajados
        $this->assertStringContainsString('Héctor Martínez', $cuerpo);
        $this->assertStringNotContainsString('{', $cuerpo);

        // Aterriza en la fila editable (el recurso no tiene `show`) y él puede abrirla.
        $instalacion = Instalacion::firstOrFail();
        $this->assertSame(route('admin.instalaciones.edit', $instalacion->id), $aviso->urlDestino());
        $this->assertNotNull($aviso->urlDestinoPara($jefe));
    }

    public function test_el_tecnico_que_registra_la_instalacion_no_se_autonotifica(): void
    {
        // Es quien la registra: avisarle de su propia acción es ruido. Se usa un
        // jefe_ventas como actor porque el técnico industrial no está en ROLES_AVISO
        // (si no, el test pasaría por vacío y no probaría el reject).
        $jefe = $this->conRol('jefe_ventas');
        $otroJefe = $this->conRol('jefe_ventas');

        $this->actingAs($jefe)->post(route('admin.instalaciones.store'), [
            'fecha' => now()->toDateString(),
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '77267378-7',
            'comuna_region' => 'La Serena, Coquimbo',
            'categoria' => 'lavadora',
            'producto' => 'Lavadora 3 pistas',
            'dias' => 1,
            'vendedor' => 'Carolina Medina',
        ])->assertRedirect(route('admin.instalaciones.index'));

        $this->assertCount(0, $this->campanita($jefe, 'instalacion.registrada'));
        $this->assertCount(1, $this->campanita($otroJefe, 'instalacion.registrada'));
    }

    // --- Cobertura: ningún evento del taller queda sin destino alcanzable ---

    /**
     * Todo evento nuevo tiene que traer su `urlDestino()` Y su gate en
     * `urlDestinoPara()`. Sin las dos mitades la fila aparece en la campanita y no
     * se puede tocar (`default => false` la deja muda), que es el defecto que
     * `urlDestinoPara` vino a cerrar.
     */
    public function test_el_evento_de_instalaciones_tiene_destino_y_gate(): void
    {
        $this->assertArrayHasKey('instalacion.registrada', Notificacion::EVENTOS);

        $instalacion = Instalacion::factory()->create();
        $notificacion = new Notificacion([
            'evento' => 'instalacion.registrada',
            'canal' => Notificacion::CANAL_DATABASE,
        ]);
        $notificacion->notificable_type = $instalacion->getMorphClass();
        $notificacion->notificable_id = $instalacion->getKey();

        $this->assertSame(
            route('admin.instalaciones.edit', $instalacion->id),
            $notificacion->urlDestino(),
        );

        // Con el permiso llega; sin el permiso la fila queda sin enlace (no en 403).
        $this->assertNotNull($notificacion->urlDestinoPara($this->conRol('jefe_ventas')));
        $this->assertNull($notificacion->urlDestinoPara($this->conRol('soplador')));
    }
}
