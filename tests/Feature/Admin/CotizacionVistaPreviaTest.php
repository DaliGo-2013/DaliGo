<?php

namespace Tests\Feature\Admin;

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
 * VER LA CARTA ANTES DE MANDARLA.
 *
 * Pedido del dueño (20-08-2026): «cuando uno aprieta enviar una cotización nueva, ¿hay alguna
 * posibilidad que haya una ventana previa donde se vea la cotización y después se pueda enviar
 * apretando un botón en esa vista previa?».
 *
 * Antes el botón mandaba la carta detrás de un `confirm()` del navegador, que solo sabía decir
 * un número («se enviará por $20.986, ¿continuar?»). El total puede estar bien y la carta mal:
 * el trabajo con una falta de ortografía, un repuesto que sobra, el diagnóstico en blanco. Ahora
 * el botón GUARDA y abre la carta; el envío sale de ahí.
 *
 * LA VISTA PREVIA NO PUEDE SER UNA MAQUETA PARECIDA: sale del mismo snapshot
 * (`OrdenServicioCotizacion::datosDesde`) y de la misma plantilla del correo. Dibujada aparte
 * mostraría un total y el cliente recibiría otro — el último candado de este archivo es el que
 * ata las dos cosas.
 */
class CotizacionVistaPreviaTest extends TestCase
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
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    /** Valor hora + tiempo estándar que explican la mano de obra (sin esto el envío se bloquea). */
    private function conCatalogoDeManoObra(string $trabajo): void
    {
        $producto = Producto::firstOrCreate(
            ['sku' => config('servicio_tecnico.sku_hora_servicio')],
            Producto::factory()->raw(['sku' => config('servicio_tecnico.sku_hora_servicio')]),
        );
        if (! $producto->precios()->exists()) {
            Precio::factory()->create(['producto_id' => $producto->id, 'precio_con_iva' => 4000]);
        }
        TiempoReparacion::firstOrCreate(['trabajo' => $trabajo], ['horas' => 2.5, 'activo' => true]);
    }

    private function ordenCotizable(): OrdenServicio
    {
        $trabajo = 'Cambio de caldera — funciona normal';
        $this->conCatalogoDeManoObra($trabajo);

        $orden = OrdenServicio::factory()->create([
            'estado' => 'cotizacion',
            'facturacion' => 'reparacion',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_email' => 'cliente@example.com',
            'mano_obra' => 10000,
            'causa_falla' => 'uso_normal',
            'trabajo_realizado' => $trabajo,
        ]);
        $orden->repuestos()->create(['nombre' => 'Caldera nueva', 'cantidad' => 1, 'precio_unitario' => 4000]);

        return $orden->fresh();
    }

    /** El submit del botón «Revisar y enviar»: guarda igual que antes, pero no manda nada. */
    private function revisar(OrdenServicio $orden, array $extra = [])
    {
        return $this->actingAs($this->tecnico())->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            array_merge([
                'estado' => 'cotizacion',
                'trabajo_realizado' => $orden->trabajo_realizado,
                'causa_falla' => 'uso_normal',
                'repuestos' => [['nombre' => 'Caldera nueva', 'cantidad' => 1, 'precio_unitario' => 4000]],
                'previsualizar' => '1',
            ], $extra),
        );
    }

    // ─────────────────────────────────────────── el botón ya no manda la carta

    public function test_revisar_guarda_pero_no_le_manda_nada_al_cliente(): void
    {
        $orden = $this->ordenCotizable();

        $this->revisar($orden, ['trabajo_realizado' => 'Cambio de caldera y limpieza — funciona normal'])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertSessionHas('cotizacion_previa');

        // Se guardó…
        $this->assertSame('Cambio de caldera y limpieza — funciona normal', $orden->fresh()->trabajo_realizado);
        // …y no salió ninguna carta ni quedó cotización enviada.
        Mail::assertNothingSent();
        $this->assertSame(0, OrdenServicioCotizacion::count());
    }

    /** Un repuesto sin precio se sigue frenando ACÁ, antes de ver la carta, no después. */
    public function test_revisar_exige_los_precios_igual_que_enviar(): void
    {
        $orden = $this->ordenCotizable();

        $this->revisar($orden, ['repuestos' => [['nombre' => 'Caldera nueva', 'cantidad' => 1, 'precio_unitario' => 0]]])
            ->assertSessionHasErrors('repuestos.0.precio_unitario');
    }

    // ─────────────────────────────────────────── la ventana

    public function test_la_pantalla_abre_la_ventana_con_la_carta_y_el_boton_de_enviar(): void
    {
        $orden = $this->ordenCotizable();

        $html = $this->actingAs($this->tecnico())
            ->withSession(['cotizacion_previa' => true])
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Así la va a ver el cliente', $html);
        // La carta se muestra EN UN IFRAME contra la ruta de la vista previa: es la plantilla
        // del correo, no una maqueta escrita en esta pantalla.
        $this->assertStringContainsString(route('admin.servicio-tecnico.cotizacion.previa', $orden), $html);
        // Y el botón de enviar es el POST de siempre.
        $this->assertStringContainsString(route('admin.servicio-tecnico.cotizacion.enviar', $orden), $html);
        $this->assertStringContainsString('Enviar al cliente', $html);
    }

    /** Sin la bandera la ventana no se abre sola (se entra a la pantalla a hacer otra cosa). */
    public function test_sin_venir_de_revisar_la_ventana_no_se_abre(): void
    {
        $orden = $this->ordenCotizable();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->getContent();

        // El modal está en el HTML pero arranca cerrado.
        $this->assertStringContainsString('Así la va a ver el cliente', $html);
        $this->assertStringContainsString('show: false', $html);
    }

    // ─────────────────────────────────────────── la carta de la vista previa

    public function test_la_vista_previa_muestra_la_carta_con_los_numeros_de_la_orden(): void
    {
        $orden = $this->ordenCotizable();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion.previa', $orden))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Cotización de reparación', $html);
        $this->assertStringContainsString('Aguas Claras SpA', $html);
        $this->assertStringContainsString('Caldera nueva', $html);
        $this->assertStringContainsString('$'.number_format((int) $orden->costo_total, 0, ',', '.'), $html);
    }

    /** Y no deja rastro: es un borrador en memoria, no reemplaza la cotización vigente. */
    public function test_la_vista_previa_no_escribe_nada_en_la_base(): void
    {
        $orden = $this->ordenCotizable();

        $this->actingAs($this->tecnico())->get(route('admin.servicio-tecnico.cotizacion.previa', $orden))->assertOk();

        $this->assertSame(0, OrdenServicioCotizacion::count());
    }

    /**
     * EL LINK DE RESPUESTA VA INERTE: en la vista previa todavía no hay token, y una carta de
     * prueba con un link válido sería una cotización aceptable sin haberla enviado.
     */
    public function test_la_vista_previa_no_lleva_un_link_de_respuesta_valido(): void
    {
        $orden = $this->ordenCotizable();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion.previa', $orden))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('/cotizacion/responder', $html);
        $this->assertStringContainsString('href="#"', $html);
    }

    public function test_sin_permiso_no_se_puede_espiar_la_carta(): void
    {
        $orden = $this->ordenCotizable();
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)
            ->get(route('admin.servicio-tecnico.cotizacion.previa', $orden))
            ->assertRedirect();
    }

    /**
     * EL DIAGNÓSTICO SE LE MUESTRA EN CASTELLANO. Lo encontró la vista previa el primer día:
     * la carta decía «Diagnóstico del técnico: uso_normal» —la clave del enum tal cual— y el
     * cliente leía eso. Vale para el correo y para la página pública, que comparten el dato.
     */
    public function test_la_carta_dice_el_diagnostico_en_castellano(): void
    {
        $orden = $this->ordenCotizable();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion.previa', $orden))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Desgaste por uso normal', $html);
        $this->assertStringNotContainsString('uso_normal', $html);
    }

    /**
     * Y un diagnóstico VIEJO de texto libre se imprime tal cual: esas cotizaciones guardaron
     * frases («Filtración interna») y para el cliente eso sí es información. Mapearlas a «Sin
     * determinar» —lo que hace el accessor de la orden— sería borrarle el diagnóstico.
     */
    public function test_un_diagnostico_viejo_de_texto_libre_no_se_pierde(): void
    {
        $orden = $this->ordenCotizable();
        $orden->update(['causa_falla' => 'Filtración interna']);

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion.previa', $orden->fresh()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Filtración interna', $html);
        $this->assertStringNotContainsString('Sin determinar', $html);
    }
    // ─────────────────────────────────────────── el candado que ata las dos cosas

    /**
     * LO QUE SE VE ES LO QUE SE MANDA. Se previsualiza, se envía, y los montos de la carta que
     * se mostró tienen que ser los de la cotización que quedó guardada. Si alguien hace que la
     * vista previa calcule por su cuenta, esto se pone rojo — es el único defecto grave posible
     * acá: aprobar una carta y que salga otra.
     */
    public function test_lo_que_muestra_la_vista_previa_es_lo_que_se_envia(): void
    {
        $orden = $this->ordenCotizable();
        $tecnico = $this->tecnico();

        $previa = $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.cotizacion.previa', $orden))
            ->assertOk()
            ->getContent();

        $this->actingAs($tecnico)
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $enviada = OrdenServicioCotizacion::firstOrFail();

        foreach ([$enviada->costo_total, $enviada->mano_obra, $enviada->costo_repuestos] as $monto) {
            $this->assertStringContainsString(
                '$'.number_format((int) $monto, 0, ',', '.'),
                $previa,
                'La carta que se mostró no trae un monto de la cotización que salió: la vista previa y el envío se desincronizaron.',
            );
        }
        $this->assertStringContainsString($enviada->trabajo_realizado, $previa);
    }
}
