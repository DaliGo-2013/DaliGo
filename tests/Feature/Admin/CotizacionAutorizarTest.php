<?php

namespace Tests\Feature\Admin;

use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Autorización de la reparación tras el pago (P-M12-02): sobre la cotización
 * ACEPTADA por el cliente, ventas registra la forma de pago (obligatoria) +
 * comprobante opcional + nota y autoriza → avisa al técnico. La info de pago
 * queda visible para el equipo. No cambia el estado de la orden.
 */
class CotizacionAutorizarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Storage::fake('local');
    }

    private function conRol(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    /** Orden con una cotización ACEPTADA por el cliente (lista para autorizar). */
    private function ordenConCotizacionAceptada(): OrdenServicio
    {
        $orden = OrdenServicio::factory()->create([
            'estado' => 'cotizacion', 'facturacion' => 'reparacion',
            'cliente_nombre' => 'Aguas Claras SpA', 'cliente_email' => 'cliente@example.com',
            'mano_obra' => 10000,
        ]);
        $orden->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 4000]);
        $c = OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $this->conRol('tecnico'));
        $c->update(['estado' => 'aceptada', 'respondida_at' => now()]);

        return $orden;
    }

    private function autorizar(OrdenServicio $orden, array $data = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->conRol('vendedor'))
            ->post(route('admin.servicio-tecnico.cotizacion.autorizar', $orden), array_merge([
                'pago_forma' => 'transferencia',
            ], $data));
    }

    // --- Permisos ---

    public function test_member_no_puede_autorizar(): void
    {
        $this->autorizar($this->ordenConCotizacionAceptada(), user: $this->conRol('member'))
            ->assertForbidden();
    }

    public function test_vendedor_jefe_ventas_y_tecnico_pueden_autorizar(): void
    {
        foreach (['vendedor', 'jefe_ventas', 'tecnico'] as $rol) {
            $orden = $this->ordenConCotizacionAceptada();
            $this->autorizar($orden, user: $this->conRol($rol))->assertRedirect();
            $this->assertTrue($orden->cotizaciones()->where('estado', 'aceptada')->first()->esta_autorizada, "falló para {$rol}");
        }
    }

    // --- Registro del pago ---

    public function test_exige_forma_de_pago(): void
    {
        $this->autorizar($this->ordenConCotizacionAceptada(), ['pago_forma' => ''])
            ->assertSessionHasErrors('pago_forma');
    }

    public function test_registra_pago_comprobante_y_no_cambia_el_estado_de_la_orden(): void
    {
        $orden = $this->ordenConCotizacionAceptada();

        $this->autorizar($orden, [
            'pago_forma' => 'transferencia',
            'pago_nota' => 'Transferencia recibida, pagó todo',
            'comprobante' => UploadedFile::fake()->image('transfer.jpg', 800, 600),
        ])->assertRedirect();

        $c = $orden->cotizaciones()->where('estado', 'aceptada')->first();
        $this->assertSame('transferencia', $c->pago_forma);
        $this->assertSame('Transferencia recibida, pagó todo', $c->pago_nota);
        $this->assertNotNull($c->pago_comprobante_ruta);
        $this->assertNotNull($c->autorizada_at);
        $this->assertNotNull($c->autorizada_por);
        Storage::disk('local')->assertExists($c->pago_comprobante_ruta);
        // La orden NO cambia de etapa: el técnico decide el siguiente paso.
        $this->assertSame('cotizacion', $orden->fresh()->estado);
    }

    public function test_paga_al_retiro_no_requiere_comprobante(): void
    {
        $orden = $this->ordenConCotizacionAceptada();

        $this->autorizar($orden, ['pago_forma' => 'al_retiro'])->assertRedirect();

        $c = $orden->cotizaciones()->where('estado', 'aceptada')->first();
        $this->assertTrue($c->esta_autorizada);
        $this->assertNull($c->pago_comprobante_ruta);
    }

    // --- Aviso interno ---

    public function test_autorizar_avisa_al_tecnico_y_a_ventas(): void
    {
        $tecnico = $this->conRol('tecnico');
        $jefe = $this->conRol('jefe_ventas');
        $orden = $this->ordenConCotizacionAceptada();

        $this->autorizar($orden, user: $this->conRol('vendedor'));

        foreach ([$tecnico, $jefe] as $u) {
            $this->assertSame(1, Notificacion::where('user_id', $u->id)
                ->where('evento', 'cotizacion.autorizada')
                ->where('canal', Notificacion::CANAL_DATABASE)->count(), "falta campanita de {$u->name}");
        }
    }

    // --- Guardas ---

    public function test_sin_cotizacion_aceptada_no_autoriza(): void
    {
        // Orden con cotización solo ENVIADA (no aceptada).
        $orden = OrdenServicio::factory()->create([
            'estado' => 'cotizacion', 'facturacion' => 'reparacion',
            'cliente_email' => 'x@example.com', 'mano_obra' => 5000,
        ]);
        OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $this->conRol('tecnico'));

        $this->autorizar($orden)->assertRedirect();
        $this->assertSame(0, $orden->cotizaciones()->whereNotNull('autorizada_at')->count());
    }

    public function test_no_re_autoriza_una_ya_autorizada(): void
    {
        $orden = $this->ordenConCotizacionAceptada();
        $this->autorizar($orden, ['pago_forma' => 'efectivo']);
        $primera = $orden->cotizaciones()->where('estado', 'aceptada')->first()->autorizada_at;

        // Segundo intento: no re-escribe.
        $this->autorizar($orden, ['pago_forma' => 'transferencia'])->assertRedirect();

        $c = $orden->cotizaciones()->where('estado', 'aceptada')->first();
        $this->assertEquals($primera, $c->autorizada_at);
        $this->assertSame('efectivo', $c->pago_forma); // conservó la primera
    }
}
