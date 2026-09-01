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
 * EL PRESUPUESTO SE GUARDA EN UN SOLO LUGAR: el parte del técnico.
 *
 * Hasta el 20-08-2026 había DOS formularios para el mismo dinero (este archivo se
 * llamaba así por el segundo, `cotizacion.guardar`, que ya no existe). El dueño lo
 * cerró: «que la cotización no tenga opción de modificarse… en la parte del técnico
 * se pueda modificar la información». Así que todo lo que antes se probaba contra
 * esa acción se prueba ahora contra `reparacion.guardar`, que es la única que
 * escribe precios, mano de obra y descuento.
 *
 * La pestaña Cotización quedó como VISTA PREVIA de solo lectura, y eso también se
 * vigila acá: sin filas de repuestos, sin selector de descuento y sin PUT.
 *
 * En garantía no se cotiza: se envía el detalle del trabajo sin cobro.
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

    /**
     * Una orden de reparación. Si su `trabajo_realizado` coincide con una fila del catálogo, ese
     * trabajo queda MARCADO — que es exactamente el estado en que la migración one-shot
     * (2026_08_28_100200) deja a las órdenes que ya existían, y el que produce el formulario
     * cuando el técnico lo marca. Sin esto, una orden con el texto correcto tendría mano de obra
     * $0 y los tests estarían midiendo un estado que la app no produce.
     */
    private function reparacion(array $overrides = []): OrdenServicio
    {
        $orden = OrdenServicio::factory()->create(array_merge([
            'facturacion' => 'reparacion',
            'estado' => 'cotizacion',
            'cliente_email' => 'cliente@example.com',
        ], $overrides));

        $delCatalogo = TiempoReparacion::where('trabajo', $orden->trabajo_realizado)->first();
        if ($delCatalogo) {
            $orden->trabajos()->syncWithoutDetaching([$delCatalogo->id => ['horas' => $delCatalogo->horas]]);
            $orden->load('trabajos');
        }

        return $orden;
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

    /**
     * Pone el trabajo en el catálogo Y LO MARCA en la orden. Desde el 28-08 la mano de obra sale
     * de los trabajos MARCADOS, no de que el texto coincida con el catálogo, así que sembrar el
     * catálogo ya no alcanza para que una orden tenga mano de obra: hay que marcarlo, que es lo
     * que hace el técnico.
     */
    private function tiempo(string $trabajo, float $horas, ?OrdenServicio $orden = null): TiempoReparacion
    {
        $fila = TiempoReparacion::create(['trabajo' => $trabajo, 'horas' => $horas, 'activo' => true]);

        if ($orden) {
            $orden->trabajos()->syncWithoutDetaching([$fila->id => ['horas' => $horas]]);
            $orden->load('trabajos');
        }

        return $fila;
    }

    /**
     * Guarda el presupuesto por la ÚNICA acción que lo escribe: el parte del
     * técnico. `estado` es obligatorio ahí (es el select de la etapa), así que se
     * pasa siempre; el resto del payload es el que traía la cotización.
     *
     * OJO con `trabajos`: el parte los guarda, así que un payload que no los traiga desmarca los
     * trabajos y con ellos la mano de obra. Se mandan los que la orden ya tiene salvo que la
     * prueba diga otra cosa — es lo que hace el formulario real, que reenvía los chips marcados
     * en cada guardado.
     *
     * El TEXTO ya no viaja: desde el 01-09-2026 el parte no lo recibe (lo arma el servidor con
     * los trabajos marcados) y una orden sin trabajos conserva el que ya tenía.
     */
    private function guardarPresupuesto(OrdenServicio $orden, array $payload)
    {
        $trabajo = ['trabajos' => $orden->trabajos->pluck('id')->all()];

        return $this->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            array_merge(['estado' => 'cotizacion'], $trabajo, $payload),
        );
    }

    // --- Guardar precios (reparación) ---

    public function test_guardar_cotizacion_registra_precios_y_mano_de_obra_del_trabajo(): void
    {
        // La mano de obra la FIJA el trabajo (horas estándar × valor hora); lo que
        // se envíe en el form se ignora.
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);   // → 6000
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);

        $this->actingAs($this->tecnico());
        $this->guardarPresupuesto($orden, [
            'mano_obra' => 999999,   // se ignora
            'descuento_pct' => 0,
            'repuestos' => [
                ['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000],
                ['nombre' => 'Correa', 'cantidad' => 2, 'precio_unitario' => 5000],
            ],
        ])
            ->assertSessionHasNoErrors()
            // Se queda en la misma pantalla donde se está trabajando.
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

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
        $this->actingAs($this->jefeVentas());
        $this->guardarPresupuesto($orden, [
            'descuento_pct' => 20,
            'descuento_motivo' => 'cliente_grande',
            'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
        ])->assertSessionHasNoErrors();

        $fresh = $orden->fresh();
        // bruto = 14000 + 6000 = 20000; 20% = 4000; total = 16000
        $this->assertSame(20, $fresh->descuento_pct);
        $this->assertSame('cliente_grande', $fresh->descuento_motivo);
        $this->assertSame(4000, $fresh->descuento_monto);
        $this->assertSame(16000, (int) $fresh->costo_total);
    }

    public function test_reguardar_el_parte_del_tecnico_no_borra_el_descuento(): void
    {
        // El descuento lo fija jefatura; que el técnico re-guarde el parte (donde el
        // selector ni se dibuja para él) no puede borrarlo.
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);

        // Jefatura aplica el descuento…
        $this->actingAs($this->jefeVentas());
        $this->guardarPresupuesto($orden, [
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

    /**
     * EL PRECIO SE EXIGE AL ENVIAR, NO AL GUARDAR (regla del dueño al unificar las
     * pantallas, 20-08-2026). Antes el precio era obligatorio para guardar porque
     * guardar y cotizar eran dos acciones distintas; ahora es la misma, y el técnico
     * anota el repuesto cuando lo pone —con la máquina delante— y le busca el precio
     * después. Lo que no puede salir al cliente es un repuesto en $0: ahí se cobra de
     * menos y nadie lo nota. Mismo criterio que el candado de la mano de obra.
     */
    public function test_el_precio_del_repuesto_se_exige_al_enviar_no_al_guardar(): void
    {
        $orden = $this->reparacion();
        $sinPrecio = ['repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 0]]];

        // Guardar sin precio: se puede, y el repuesto queda registrado.
        $this->actingAs($this->tecnico());
        $this->guardarPresupuesto($orden, $sinPrecio)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orden_servicio_repuestos', [
            'orden_servicio_id' => $orden->id, 'nombre' => 'Motor', 'precio_unitario' => 0,
        ]);

        // Enviárselo al cliente: no, y el error señala ese precio.
        $this->guardarPresupuesto($orden, $sinPrecio + ['enviar' => '1'])
            ->assertSessionHasErrors(['repuestos.0.precio_unitario']);
        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();
    }

    public function test_descuento_exige_motivo(): void
    {
        $orden = $this->reparacion();

        $this->actingAs($this->jefeVentas());
        $this->guardarPresupuesto($orden, [
            'mano_obra' => 10000,
            'descuento_pct' => 20,   // con descuento pero sin motivo
            'repuestos' => [],
        ])->assertSessionHasErrors('descuento_motivo');
    }

    public function test_el_tecnico_no_puede_aplicar_ni_quitar_descuento(): void
    {
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);

        // Jefatura aplica 20%.
        $this->actingAs($this->jefeVentas());
        $this->guardarPresupuesto($orden, [
            'descuento_pct' => 20, 'descuento_motivo' => 'cliente_grande',
            'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
        ]);
        $this->assertSame(20, $orden->fresh()->descuento_pct);

        // El técnico re-guarda el parte intentando QUITARLO → se ignora.
        $this->actingAs($this->tecnico());
        $this->guardarPresupuesto($orden, [
            'descuento_pct' => 0,
            'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
        ]);
        $this->assertSame(20, $orden->fresh()->descuento_pct, 'El técnico no puede quitar el descuento.');

        // Y tampoco puede APLICAR uno nuevo en otra orden sin descuento.
        $orden2 = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);
        $this->guardarPresupuesto($orden2, [
            'descuento_pct' => 15, 'descuento_motivo' => 'cliente_grande',
            'repuestos' => [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 14000]],
        ]);
        $this->assertSame(0, $orden2->fresh()->descuento_pct, 'El técnico no puede aplicar descuento.');
    }

    /**
     * UN CAMPO AUSENTE NO BORRA EL DESCUENTO. El parte de una garantía no dibuja el
     * selector (no hay cobro), así que no lo manda: sin esta guarda, que jefatura
     * guardara ese parte lo borraría en silencio. Quitarlo sigue siendo posible con
     * un 0 explícito, que es lo que manda el selector cuando está en pantalla.
     */
    public function test_guardar_sin_el_campo_de_descuento_lo_conserva(): void
    {
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion([
            'trabajo_realizado' => 'Cambio de caldera — funciona normal',
            'descuento_pct' => 20, 'descuento_motivo' => 'cliente_grande',
        ]);

        // Jefatura guarda SIN mandar descuento_pct → se conserva.
        $this->actingAs($this->jefeVentas());
        $this->guardarPresupuesto($orden, ['repuestos' => []])->assertSessionHasNoErrors();
        $this->assertSame(20, $orden->fresh()->descuento_pct);
        $this->assertSame('cliente_grande', $orden->fresh()->descuento_motivo);

        // Y con un 0 explícito sí lo quita (el motivo no queda colgado).
        $this->guardarPresupuesto($orden, ['repuestos' => [], 'descuento_pct' => 0]);
        $this->assertSame(0, $orden->fresh()->descuento_pct);
        $this->assertNull($orden->fresh()->descuento_motivo);
    }

    /**
     * EL SELECTOR DE DESCUENTO VIVE EN EL PARTE DEL TÉCNICO (dueño 20-08-2026: «que
     * el botón que indica descuento con un dropdown pase a la parte del técnico»), y
     * sigue siendo decisión de jefatura: el técnico ve el aviso, no el selector.
     */
    public function test_el_selector_de_descuento_esta_en_el_parte_y_solo_para_jefatura(): void
    {
        $orden = $this->reparacion(['trabajo_realizado' => 'Algo']);

        // El técnico ve el aviso, no el selector de descuento.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertSee('Solo jefatura de ventas aplica descuentos')
            ->assertDontSee('name="descuento_pct"', false);

        // Jefatura de ventas SÍ ve el selector (no el aviso de bloqueo).
        $this->actingAs($this->jefeVentas())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertDontSee('Solo jefatura de ventas aplica descuentos')
            ->assertSee('name="descuento_pct"', false);
    }

    /**
     * LA PESTAÑA COTIZACIÓN NO EDITA NADA (dueño 20-08-2026). Tres formas de lo
     * mismo, porque una sola se puede satisfacer por accidente: no hay selector de
     * descuento (ni para jefatura), no hay filas de repuestos —«se repite el detalle
     * de los repuestos… sácalo, sino es doble información»— y no existe una acción
     * que guarde por ahí.
     */
    public function test_la_pestana_de_cotizacion_es_solo_lectura(): void
    {
        $orden = $this->reparacion(['trabajo_realizado' => 'Algo']);
        $orden->repuestos()->create(['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]);

        $html = $this->actingAs($this->jefeVentas())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            // El dinero SÍ se ve (es la vista previa de lo que paga el cliente).
            ->assertSee('Costo total a pagar')
            ->assertDontSee('name="descuento_pct"', false)
            ->assertDontSee('Guardar cotización')
            ->getContent();

        // Ni una fila de repuestos editable: los `name="repuestos[…]"` los arma
        // Alpine en el template, así que se busca la forma que emite el partial.
        $this->assertStringNotContainsString('repuestos[${i}]', $html);
        $this->assertStringNotContainsString('reparacionForm(', $html);

        // Y no hay ruta que escriba el presupuesto desde acá.
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('admin.servicio-tecnico.cotizacion.guardar'),
            'Volvió a existir una segunda acción para guardar el presupuesto.',
        );
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
        // Orden con $8.000 GUARDADOS y SIN trabajos marcados: es el caso de una orden histórica
        // cuyo texto no coincidía con ninguna fila del catálogo, así que la migración one-shot
        // no pudo marcarle nada. Antes del 28-08 el escenario equivalente era «el trabajo no
        // tiene tiempo estándar»; la regla que se vigila no cambió — la pantalla no puede
        // prometer un monto que su propio Guardar va a bajar.
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de manilla a medida', 'mano_obra' => 8000]);

        // En el parte (donde se arma): NADA de los $8.000 fósiles, ni sembrados en el x-data ni
        // impresos en el panel del total. El assert es por AUSENCIA del monto viejo y de la
        // siembra: con `manoObra: 8000` de vuelta en el x-data, se pone rojo.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            // `marcados: []` es lo que hace que el getter dé $0, y a diferencia de un texto de
            // la pantalla SÍ discrimina el estado: el markup de los avisos es estático (vive en
            // `<template x-if>`) y está en el HTML se marque o no algo, así que asertarlo sería
            // un verde que pasa siempre — doctrina de la bitácora [2026-07-20].
            ->assertSee('marcados: []', false)
            ->assertDontSee('manoObra:', false)
            ->assertDontSee('8000', false)
            ->assertDontSee('$8.000');

        // Y en la vista previa (donde se lee lo que paga el cliente) lo mismo, pero
        // renderizado en el servidor: $0 y el porqué, nunca los $8.000 fósiles.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertSee('Sin trabajos marcados')
            ->assertDontSee('$8.000');
    }

    /**
     * CAMBIO DE CONDUCTA DELIBERADO (28-08-2026). Este test decía lo contrario: que desactivar
     * el trabajo en el catálogo BAJABA la mano de obra a $0 al re-guardar.
     *
     * Se dio vuelta junto con el paso a trabajos marcados, porque la conducta vieja era un
     * efecto colateral peligroso: tocar el catálogo le cambiaba el precio a órdenes en curso, y
     * un `activo = false` bastaba para que una orden ya cotizada perdiera su mano de obra sin
     * que nadie se enterara — literalmente el disparador del defecto de la bitácora
     * [2026-08-07]. Ahora las horas se congelan en el pivote al marcar, igual que
     * `orden_servicio_repuestos.precio_unitario` no relee el catálogo: la carta que se le mandó
     * al cliente prometió un monto y ese monto se sostiene.
     *
     * Desactivar un trabajo significa «ya no se ofrece», no «lo cobrado estaba mal». Para
     * corregir un monto mal cargado se editan las horas y se re-marca en el parte.
     */
    public function test_desactivar_el_trabajo_en_el_catalogo_no_le_cambia_el_precio_a_una_orden_en_curso(): void
    {
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);
        $repuestos = [['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]];

        $this->actingAs($this->tecnico());
        $this->guardarPresupuesto($orden, ['repuestos' => $repuestos]);
        $this->assertSame(6000, $orden->fresh()->mano_obra);   // 1,5 h × 4000

        // Jefatura saca ese trabajo del catálogo (no se borra: se desactiva)…
        TiempoReparacion::where('trabajo', 'Cambio de caldera — funciona normal')->update(['activo' => false]);

        // …y al re-guardar el monto SE CONSERVA: sigue marcado, con sus horas congeladas.
        $this->guardarPresupuesto($orden->fresh()->load('trabajos'), ['repuestos' => $repuestos]);
        $this->assertSame(6000, $orden->fresh()->mano_obra);
        $this->assertSame(36000, (int) $orden->fresh()->costo_total);

        // Y el trabajo desactivado sigue OFRECIÉNDOSE en esta orden: si la pantalla no lo
        // dibujara, el técnico lo perdería al guardar y ahí sí bajaría el precio en silencio.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertSee('Cambio de caldera');
    }

    /** El envío tampoco se traba por eso: la orden tiene su mano de obra y sale. */
    public function test_un_trabajo_desactivado_no_traba_el_envio_de_la_cotizacion(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->conValorHora(4000);
        $this->tiempo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de caldera — funciona normal', 'mano_obra' => 6000]);
        $orden->repuestos()->create(['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]);

        TiempoReparacion::where('trabajo', 'Cambio de caldera — funciona normal')->update(['activo' => false]);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $this->assertSame(1, OrdenServicioCotizacion::count());
        $this->assertSame(6000, (int) OrdenServicioCotizacion::first()->mano_obra);
    }

    /**
     * NOMBRE VIEJO, CASO NUEVO. Hasta el 28-08 la mano de obra salía de que el TEXTO del trabajo
     * coincidiera con una fila del catálogo, así que «el trabajo no tiene tiempo estándar» era un
     * estado alcanzable. Con los chips ya no: un trabajo marcado viene del catálogo y por
     * definición tiene horas. Lo que queda —y es lo que este test fija— es la otra mitad de la
     * misma regla: sin NINGÚN trabajo marcado no hay mano de obra, y la cotización no sale.
     *
     * OJO CON EL ASSERT DE PANTALLA: hasta el 01-09 buscaba «no tiene tiempo estándar» y pasaba
     * por la razón equivocada — esa cadena la aportaba la AYUDA del campo «algo que no está en la
     * lista», otro control, no el mensaje del servidor. Al retirarse ese campo con el cambio de
     * «el técnico no escribe», el verde-engañoso quedó a la vista (doctrina, bitácora
     * [2026-07-20]). Ahora se assertea el motivo que emite `faltaManoObra()`.
     */
    public function test_no_se_envia_la_cotizacion_sin_ningun_trabajo_marcado(): void
    {
        $this->conValorHora(4000);
        // Hay costo (repuestos), así que el bloqueo por «suma $0» no aplica — lo que frena el
        // envío es la mano de obra sin calcular.
        $orden = $this->reparacion(['trabajo_realizado' => 'Cambio de manilla a medida', 'mano_obra' => 8000]);
        $orden->repuestos()->create(['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();

        // Y la pantalla dice lo mismo que el servidor: la falta se lista donde vive el
        // botón «Enviar» (el pie del PARTE, misma fila que «Guardar»), que no se
        // dibuja. Se mira ahí y no en la cotización: esa pestaña ya no tiene botón de
        // enviar en ningún caso, así que un `assertDontSee('name="enviar"')` contra
        // ella pasaría siempre — un candado inerte.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertSee('Para enviarla al cliente')
            ->assertSee('marca en «Trabajo realizado» al menos un trabajo de la lista')
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

        // Las dos pantallas nombran el código de la hora en vez de mostrar «1,5 h × —»
        // junto a un monto que el guardado ya no puede sostener.
        // El assert del monto pasó de «la siembra dice 0» a «NO se siembra ningún monto»: desde
        // el 28-08 la mano de obra es un getter derivado de los trabajos marcados, así que ya no
        // hay un número que sembrar mal. Se vigila la ausencia de la siembra, que es el fix
        // estructural — con `manoObra:` de vuelta en el x-data, este assert se pone rojo.
        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertSee('no tiene precio en la lista oficial de ventas')
            ->assertDontSee('manoObra:', false)
            ->assertDontSee('$6.000');

        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertSee('no tiene precio en la lista oficial de ventas')
            ->assertDontSee('$6.000');
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

    /**
     * GARANTÍA NO SE COBRA, así que su parte no pide ni muestra dinero: solo QUÉ
     * repuestos se usaron. Es el defecto que trajo unificar las pantallas — el
     * presupuesto entró al parte sin condición y una garantía mostraba «Costo total a
     * pagar», que contradice al resto de la app y al correo que recibe el cliente
     * (repuestos sin precios).
     */
    public function test_el_parte_de_una_garantia_no_muestra_precios_ni_total(): void
    {
        $orden = $this->garantiaVigente();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            // Los repuestos sí se registran (van en el detalle del trabajo).
            ->assertSee('Repuestos usados')
            ->assertSee('Garantía: no se cobra')
            ->assertDontSee('Costo total a pagar')
            ->assertDontSee('Mano de obra (fijada por el trabajo)')
            ->assertDontSee('name="descuento_pct"', false)
            ->getContent();

        // Y ninguna casilla de precio: el precio existente viaja en un hidden para no
        // perderlo, así que se busca el INPUT visible, no el nombre del campo.
        $this->assertStringNotContainsString('placeholder="Precio"', $html);
        $this->assertStringContainsString('repuestos[${i}][nombre]', $html);
    }

    public function test_garantia_no_se_puede_cotizar(): void
    {
        $orden = $this->garantiaVigente();
        $orden->repuestos()->create(['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000]);

        // Aunque la orden tenga repuestos con precio (quedaron de cuando se creía
        // reparación), no sale ninguna cotización al cliente.
        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();
    }

    public function test_sin_permiso_no_puede_guardar(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $this->reparacion()), [
                'estado' => 'cotizacion', 'repuestos' => [],
            ])
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
