<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A QUIÉN de ventas le llega el aviso de una orden (regla del dueño 07-08-2026).
 *
 * Dos mitades, y la segunda es la que faltaba:
 *   1. Cliente CON vendedor asignado → el aviso es de ESE vendedor (más jefatura,
 *      que ve todo). No se le manda a los otros ocho.
 *   2. Cliente SIN vendedor asignado → es de SALA DE VENTAS, donde se atiende a
 *      todo público: le llega a todos los `vendedor`, que monitorean hasta que se
 *      le asigne uno o quede fijo en sala. Sala de ventas no es un rol aparte.
 *
 * Sin la mitad 2 el aviso se caía en un hueco silencioso: `esVisiblePara` exige
 * `clientes.vendedor_id`, que el sync de Bsale no llena, así que NINGÚN vendedor
 * recibía los avisos de cierre. El reparto por cartera se construyó antes de que
 * existieran las carteras.
 */
class AvisoCarteraSalaDeVentasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Mail::fake();
    }

    private function vendedor(): User
    {
        return tap(User::factory()->create())->assignRole('vendedor');
    }

    private function jefeVentas(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_ventas');
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    private function orden(string $rut = '11111111-1'): OrdenServicio
    {
        return OrdenServicio::factory()->create([
            'estado' => 'cotizacion',
            'facturacion' => 'reparacion',
            'cliente_rut' => $rut,
            'cliente_email' => 'cliente@example.com',
            'mano_obra' => 10000,
            'trabajo_realizado' => 'Cambio de caldera — funciona normal',
            'causa_falla' => 'uso_normal',
        ]);
    }

    /**
     * El cliente responde por su link público (firmado): dispara
     * `cotizacion.respondida`. Se afirma el redirect para que un rechazo del
     * endpoint no se disfrace de «el aviso no llegó».
     */
    private function responder(OrdenServicio $orden, string $respuesta = 'aceptada'): void
    {
        $cotizacion = OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $this->tecnico());

        $this->post(
            URL::signedRoute('cotizacion.responder', ['cotizacion' => $cotizacion->token]),
            ['respuesta' => $respuesta],
        )->assertRedirect();
    }

    private function recibio(User $user, string $evento): bool
    {
        return Notificacion::where('user_id', $user->id)
            ->where('evento', $evento)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->exists();
    }

    // --- El cliente aceptó: a quién le suena la campanita ---

    public function test_con_cartera_asignada_el_aviso_va_al_vendedor_del_cliente(): void
    {
        $suyo = $this->vendedor();
        $ajeno = $this->vendedor();
        $jefe = $this->jefeVentas();
        Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => $suyo->id]);

        $this->responder($this->orden());

        $this->assertTrue($this->recibio($suyo, 'cotizacion.respondida'), 'El vendedor del cliente no fue avisado.');
        $this->assertFalse($this->recibio($ajeno, 'cotizacion.respondida'), 'Le llegó a un vendedor ajeno a la cartera.');
        // Jefatura ve todo: controla lo que pasa en servicio técnico.
        $this->assertTrue($this->recibio($jefe, 'cotizacion.respondida'), 'El jefe de ventas no fue avisado.');
    }

    public function test_sin_cartera_asignada_el_aviso_cae_en_sala_de_ventas(): void
    {
        // El cliente existe pero nadie lo tiene asignado (el caso de HOY: el sync
        // de Bsale no llena vendedor_id).
        $unaDeSala = $this->vendedor();
        $otraDeSala = $this->vendedor();
        $jefe = $this->jefeVentas();
        Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => null]);

        $this->responder($this->orden());

        $this->assertTrue($this->recibio($unaDeSala, 'cotizacion.respondida'));
        $this->assertTrue($this->recibio($otraDeSala, 'cotizacion.respondida'));
        $this->assertTrue($this->recibio($jefe, 'cotizacion.respondida'));
    }

    /** Y si el cliente ni siquiera tiene ficha, tampoco se pierde el aviso. */
    public function test_un_cliente_sin_ficha_tambien_cae_en_sala_de_ventas(): void
    {
        $sala = $this->vendedor();

        $this->responder($this->orden('99999999-9'));

        $this->assertTrue($this->recibio($sala, 'cotizacion.respondida'));
    }

    /**
     * El taller se entera SIEMPRE: desde el 07-08 el ACEPTO del cliente es la luz
     * verde para reparar (ya no espera autorización de plata), así que esa señal no
     * puede depender de a qué vendedor esté asignado el cliente.
     *
     * Hoy eso sale solo, sin código aparte: el rol `tecnico` tiene 'ver todo
     * servicio tecnico', así que pasa el filtro de cartera por sí mismo. Este test
     * existe para el día que alguien le quite ese permiso — ahí se le cortaría la
     * luz verde en silencio, y este candado se pone rojo.
     */
    public function test_el_tecnico_recibe_la_respuesta_aunque_el_cliente_sea_de_otra_cartera(): void
    {
        $tecnico = $this->tecnico();
        $otro = $this->vendedor();
        Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => $otro->id]);

        $this->responder($this->orden());

        $this->assertTrue($this->recibio($tecnico, 'cotizacion.respondida'), 'El taller se quedó sin su luz verde.');
    }

    /** Un NO ACEPTO reparte igual: ventas tiene que llamar y ofrecer el retiro. */
    public function test_el_rechazo_reparte_con_la_misma_regla(): void
    {
        $suyo = $this->vendedor();
        $ajeno = $this->vendedor();
        Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => $suyo->id]);

        $this->responder($this->orden(), 'rechazada');

        $this->assertTrue($this->recibio($suyo, 'cotizacion.respondida'));
        $this->assertFalse($this->recibio($ajeno, 'cotizacion.respondida'));
    }

    // --- Los avisos de CIERRE heredan el mismo respaldo ---

    /**
     * Regresión que este cambio arregla: los avisos de cierre ya repartían por
     * cartera, así que sin carteras cargadas no le llegaban a NINGÚN vendedor.
     */
    public function test_reparado_sin_cartera_asignada_llega_a_sala_de_ventas(): void
    {
        $sala = $this->vendedor();
        Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => null]);

        $this->orden()->notificarReparado($this->tecnico());

        $this->assertTrue($this->recibio($sala, 'taller.reparado'), 'Sala de ventas no se enteró de que el equipo está listo.');
    }

    public function test_reparado_con_cartera_asignada_sigue_yendo_solo_al_suyo(): void
    {
        $suyo = $this->vendedor();
        $ajeno = $this->vendedor();
        Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => $suyo->id]);

        $this->orden()->notificarReparado($this->tecnico());

        $this->assertTrue($this->recibio($suyo, 'taller.reparado'));
        $this->assertFalse($this->recibio($ajeno, 'taller.reparado'), 'El respaldo de sala se comió el filtro de cartera.');
    }
}
