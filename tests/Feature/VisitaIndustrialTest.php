<?php

namespace Tests\Feature;

use App\Models\AgendaTrabajo;
use App\Models\Cliente;
use App\Models\Notificacion;
use App\Models\ServicioTerreno;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * LA VISITA / REVISIÓN INDUSTRIAL, DESPUÉS DE QUE DEJÓ DE PEDIRSE POR EL QR (25-08-2026).
 *
 * Nació como una solicitud PÚBLICA: el cliente pedía por el QR que el técnico fuera a su
 * planta, entraba a la Agenda de terreno como 'solicitado' (sin fecha) y el staff la
 * coordinaba. El gerente retiró esa puerta: *«que la coordinación de visita/revisión
 * industrial la saques de la vista de ingreso; estos los harán ahora los vendedores y serán
 * autorizados por el jefe de ventas»*.
 *
 * QUÉ QUEDÓ DE ESTE ARCHIVO Y QUÉ SE FUE. Lo que se probaba acá eran dos cosas mezcladas: el
 * formulario público y la COORDINACIÓN interna de lo que ese formulario dejaba. La segunda es
 * la que sigue viva —y ahora es todo lo que hay—, así que estos tests pasan a crear la
 * solicitud con la factory (`solicitudDelCliente()`), que es exactamente la forma en que
 * siguen existiendo: las que los clientes ya habían pedido antes del cambio.
 *
 * Se retiraron con su flujo, y vale dejar dicho cuáles para que nadie los busque:
 *   - firma del link, honeypot y «gracias» → eran del formulario público;
 *   - «el cliente no elige el tipo» y «un tipo enviado a mano no se respeta» → no hay cliente
 *     que elija; el tipo lo pone el vendedor y el formulario interno ofrece los cuatro;
 *   - «el cliente no ve los valores UF» → esa pantalla no existe;
 *   - «fecha preferida pasada es rechazada» y «no se puede pedir en días ocupados» → NO se
 *     perdieron: se mudaron al camino interno, que ya rechazaba días ocupados
 *     (`bloquearSiOcupado`) y desde hoy también los días que no se atienden
 *     (`bloquearSiNoSeAtiende`). Sus candados viven abajo.
 */
class VisitaIndustrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
    }

    private function vendedor(): User
    {
        return tap(User::factory()->create())->assignRole('vendedor');
    }

    /**
     * Una visita que el cliente pidió y todavía nadie coordinó: 'solicitado', SIN fecha.
     *
     * Reemplaza al POST del formulario público, que se retiró el 25-08. Los datos son los
     * mismos que ese formulario dejaba —por eso los literales no cambiaron— y el estado
     * también: así los tests de coordinación siguen probando exactamente el caso que van a
     * encontrar en producción, que es el de las solicitudes ya ingresadas.
     */
    private function solicitudDelCliente(array $overrides = []): AgendaTrabajo
    {
        return AgendaTrabajo::factory()->create(array_merge([
            'tipo' => AgendaTrabajo::TIPO_PUBLICO,
            'estado' => 'solicitado',
            'fecha' => null,
            'hora' => null,
            'tecnico_id' => null,
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La planta de osmosis 1T pierde presión.',
        ], $overrides));
    }

    /**
     * Un vendedor ANOTA la visita desde el formulario interno, sin fecha («la coordino
     * después»). Es el POST real, así que pasa por lo que el controlador hace de camino:
     * enlazar la ficha del cliente por RUT y avisarle a ventas.
     *
     * @param  array<string, mixed>  $extra
     */
    private function anotarComoVendedor(array $extra = []): TestResponse
    {
        return $this->actingAs($this->vendedor())->post(route('admin.agenda-terreno.store'), array_merge([
            'tipo' => AgendaTrabajo::TIPO_PUBLICO,
            'estado' => 'solicitado',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La planta de osmosis 1T pierde presión.',
        ], $extra));
    }

    /** Un día laborable, para que la fecha no caiga en uno que la agenda rechaza. */
    private function dia(int $dias = 3): string
    {
        $d = Carbon::parse(FechaNegocio::hoy())->addDays($dias);

        while (! AgendaTrabajo::esLaborable($d->toDateString())) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    // --- La puerta pública, cerrada ---

    /**
     * EL CLIENTE YA NO PUEDE PEDIR UNA VISITA INDUSTRIAL (gerente, 25-08-2026).
     *
     * Este candado era el inverso: verificaba que la opción SÍ estuviera en el menú del QR y
     * que su formulario abriera con link firmado. Se invierte, no se borra — es la decisión
     * misma, y sin él nada impide que la tarjeta vuelva de un copy-paste.
     *
     * Se comprueban las DOS mitades, porque una sola no alcanza: que la tarjeta no esté (lo
     * que se ve) y que las rutas no existan (lo que importa). Sacar solo la tarjeta habría
     * dejado a cualquiera con el link guardado creando visitas sin pasar por el vendedor ni
     * por la autorización del jefe.
     */
    public function test_el_chooser_ya_no_ofrece_la_visita_industrial(): void
    {
        $sucursal = $this->sucursal();

        $this->get(URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursal->id]))
            ->assertOk()
            // Las otras tres opciones siguen: esto no toca el ingreso a taller.
            ->assertSee('Ingreso por unidad')
            ->assertSee('Ingreso por cantidad')
            ->assertDontSee('Visita / revisión industrial');

        // Y las rutas se retiraron: un link viejo no puede crear nada.
        foreach (['visita-industrial.create', 'visita-industrial.store', 'visita-industrial.gracias',
            'visita-industrial.disponibilidad'] as $ruta) {
            $this->assertFalse(Route::has($ruta),
                "La ruta pública [{$ruta}] sigue viva: el link guardado saltea al vendedor y al jefe.");
        }
    }

    // --- Anotar una visita para coordinarla después ---

    /**
     * GUARDAR SIN FECHA ES EL CASO NORMAL AHORA, y hasta hoy devolvía un 500.
     *
     * El registro se creaba bien y el redirect reventaba leyendo el año de una fecha que no
     * existe: el vendedor veía una pantalla de error habiendo guardado. No se notaba porque
     * ese camino casi no se usaba —las visitas sin fecha llegaban por el QR y este formulario
     * se abría para ponerles la fecha—. Al retirar el QR, «lo anoto y lo coordino cuando hable
     * con el cliente» pasó a ser la única forma de dejar una visita pendiente.
     */
    public function test_anotar_una_visita_sin_fecha_la_deja_por_coordinar(): void
    {
        $vendedor = $this->vendedor();

        $this->actingAs($vendedor)->post(route('admin.agenda-terreno.store'), [
            'tipo' => AgendaTrabajo::TIPO_PUBLICO,
            'estado' => 'solicitado',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La planta de osmosis 1T pierde presión.',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.agenda-terreno.index'));

        $t = AgendaTrabajo::sole();
        $this->assertSame('solicitado', $t->estado);
        $this->assertNull($t->fecha);
    }

    /**
     * Y AVISA A VENTAS QUE HAY ALGO POR COORDINAR.
     *
     * El aviso existía y lo disparaba el formulario público; ahora lo dispara el interno
     * cuando el trabajo nace sin fecha. El destinatario no cambió y por eso el candado
     * tampoco: quien coordina necesita saber que hay algo esperando día, y eso es igual de
     * cierto lo haya anotado un cliente o un vendedor.
     */
    public function test_anotar_sin_fecha_avisa_a_ventas_por_coordinar(): void
    {
        // Ventas (jefe + vendedor) reciben campanita; el técnico industrial NO
        // (a él le llega el trabajo recién cuando lo fijan en su agenda).
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');
        $servicio = ServicioTerreno::factory()->create(['nombre' => 'Full planta 1T']);

        $this->actingAs($vendedor)->post(route('admin.agenda-terreno.store'), [
            'tipo' => AgendaTrabajo::TIPO_PUBLICO,
            'estado' => 'solicitado',
            'servicio_terreno_id' => $servicio->id,
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La planta de osmosis 1T pierde presión.',
        ])->assertSessionHasNoErrors();

        foreach ([$jefe, $vendedor] as $u) {
            $this->assertSame(1, Notificacion::where('user_id', $u->id)
                ->where('evento', 'terreno.solicitada')
                ->where('canal', Notificacion::CANAL_DATABASE)->count(),
                "Falta la campanita de {$u->name}");
        }
        $this->assertSame(0, Notificacion::where('user_id', $tecnico->id)
            ->where('evento', 'terreno.solicitada')->count());

        // Y el aviso lleva lo que quien coordina necesita para llamar: servicio, dirección y
        // el detalle. Son los campos que el lote del 22-07 agregó a la plantilla.
        $notif = Notificacion::where('user_id', $jefe->id)
            ->where('evento', 'terreno.solicitada')
            ->where('canal', Notificacion::CANAL_DATABASE)->sole();

        $this->assertSame('Full planta 1T', $notif->payload['servicio']);
        $this->assertSame('Camino Industrial 500', $notif->payload['direccion']);
        $this->assertSame('La planta de osmosis 1T pierde presión.', $notif->payload['descripcion']);
    }

    /**
     * EL TEXTO LIBRE SALIÓ DEL FORMULARIO PÚBLICO (dueño, 14-08-2026): «quitar el apartado de
     * cuándo puedes y cuándo no, sino agregar un horario de trabajo… el cliente pincha y elige
     * el horario». El cliente ahora elige una HORA de la lista del día (ver `HorarioVisitaTest`).
     *
     * Pero el campo NO se fue del sistema: quien coordina lo sigue teniendo en el formulario
     * interno para anotar lo que el cliente cuenta por teléfono («solo martes en la mañana»,
     * «avisar antes de llegar»), que no cabe en una hora elegida. Eso es lo que estos dos
     * candados cuidan ahora — y que lo viejo se siga leyendo.
     */
    public function test_el_formulario_interno_conserva_la_disponibilidad_escrita(): void
    {
        $this->actingAs($this->vendedor())
            ->get(route('admin.agenda-terreno.create'))
            ->assertOk()
            ->assertSee('Disponibilidad del cliente');
    }

    public function test_la_agenda_muestra_la_disponibilidad_al_coordinar(): void
    {
        // Se carga en la solicitud como la cargaría quien atiende el teléfono.
        $solicitud = AgendaTrabajo::create([
            'tipo' => AgendaTrabajo::TIPO_PUBLICO,
            'estado' => 'solicitado',
            'cliente_nombre' => 'Aguas Claras SpA',
            'descripcion' => 'La planta pierde presión.',
            'disponibilidad' => 'Solo martes y jueves en la mañana',
        ]);

        $this->assertSame('Solo martes y jueves en la mañana', $solicitud->disponibilidad);

        // Quien coordina (vendedor) la ve en la lista "por coordinar".
        $this->actingAs($this->vendedor())
            ->get(route('admin.agenda-terreno.index'))
            ->assertOk()
            ->assertSee('Solo martes y jueves en la mañana');
    }

    /**
     * LA REGLA DE LOS DÍAS QUE NO SE ATIENDEN, AHORA EN EL FORMULARIO INTERNO.
     *
     * Este candado reemplaza a `test_no_se_puede_pedir_visita_en_dias_ocupados`, que probaba lo
     * mismo contra el formulario público. La regla del dueño (13-08: *«el técnico va a terreno
     * de lunes a viernes»*) se validaba SOLO ahí: el camino interno nunca la tuvo, así que al
     * retirar el público se quedaba sin ningún lugar donde aplicar. Se mudó a
     * `bloquearSiNoSeAtiende` y de paso ahora cubre los cuatro tipos.
     *
     * Dos mitades, porque el bloqueo sin la salida sería un callejón: que RECHACE el sábado, y
     * que el mensaje diga POR QUÉ y cuál es el próximo día con disponibilidad — que es lo que
     * hacía el cartel en vivo del formulario público.
     */
    public function test_el_formulario_interno_rechaza_un_dia_que_no_se_atiende(): void
    {
        $sabado = Carbon::parse($this->dia())->next(Carbon::SATURDAY)->toDateString();

        $res = $this->actingAs($this->vendedor())->post(route('admin.agenda-terreno.store'), [
            'tipo' => 'mantencion',
            'estado' => 'agendado',
            'fecha' => $sabado,
            'hora' => '10:00',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'Mantención de la planta.',
        ]);

        $res->assertSessionHasErrors('fecha');
        $this->assertSame(0, AgendaTrabajo::count(), 'Se guardó una cita en un día sin nadie.');

        $error = session('errors')->getBag('default')->first('fecha');
        $this->assertStringContainsString('lunes a viernes', $error);
        $this->assertStringContainsString('más cercano con disponibilidad', $error);
    }

    /** Y el ADMIN sí puede: misma excepción que con los días ya ocupados. */
    public function test_el_admin_puede_agendar_un_dia_que_no_se_atiende(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');
        $sabado = Carbon::parse($this->dia())->next(Carbon::SATURDAY)->toDateString();

        $this->actingAs($admin)->post(route('admin.agenda-terreno.store'), [
            'tipo' => 'mantencion',
            'estado' => 'agendado',
            'fecha' => $sabado,
            'hora' => '10:00',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'Mantención de la planta.',
        ])->assertSessionHasNoErrors();

        $this->assertSame($sabado, AgendaTrabajo::sole()->fecha->toDateString());
    }

    // --- Coordinación ---

    public function test_la_solicitud_aparece_en_por_coordinar_y_no_en_el_mes(): void
    {
        $this->solicitudDelCliente();

        $res = $this->actingAs($this->vendedor())->get('/admin/agenda-terreno');
        $res->assertOk()
            ->assertSee('Por coordinar (solicitudes del cliente)')
            ->assertSee('Aguas Claras SpA')
            ->assertSee('Coordinar')
            ->assertSee('No se puede'); // botón de rechazo con motivo

        // Sin fecha, no está en ningún día del mes (solo en el bloque).
        $this->assertSame(0, AgendaTrabajo::delMes(now()->year, now()->month)->count());
    }

    public function test_el_tecnico_industrial_no_ve_el_bloque_por_coordinar(): void
    {
        // Coordinar una solicitud = agendarla; por pedido de gerencia el técnico
        // industrial ya no agenda, así que no ve el bloque "Por coordinar".
        $this->solicitudDelCliente();

        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');
        $this->actingAs($tecnico)->get('/admin/agenda-terreno')
            ->assertOk()
            ->assertDontSee('Por coordinar (solicitudes del cliente)');
    }

    public function test_coordinar_pone_fecha_y_la_agenda(): void
    {
        $this->solicitudDelCliente();
        $t = AgendaTrabajo::first();

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), [
                'tipo' => $t->tipo,
                'fecha' => '2026-07-22',
                'estado' => 'agendado',
                'cliente_nombre' => $t->cliente_nombre,
                'cliente_rut' => $t->cliente_rut,
                'cliente_telefono' => $t->cliente_telefono,
                'cliente_email' => $t->cliente_email,
                'direccion' => $t->direccion,
                'ciudad' => $t->ciudad,
                'descripcion' => $t->descripcion,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $t->fresh();
        $this->assertSame('agendado', $fresh->estado);
        $this->assertSame('2026-07-22', $fresh->fecha->toDateString());
        // Ahora sí está en el mes.
        $this->assertSame(1, AgendaTrabajo::delMes(2026, 7)->count());
    }

    public function test_editar_una_solicitud_sin_fecha_no_exige_fecha(): void
    {
        $this->solicitudDelCliente();
        $t = AgendaTrabajo::first();

        // Corregir un dato manteniendo el estado 'solicitado' (sin fecha aún).
        // Los datos de contacto ya vienen de la solicitud pública (obligatorios).
        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), [
                'tipo' => $t->tipo,
                'estado' => 'solicitado',
                'cliente_nombre' => 'Aguas Claras SpA (corregido)',
                'cliente_rut' => $t->cliente_rut,
                'cliente_telefono' => $t->cliente_telefono,
                'cliente_email' => $t->cliente_email,
                'direccion' => $t->direccion,
                'ciudad' => $t->ciudad,
                'descripcion' => $t->descripcion,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Aguas Claras SpA (corregido)', $t->fresh()->cliente_nombre);
        $this->assertNull($t->fresh()->fecha);
    }

    public function test_agendar_interno_sigue_exigiendo_fecha(): void
    {
        // El flujo interno (staff) no puede crear sin fecha.
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', [
                'tipo' => 'mantencion',
                'cliente_nombre' => 'Cliente Interno',
                'descripcion' => 'Trabajo sin fecha',
            ])
            ->assertSessionHasErrors('fecha');
    }

    // La pantalla «¡Listo!» de la visita se retiró con el formulario público: ya no hay envío
    // del cliente que la muestre. Las de los otros dos ingresos —por unidad y por cantidad—
    // conservan su «Volver al inicio» y sus candados en `IngresoTallerPublicoTest`.

    // --- Catálogo de clientes: reconocer / guardar / actualizar ---

    /**
     * El enlace por RUT lo hacía el controlador PÚBLICO al recibir el formulario; ahora lo
     * hace el interno (`sincronizarCatalogo`, que ya estaba en `store`). La regla es la misma
     * y por eso el candado también: si el RUT ya existe en el catálogo, la visita nace
     * enlazada a esa ficha en vez de quedar como un nombre suelto.
     */
    public function test_la_visita_anotada_se_enlaza_a_la_ficha_conocida_por_rut(): void
    {
        $cliente = Cliente::factory()->create(['rut' => '12345678-5']);

        $this->anotarComoVendedor()->assertSessionHasNoErrors();

        $this->assertSame($cliente->id, AgendaTrabajo::sole()->cliente_id);
    }

    public function test_la_visita_anotada_no_se_enlaza_si_el_rut_es_desconocido(): void
    {
        $this->anotarComoVendedor()->assertSessionHasNoErrors();

        $this->assertNull(AgendaTrabajo::sole()->cliente_id);
    }

    public function test_coordinar_una_solicitud_sin_fecha_reconoce_al_cliente(): void
    {
        // Regresión: abrir "Coordinar" (edit) de una solicitud SIN fecha no debe
        // crashear, y muestra el recuadro "Cliente conocido" con los datos guardados.
        Cliente::factory()->create([
            'rut' => '12345678-5', 'razon_social' => 'Aguas Claras SpA', 'telefono' => '+56 2 2555 0000',
        ]);
        $this->solicitudDelCliente();
        $t = AgendaTrabajo::first();

        $this->actingAs($this->vendedor())
            ->get(route('admin.agenda-terreno.edit', $t))
            ->assertOk()
            ->assertSee('Coordinar solicitud')
            ->assertSee('ya está en tu catálogo')
            ->assertSee('Usar datos guardados')
            ->assertSee('+56 2 2555 0000');
    }

    public function test_guardar_en_catalogo_crea_la_ficha_local_y_la_enlaza(): void
    {
        $this->solicitudDelCliente();
        $t = AgendaTrabajo::first();
        $this->assertNull($t->cliente_id); // el RUT no estaba en el catálogo

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), $this->coordinarPayload($t, [
                'guardar_en_catalogo' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $cliente = Cliente::where('rut', '12345678-5')->first();
        $this->assertNotNull($cliente);
        $this->assertSame('Aguas Claras SpA', $cliente->razon_social);
        $this->assertNull($cliente->bsale_client_id);          // ficha LOCAL, no de Bsale
        $this->assertSame($cliente->id, $t->fresh()->cliente_id);
    }

    public function test_actualizar_catalogo_actualiza_una_ficha_local(): void
    {
        $cliente = Cliente::factory()->create([
            'rut' => '12345678-5', 'telefono' => '+56 9 0000 0000', 'bsale_client_id' => null,
        ]);
        $this->solicitudDelCliente();
        $t = AgendaTrabajo::first();

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), $this->coordinarPayload($t, [
                'cliente_telefono' => '+56 9 1111 2222',       // el cliente cambió de número
                'actualizar_catalogo' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('+56 9 1111 2222', $cliente->fresh()->telefono);
    }

    public function test_actualizar_catalogo_no_toca_una_ficha_de_bsale(): void
    {
        // Ficha espejo de Bsale: la sync horaria es su fuente de verdad, así que
        // NO se pisa desde DaliGo (aunque marquen la casilla).
        $cliente = Cliente::factory()->create([
            'rut' => '12345678-5', 'telefono' => '+56 9 0000 0000', 'bsale_client_id' => 777,
        ]);
        $this->solicitudDelCliente();
        $t = AgendaTrabajo::first();

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), $this->coordinarPayload($t, [
                'cliente_telefono' => '+56 9 1111 2222',
                'actualizar_catalogo' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('+56 9 0000 0000', $cliente->fresh()->telefono);   // la ficha no cambia
        $this->assertSame('+56 9 1111 2222', $t->fresh()->cliente_telefono); // pero la visita sí usa el dato nuevo
    }

    /**
     * Payload para coordinar (PUT) una solicitud manteniéndola sin fecha, tomando
     * los datos del trabajo y permitiendo overrides (teléfono nuevo, casillas…).
     */
    private function coordinarPayload(AgendaTrabajo $t, array $overrides = []): array
    {
        return array_merge([
            'tipo' => $t->tipo,
            'estado' => 'solicitado',
            'cliente_nombre' => $t->cliente_nombre,
            'cliente_rut' => $t->cliente_rut,
            'cliente_telefono' => $t->cliente_telefono,
            'cliente_email' => $t->cliente_email,
            'direccion' => $t->direccion,
            'ciudad' => $t->ciudad,
            'descripcion' => $t->descripcion,
        ], $overrides);
    }
}
