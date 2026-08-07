<?php

namespace Tests\Feature\Admin;

use App\Mail\SinSolucionCliente;
use App\Models\Cliente;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cierre SIN SOLUCION (decision del dueño 30-07-2026): cuando el tecnico cierra
 * una orden sin arreglo hay que avisarle AL CLIENTE, al jefe de ventas y a los
 * vendedores. Es el hermano de `taller.reparado`, con dos diferencias que son el
 * motivo de esta clase aparte:
 *
 *   1. Ademas del aviso interno sale un CORREO AL CLIENTE, automatico.
 *   2. El aviso interno lleva el DIAGNOSTICO del tecnico; el correo al cliente
 *      NO — «mal uso del cliente» en un correo automatico es una acusacion sin
 *      nadie que la explique, y «falla de fabrica» abre una conversacion de
 *      garantia que la tiene ventas por telefono.
 *
 * El reparto interno (jefatura todas / cada vendedor su cartera) lo cubre
 * AvisoReparadoTest: es el MISMO metodo privado, `avisarACartera()`. Aca se fija
 * lo propio de este cierre.
 */
class AvisoSinSolucionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create(['name' => 'Fernando Soto']))->assignRole('tecnico');
    }

    private function jefeVentas(): User
    {
        return tap(User::factory()->create(['name' => 'Héctor Martínez']))->assignRole('jefe_ventas');
    }

    private function orden(array $extra = []): OrdenServicio
    {
        $sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'MIRADOR'],
            ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true],
        );

        return OrdenServicio::factory()->create(array_merge([
            'estado' => 'en_revision',
            'facturacion' => 'reparacion',
            'sucursal_id' => $sucursal->id,
            'cliente_nombre' => 'Ana Pérez',
            'cliente_rut' => '11111111-1',
            'cliente_email' => 'ana@correo.cl',
            'cliente_telefono' => '+56 9 8765 4321',
            'tipo_equipo' => 'dispensador',
            'numero_serie' => 'EST20260100251',
            'falla_reportada' => 'No enfría desde hace dos semanas.',
        ], $extra));
    }

    private function cerrar(User $actor, OrdenServicio $orden, string $estado = 'sin_solucion', array $extra = [])
    {
        return $this->actingAs($actor)->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            array_merge([
                'estado' => $estado,
                'trabajo_realizado' => 'Se probó la celda de peltier y la tarjeta; sin repuesto disponible.',
                'causa_falla' => 'uso_normal',
                'repuestos' => [],
            ], $extra),
        );
    }

    private function avisoInterno(?int $userId = null): ?Notificacion
    {
        return Notificacion::where('evento', 'taller.sin_solucion')
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->first();
    }

    // --- El correo al cliente ---

    public function test_al_cerrar_sin_solucion_le_llega_el_correo_al_cliente(): void
    {
        $orden = $this->orden();

        $this->cerrar($this->tecnico(), $orden)->assertRedirect();

        Mail::assertSent(SinSolucionCliente::class, fn ($m) => $m->hasTo('ana@correo.cl') && $m->orden->is($orden));
        $this->assertSame('sin_solucion', $orden->fresh()->estado);
    }

    /**
     * El diagnostico NO viaja al cliente. Es la decision de contenido de este
     * correo, y la que mas facil se rompe agregando «un dato mas».
     */
    public function test_el_correo_al_cliente_no_lleva_el_diagnostico_del_tecnico(): void
    {
        $orden = $this->orden(['causa_falla' => null]);

        $this->cerrar($this->tecnico(), $orden, 'sin_solucion', ['causa_falla' => 'mal_uso']);

        Mail::assertSent(SinSolucionCliente::class, function ($mail) {
            $html = $mail->render();

            // Ninguna de las tres etiquetas de causa puede aparecer.
            foreach (OrdenServicio::CAUSA_FALLA_ETIQUETAS as $etiqueta) {
                if (str_contains($html, $etiqueta)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_el_correo_al_cliente_dice_que_no_se_pudo_reparar_y_donde_retirarlo(): void
    {
        $orden = $this->orden();

        $this->cerrar($this->tecnico(), $orden);

        Mail::assertSent(SinSolucionCliente::class, function ($mail) use ($orden) {
            $html = $mail->render();

            return str_contains($html, 'no fue posible repararlo')
                && str_contains($html, $orden->folio)
                && str_contains($html, 'Ana Pérez')
                && str_contains($html, 'EST20260100251')
                // Dónde retirar y que lo van a contactar.
                && str_contains($html, 'Mirador')
                && str_contains($html, 'contactar')
                // Lo que reportó el cliente, para que no parezca automático.
                && str_contains($html, 'No enfría desde hace dos semanas.')
                // Tuteo, como el resto del flujo al cliente.
                && str_contains($html, 'tu dispensador');
        });
    }

    /** Y no lleva montos: el costo de revision lo define ventas caso a caso. */
    public function test_el_correo_al_cliente_no_lleva_montos(): void
    {
        $this->cerrar($this->tecnico(), $this->orden(['mano_obra' => 25000]));

        Mail::assertSent(SinSolucionCliente::class, fn ($m) => ! str_contains($m->render(), '25.000')
            && ! str_contains($m->render(), 'Total'));
    }

    public function test_sin_correo_del_cliente_no_se_intenta_enviar(): void
    {
        $this->cerrar($this->tecnico(), $this->orden(['cliente_email' => null]));

        Mail::assertNothingSent();
    }

    // --- El aviso interno ---

    public function test_ventas_recibe_el_aviso_interno_con_el_diagnostico(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $jefe = $this->jefeVentas();

        $this->cerrar($this->tecnico(), $this->orden());

        $aviso = $this->avisoInterno($jefe->id);
        $this->assertNotNull($aviso, 'El jefe de ventas no recibió el aviso de cierre sin solución.');
        $this->assertStringContainsString('SIN SOLUCIÓN', $aviso->cuerpo);
        $this->assertStringContainsString('Fernando Soto', $aviso->cuerpo);
        // El diagnóstico SÍ va al aviso interno: de él depende la conversación
        // que sigue (reemplazo, o garantía si fue falla de fábrica).
        $this->assertStringContainsString('Desgaste por uso normal', $aviso->cuerpo);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $aviso->titulo.' '.$aviso->cuerpo);
    }

    /**
     * Honestidad del aviso interno: si el correo SALIO lo dice, y si NO salio pide
     * llamar al cliente. Nunca se afirma a ciegas que el cliente ya sabe — ese fue
     * el defecto que se corrigio en el rechazo de terreno.
     */
    public function test_el_aviso_interno_dice_que_al_cliente_SI_se_le_aviso(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $jefe = $this->jefeVentas();

        $this->cerrar($this->tecnico(), $this->orden());

        $this->assertStringContainsString('ya se le avisó por correo', $this->avisoInterno($jefe->id)->cuerpo);
    }

    public function test_el_aviso_interno_pide_llamar_cuando_no_hay_correo(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $jefe = $this->jefeVentas();

        $this->cerrar($this->tecnico(), $this->orden(['cliente_email' => null]));

        $cuerpo = $this->avisoInterno($jefe->id)->cuerpo;
        $this->assertStringContainsString('Falta avisarle al cliente', $cuerpo);
        $this->assertStringNotContainsString('ya se le avisó', $cuerpo);
        // Y con el teléfono, que es con lo que se actúa.
        $this->assertStringContainsString('+56 9 8765 4321', $cuerpo);
    }

    /** Si el SMTP se cae, el aviso interno NO puede decir que el cliente ya sabe. */
    public function test_si_el_correo_falla_el_aviso_interno_pide_llamar(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $jefe = $this->jefeVentas();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->cerrar($this->tecnico(), $this->orden());

        $cuerpo = $this->avisoInterno($jefe->id)->cuerpo;
        $this->assertStringContainsString('NO se pudo avisar al cliente', $cuerpo);
        $this->assertStringNotContainsString('ya se le avisó', $cuerpo);
    }

    /** Y el parte del técnico se guarda igual: el correo es acción secundaria. */
    public function test_un_correo_caido_no_hace_perder_el_parte_del_tecnico(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $orden = $this->orden();

        $this->cerrar($this->tecnico(), $orden)->assertRedirect();

        $this->assertSame('sin_solucion', $orden->fresh()->estado);
        $this->assertSame('uso_normal', $orden->fresh()->causa_falla);
    }

    // --- Cuándo se dispara ---

    public function test_reguardar_una_orden_ya_sin_solucion_no_avisa_de_nuevo(): void
    {
        $this->jefeVentas();
        $tecnico = $this->tecnico();
        $orden = $this->orden();

        $this->cerrar($tecnico, $orden);
        $this->cerrar($tecnico, $orden->fresh());

        Mail::assertSent(SinSolucionCliente::class, 1);
        $this->assertSame(1, Notificacion::where('evento', 'taller.sin_solucion')
            ->where('canal', Notificacion::CANAL_DATABASE)->count());
    }

    /** Cerrar como REPARADO no manda el correo de sin solución (ni al revés). */
    public function test_los_dos_cierres_no_se_cruzan(): void
    {
        $this->jefeVentas();

        $this->cerrar($this->tecnico(), $this->orden(), 'reparado');

        Mail::assertNotSent(SinSolucionCliente::class);
        $this->assertNull($this->avisoInterno());
        $this->assertSame(1, Notificacion::where('evento', 'taller.reparado')
            ->where('canal', Notificacion::CANAL_DATABASE)->count());
    }

    public function test_el_tecnico_no_recibe_el_aviso_de_su_propia_accion(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');
        $jefe = $this->jefeVentas();

        $this->cerrar($admin, $this->orden());

        $this->assertNotNull($this->avisoInterno($jefe->id));
        $this->assertNull($this->avisoInterno($admin->id));
    }

    /** Y el vendedor sigue recibiendo solo lo de SU cartera. */
    public function test_el_vendedor_recibe_solo_su_cartera(): void
    {
        $vendedorA = tap(User::factory()->create())->assignRole('vendedor');
        Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => $vendedorA->id]);
        $vendedorB = tap(User::factory()->create())->assignRole('vendedor');

        $this->cerrar($this->tecnico(), $this->orden(['cliente_rut' => '11111111-1']));

        $this->assertNotNull($this->avisoInterno($vendedorA->id));
        $this->assertNull($this->avisoInterno($vendedorB->id));
    }

    // --- Lo que ve el técnico en pantalla ---

    public function test_al_tecnico_se_le_dice_que_el_cliente_fue_avisado(): void
    {
        $this->cerrar($this->tecnico(), $this->orden())
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'ana@correo.cl'));
    }

    public function test_al_tecnico_se_le_pide_llamar_si_no_hay_correo(): void
    {
        $this->cerrar($this->tecnico(), $this->orden(['cliente_email' => null]))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'hay que llamarlo'));
    }

    /** Y en un guardado normal el mensaje NO habla del cliente. */
    public function test_un_guardado_normal_no_menciona_al_cliente(): void
    {
        $this->cerrar($this->tecnico(), $this->orden(), 'en_revision')
            ->assertSessionHas('status', fn ($s) => ! str_contains($s, 'llamarlo') && ! str_contains($s, 'avisó'));
    }
}
