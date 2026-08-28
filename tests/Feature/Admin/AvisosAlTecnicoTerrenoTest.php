<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\Aprobacion;
use App\Models\Configuracion;
use App\Models\Notificacion;
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
 * EL TÉCNICO SE ENTERA DE SU PROPIA AGENDA (dueño 14-08-2026: «cuando realizamos
 * pruebas de testeo con instalaciones o reparaciones nunca le llegó una
 * notificación… cualquier dato que influya en sus tareas laborales, que esté al
 * tanto»).
 *
 * El hueco era ESTRUCTURAL y por eso pasó tanto tiempo sin verse: el rol
 * `tecnico_industrial` no figuraba en NINGUNA lista de destinatarios de la app
 * (ROLES_AVISO_COORDINAR y ROLES_AVISO_CIERRE son de ventas; ROLES_AVISO_INGRESO
 * del taller nombra a 'tecnico', que es otro rol). O sea: el técnico no recibía un
 * aviso de nada. Le agendaban el día y se enteraba abriendo la agenda a ver si
 * había algo nuevo.
 *
 * Lo que estos candados protegen, y el orden importa:
 *
 * 1. Que le llegue por los TRES caminos que le agendan un trabajo. El tercero
 *    —el jefe de ventas autorizando la cita horas o días después— es el que más
 *    falta hace y el que se olvida, porque no pasa por la pantalla de la agenda.
 *    Ese es el motivo por el que la decisión vive en el modelo y no en un
 *    controlador (la misma lección que ya estaba escrita para el aviso al
 *    cliente).
 * 2. Que le llegue cuando le MUEVEN o le CANCELAN algo. Un técnico que maneja
 *    hasta la planta de un cliente para encontrarse con que se canceló es el peor
 *    caso de todos.
 * 3. Que un trabajo sin técnico asignado NO deje el aviso en el aire: si el aviso
 *    dependiera del `tecnico_id`, un trabajo sin asignar no le llegaría a nadie —
 *    que es justo la forma que tuvo esta falla.
 */
class AvisosAlTecnicoTerrenoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Mail::fake();
    }

    private function carlos(): User
    {
        return tap(User::factory()->create(['name' => 'Carlos Terreno']))
            ->assignRole('tecnico_industrial');
    }

    private function jefe(): User
    {
        return tap(User::factory()->create(['email' => 'jefe@impdali.cl']))->assignRole('jefe_ventas');
    }

    /** @return array<string, mixed> */
    private function datosCita(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'instalacion',
            'estado' => 'agendado',
            'fecha' => '2026-09-10',
            'hora' => '09:30',
            'cliente_nombre' => 'Embotelladora Curicó',
            'cliente_rut' => '12.345.678-5',
            'cliente_email' => 'planta@embotelladora.cl',
            'cliente_telefono' => '+56911112222',
            'direccion' => 'Camino a Los Niches 1200',
            'ciudad' => 'Curicó',
            'descripcion' => 'Instalar la planta de osmosis y dejarla midiendo.',
        ], $extra);
    }

    private function avisosDe(User $u, ?string $evento = null)
    {
        return Notificacion::where('user_id', $u->id)
            ->when($evento, fn ($q) => $q->where('evento', $evento))
            ->get();
    }

    /**
     * Le llegó el aviso, y una sola vez.
     *
     * NO se cuentan filas: M15 crea UNA FILA POR CANAL (la campanita siempre, más
     * el correo según las preferencias del usuario), así que un `assertCount(1)`
     * fija de rebote la configuración de canales y se rompe el día que alguien
     * active WhatsApp. Lo que sí importa —y lo que un conteo de filas no ve— es
     * que no se haya duplicado DENTRO de un canal: eso es el bug de avisar dos
     * veces por dos caminos, que en esta agenda es un riesgo real (el trabajo
     * pasa por store, update y la autorización del jefe).
     */
    private function assertAvisado(User $u, string $evento): Notificacion
    {
        $avisos = $this->avisosDe($u, $evento);

        $this->assertTrue($avisos->isNotEmpty(), "A {$u->name} no le llegó «{$evento}».");
        $this->assertSame(
            $avisos->pluck('canal')->unique()->count(),
            $avisos->count(),
            "El aviso «{$evento}» se despachó dos veces por el mismo canal."
        );

        return $avisos->first();
    }

    // --- Camino 1: le agendan el trabajo directamente -------------------------

    public function test_agendar_un_trabajo_le_avisa_al_tecnico_con_hora_y_direccion(): void
    {
        $carlos = $this->carlos();

        $this->actingAs($this->jefe())
            ->post(route('admin.agenda-terreno.store'), $this->datosCita(['tecnico_id' => $carlos->id]))
            ->assertSessionHasNoErrors();

        $cuerpo = (string) $this->assertAvisado($carlos, 'terreno.agendado')->cuerpo;
        // Lo que decide a qué hora sale y para dónde: si falta, el aviso no sirve.
        $this->assertStringContainsString('10-09-2026', $cuerpo);
        $this->assertStringContainsString('09:30', $cuerpo);
        $this->assertStringContainsString('Camino a Los Niches 1200', $cuerpo);
        $this->assertStringContainsString('Curicó', $cuerpo);
        $this->assertStringContainsString('+56911112222', $cuerpo);
        $this->assertStringContainsString('Instalar la planta de osmosis', $cuerpo);
        $this->assertDoesNotMatchRegularExpression('~\{[a-z_]+\}~', $cuerpo, 'Quedó un placeholder sin reemplazar.');
    }

    /**
     * El candado del punto 3: sin `tecnico_id` el aviso tiene que ir igual a los
     * técnicos industriales. Un trabajo sin asignar sigue siendo trabajo del
     * equipo, y el aviso que depende de una asignación que nadie hizo no existe.
     */
    public function test_un_trabajo_sin_tecnico_asignado_igual_le_avisa_al_equipo(): void
    {
        $carlos = $this->carlos();

        $this->actingAs($this->jefe())
            ->post(route('admin.agenda-terreno.store'), $this->datosCita())
            ->assertSessionHasNoErrors();

        $this->assertAvisado($carlos, 'terreno.agendado');
    }

    /**
     * Una solicitud SIN fecha no compromete al técnico: no hay nada que avisarle
     * todavía. El aviso llega cuando la cita se fija (lo cubre el test de editar).
     */
    public function test_una_solicitud_sin_fecha_no_le_avisa_nada(): void
    {
        $carlos = $this->carlos();

        AgendaTrabajo::factory()->create(['estado' => 'solicitado', 'fecha' => null]);

        $this->assertCount(0, $this->avisosDe($carlos));
    }

    // --- Camino 2: le fijan la fecha editando --------------------------------

    public function test_coordinar_una_solicitud_le_avisa_al_tecnico(): void
    {
        $carlos = $this->carlos();
        $t = AgendaTrabajo::factory()->create([
            'estado' => 'solicitado',
            'fecha' => null,
            'cliente_nombre' => 'Aguas del Maule',
        ]);

        $this->actingAs($this->jefe())
            ->put(route('admin.agenda-terreno.update', $t), $this->datosCita([
                'cliente_nombre' => 'Aguas del Maule',
                'tecnico_id' => $carlos->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertAvisado($carlos, 'terreno.agendado');
    }

    public function test_moverle_la_fecha_le_avisa_y_le_dice_cual_era(): void
    {
        $carlos = $this->carlos();
        $t = AgendaTrabajo::factory()->create(array_merge($this->datosCita(), [
            'tecnico_id' => $carlos->id,
        ]));

        // La fecha va FIJA y no calculada porque el assert de abajo busca el literal
        // «14-09-2026» en el cuerpo del aviso. Y es un LUNES a propósito: desde el 25-08 el
        // formulario interno rechaza los días que el técnico no atiende, así que el
        // 2026-09-12 que estaba acá —un sábado— empezó a caer en la validación. Es el mismo
        // tropiezo de la bitácora [2026-08-17]: una regla nueva convierte los fixtures de
        // fecha viejos en bombas de tiempo, y hay que barrerlos en el mismo lote.
        $this->actingAs($this->jefe())
            ->put(route('admin.agenda-terreno.update', $t), $this->datosCita([
                'tecnico_id' => $carlos->id,
                'fecha' => '2026-09-14',
                'hora' => '15:00',
            ]))
            ->assertSessionHasNoErrors();

        $cuerpo = (string) $this->assertAvisado($carlos, 'terreno.reagendado')->cuerpo;
        $this->assertStringContainsString('14-09-2026', $cuerpo);
        $this->assertStringContainsString('15:00', $cuerpo);
        // Sin el «antes» no sabe CUÁL de sus trabajos se movió.
        $this->assertStringContainsString('2026-09-10', $cuerpo);
    }

    public function test_editar_sin_tocar_el_dia_no_lo_molesta(): void
    {
        $carlos = $this->carlos();
        $t = AgendaTrabajo::factory()->create(array_merge($this->datosCita(), [
            'tecnico_id' => $carlos->id,
        ]));

        // Cambia solo el teléfono del cliente: no le cambia el día al técnico.
        $this->actingAs($this->jefe())
            ->put(route('admin.agenda-terreno.update', $t), $this->datosCita([
                'tecnico_id' => $carlos->id,
                'cliente_telefono' => '+56999998888',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $this->avisosDe($carlos), 'Un cambio que no afecta su día no debe notificarlo.');
    }

    // --- Camino 3: el jefe de ventas AUTORIZA la cita ------------------------

    /**
     * EL CAMINO QUE SE OLVIDA. La cita se le agenda al técnico horas o días
     * después de que el vendedor la pidió, y no pasa por la pantalla de la agenda:
     * si el aviso viviera en el controlador, este camino saldría mudo. Es la misma
     * falla que ya se había arreglado para el aviso al CLIENTE, y la razón por la
     * que la decisión vive en el modelo.
     */
    public function test_cuando_el_jefe_autoriza_la_cita_el_tecnico_se_entera(): void
    {
        // Este es el único test que necesita las reglas: sin ellas la cita del
        // vendedor nace agendada y no hay nada que autorizar.
        $this->seed(ReglasAprobacionSeeder::class);

        $carlos = $this->carlos();
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        // El vendedor fija la cita: NACE en espera y NO se le avisa a nadie
        // todavía (la cita no existe hasta que la autoricen).
        $dia = $this->diaLaborable();
        $this->actingAs($vendedor)
            ->post(route('admin.agenda-terreno.store'), $this->datosCita([
                'fecha' => $dia,
                'tecnico_id' => $carlos->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertCount(
            0,
            $this->avisosDe($carlos, 'terreno.agendado'),
            'La cita todavía no está autorizada: al técnico no se le puede prometer un día.'
        );

        // El jefe la autoriza — horas o días después, por la bandeja, sin pasar
        // por la pantalla de la agenda. ACÁ es donde el aviso se olvidaba.
        app(Aprobaciones::class)->aprobar(Aprobacion::sole(), $this->jefe());

        $aviso = $this->assertAvisado($carlos, 'terreno.agendado');
        $this->assertStringContainsString(
            Carbon::parse($dia)->format('d-m-Y'),
            (string) $aviso->cuerpo
        );
    }

    /** Un día laborable: la agenda rechaza los que no lo son. */
    private function diaLaborable(int $dias = 5): string
    {
        $d = Carbon::parse(FechaNegocio::hoy())->addDays($dias);

        while (! AgendaTrabajo::esLaborable($d->toDateString())) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    // --- Cancelaciones -------------------------------------------------------

    public function test_cancelar_un_trabajo_agendado_le_avisa_que_no_vaya(): void
    {
        $carlos = $this->carlos();
        $t = AgendaTrabajo::factory()->create(array_merge($this->datosCita(), [
            'tecnico_id' => $carlos->id,
        ]));

        $this->actingAs($this->jefe())
            ->patch(route('admin.agenda-terreno.estado', $t), ['estado' => 'cancelado'])
            ->assertSessionHasNoErrors();

        $aviso = $this->assertAvisado($carlos, 'terreno.cancelado');
        $this->assertStringContainsString('No vayas', (string) $aviso->cuerpo);
    }

    /**
     * El técnico cerrando su propio trabajo NO se auto-notifica: ya sabe lo que
     * hizo. El aviso de cierre es para ventas (`terreno.realizado`).
     */
    public function test_el_tecnico_cerrando_su_trabajo_no_se_avisa_a_si_mismo(): void
    {
        $carlos = $this->carlos();
        $this->jefe();
        $t = AgendaTrabajo::factory()->create(array_merge($this->datosCita(), [
            'tecnico_id' => $carlos->id,
        ]));

        $this->actingAs($carlos)
            ->patch(route('admin.agenda-terreno.estado', $t), [
                'estado' => 'realizado',
                'notas_tecnico' => 'Instalada y midiendo 12 ppm.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $this->avisosDe($carlos), 'El técnico no tiene que avisarse a sí mismo.');
    }

    // --- Que el aviso sea alcanzable ----------------------------------------

    public function test_el_aviso_lleva_al_tecnico_a_su_agenda(): void
    {
        $carlos = $this->carlos();

        $this->actingAs($this->jefe())
            ->post(route('admin.agenda-terreno.store'), $this->datosCita(['tecnico_id' => $carlos->id]))
            ->assertSessionHasNoErrors();

        $aviso = $this->avisosDe($carlos, 'terreno.agendado')->first();

        // Un aviso que el destinatario no puede abrir es peor que no avisar: la
        // campanita enlaza con `urlDestinoPara`, que devuelve null si no alcanza.
        $this->assertSame(
            route('admin.agenda-terreno.index'),
            $aviso->urlDestinoPara($carlos),
            'El técnico no puede abrir su propio aviso.'
        );
    }

    /**
     * Los tres eventos nuevos tienen su plantilla sembrada. Sin ella el despacho
     * cae en un texto genérico y el aviso pierde justo los datos que lo hacen
     * útil (hora, dirección, teléfono).
     */
    public function test_los_tres_eventos_del_tecnico_tienen_plantilla(): void
    {
        foreach (['terreno.agendado', 'terreno.reagendado', 'terreno.cancelado'] as $evento) {
            $this->assertArrayHasKey($evento, Notificacion::EVENTOS, "Falta el evento {$evento} en el catálogo.");

            $clave = 'notif_plantilla_'.str_replace('.', '_', $evento);
            $plantilla = Configuracion::get($clave);

            $this->assertNotNull($plantilla, "Falta la plantilla {$clave}.");
            $this->assertNotEmpty($plantilla['asunto'] ?? null, "La plantilla {$clave} no tiene asunto.");
            $this->assertNotEmpty($plantilla['cuerpo'] ?? null, "La plantilla {$clave} no tiene cuerpo.");
        }
    }
}
