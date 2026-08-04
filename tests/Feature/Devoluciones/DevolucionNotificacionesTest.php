<?php

namespace Tests\Feature\Devoluciones;

use App\Models\Devolucion;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\Devoluciones\Devoluciones;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integración M15 de las devoluciones: plantillas sembradas que renderizan
 * sin {placeholders} huérfanos, el aviso interno navegable solo para quien
 * puede entrar (urlDestinoPara), y el cliente externo solo por mail.
 */
class DevolucionNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    public function test_el_aviso_interno_renderiza_la_plantilla_sin_placeholders_huerfanos(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $devolucion = Devolucion::factory()->create(['cliente_nombre' => 'María Pérez', 'canal' => 'falabella']);
        $devolucion->items()->create(['descripcion' => 'Dispensador XYZ', 'cantidad' => 2]);

        app(Devoluciones::class)->avisarSolicitada($devolucion);

        $fila = Notificacion::where('evento', 'devolucion.solicitada')
            ->where('user_id', $jefe->id)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->firstOrFail();

        // La plantilla sustituyó los datos reales…
        $this->assertStringContainsString($devolucion->folio, $fila->titulo);
        $this->assertStringContainsString('María Pérez', $fila->titulo);
        $this->assertStringContainsString('Falabella', $fila->titulo);
        $this->assertStringContainsString('2× Dispensador XYZ', $fila->cuerpo);

        // …y no quedó ningún {placeholder} sin reemplazar (contrato: todo
        // placeholder con default '—', nunca null).
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $fila->titulo.$fila->cuerpo);
    }

    public function test_la_campanita_navega_solo_para_quien_puede_entrar(): void
    {
        $devolucion = Devolucion::factory()->create();

        $fila = new Notificacion([
            'evento' => 'devolucion.solicitada',
            'notificable_type' => Devolucion::class,
            'notificable_id' => $devolucion->id,
        ]);
        $fila->setRelation('notificable', $devolucion);

        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        // Con permiso: aterriza en la FICHA (donde se recibe/categoriza/resuelve).
        $this->assertSame(
            route('admin.devoluciones.show', $devolucion->id),
            $fila->urlDestinoPara($jefe),
        );
        // Sin permiso: el enlace se suprime (nada de 403 → rebote).
        $this->assertNull($fila->urlDestinoPara($soplador));
    }

    public function test_el_cliente_externo_recibe_solo_mail(): void
    {
        $devolucion = Devolucion::factory()->recibida()->create();

        app(Devoluciones::class)->avisarCliente($devolucion, 'devolucion.recibida');

        $filas = Notificacion::where('evento', 'devolucion.recibida')->get();

        // Un destinatario externo (string) genera SOLO el canal mail: no hay
        // usuario, no hay campanita, no hay whatsapp.
        $this->assertCount(1, $filas);
        $this->assertSame(Notificacion::CANAL_MAIL, $filas->first()->canal);
        $this->assertSame($devolucion->cliente_email, $filas->first()->destinatario);
        $this->assertNull($filas->first()->user_id);
    }
}
