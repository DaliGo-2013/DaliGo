<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\Aprobacion;
use App\Models\User;
use App\Services\Aprobaciones\Aprobaciones;
use App\Support\FechaNegocio;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * EL JEFE DE VENTAS AUTORIZA LAS CITAS QUE FIJAN LOS VENDEDORES.
 *
 * Pedido del dueño (13-08-2026): «cuando un vendedor fije una cita con un cliente por
 * mantención, reparación o instalación le tiene que llegar una notificación al jefe de ventas
 * para autorizar eso, que él siempre esté al tanto de lo que hacen sus vendedores». Y su
 * decisión sobre el mientras: **la cita queda en espera**.
 *
 * Se monta sobre el motor de aprobaciones que ya existía (M14): tipo de acción nuevo, handler
 * nuevo y una regla. Nada de un mecanismo paralelo — el jefe la resuelve en la misma bandeja
 * donde ya aprueba otras cosas, con su notificación y su historial.
 *
 * LO QUE MÁS IMPORTA DE ESTOS CANDADOS: que «en espera» signifique de verdad que la agenda NO
 * está tomada. Si una cita sin autorizar bloqueara el día, el jefe estaría autorizando algo que
 * ya ocupó el lugar y el permiso sería decorativo.
 */
class AutorizacionCitaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        Mail::fake();
    }

    private function vendedor(): User
    {
        return tap(User::factory()->create())->assignRole('vendedor');
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_ventas');
    }

    /** Un día laborable, para que la fecha no caiga en un día que la agenda rechaza. */
    private function dia(int $dias = 5): string
    {
        $d = Carbon::parse(FechaNegocio::hoy())->addDays($dias);

        while (! AgendaTrabajo::esLaborable($d->toDateString())) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    /** @param  array<string, mixed>  $extra */
    private function agendar(User $como, array $extra = [])
    {
        return $this->actingAs($como)->post(route('admin.agenda-terreno.store'), $extra + [
            'tipo' => 'mantencion',
            'estado' => 'agendado',
            'fecha' => $this->dia(),
            'hora' => '09:00',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'Mantención de la llenadora.',
        ]);
    }

    // ─────────────────────────────────────────────── el vendedor pide

    public function test_la_cita_de_un_vendedor_queda_esperando_y_no_se_agenda(): void
    {
        $this->agendar($this->vendedor())->assertRedirect();

        $trabajo = AgendaTrabajo::sole();

        $this->assertSame('solicitado', $trabajo->estado, 'La cita se agendó sin autorización.');
        $this->assertNull($trabajo->fecha, 'Quedó con fecha real: eso ya es una cita agendada.');
        // Lo que pidió el vendedor no se pierde: viaja como PREFERIDA, que es lo que la agenda
        // muestra en «Por coordinar».
        $this->assertSame($this->dia(), $trabajo->fecha_preferida->toDateString());
        $this->assertSame('09:00', $trabajo->hora_preferida_corta);
    }

    /**
     * EL CANDADO QUE SOSTIENE TODO: una cita esperando autorización NO ocupa el día. Si lo
     * ocupara, el jefe estaría autorizando algo que ya tomó el lugar —y el formulario público
     * le diría a un cliente que ese día no hay, por una cita que quizás se rechace—.
     */
    public function test_una_cita_en_espera_no_ocupa_el_dia(): void
    {
        $this->agendar($this->vendedor());

        $this->assertTrue(AgendaTrabajo::conflictos($this->dia(), $this->dia())->isEmpty(),
            'Una cita sin autorizar está bloqueando el día.');
        $this->assertFalse(AgendaTrabajo::disponibilidad($this->dia())['ocupado'],
            'El formulario público diría que ese día está ocupado por una cita que nadie autorizó.');
    }

    public function test_le_llega_la_solicitud_al_jefe_de_ventas(): void
    {
        $this->agendar($this->vendedor());

        $aprobacion = Aprobacion::sole();

        $this->assertSame(Aprobacion::ACCION_AGENDA_CITA, $aprobacion->tipo_accion);
        $this->assertSame(Aprobacion::ESTADO_PENDIENTE, $aprobacion->estado);
        $this->assertSame('jefe_ventas', $aprobacion->rol_aprobador);
        $this->assertStringContainsString('Aguas Claras', $aprobacion->motivo);
    }

    public function test_el_vendedor_ve_que_quedo_esperando(): void
    {
        // Un mensaje que diga «agendado» acá sería mentirle: el técnico no está comprometido.
        $this->agendar($this->vendedor())
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'ESPERANDO la autorización'));
    }

    /** Y no se le avisa al cliente todavía: la cita no existe hasta que la autoricen. */
    public function test_no_se_le_avisa_al_cliente_antes_de_autorizar(): void
    {
        $this->agendar($this->vendedor());

        Mail::assertNothingSent();
    }

    // ─────────────────────────────────────────────── el jefe autoriza

    public function test_al_autorizar_la_cita_queda_agendada_y_se_avisa_al_cliente(): void
    {
        $this->agendar($this->vendedor());

        app(Aprobaciones::class)->aprobar(Aprobacion::sole(), $this->jefe());

        $trabajo = AgendaTrabajo::sole()->refresh();

        $this->assertSame('agendado', $trabajo->estado);
        $this->assertSame($this->dia(), $trabajo->fecha->toDateString());
        $this->assertSame('09:00', $trabajo->hora_corta);
        // Y ahora sí ocupa el día.
        $this->assertTrue(AgendaTrabajo::disponibilidad($this->dia())['ocupado']);
        Mail::assertSent(\App\Mail\AgendaTrabajoAviso::class);
    }

    /**
     * Si el jefe RECHAZA, la cita no se agenda y el registro queda para volver a coordinarla
     * con el cliente. No desaparece: alguien ya le prometió algo a alguien.
     */
    public function test_al_rechazar_la_cita_no_se_agenda(): void
    {
        $this->agendar($this->vendedor());

        app(Aprobaciones::class)->rechazar(Aprobacion::sole(), $this->jefe(), 'El cliente está en atraso de pagos.');

        $trabajo = AgendaTrabajo::sole()->refresh();

        $this->assertSame('solicitado', $trabajo->estado);
        $this->assertNull($trabajo->fecha);
        $this->assertSame($this->dia(), $trabajo->fecha_preferida->toDateString(),
            'Se perdió lo que el vendedor había pedido: hay que poder volver a coordinarlo.');
        $this->assertSame(Aprobacion::ESTADO_RECHAZADA, Aprobacion::sole()->estado);
    }

    /**
     * DOS VENDEDORES, EL MISMO DÍA. El jefe autoriza una; al resolver la segunda el motor tiene
     * que rebotar con un motivo legible en vez de dejar dos citas encimadas. Es el caso que
     * hace falta justamente porque las citas en espera no bloquean el día.
     */
    public function test_si_el_dia_se_ocupo_mientras_esperaba_la_segunda_rebota(): void
    {
        $this->agendar($this->vendedor(), ['cliente_nombre' => 'Primera SpA']);
        $this->agendar($this->vendedor(), ['cliente_nombre' => 'Segunda SpA']);

        $pendientes = Aprobacion::orderBy('id')->get();
        $jefe = $this->jefe();

        app(Aprobaciones::class)->aprobar($pendientes[0], $jefe);
        app(Aprobaciones::class)->aprobar($pendientes[1], $jefe);

        $this->assertSame(Aprobacion::ESTADO_APROBADA, $pendientes[0]->refresh()->estado);
        // La segunda se resolvió como RECHAZADA automática, con el motivo del conflicto.
        $this->assertSame(Aprobacion::ESTADO_RECHAZADA, $pendientes[1]->refresh()->estado);
        $this->assertStringContainsString('ocupado', (string) $pendientes[1]->resultado_motivo);

        $this->assertSame(1, AgendaTrabajo::where('estado', 'agendado')->count(),
            'Quedaron dos citas agendadas el mismo día.');
    }

    // ─────────────────────────────────────────────── el atajo que había que tapar

    /**
     * EL AGUJERO QUE HACÍA DECORATIVA LA AUTORIZACIÓN. La cita en espera aparece en «Por
     * coordinar» con el botón «Coordinar», que va a `update()`: sin gate ahí, el vendedor
     * ponía la fecha por ese camino y la cita quedaba AGENDADA sin que el jefe viera nada.
     *
     * Un control que se puede saltar por la pantalla de al lado no es un control.
     */
    public function test_el_vendedor_no_puede_agendarla_por_el_camino_de_coordinar(): void
    {
        $this->agendar($this->vendedor());
        $trabajo = AgendaTrabajo::sole();
        $vendedor = $this->vendedor();

        // Resuelve la primera solicitud para que el «ya está esperando» no tape el caso.
        app(Aprobaciones::class)->rechazar(Aprobacion::sole(), $this->jefe(), 'Probemos otra fecha.');

        $this->actingAs($vendedor)->put(route('admin.agenda-terreno.update', $trabajo), [
            'tipo' => 'mantencion',
            'estado' => 'agendado',
            'fecha' => $this->dia(9),
            'hora' => '11:00',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'Mantención de la llenadora.',
        ])->assertRedirect();

        $trabajo->refresh();

        $this->assertSame('solicitado', $trabajo->estado, 'Se agendó por el camino de «Coordinar», salteando al jefe.');
        $this->assertNull($trabajo->fecha);
        $this->assertSame($this->dia(9), $trabajo->fecha_preferida->toDateString(), 'La fecha nueva se perdió: tiene que viajar a la aprobación.');
        $this->assertSame(1, Aprobacion::where('estado', Aprobacion::ESTADO_PENDIENTE)->count());
    }

    /** Y editar dos veces no le deja al jefe dos pedidos de lo mismo. */
    public function test_editar_dos_veces_no_duplica_la_solicitud(): void
    {
        $this->agendar($this->vendedor());
        $trabajo = AgendaTrabajo::sole();

        $this->actingAs($this->vendedor())->put(route('admin.agenda-terreno.update', $trabajo), [
            'tipo' => 'mantencion', 'estado' => 'agendado', 'fecha' => $this->dia(9), 'hora' => '11:00',
            'cliente_nombre' => 'Aguas Claras SpA', 'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678', 'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500', 'ciudad' => 'Talca', 'descripcion' => 'Mantención.',
        ]);

        $this->assertSame(1, Aprobacion::count(), 'El jefe vería dos pedidos de la misma cita.');
    }

    /** La agenda tiene que DECIR que esa cita espera autorización, o se lee como pedido del cliente. */
    public function test_la_agenda_marca_la_cita_que_espera_autorizacion(): void
    {
        $this->agendar($this->vendedor());

        $this->actingAs($this->jefe())
            ->get(route('admin.agenda-terreno.index'))
            ->assertOk()
            ->assertSee('esperando autorización');
    }

    // ─────────────────────────────────────────────── lo que NO pasa por autorización

    /** El jefe de ventas agendando no se auto-solicita permiso: se agenda derecho. */
    public function test_el_jefe_de_ventas_agenda_sin_pedir_permiso(): void
    {
        $this->agendar($this->jefe())->assertRedirect();

        $trabajo = AgendaTrabajo::sole();

        $this->assertSame('agendado', $trabajo->estado);
        $this->assertSame($this->dia(), $trabajo->fecha->toDateString());
        $this->assertSame(0, Aprobacion::count(), 'Se creó una solicitud que nadie tiene que mirar.');
    }

    /**
     * La VISITA TÉCNICA queda afuera: es la que pide el cliente por el QR y el vendedor solo la
     * coordina. Autorizarla sería trabar la respuesta a un cliente que ya pidió la visita.
     */
    public function test_una_visita_tecnica_no_necesita_autorizacion(): void
    {
        $this->agendar($this->vendedor(), ['tipo' => 'visita_tecnica'])->assertRedirect();

        $this->assertSame('agendado', AgendaTrabajo::sole()->estado);
        $this->assertSame(0, Aprobacion::count());
    }

    /** Guardar una solicitud SIN fecha no compromete al técnico: no hay nada que autorizar. */
    public function test_una_solicitud_sin_fecha_no_pide_autorizacion(): void
    {
        $this->actingAs($this->vendedor())->post(route('admin.agenda-terreno.store'), [
            'tipo' => 'reparacion',
            'estado' => 'solicitado',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'Hay que ver la bomba.',
        ]);

        $this->assertSame(0, Aprobacion::count());
    }

    public function test_los_tres_tipos_que_pidio_el_dueno_pasan_por_autorizacion(): void
    {
        foreach (['mantencion', 'reparacion', 'instalacion'] as $i => $tipo) {
            $this->agendar($this->vendedor(), [
                'tipo' => $tipo,
                'fecha' => $this->dia(5 + $i * 7),
                'cliente_nombre' => "Cliente {$tipo}",
            ]);
        }

        $this->assertSame(3, Aprobacion::where('tipo_accion', Aprobacion::ACCION_AGENDA_CITA)->count());
        $this->assertSame(0, AgendaTrabajo::where('estado', 'agendado')->count());
    }
}
