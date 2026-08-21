<?php

namespace Tests\Feature\Admin;

use App\Mail\CotizacionCliente;
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
 * Envío de la cotización al cliente desde la pantalla de reparación (P-M12-02):
 * snapshot congelado, carta por correo (acción secundaria), reemplazo de la
 * anterior al re-enviar y aviso interno a los roles del taller/ventas.
 */
class CotizacionEnviarTest extends TestCase
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

    /**
     * Orden cotizable: reparación (se cobra), en etapa cotización, con email y costo.
     * La mano de obra guardada ($10.000) tiene detrás el catálogo que la explica
     * (2,5 h × $4.000): sin eso el envío se bloquea, porque una cotización cuya mano
     * de obra el catálogo no puede calcular cobraría de menos (ver
     * CotizacionGuardarTest, «mano de obra sin tiempo estándar»).
     */
    private function ordenCotizable(array $overrides = []): OrdenServicio
    {
        $this->conCatalogoDeManoObra();

        $orden = OrdenServicio::factory()->create(array_merge([
            'estado' => 'cotizacion',
            'facturacion' => 'reparacion',
            'cliente_email' => 'cliente@example.com',
            'mano_obra' => 10000,
            // A PROPÓSITO fuera de la lista de causas: es un dato HISTÓRICO (texto
            // libre de antes de que existiera el enum, o un valor renombrado). Las
            // pantallas tienen que resolverlo por el accessor y mostrar «Sin
            // determinar»; indexando la constante daban 500 —así se descubrió, al
            // pasar el envío al parte del técnico, que sí muestra la causa.
            'causa_falla' => 'Filtración interna',
            'trabajo_realizado' => 'Cambio de caldera — funciona normal',
        ], $overrides));
        $orden->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 4000]);

        return $orden;
    }

    /** Valor hora + tiempo estándar que explican los $10.000 (idempotente). */
    private function conCatalogoDeManoObra(): void
    {
        $producto = Producto::firstOrCreate(
            ['sku' => config('servicio_tecnico.sku_hora_servicio')],
            Producto::factory()->raw(['sku' => config('servicio_tecnico.sku_hora_servicio')]),
        );
        if (! $producto->precios()->exists()) {
            Precio::factory()->create(['producto_id' => $producto->id, 'precio_con_iva' => 4000]);
        }

        TiempoReparacion::firstOrCreate(
            ['trabajo' => 'Cambio de caldera — funciona normal'],
            ['horas' => 2.5, 'activo' => true],
        );
    }

    private function enviar(OrdenServicio $orden, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden));
    }

    /**
     * Guarda el parte del técnico —la única acción que escribe el presupuesto desde
     * el 20-08-2026— con `enviar=1` cuando corresponda. `estado` y el trabajo van
     * siempre: el parte los guarda, así que omitirlos apagaría la mano de obra y el
     * envío se bloquearía por otra razón que la que prueba el test.
     */
    private function guardarParte(OrdenServicio $orden, array $payload)
    {
        // Sin trabajo no se manda el centinela: `trabajo_realizado_otro` sería
        // obligatorio y el test fallaría por un error de validación en vez de por lo
        // que prueba.
        $trabajo = blank($orden->trabajo_realizado) ? [] : [
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => $orden->trabajo_realizado,
        ];

        return $this->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            array_merge(['estado' => $orden->estado], $trabajo, $payload),
        );
    }

    // --- Acceso ---

    public function test_vendedor_sin_manage_no_puede_enviar(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->actingAs($vendedor)
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $this->ordenCotizable()))
            ->assertForbidden();
    }

    // --- Envío ---

    public function test_envia_cotizacion_con_snapshot_y_correo(): void
    {
        $orden = $this->ordenCotizable();

        $this->enviar($orden)->assertRedirect();

        $c = OrdenServicioCotizacion::first();
        $this->assertSame('enviada', $c->estado);
        $this->assertSame('cliente@example.com', $c->cliente_email);
        $this->assertSame(14000, $c->costo_total);           // 4000 repuesto + 10000 mano de obra
        $this->assertSame('Filtración interna', $c->causa_falla);
        $this->assertNotNull($c->correo_enviado_at);
        $this->assertNotNull($c->vence_at);                   // vigencia por Configuracion
        $this->assertSame(64, strlen($c->token));
        $this->assertSame([['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 4000, 'subtotal' => 4000]], $c->repuestos);

        Mail::assertSent(CotizacionCliente::class, fn ($m) => $m->hasTo('cliente@example.com'));
    }

    public function test_snapshot_queda_congelado_si_editan_la_orden(): void
    {
        $orden = $this->ordenCotizable();
        $this->enviar($orden);

        // El técnico renegocia DESPUÉS: el snapshot enviado no cambia.
        $orden->update(['mano_obra' => 99999]);

        $this->assertSame(14000, OrdenServicioCotizacion::first()->costo_total);
    }

    public function test_snapshot_desglosa_neto_e_iva_del_total(): void
    {
        // El total del snapshot ya viene con IVA (14000): neto + IVA = total.
        $this->enviar($this->ordenCotizable());

        $c = OrdenServicioCotizacion::first();
        $this->assertSame(14000, (int) $c->costo_total);
        $this->assertSame(11765, $c->costo_neto);   // 14000 / 1,19
        $this->assertSame(2235, $c->costo_iva);      // total − neto
        $this->assertSame((int) $c->costo_total, $c->costo_neto + $c->costo_iva);
    }

    public function test_reenvio_reemplaza_la_anterior_pero_no_las_respondidas(): void
    {
        $orden = $this->ordenCotizable();
        $this->enviar($orden);
        OrdenServicioCotizacion::first()->update(['estado' => 'aceptada', 'respondida_at' => now()]);

        $this->enviar($orden); // 2ª (queda enviada)
        $this->enviar($orden); // 3ª: reemplaza a la 2ª, no toca la aceptada

        $estados = OrdenServicioCotizacion::orderBy('id')->pluck('estado')->all();
        $this->assertSame(['aceptada', 'reemplazada', 'enviada'], $estados);
    }

    // --- Bloqueos (validación server-side del botón) ---

    public function test_no_envia_sin_email_ni_costo_ni_etapa_ni_garantia(): void
    {
        // Sin email.
        $this->enviar($this->ordenCotizable(['cliente_email' => null]));
        // Etapa POSTERIOR a cotización (las previas ya no bloquean: ver el test
        // de abajo — dueño 06-08).
        $this->enviar($this->ordenCotizable(['estado' => 'reparado']));
        // Garantía vigente (no se cobra).
        $this->enviar($this->ordenCotizable([
            'facturacion' => 'garantia', 'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => '123', 'garantia_doc_fecha' => now()->subMonth()->toDateString(),
        ]));
        // Costo $0.
        $sinCosto = OrdenServicio::factory()->create([
            'estado' => 'cotizacion', 'facturacion' => 'reparacion',
            'cliente_email' => 'x@example.com', 'mano_obra' => 0,
        ]);
        $this->enviar($sinCosto);

        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();
    }

    public function test_el_boton_enviar_guarda_los_precios_de_la_pantalla_y_manda_eso(): void
    {
        // Dueño 07-08: «Enviar» quedó al lado de «Guardar», así que es un submit
        // del MISMO formulario (enviar=1). Tiene que guardar primero: lo que sale
        // al cliente es lo de la pantalla, no el snapshot viejo — con los botones
        // pegados, mandar precios viejos sin darse cuenta era demasiado fácil.
        $orden = $this->ordenCotizable();
        $orden->repuestos()->delete();
        $orden->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 4000]);
        // Mano de obra fija del catálogo (como en producción): 1,5 h × $4.000. El
        // valor hora ya lo sembró ordenCotizable() —sin catálogo no se puede
        // enviar—, así que acá solo se ajustan las horas de este caso.
        TiempoReparacion::updateOrCreate(
            ['trabajo' => $orden->trabajo_realizado],
            ['horas' => 1.5, 'activo' => true],
        );

        $this->actingAs($this->tecnico());
        $this->guardarParte($orden, [
            'enviar' => '1',
            'descuento_pct' => 0,
            // El técnico acaba de corregir el precio en pantalla: 4000 → 7000.
            'repuestos' => [['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 7000]],
        ])
            ->assertSessionHasNoErrors()
            // Vuelve al parte, que es de donde se envió Y donde quedó la constancia
            // (dueño 20-08: el historial se mudó ahí desde la pestaña Cotización).
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

        $c = OrdenServicioCotizacion::first();
        $this->assertNotNull($c, 'El botón «Enviar» debe crear la cotización, no solo guardar.');
        $this->assertSame(7000, $c->repuestos[0]['precio_unitario']); // el precio NUEVO, no el viejo
        $this->assertSame(6000, (int) $c->mano_obra);                 // la mano de obra fija sobrevive
        $this->assertSame(13000, (int) $c->costo_total);
        Mail::assertSent(CotizacionCliente::class, fn ($m) => $m->hasTo('cliente@example.com'));
    }

    public function test_guardar_sin_el_boton_enviar_no_manda_nada(): void
    {
        // El mismo formulario sin enviar=1 solo guarda: dos botones vecinos, dos
        // efectos bien distintos.
        $orden = $this->ordenCotizable();

        $this->actingAs($this->tecnico());
        $this->guardarParte($orden, [
            'descuento_pct' => 0,
            'repuestos' => [['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 7000]],
        ])->assertRedirect();

        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();
    }

    public function test_enviar_con_todo_en_cero_no_crea_cotizacion_y_lo_guardado_queda(): void
    {
        // Sin repuestos ni mano de obra el envío no procede, pero el guardado sí:
        // el técnico no pierde lo que escribió (mensaje explicativo en pantalla).
        $orden = OrdenServicio::factory()->create([
            'estado' => 'cotizacion', 'facturacion' => 'reparacion',
            'cliente_email' => 'x@example.com', 'mano_obra' => 0,
        ]);

        $this->actingAs($this->tecnico());
        $this->guardarParte($orden, ['enviar' => '1', 'descuento_pct' => 0, 'repuestos' => []])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertSessionHas('status', fn (string $s) => str_contains($s, '$0'));

        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();
    }

    public function test_los_dos_botones_van_juntos_y_la_tarjeta_de_envio_no_ocupa_espacio_de_mas(): void
    {
        // Candado de layout (dueño 07-08 y 20-08): el botón que lleva al envío y «Guardar»
        // en la MISMA fila del formulario —el del PARTE DEL TÉCNICO, que es donde el dueño
        // pidió el botón— y sin nada enviado la constancia no se dibuja.
        //
        // Desde el 20-08 ese botón se llama «Revisar y enviar» y manda `previsualizar`: abre
        // la carta y el envío sale de ahí (CotizacionVistaPreviaTest). Lo que este candado
        // vigila no cambió: que no vuelva a ser un <form> aparte en su propia tarjeta.
        $orden = $this->ordenCotizable();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertSee('Revisar y enviar cotización')
            ->assertDontSee('Enviada al cliente')   // nada enviado todavía → sin tarjeta
            // La causa histórica de la orden no está en la lista: se muestra, no revienta.
            ->assertSee('Sin determinar')
            ->getContent();

        // El botón de enviar es un submit del formulario que GUARDA (el del
        // @method PUT), no un <form> aparte: si alguien lo vuelve a separar en su
        // propia tarjeta, esto se cae.
        $enviar = strpos($html, 'name="previsualizar"');
        $this->assertNotFalse($enviar, 'Falta el botón «Revisar y enviar» dentro del formulario de guardar.');

        $abre = strrpos(substr($html, 0, $enviar), '<form');
        $tramo = substr($html, $abre, $enviar - $abre);
        $this->assertStringContainsString('PUT', $tramo, 'El botón «Revisar y enviar» debe vivir en el formulario que guarda (PUT).');
        $this->assertStringNotContainsString('</form>', $tramo, 'Volvieron a separar el botón en su propio formulario.');

        // Y «Guardar» va DESPUÉS de «Enviar» pero ANTES de que cierre el formulario:
        // misma fila, mismo form. (Se mide contra el cierre y no contra una posición
        // absoluta porque abajo, fuera del form, hay más botones.)
        // Se busca en el tramo POSTERIOR a «Enviar» (así un «Guardar» de otro texto
        // más arriba —el aviso ámbar dice «Guardar sí se puede»— no lo satisface).
        $resto = substr($html, $enviar);
        $guardar = strpos($resto, 'Guardar');
        $cierra = strpos($resto, '</form>');
        $this->assertNotFalse($guardar, 'Falta el botón «Guardar» después del de revisar.');
        $this->assertLessThan($cierra, $guardar, 'Los dos botones tienen que quedar en la misma fila del formulario.');
    }

    public function test_enviar_desde_una_etapa_previa_pasa_la_orden_a_cotizacion(): void
    {
        // Dueño 06-08: enviar la carta ES pasar el presupuesto — la orden salta
        // sola a «Cotización» sin peregrinar por Parte del técnico. Antes este
        // caso ('en_revision') estaba en el test de bloqueos.
        $orden = $this->ordenCotizable(['estado' => 'en_revision']);

        $this->enviar($orden)->assertRedirect();

        $this->assertSame('cotizacion', $orden->fresh()->estado);
        $this->assertSame(1, OrdenServicioCotizacion::count());
        Mail::assertSent(CotizacionCliente::class, fn ($m) => $m->hasTo('cliente@example.com'));

        // La etapa que ya pasó de largo NO retrocede sola.
        $reparado = $this->ordenCotizable(['estado' => 'reparado']);
        $this->enviar($reparado);
        $this->assertSame('reparado', $reparado->fresh()->estado);
        $this->assertSame(1, OrdenServicioCotizacion::count());
    }

    // --- Fallo SMTP ---

    public function test_si_el_correo_falla_la_cotizacion_queda_registrada(): void
    {
        $orden = $this->ordenCotizable();

        // Sin ->once(): el aviso interno también pasa por Mail (cola sync en tests).
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->enviar($orden)->assertRedirect();

        $c = OrdenServicioCotizacion::first();
        $this->assertNotNull($c);                    // el registro NO se pierde
        $this->assertNull($c->correo_enviado_at);    // pero el correo no salió
    }

    public function test_reintentar_reenvia_el_mismo_snapshot_sin_fila_nueva(): void
    {
        $orden = $this->ordenCotizable();
        // Cotización cuyo correo falló (correo_enviado_at null, aún vigente).
        $c = OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $this->tecnico());

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.reintentar', [$orden, $c->id]))
            ->assertRedirect();

        Mail::assertSent(CotizacionCliente::class, fn ($m) => $m->hasTo('cliente@example.com'));
        $this->assertNotNull($c->fresh()->correo_enviado_at);
        $this->assertSame(1, OrdenServicioCotizacion::count()); // sin fila nueva
    }

    // --- Aviso interno ---

    public function test_avisa_a_los_roles_internos_sin_duplicar(): void
    {
        $tecnico = $this->tecnico();
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->enviar($this->ordenCotizable(['tipo_equipo' => 'lavadora', 'modelo' => 'LB-07B']), $tecnico);

        // Campanita (canal database) para cada rol avisado.
        foreach ([$tecnico, $jefe, $vendedor] as $u) {
            $this->assertSame(
                1,
                Notificacion::where('user_id', $u->id)->where('evento', 'cotizacion.enviada')
                    ->where('canal', Notificacion::CANAL_DATABASE)->count(),
                "Falta la campanita de {$u->name}"
            );
        }

        // El aviso identifica la máquina cotizada (tipo + modelo).
        $notif = Notificacion::where('user_id', $jefe->id)->where('evento', 'cotizacion.enviada')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();
        $this->assertStringContainsString('LB-07B', $notif->payload['equipo']);
        $this->assertStringContainsString('Equipo:', $notif->cuerpo);
        $this->assertStringContainsString('LB-07B', $notif->cuerpo);
    }
}
