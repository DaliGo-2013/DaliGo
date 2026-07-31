<?php

namespace Tests\Feature\Admin;

use App\Mail\AgendaTrabajoAviso;
use App\Mail\DetalleTrabajoCliente;
use App\Models\AgendaTrabajo;
use App\Models\Configuracion;
use App\Models\LoteServicio;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de los arreglos de la auditoría de la ruta completa de taller
 * (30-07-2026): QR → recepción → parte del técnico → cotización → correos →
 * aprobación.
 *
 * Cada test fija un hallazgo concreto para que no vuelva. Los agrupa un archivo
 * propio —en vez de repartirlos— porque salieron todos del mismo barrido y
 * conviene poder leerlos juntos.
 */
class AuditoriaRutaTallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private const MIGRACION = 'migrations/2026_07_30_100000_limpia_plantillas_taller_terreno_auditoria.php';

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    /**
     * Orden que entró por QR: sin producto del catálogo y con el `modelo` escrito por
     * el cliente. Lleva correo porque es obligatorio para clientes externos (la
     * factory lo deja en null).
     */
    private function ordenQr(array $extra = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'fuente' => 'qr',
            'confirmada_at' => null,
            'producto_id' => null,
            'modelo' => 'Lavadora azul marca Dali, tapa trizada',
            'tipo_equipo' => 'lavadora',
            'facturacion' => 'reparacion',
            'cliente_email' => 'cliente@ejemplo.cl',
        ], $extra));
    }

    // --- Datos del cliente que se perdían de vista ---

    public function test_la_ficha_muestra_el_equipo_que_escribio_el_cliente(): void
    {
        $orden = $this->ordenQr();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('Equipo según el cliente')
            ->assertSee('Lavadora azul marca Dali, tapa trizada');
    }

    public function test_el_parte_del_tecnico_y_la_cotizacion_muestran_el_equipo_del_cliente(): void
    {
        // El técnico trabajaba sin ver lo que el dueño de la máquina declaró.
        $orden = $this->ordenQr(['mano_obra' => 10000]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()->assertSee('Lavadora azul marca Dali, tapa trizada');

        $this->actingAs($admin)->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()->assertSee('Lavadora azul marca Dali, tapa trizada');
    }

    public function test_la_ficha_avisa_cuando_falta_el_correo_del_cliente(): void
    {
        // Sin correo la cotización se bloquea, así que la falta tiene que verse en la
        // ficha y no recién al intentar cotizar.
        $orden = $this->ordenQr(['cliente_email' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('sin correo no se puede cotizar');
    }

    public function test_la_ficha_muestra_el_correo_cuando_existe(): void
    {
        $orden = $this->ordenQr(['cliente_email' => 'cliente@ejemplo.cl']);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('cliente@ejemplo.cl');
    }

    public function test_el_retiro_en_ruta_muestra_conductor_y_ciudad_de_origen(): void
    {
        $sucursal = Sucursal::factory()->create();
        $lote = LoteServicio::create([
            'cliente_nombre' => 'Aguas del Valle',
            'sucursal_id' => $sucursal->id,
            'origen_ciudad' => 'Curicó',
            'conductor_nombre' => 'Ariel Hernández',
            'tipo_default' => 'dispensador',
            'facturacion_default' => 'reparacion',
            'fecha_ingreso' => now()->toDateString(),
            'total_ordenes' => 3,
            'lote_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $orden = $this->ordenQr(['fuente' => OrdenServicio::FUENTE_RUTA, 'lote_id' => $lote->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('Retiro en ruta')
            ->assertSee('Ariel Hernández')
            ->assertSee('Curicó');
    }

    public function test_una_maquina_traida_por_el_conductor_no_se_anuncia_como_enviada_por_el_cliente(): void
    {
        $orden = $this->ordenQr(['fuente' => OrdenServicio::FUENTE_RUTA]);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('Retiro en ruta — por confirmar')
            ->assertSee('La trajo el conductor desde la ruta')
            ->assertDontSee('El cliente lo envió desde su celular');
    }

    // --- El re-tipeo al editar una orden que entró sin producto ---

    public function test_editar_una_orden_qr_no_obliga_a_clasificar_el_equipo(): void
    {
        // Antes: para corregir un teléfono había que además buscar el producto en el
        // catálogo, porque producto_id era required al editar y las órdenes QR nacen
        // sin producto.
        $orden = $this->ordenQr(['cliente_telefono' => '+56 9 1111 1111']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.update', $orden), [
                'cliente_nombre' => $orden->cliente_nombre,
                'cliente_rut' => $orden->cliente_rut,
                'cliente_email' => $orden->cliente_email,
                'cliente_telefono' => '+56 9 2222 2222',
                'sucursal_id' => $orden->sucursal_id ?: Sucursal::factory()->create()->id,
                'fecha_ingreso' => $orden->fecha_ingreso->toDateString(),
                'tipo_equipo' => $orden->tipo_equipo,
                'numero_serie' => $orden->numero_serie ?: 'SN-12345',
                'falla_reportada' => $orden->falla_reportada ?: 'No enfría',
                'estado' => $orden->estado,
                'facturacion' => 'reparacion',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('+56 9 2222 2222', $orden->fresh()->cliente_telefono);
        $this->assertNull($orden->fresh()->producto_id, 'Sigue sin producto: no se obligó a inventarlo.');
    }

    public function test_una_orden_que_ya_tenia_producto_no_puede_perderlo(): void
    {
        $producto = Producto::factory()->create();
        $orden = $this->ordenQr(['producto_id' => $producto->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.update', $orden), [
                'cliente_nombre' => $orden->cliente_nombre,
                'cliente_rut' => $orden->cliente_rut,
                'cliente_email' => $orden->cliente_email,
                'sucursal_id' => $orden->sucursal_id ?: Sucursal::factory()->create()->id,
                'fecha_ingreso' => $orden->fecha_ingreso->toDateString(),
                'tipo_equipo' => $orden->tipo_equipo,
                'numero_serie' => $orden->numero_serie ?: 'SN-12345',
                'falla_reportada' => 'No enfría',
                'estado' => $orden->estado,
                'facturacion' => 'reparacion',
                // producto_id ausente a propósito
            ])
            ->assertSessionHasErrors('producto_id');
    }

    // --- Correos al cliente ---

    public function test_los_correos_no_prometen_un_telefono_que_no_traen(): void
    {
        $orden = $this->ordenQr(['mano_obra' => 10000]);

        $html = (new DetalleTrabajoCliente($orden))->render();

        // Decía «responde este correo o llámanos» sin número en ninguna parte.
        $this->assertStringNotContainsString('llámanos', $html);
        $this->assertStringContainsString('responde este correo', $html);
    }

    public function test_el_correo_de_garantia_tutea_como_el_resto_del_flujo(): void
    {
        $orden = $this->ordenQr();
        $mail = new DetalleTrabajoCliente($orden);

        $this->assertStringContainsString('Detalle de tu servicio', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('Detalle de tu servicio', $html);
        // El cuerpo estaba en usted y el pie en tú, en el mismo correo.
        $this->assertStringNotContainsString('Revisamos su ', $html);
        $this->assertStringNotContainsString('le informamos', $html);
    }

    public function test_una_solicitud_sin_fecha_no_se_anuncia_como_visita_cancelada(): void
    {
        // Rechazar una solicitud que nunca llegó a agendarse mandaba al cliente a
        // buscar una cita que no existió.
        $sinFecha = AgendaTrabajo::factory()->create(['fecha' => null, 'estado' => 'solicitado']);
        $conFecha = AgendaTrabajo::factory()->create(['fecha' => now()->addWeek()->toDateString()]);

        $this->assertStringContainsString('Sobre tu solicitud', (new AgendaTrabajoAviso($sinFecha, 'anulada'))->envelope()->subject);
        $this->assertStringContainsString('No podremos realizar tu servicio', (new AgendaTrabajoAviso($sinFecha, 'anulada'))->render());

        $this->assertStringContainsString('Visita cancelada', (new AgendaTrabajoAviso($conFecha, 'anulada'))->envelope()->subject);
    }

    public function test_el_horario_del_correo_de_terreno_no_dice_a_las_dentro_de_la_tabla(): void
    {
        $trabajo = AgendaTrabajo::factory()->create([
            'fecha' => now()->addWeek()->toDateString(),
            'hora' => '10:30',
            'hora_fin' => null,
        ]);

        $html = (new AgendaTrabajoAviso($trabajo, 'agendada'))->render();

        // Quedaba «Horario: a las 10:30 hrs».
        $this->assertStringNotContainsString('a las 10:30', $html);
        $this->assertStringContainsString('10:30 hrs', $html);
    }

    // --- Notificaciones internas ---

    public function test_el_aviso_de_ingreso_lleva_el_folio_y_apunta_a_la_ficha(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        tap(User::factory()->create())->assignRole('tecnico');
        $orden = $this->ordenQr();

        $orden->notificarIngresoInterno();

        $notificacion = Notificacion::where('evento', 'taller.ingresado')->firstOrFail();
        $this->assertStringContainsString($orden->folio, $notificacion->cuerpo, 'El folio es con lo que se busca la orden.');
        // La superficie para confirmar es la ficha, no el listado.
        $this->assertSame(route('admin.servicio-tecnico.show', $orden->id), $notificacion->urlDestino());
    }

    public function test_el_aviso_de_ingreso_no_le_ordena_confirmar_a_quien_no_puede(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        tap(User::factory()->create())->assignRole('vendedor');
        $orden = $this->ordenQr();

        $orden->notificarIngresoInterno();

        $cuerpo = Notificacion::where('evento', 'taller.ingresado')->firstOrFail()->cuerpo;
        // El vendedor no tiene 'confirmar servicio tecnico': el imperativo era ruido.
        $this->assertStringNotContainsString('confírmalo', $cuerpo);
        $this->assertStringContainsString('Falta confirmar la recepción', $cuerpo);
    }

    public function test_ningun_aviso_interno_del_taller_repite_el_enlace_en_el_cuerpo(): void
    {
        // El correo de notificación ya pinta el botón «Abrir en DaliGo» con la misma
        // URL: imprimirla en el cuerpo la mostraba dos veces.
        $this->seed(ConfiguracionSeeder::class);

        $claves = [
            'notif_plantilla_taller_ingresado',
            'notif_plantilla_cotizacion_enviada',
            'notif_plantilla_cotizacion_respondida',
            'notif_plantilla_cotizacion_autorizada',
            'notif_plantilla_terreno_solicitada',
            'notif_plantilla_terreno_confirmada',
            'notif_plantilla_terreno_rechazada',
        ];

        foreach ($claves as $clave) {
            $plantilla = Configuracion::get($clave);
            $this->assertIsArray($plantilla, "Falta la plantilla {$clave}.");
            $this->assertStringNotContainsString('{url}', (string) $plantilla['cuerpo'],
                "La plantilla {$clave} vuelve a imprimir la URL en el cuerpo.");
        }
    }

    public function test_el_aviso_de_autorizacion_no_atribuye_la_decision_a_ventas_en_duro(): void
    {
        // 'autorizar reparacion' lo tienen también el técnico y el vendedor.
        $this->seed(ConfiguracionSeeder::class);
        $cuerpo = (string) Configuracion::get('notif_plantilla_cotizacion_autorizada')['cuerpo'];

        $this->assertStringNotContainsString('Ventas autorizó', $cuerpo);
        $this->assertStringContainsString('{autorizada_por} autorizó', $cuerpo);
        // Y no tutea al técnico en un aviso que va a cuatro roles.
        $this->assertStringNotContainsString('Técnico: puedes proceder', $cuerpo);
    }

    public function test_el_aviso_de_rechazo_dice_si_al_cliente_se_le_aviso_de_verdad(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        tap(User::factory()->create())->assignRole('jefe_ventas');
        $trabajo = AgendaTrabajo::factory()->create(['estado' => 'cancelado', 'motivo_cancelacion' => 'Fuera de zona']);

        // Caso "no se pudo avisar": el aviso interno tiene que decirlo para que alguien
        // llame al cliente.
        $trabajo->avisarRechazoInterno('Héctor Martínez', avisadoAlCliente: false);
        $cuerpo = Notificacion::where('evento', 'terreno.rechazada')->latest('id')->firstOrFail()->cuerpo;

        $this->assertStringContainsString('NO se pudo avisar al cliente', $cuerpo);
        $this->assertStringNotContainsString('Se le avisó al cliente por correo.', $cuerpo);

        Notificacion::query()->delete();

        $trabajo->avisarRechazoInterno('Héctor Martínez', avisadoAlCliente: true);
        $this->assertStringContainsString(
            'Se le avisó al cliente por correo.',
            Notificacion::where('evento', 'terreno.rechazada')->latest('id')->firstOrFail()->cuerpo,
        );
    }

    public function test_la_migracion_de_plantillas_respeta_las_editadas_a_mano(): void
    {
        // Texto vigente en una BD ya migrada (el 'new' de la migración de 22-07).
        Configuracion::create([
            'clave' => 'notif_plantilla_cotizacion_enviada',
            'valor' => json_encode([
                'asunto' => 'Cotización enviada — Orden {folio} ({cliente})',
                'cuerpo' => "Se envió la cotización de la orden {folio} a {cliente} por {total}.\nEquipo: {equipo}\nEnviada por: {enviada_por}.\n\nVer la orden: {url}",
            ], JSON_UNESCAPED_UNICODE),
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => 'notificaciones',
            'descripcion' => 'x',
        ]);
        $editada = ['asunto' => 'Mi asunto', 'cuerpo' => 'Mi cuerpo editado desde la UI'];
        Configuracion::create([
            'clave' => 'notif_plantilla_terreno_confirmada',
            'valor' => json_encode($editada, JSON_UNESCAPED_UNICODE),
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => 'notificaciones',
            'descripcion' => 'x',
        ]);

        (require database_path(self::MIGRACION))->up();

        $this->assertStringNotContainsString('{url}', Configuracion::get('notif_plantilla_cotizacion_enviada')['cuerpo']);
        $this->assertSame($editada, Configuracion::get('notif_plantilla_terreno_confirmada'), 'La editada a mano no se toca.');
    }
}
