<?php

namespace Tests\Feature\Admin;

use App\Mail\DetalleTrabajoCliente;
use App\Models\OrdenServicio;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\TiempoReparacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cotización = donde se arma lo que paga el cliente. Los precios (repuestos,
 * mano de obra, descuento) se ingresan aquí, no en el parte del técnico. En
 * garantía no se cotiza: se envía el detalle del trabajo sin cobro.
 */
class CotizacionGuardarTest extends TestCase
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
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    private function reparacion(array $overrides = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'facturacion' => 'reparacion',
            'estado' => 'cotizacion',
            'cliente_email' => 'cliente@example.com',
        ], $overrides));
    }

    private function garantiaVigente(array $overrides = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => '123',
            'garantia_doc_fecha' => now()->subMonths(2)->toDateString(),
            'fecha_ingreso' => now()->toDateString(),
            'cliente_email' => 'cliente@example.com',
            'trabajo_realizado' => 'Cambio de caldera — funciona normal',
        ], $overrides));
    }

    /** Valor hora: producto SKU de la config con precio con IVA. */
    private function conValorHora(int $valor = 4000): void
    {
        $p = Producto::factory()->create(['sku' => config('servicio_tecnico.sku_hora_servicio')]);
        Precio::factory()->create(['producto_id' => $p->id, 'precio_con_iva' => $valor]);
    }

    private function tiempo(string $trabajo, float $horas): void
    {
        TiempoReparacion::create(['trabajo' => $trabajo, 'horas' => $horas, 'activo' => true]);
    }

    // --- Guardar precios (reparación) ---

    public function test_guardar_cotizacion_registra_precios_y_mano_de_obra_del_trabajo(): void
    {
        // La mano de obra la FIJA el trabajo (horas estándar × valor hora); lo que
        // se envíe en el form se ignora.
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);   // → 6000
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'mano_obra' => 999999,   // se ignora
                'descuento_pct' => 0,
                'repuestos' => [
                    ['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000],
                    ['nombre' => 'Correa', 'cantidad' => 2, 'precio_unitario' => 5000],
                ],
            ])
            ->assertRedirect(route('admin.servicio-tecnico.cotizacion', $orden));

        $fresh = $orden->fresh()->load('repuestos');
        $this->assertSame(6000, $fresh->mano_obra);          // 1,5h × 4000 (no lo enviado)
        $this->assertCount(2, $fresh->repuestos);
        $this->assertSame(46000, (int) $fresh->costo_total); // 40000 repuestos + 6000
    }

    public function test_guardar_cotizacion_aplica_descuento_con_motivo(): void
    {
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);   // → 6000
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'descuento_pct' => 20,
                'descuento_motivo' => 'cliente_grande',
                'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
            ])
            ->assertRedirect();

        $fresh = $orden->fresh();
        // bruto = 14000 + 6000 = 20000; 20% = 4000; total = 16000
        $this->assertSame(20, $fresh->descuento_pct);
        $this->assertSame('cliente_grande', $fresh->descuento_motivo);
        $this->assertSame(4000, $fresh->descuento_monto);
        $this->assertSame(16000, (int) $fresh->costo_total);
    }

    public function test_reguardar_el_parte_del_tecnico_no_borra_el_descuento(): void
    {
        // El descuento se fija en Cotización; re-guardar el parte del técnico
        // (que no toca el descuento) no debe borrarlo.
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'descuento_pct' => 20,
                'descuento_motivo' => 'cliente_grande',
                'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
            ]);

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'causa_falla' => 'uso_normal',
                'trabajo_realizado' => 'Cambio de caldera — funciona normal',
                'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
            ]);

        $fresh = $orden->fresh();
        $this->assertSame(20, $fresh->descuento_pct);
        $this->assertSame('cliente_grande', $fresh->descuento_motivo);
    }

    public function test_guardar_cotizacion_exige_precio_de_cada_repuesto(): void
    {
        $orden = $this->reparacion();

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'repuestos' => [
                    ['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 0], // sin precio
                ],
            ])
            ->assertSessionHasErrors(['repuestos.0.precio_unitario']);
    }

    public function test_descuento_exige_motivo(): void
    {
        $orden = $this->reparacion();

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'mano_obra' => 10000,
                'descuento_pct' => 20,   // con descuento pero sin motivo
                'repuestos' => [],
            ])
            ->assertSessionHasErrors('descuento_motivo');
    }

    public function test_garantia_no_se_puede_cotizar(): void
    {
        $orden = $this->garantiaVigente();

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'mano_obra' => 15000,
                'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]],
            ])
            ->assertRedirect();

        // No cambió nada: sigue sin mano de obra ni repuestos.
        $this->assertNull($orden->fresh()->mano_obra);
        $this->assertDatabaseMissing('orden_servicio_repuestos', ['orden_servicio_id' => $orden->id, 'nombre' => 'Motor']);
    }

    public function test_sin_permiso_no_puede_guardar(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $this->reparacion()), ['repuestos' => []])
            ->assertForbidden();
    }

    public function test_sin_permiso_no_puede_enviar_detalle(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)
            ->post(route('admin.servicio-tecnico.detalle-trabajo.enviar', $this->garantiaVigente()))
            ->assertForbidden();
    }

    // --- Detalle del trabajo (garantía) ---

    public function test_garantia_envia_el_detalle_del_trabajo_sin_cobro(): void
    {
        $orden = $this->garantiaVigente();
        $orden->repuestos()->create(['nombre' => 'Sensor', 'cantidad' => 1, 'precio_unitario' => 0]);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.detalle-trabajo.enviar', $orden))
            ->assertRedirect();

        Mail::assertSent(DetalleTrabajoCliente::class, fn ($m) => $m->hasTo('cliente@example.com'));
    }

    public function test_reparacion_no_envia_detalle_de_garantia(): void
    {
        $orden = $this->reparacion(['trabajo_realizado' => 'Algo']);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.detalle-trabajo.enviar', $orden))
            ->assertRedirect();

        Mail::assertNotSent(DetalleTrabajoCliente::class);
    }

    public function test_no_envia_detalle_sin_correo(): void
    {
        $orden = $this->garantiaVigente(['cliente_email' => null]);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.detalle-trabajo.enviar', $orden))
            ->assertRedirect();

        Mail::assertNotSent(DetalleTrabajoCliente::class);
    }
}
