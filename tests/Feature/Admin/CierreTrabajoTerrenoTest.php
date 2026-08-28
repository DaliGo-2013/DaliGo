<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\Cliente;
use App\Models\Notificacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cierre del trabajo en terreno (pedido del dueño, 14-08-2026): el técnico
 * escribe el paso a paso de lo que hizo, cierra con «Realizado» o con «No
 * realizado», y el aviso le llega al jefe de ventas y al vendedor del cliente.
 *
 * Lo que estos candados protegen:
 * - Que el cierre EXIJA contar qué pasó. Sin eso, el aviso a ventas llega vacío y
 *   el vendedor termina llamando al técnico para preguntarle — que es justo lo
 *   que esto viene a evitar.
 * - Que «No realizado» exista y sea un estado FINAL con su motivo, distinto de
 *   «cancelado» (que es la coordinación diciendo «no va» ANTES y le escribe al
 *   cliente).
 * - Que el aviso llegue a quien tiene que actuar por la zona, y que el día que
 *   asignen las carteras el vendedor se sume SOLO.
 * - Que los no realizados NO desaparezcan del informe: son el trabajo que hay
 *   que mirar.
 */
class CierreTrabajoTerrenoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Mail::fake();
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico_industrial');
    }

    private function jefeVentas(): User
    {
        return tap(User::factory()->create(['email' => 'jefe@impdali.cl']))->assignRole('jefe_ventas');
    }

    /** @param  array<string, mixed>  $extra */
    private function trabajo(array $extra = []): AgendaTrabajo
    {
        return AgendaTrabajo::factory()->create(array_merge([
            'fecha' => '2026-08-10',
            'tipo' => 'instalacion',
            'estado' => 'agendado',
            'cliente_nombre' => 'Embotelladora Curicó',
            'ciudad' => 'Curicó',
        ], $extra));
    }

    /** @param  array<string, mixed>  $datos */
    private function cerrar(User $como, AgendaTrabajo $t, array $datos)
    {
        return $this->actingAs($como)->patch(route('admin.agenda-terreno.estado', $t), $datos);
    }

    // --- El detalle es obligatorio -------------------------------------------

    public function test_no_se_puede_cerrar_sin_contar_que_paso(): void
    {
        $jefe = $this->jefeVentas();
        $t = $this->trabajo();

        foreach (['realizado', 'no_realizado'] as $estado) {
            $this->cerrar($this->tecnico(), $t, ['estado' => $estado])
                ->assertSessionHasErrors('notas_tecnico');
        }

        $this->assertSame('agendado', $t->fresh()->estado, 'El trabajo no debió cerrarse sin detalle.');
        $this->assertSame(0, Notificacion::count(), 'Un cierre rechazado no puede avisar nada.');
        $this->assertNotNull($jefe);
    }

    // --- Realizado ------------------------------------------------------------

    public function test_el_tecnico_cierra_con_el_paso_a_paso_y_avisa_a_ventas(): void
    {
        $jefe = $this->jefeVentas();
        $t = $this->trabajo();
        $paso = '1) Revisé la bomba: sin presión. 2) Cambié la membrana. 3) Medí 65 psi y quedó funcionando.';

        $this->cerrar($this->tecnico(), $t, ['estado' => 'realizado', 'notas_tecnico' => $paso])
            ->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('realizado', $t->estado);
        $this->assertSame($paso, $t->notas_tecnico);

        // El aviso llegó al jefe de ventas, con el evento correcto y el paso a
        // paso DENTRO del cuerpo: es lo que hace que no tenga que llamar al técnico.
        $avisos = Notificacion::where('evento', 'terreno.realizado')->get();
        $this->assertTrue($avisos->isNotEmpty(), 'No se avisó el cierre a ventas.');
        $this->assertTrue(
            $avisos->contains(fn (Notificacion $n) => (int) $n->user_id === $jefe->id),
            'El jefe de ventas no recibió el aviso.'
        );
        $this->assertStringContainsString('Cambié la membrana', (string) $avisos->first()->cuerpo);
    }

    // --- No realizado ---------------------------------------------------------

    public function test_no_realizado_cierra_el_trabajo_con_su_motivo_y_avisa(): void
    {
        $jefe = $this->jefeVentas();
        $t = $this->trabajo();

        $this->cerrar($this->tecnico(), $t, [
            'estado' => 'no_realizado',
            'notas_tecnico' => 'Faltaba la membrana de repuesto; el cliente prefiere que volvamos con ella.',
        ])->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('no_realizado', $t->estado);
        // Estado FINAL y distinto de cancelado: la fecha se conserva (el técnico
        // fue ese día) y el motivo queda en las notas del técnico.
        $this->assertNotNull($t->fecha);
        $this->assertStringContainsString('Faltaba la membrana', (string) $t->notas_tecnico);

        $avisos = Notificacion::where('evento', 'terreno.no_realizado')->get();
        $this->assertTrue(
            $avisos->contains(fn (Notificacion $n) => (int) $n->user_id === $jefe->id),
            'El jefe de ventas no recibió el aviso de «no realizado».'
        );
        $this->assertStringContainsString('Faltaba la membrana', (string) $avisos->first()->cuerpo);
    }

    // --- Destinatarios --------------------------------------------------------

    /**
     * El vendedor del cliente se suma SOLO cuando existe. Hoy no existe ninguno
     * (las carteras están sin asignar por decisión del dueño), así que este
     * candado es el que garantiza que el día que se asignen no haya que tocar
     * código — y que mientras tanto el aviso igual le llegue al jefe.
     */
    public function test_el_vendedor_del_cliente_recibe_el_aviso_cuando_existe(): void
    {
        $jefe = $this->jefeVentas();
        $vendedor = tap(User::factory()->create(['email' => 'vendedor@impdali.cl']))->assignRole('vendedor');
        $ajeno = tap(User::factory()->create(['email' => 'otro@impdali.cl']))->assignRole('vendedor');

        $cliente = Cliente::factory()->create(['vendedor_id' => $vendedor->id]);
        $t = $this->trabajo(['cliente_id' => $cliente->id]);

        $this->cerrar($this->tecnico(), $t, ['estado' => 'realizado', 'notas_tecnico' => 'Listo.'])
            ->assertSessionHasNoErrors();

        $destinatarios = Notificacion::where('evento', 'terreno.realizado')->pluck('user_id')->unique();

        $this->assertTrue($destinatarios->contains($jefe->id), 'Falta el jefe de ventas.');
        $this->assertTrue($destinatarios->contains($vendedor->id), 'Falta el vendedor del cliente.');
        // Y NO se le avisa a un vendedor que no lleva a este cliente: el aviso es
        // «por la zona», no una lista de correo general.
        $this->assertFalse($destinatarios->contains($ajeno->id), 'Se avisó a un vendedor ajeno al cliente.');
    }

    public function test_sin_vendedor_asignado_el_aviso_igual_llega_al_jefe(): void
    {
        $jefe = $this->jefeVentas();
        $t = $this->trabajo(['cliente_id' => null]);

        $this->cerrar($this->tecnico(), $t, ['estado' => 'realizado', 'notas_tecnico' => 'Listo.'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            Notificacion::where('evento', 'terreno.realizado')->pluck('user_id')->contains($jefe->id),
            'Sin vendedor asignado el aviso tiene que llegar igual al jefe de ventas.'
        );
        $this->assertNull($t->fresh()->vendedorDelCliente());
    }

    // --- Permisos -------------------------------------------------------------

    /**
     * El técnico industrial solo VE su agenda: cerrar sí, cualquier otra cosa no
     * (decisión de gerencia que ya estaba en el seeder de roles).
     */
    public function test_el_tecnico_no_puede_usar_el_cierre_para_otra_cosa(): void
    {
        $t = $this->trabajo();

        // No se asserta 403 crudo: en esta app el handler amable de 403 (D-014)
        // redirige y explica por `session('aviso')`, que es un canal RESERVADO
        // para eso — así que su presencia es la prueba de que se negó el acceso y
        // no de que la acción salió bien.
        $this->cerrar($this->tecnico(), $t, ['estado' => 'cancelado', 'notas_tecnico' => 'x'])
            ->assertRedirect()
            ->assertSessionHas('aviso');

        $this->assertSame('agendado', $t->fresh()->estado);
        $this->assertSame(0, Notificacion::count());
    }

    // --- El informe no puede perderlos ---------------------------------------

    /**
     * El candado del efecto colateral: antes de este cambio el informe miraba
     * solo agendado+realizado, así que un 'no_realizado' habría DESAPARECIDO del
     * período — y es justo el que hay que mirar. Además tiene que contarse como
     * su propia categoría: si cayera en «pendientes» diría que falta ir, cuando
     * el técnico ya fue.
     */
    public function test_el_informe_cuenta_los_no_realizados_aparte(): void
    {
        $this->trabajo(['fecha' => '2026-08-05', 'estado' => 'realizado', 'cliente_nombre' => 'Hecho SA']);
        $this->trabajo(['fecha' => '2026-08-06', 'estado' => 'agendado', 'cliente_nombre' => 'Pendiente SA']);
        $this->trabajo(['fecha' => '2026-08-07', 'estado' => 'no_realizado', 'cliente_nombre' => 'No Pudo SA',
            'notas_tecnico' => 'Faltó el repuesto']);

        $res = $this->actingAs(tap(User::factory()->create())->assignRole('admin'))
            ->get(route('admin.servicio-tecnico.informe.industrial', ['anio' => 2026, 'mes' => 8]))
            ->assertOk();

        $res->assertViewHas('realizados', 1)
            ->assertViewHas('pendientes', 1)
            ->assertViewHas('noRealizados', 1)
            // Los tres suman el total: ninguno se perdió ni se contó dos veces.
            ->assertViewHas('total', 3);

        $res->assertSee('No realizados')
            ->assertSee('No Pudo SA')
            ->assertSee('Faltó el repuesto');
    }
}
