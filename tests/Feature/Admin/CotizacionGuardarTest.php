<?php

namespace Tests\Feature\Admin;

use App\Mail\DetalleTrabajoCliente;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\TiempoReparacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
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

    /** Jefatura de ventas: gestiona el taller y ADEMÁS puede aplicar descuentos. */
    private function jefeVentas(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_ventas');
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

        // El descuento lo aplica jefatura de ventas (el técnico no está autorizado).
        $this->actingAs($this->jefeVentas())
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

        // Jefatura aplica el descuento…
        $this->actingAs($this->jefeVentas())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'descuento_pct' => 20,
                'descuento_motivo' => 'cliente_grande',
                'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
            ]);

        // …y el técnico re-guarda su parte (no toca el descuento).
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

        $this->actingAs($this->jefeVentas())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'mano_obra' => 10000,
                'descuento_pct' => 20,   // con descuento pero sin motivo
                'repuestos' => [],
            ])
            ->assertSessionHasErrors('descuento_motivo');
    }

    public function test_el_tecnico_no_puede_aplicar_ni_quitar_descuento(): void
    {
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);

        // Jefatura aplica 20%.
        $this->actingAs($this->jefeVentas())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'descuento_pct' => 20, 'descuento_motivo' => 'cliente_grande',
                'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
            ]);
        $this->assertSame(20, $orden->fresh()->descuento_pct);

        // El técnico re-guarda la cotización intentando QUITARLO → se ignora.
        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), [
                'descuento_pct' => 0,
                'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
            ]);
        $this->assertSame(20, $orden->fresh()->descuento_pct, 'El técnico no puede quitar el descuento.');

        // Y tampoco puede APLICAR uno nuevo en otra orden sin descuento.
        $orden2 = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);
        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden2), [
                'descuento_pct' => 15, 'descuento_motivo' => 'cliente_grande',
                'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
            ]);
        $this->assertSame(0, $orden2->fresh()->descuento_pct, 'El técnico no puede aplicar descuento.');
    }

    public function test_la_vista_de_cotizacion_oculta_el_descuento_al_tecnico(): void
    {
        $orden = $this->reparacion(['trabajo_realizado' => 'Algo']);

        // El técnico ve el aviso, no el selector de descuento.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertSee('Solo jefatura de ventas aplica descuentos');

        // Jefatura de ventas SÍ ve el selector (no el aviso de bloqueo).
        $this->actingAs($this->jefeVentas())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertDontSee('Solo jefatura de ventas aplica descuentos')
            ->assertSee('name="descuento_pct"', false);
    }

    // --- Mano de obra que el catálogo no puede calcular (regla del dueño 07-08-2026) ---
    //
    // La mano de obra es DERIVADA del catálogo, así que la pantalla muestra la
    // VIGENTE (lo que va a quedar al guardar), nunca la guardada: prometer un monto
    // que el guardado baja a $0 es lo que hacía antes. Y como el guardado la baja a
    // $0, lo que protege al cliente de un cobro de menos es el candado del ENVÍO —
    // no conservar un monto fósil que nadie puede explicar.

    public function test_la_pantalla_muestra_la_mano_de_obra_vigente_no_la_guardada(): void
    {
        $this->conValorHora(4000);
        // Orden con $8.000 GUARDADOS pero cuyo trabajo no tiene tiempo estándar
        // (jefatura lo desactivó, o es un texto histórico fuera del catálogo).
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de manilla a medida', 'mano_obra' => 8000]);

        $res = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertSee('no tiene tiempo estándar');

        // El panel «Costo total a pagar» se siembra con lo que se va a guardar ($0),
        // no con los $8.000 viejos (si se refactoriza la siembra de Alpine, lo que
        // hay que conservar es que el total mostrado sea el vigente).
        $res->assertSee('manoObra: 0', false);
        $res->assertDontSee('manoObra: 8000', false);
    }

    public function test_desactivar_el_tiempo_estandar_baja_la_mano_de_obra_al_guardar(): void
    {
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);
        $repuestos = [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]];

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), ['repuestos' => $repuestos]);
        $this->assertSame(6000, $orden->fresh()->mano_obra);   // 1,5 h × 4000

        // Jefatura saca ese trabajo del catálogo (no se borra: se desactiva)…
        TiempoReparacion::where('trabajo', 'Cambio de caldera — funciona normal')->update(['activo' => false]);

        // …y al re-guardar la mano de obra baja a $0: NO se conserva el monto viejo.
        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.cotizacion.guardar', $orden), ['repuestos' => $repuestos]);
        $this->assertSame(0, $orden->fresh()->mano_obra);
        $this->assertSame(30000, (int) $orden->fresh()->costo_total);
    }

    public function test_no_se_envia_la_cotizacion_si_el_trabajo_no_tiene_tiempo_estandar(): void
    {
        $this->conValorHora(4000);
        // Trabajo fuera del catálogo: hay costo (repuestos), así que el bloqueo por
        // «suma $0» no aplica — lo que frena el envío es la mano de obra sin calcular.
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de manilla a medida', 'mano_obra' => 8000]);
        $orden->repuestos()->create(['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();

        // Y la pantalla dice lo mismo que el servidor: la falta se lista donde
        // vive el botón «Enviar» (misma fila que «Guardar»), que no se dibuja.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertSee('Para enviarla al cliente')
            ->assertSee('no tiene tiempo estándar')
            ->assertDontSee('name="enviar"', false);
    }

    public function test_no_se_envia_la_cotizacion_sin_valor_hora(): void
    {
        // Tiempo estándar cargado, pero el SKU de la hora no tiene precio en la
        // lista oficial (sin conValorHora) → tampoco se puede calcular.
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal', 'mano_obra' => 6000]);
        $orden->repuestos()->create(['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();

        // La pantalla nombra el código de la hora en vez de mostrar «1,5 h × —»
        // junto a un monto que el guardado ya no puede sostener.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertSee('no tiene precio en la lista oficial de ventas')
            ->assertSee('manoObra: 0', false);
    }

    public function test_cero_horas_fijadas_por_jefatura_si_se_puede_enviar(): void
    {
        // El catálogo acepta 0 h: es una decisión de jefatura («este trabajo no se
        // cobra»), NO un hueco de datos. La mano de obra es $0 legítima y el envío
        // pasa — el candado es «no se puede calcular», no «da $0».
        $this->seed(ConfiguracionSeeder::class);
        $this->conValorHora(4000);
        $this->tiempo('Revisión general, se deja en observación y no presenta fallas — funciona normal', 0);
        $orden = $this->reparacion([
            'trabajo_realizado' => 'Revisión general, se deja en observación y no presenta fallas — funciona normal',
        ]);
        $orden->repuestos()->create(['nombre' => 'Filtro', 'cantidad' => 1, 'precio_unitario' => 12000]);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $this->assertSame(1, OrdenServicioCotizacion::count());
        $this->assertSame(0, (int) OrdenServicioCotizacion::first()->mano_obra);
        $this->assertSame(12000, (int) OrdenServicioCotizacion::first()->costo_total);
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

    public function test_garantia_avisa_por_la_campanita_al_enviar_el_detalle(): void
    {
        // Dueño 06-08: la ruta de la máquina debe quedar en la campanita también
        // cuando es garantía (el par de 'cotizacion.enviada' sin cobro).
        $jefe = $this->jefeVentas();
        $orden = $this->garantiaVigente(['tipo_equipo' => 'lavadora', 'modelo' => 'LB-07B']);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.detalle-trabajo.enviar', $orden))
            ->assertRedirect();

        $notif = Notificacion::where('user_id', $jefe->id)
            ->where('evento', 'garantia.detalle_enviado')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();
        $this->assertNotNull($notif, 'Falta la campanita de garantía para jefatura de ventas.');
        $this->assertStringContainsString('LB-07B', $notif->payload['equipo']);
    }

    public function test_si_el_detalle_no_sale_no_hay_campanita_de_garantia(): void
    {
        // Sin correo del cliente el detalle no se envía → tampoco se avisa que
        // «se envió» (el aviso mentiría, defecto ya corregido en terreno el 30-07).
        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.detalle-trabajo.enviar', $this->garantiaVigente(['cliente_email' => null])))
            ->assertRedirect();

        $this->assertSame(0, Notificacion::where('evento', 'garantia.detalle_enviado')->count());
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
