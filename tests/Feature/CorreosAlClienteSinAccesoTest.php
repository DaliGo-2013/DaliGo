<?php

namespace Tests\Feature;

use App\Mail\AgendaTrabajoAviso;
use App\Mail\CotizacionCliente;
use App\Mail\DetalleTrabajoCliente;
use App\Mail\EquipoListoParaRetiro;
use App\Mail\IngresoTallerRecibido;
use App\Mail\RetiroSinReparacion;
use App\Mail\SinSolucionCliente;
use App\Models\AgendaTrabajo;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

/**
 * Regla del dueño (13-08-2026), a propósito del correo «listo para retirar»:
 *
 *   «no debe tener acceso al ingreso para evitar hackeos o ingresos no deseados.
 *    El único mensaje que puede responder el cliente es el de aceptar cotización
 *    por el costo a pagar, nada más.»
 *
 * Hoy eso se cumple, pero se cumplía por CASUALIDAD: nada impedía que alguien
 * agregara mañana un botón «Abrir en DaliGo» a un correo del cliente. Esto lo
 * vuelve una regla.
 *
 * SE RENDERIZAN los correos en vez de leer los Blade, y esa decisión tiene una
 * razón concreta: al auditar esto, un grep de `route(`/`url(` sobre las
 * plantillas dio «0 enlaces» en el correo de cotización, y era FALSO — su enlace
 * entra como variable (`$urlRespuesta`, armado en el Mailable) y el grep no lo
 * ve. Un candado que lea el Blade tendría el mismo punto ciego justo en el caso
 * que más importa.
 */
class CorreosAlClienteSinAccesoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return array<int, string> los href del correo renderizado */
    private function enlacesDe(Mailable $mail): array
    {
        preg_match_all('/href="([^"]*)"/i', $mail->render(), $m);

        return $m[1];
    }

    private function orden(): OrdenServicio
    {
        return OrdenServicio::factory()->create([
            'codigo' => 'ST-CORREO01',
            'estado' => 'reparado',
            'facturacion' => 'reparacion',
            'cliente_nombre' => 'Cliente Correo SpA',
            'cliente_email' => 'cliente@example.cl',
            'trabajo_realizado' => 'Cambio de placa eléctrica',
            'causa_falla' => 'uso_normal',
            'mano_obra' => 15000,
        ]);
    }

    /** La cotización se deriva de LA MISMA orden: dos `orden()` chocarían con su código único. */
    private function cotizacion(OrdenServicio $orden): OrdenServicioCotizacion
    {
        return OrdenServicioCotizacion::crearDesde(
            $orden->load('repuestos'),
            tap(User::factory()->create())->assignRole('tecnico')
        );
    }

    /**
     * Ningún correo al cliente lo mete a la aplicación. Un enlace a `/admin/…`
     * exige sesión y permiso, así que tampoco sería un agujero — pero es una
     * puerta que el cliente no tiene por qué ver, y es la que el dueño pidió que
     * no exista.
     */
    public function test_ningun_correo_al_cliente_enlaza_a_la_aplicacion(): void
    {
        $orden = $this->orden();
        $cotizacion = $this->cotizacion($orden);
        $trabajo = AgendaTrabajo::factory()->create(['fecha' => '2026-08-20', 'estado' => 'agendado']);

        $correos = [
            'Listo para retirar' => new EquipoListoParaRetiro($orden),
            'Ingreso recibido' => new IngresoTallerRecibido($orden),
            'Detalle del trabajo' => new DetalleTrabajoCliente($orden),
            'Sin solución' => new SinSolucionCliente($orden),
            'Cotización' => new CotizacionCliente($cotizacion),
            'Retiro sin reparación' => new RetiroSinReparacion($cotizacion),
            'Aviso de terreno' => new AgendaTrabajoAviso($trabajo),
        ];

        foreach ($correos as $nombre => $mail) {
            foreach ($this->enlacesDe($mail) as $href) {
                $this->assertStringNotContainsString(
                    '/admin',
                    $href,
                    "El correo «{$nombre}» enlaza al panel interno: {$href}"
                );
                $this->assertStringNotContainsString(
                    '/login',
                    $href,
                    "El correo «{$nombre}» enlaza al ingreso: {$href}"
                );
            }
        }
    }

    /**
     * Y la otra mitad de la regla: la ÚNICA cosa que el cliente puede responder
     * es la cotización. Los demás correos son avisos y no llevan ningún enlace
     * accionable — si mañana uno gana un botón, este candado se pone rojo y
     * obliga a decidirlo a propósito.
     */
    public function test_solo_la_cotizacion_le_da_al_cliente_algo_que_responder(): void
    {
        $orden = $this->orden();
        $cotizacion = $this->cotizacion($orden);
        $trabajo = AgendaTrabajo::factory()->create(['fecha' => '2026-08-20', 'estado' => 'agendado']);

        $soloAviso = [
            'Listo para retirar' => new EquipoListoParaRetiro($orden),
            'Ingreso recibido' => new IngresoTallerRecibido($orden),
            'Detalle del trabajo' => new DetalleTrabajoCliente($orden),
            'Sin solución' => new SinSolucionCliente($orden),
            'Retiro sin reparación' => new RetiroSinReparacion($cotizacion),
            'Aviso de terreno' => new AgendaTrabajoAviso($trabajo),
        ];

        foreach ($soloAviso as $nombre => $mail) {
            $this->assertSame(
                [],
                $this->enlacesDe($mail),
                "El correo «{$nombre}» es solo un aviso y no debería traer ningún enlace."
            );
        }

        // La excepción sancionada: un solo enlace, y es el de responder la
        // cotización — público a propósito (el cliente no tiene cuenta) pero
        // FIRMADO y por token, así que no se puede adivinar ni reusar para otra.
        $enlaces = $this->enlacesDe(new CotizacionCliente($cotizacion));
        $this->assertCount(1, $enlaces, 'La cotización debe ofrecer exactamente un enlace.');
        $this->assertStringContainsString('signature=', $enlaces[0]);
        $this->assertStringContainsString($cotizacion->token, $enlaces[0]);
    }

    /**
     * El botón «Abrir en DaliGo» es de los avisos INTERNOS y ahí se queda
     * (decisión del dueño 13-08: el equipo llega a la orden en un toque desde el
     * celular). Este candado fija el límite: que exista SOLO en esa plantilla, y
     * que esa plantilla no sea la que se le manda al cliente.
     */
    public function test_el_boton_de_abrir_vive_solo_en_los_avisos_internos(): void
    {
        $vistas = collect(glob(resource_path('views/emails/**/*.blade.php')))
            ->merge(glob(resource_path('views/emails/*.blade.php')));

        $conBoton = $vistas
            ->filter(fn (string $ruta) => str_contains((string) file_get_contents($ruta), 'Abrir en DaliGo'))
            ->map(fn (string $ruta) => basename($ruta))
            ->values()
            ->all();

        $this->assertSame(['notificacion.blade.php'], $conBoton);
    }
}
