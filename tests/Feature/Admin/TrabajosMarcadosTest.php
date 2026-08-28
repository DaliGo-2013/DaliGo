<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\OrdenServicio;
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
 * VARIOS TRABAJOS POR ORDEN, CON TOPE DE HORAS (dueño, 28-08-2026).
 *
 * El pedido: «de repente hay trabajos donde el técnico hace como tres o cuatro trabajos sobre un
 * dispensador —cambio de llave, cambio de estanque, cambio de caldera y se agrega espigón— y esa
 * respuesta ya no existe; la lista tendría que ser una combinación infinita de reparaciones que
 * sería muy extensa». Y el límite: «no quiero que se sumen 5 horas […] cuando un dispensador se
 * desarma completo más estos cambios máximo puede ser dos horas, más de ahí no pasa».
 *
 * Lo que este archivo fija, y que antes era imposible de cumplir a la vez:
 *   1. Se marcan VARIOS trabajos y las horas se SUMAN.
 *   2. La suma tiene TOPE (el desarme se paga una vez), y el tope lo edita jefatura.
 *   3. El tope NO recorta un trabajo individual más largo que él (el piso).
 *   4. El texto que lee el cliente ya NO decide el dinero: editarlo no mueve un peso.
 *   5. Escribir algo fuera del catálogo no traba la cotización, y queda listado para jefatura.
 */
class TrabajosMarcadosTest extends TestCase
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

    private function jefeVentas(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_ventas');
    }

    /** Valor hora $4.000 con IVA, para que las horas se traduzcan a plata legible. */
    private function conValorHora(int $valor = 4000): void
    {
        $p = Producto::factory()->create(['sku' => config('servicio_tecnico.sku_hora_servicio')]);
        Precio::factory()->create(['producto_id' => $p->id, 'precio_con_iva' => $valor]);
    }

    private function trabajo(string $nombre, float $horas): TiempoReparacion
    {
        return TiempoReparacion::create(['trabajo' => $nombre, 'horas' => $horas, 'grupo' => 'Reparada', 'activo' => true]);
    }

    private function orden(array $overrides = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'facturacion' => 'reparacion',
            'estado' => 'cotizacion',
            'cliente_email' => 'cliente@example.com',
        ], $overrides));
    }

    /** @param  array<int, int>  $ids */
    private function guardar(OrdenServicio $orden, array $ids, array $extra = [])
    {
        return $this->actingAs($this->tecnico())->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            array_merge([
                'estado' => 'cotizacion',
                'trabajos' => $ids,
                'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
                'trabajo_realizado_otro' => 'Lo que se hizo — funciona normal',
            ], $extra),
        );
    }

    // ─────────────────────────────────────────── 1. varios trabajos, horas sumadas

    public function test_dos_trabajos_marcados_suman_sus_horas(): void
    {
        $this->conValorHora(4000);
        $manguera = $this->trabajo('Cambio de manguera', 0.5);
        $filtro = $this->trabajo('Cambio de filtro', 0.5);
        $orden = $this->orden();

        $this->guardar($orden, [$manguera->id, $filtro->id])->assertSessionHasNoErrors();

        // 0,5 + 0,5 = 1 h (no toca el tope de 2 h) → $4.000.
        $fresh = $orden->fresh()->load('trabajos');
        $this->assertSame(4000, (int) $fresh->mano_obra);
        $this->assertSame(1.0, $fresh->horasACobrar());
        $this->assertCount(2, $fresh->trabajos);
    }

    /**
     * EL CASO DEL DUEÑO, con los números que dio: cuatro trabajos que suman 4,5 h se cobran 2 h.
     * Antes de este cambio esto ni siquiera se podía expresar — un parte tenía UN trabajo.
     */
    public function test_cuatro_trabajos_no_cobran_cinco_horas_sino_el_tope(): void
    {
        $this->conValorHora(4000);
        $ids = [
            $this->trabajo('Cambio de llave de agua', 1.0)->id,
            $this->trabajo('Cambio de estanque', 1.0)->id,
            $this->trabajo('Cambio de caldera', 1.5)->id,
            $this->trabajo('Se agrega espigón', 1.0)->id,
        ];
        $orden = $this->orden();

        $this->guardar($orden, $ids)->assertSessionHasNoErrors();

        $fresh = $orden->fresh()->load('trabajos');
        $this->assertSame(4.5, TiempoReparacion::horasSumadas($fresh->trabajos->pluck('pivot.horas')));
        $this->assertSame(2.0, $fresh->horasACobrar());       // el tope
        $this->assertSame(8000, (int) $fresh->mano_obra);     // 2 h × $4.000, NO 4,5 h × $4.000
        $this->assertCount(4, $fresh->trabajos);
    }

    // ─────────────────────────────────────────── 2 y 3. el tope y su piso

    /**
     * EL PISO. Un trabajo individual más largo que el tope se cobra COMPLETO: el tope recorta la
     * acumulación de trabajos, nunca el tiempo de uno solo.
     *
     * Sin esto, el día que jefatura cargue un trabajo de 3 h el sistema cobraría 2 — menos que su
     * propio tiempo estándar, en silencio y con un número plausible en pantalla.
     */
    public function test_un_trabajo_mas_largo_que_el_tope_se_cobra_completo(): void
    {
        $this->conValorHora(4000);
        $grande = $this->trabajo('Reacondicionamiento completo', 3.0);
        $orden = $this->orden();

        $this->guardar($orden, [$grande->id])->assertSessionHasNoErrors();

        $fresh = $orden->fresh()->load('trabajos');
        $this->assertSame(3.0, $fresh->horasACobrar());        // 3, no 2
        $this->assertSame(12000, (int) $fresh->mano_obra);
    }

    /** Y con OTRO trabajo al lado, el tope sigue siendo el mayor de los dos, no la suma. */
    public function test_el_piso_es_el_trabajo_mas_largo_no_la_suma(): void
    {
        $this->conValorHora(4000);
        $ids = [
            $this->trabajo('Reacondicionamiento completo', 3.0)->id,
            $this->trabajo('Cambio de filtro', 0.5)->id,
        ];
        $orden = $this->orden();

        $this->guardar($orden, $ids)->assertSessionHasNoErrors();

        // Suma 3,5; el tope efectivo es max(2, 3) = 3 → se cobran 3 h, no 3,5 ni 2.
        $this->assertSame(3.0, $orden->fresh()->load('trabajos')->horasACobrar());
    }

    public function test_jefatura_cambia_el_tope_y_las_ordenes_nuevas_lo_respetan(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->conValorHora(4000);

        $this->actingAs($this->jefeVentas())
            ->put(route('admin.tiempos-reparacion.tope'), ['tope_horas' => '3,5'])
            ->assertRedirect(route('admin.tiempos-reparacion.index'));

        $this->assertSame(3.5, TiempoReparacion::topeHoras());

        $ids = [
            $this->trabajo('Cambio de llave de agua', 1.5)->id,
            $this->trabajo('Cambio de caldera', 1.5)->id,
            $this->trabajo('Cambio de estanque', 1.5)->id,
        ];
        $orden = $this->orden();
        $this->guardar($orden, $ids);

        // 4,5 h de suma, tope 3,5 → 3,5 × 4000 = 14.000.
        $this->assertSame(14000, (int) $orden->fresh()->mano_obra);
    }

    public function test_el_tope_no_acepta_cualquier_cosa(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $this->actingAs($this->jefeVentas())
            ->put(route('admin.tiempos-reparacion.tope'), ['tope_horas' => '0,1'])
            ->assertSessionHasErrors('tope_horas');

        $this->actingAs($this->jefeVentas())
            ->put(route('admin.tiempos-reparacion.tope'), ['tope_horas' => '99'])
            ->assertSessionHasErrors('tope_horas');

        // Y el valor no se movió.
        $this->assertSame(2.0, TiempoReparacion::topeHoras());
    }

    public function test_el_tecnico_no_puede_cambiar_el_tope(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $this->actingAs($this->tecnico())
            ->put(route('admin.tiempos-reparacion.tope'), ['tope_horas' => '8'])
            ->assertForbidden();

        $this->assertSame(2.0, TiempoReparacion::topeHoras());
    }

    // ─────────────────────────────────────────── 4. el texto ya no decide el dinero

    /**
     * LA REGLA QUE ARREGLA EL DEFECTO DE FONDO. Antes, ajustarle una coma al texto del trabajo
     * borraba la mano de obra, porque las horas se buscaban por el texto EXACTO contra el
     * catálogo. Ahora el texto es solo lo que lee el cliente.
     */
    public function test_editar_el_texto_no_cambia_la_mano_de_obra(): void
    {
        $this->conValorHora(4000);
        $caldera = $this->trabajo('Cambio de caldera', 1.5);
        $orden = $this->orden();

        $this->guardar($orden, [$caldera->id], [
            'trabajo_realizado_otro' => 'Cambio de caldera — funciona normal',
        ]);
        $this->assertSame(6000, (int) $orden->fresh()->mano_obra);

        // El técnico reescribe el texto por completo, sin tocar los chips.
        $this->guardar($orden, [$caldera->id], [
            'trabajo_realizado_otro' => 'Se cambió la caldera y quedó funcionando perfecto, probada 20 minutos',
        ])->assertSessionHasNoErrors();

        $fresh = $orden->fresh();
        $this->assertSame(6000, (int) $fresh->mano_obra);      // NO bajó a 0
        $this->assertSame('Se cambió la caldera y quedó funcionando perfecto, probada 20 minutos', $fresh->trabajo_realizado);
    }

    /**
     * Las horas se CONGELAN en el pivote: si jefatura recalibra el catálogo, una orden ya
     * cotizada no cambia de precio sola. Su carta le prometió un monto al cliente.
     */
    public function test_recalibrar_el_catalogo_no_cambia_el_precio_de_una_orden_ya_guardada(): void
    {
        $this->conValorHora(4000);
        $caldera = $this->trabajo('Cambio de caldera', 1.5);
        $orden = $this->orden();

        $this->guardar($orden, [$caldera->id]);
        $this->assertSame(6000, (int) $orden->fresh()->mano_obra);

        // Jefatura decide que ese trabajo lleva media hora más.
        $caldera->update(['horas' => 2.0]);

        // La orden ya guardada NO se mueve mientras nadie vuelva a marcar.
        $this->assertSame(1.5, $orden->fresh()->load('trabajos')->horasACobrar());
        $this->assertSame(6000, (int) $orden->fresh()->mano_obra);
    }

    /** Pero al re-marcarlo en el parte, sí toma las horas nuevas: es el momento en que se decide. */
    public function test_volver_a_guardar_el_parte_toma_las_horas_vigentes(): void
    {
        $this->conValorHora(4000);
        $caldera = $this->trabajo('Cambio de caldera', 1.5);
        $orden = $this->orden();

        $this->guardar($orden, [$caldera->id]);
        $caldera->update(['horas' => 2.0]);

        $this->guardar($orden, [$caldera->id])->assertSessionHasNoErrors();

        $this->assertSame(8000, (int) $orden->fresh()->mano_obra);   // 2 h × 4000
    }

    // ─────────────────────────────────────────── 5. lo escrito a mano

    /**
     * Escribir algo fuera del catálogo NO traba la cotización si hay al menos un trabajo
     * marcado. Es el cambio que desbloquea el caso real: antes el envío se frenaba y el técnico
     * terminaba cargando la hora de servicio como un repuesto para poder cobrarla.
     */
    public function test_lo_escrito_a_mano_no_traba_el_envio_si_hay_un_trabajo_marcado(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->conValorHora(4000);
        $caldera = $this->trabajo('Cambio de caldera', 1.5);
        $orden = $this->orden();
        $orden->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 30000]);

        $this->guardar($orden, [$caldera->id], ['trabajos_extra' => 'cambio de estanque']);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $this->assertSame(1, \App\Models\OrdenServicioCotizacion::count());
        $this->assertSame(6000, (int) \App\Models\OrdenServicioCotizacion::first()->mano_obra);
    }

    /** Sin NINGÚN trabajo marcado sí se traba: ahí la mano de obra es $0 por un hueco de datos. */
    public function test_sin_trabajos_marcados_no_se_envia_la_cotizacion(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->conValorHora(4000);
        $orden = $this->orden();
        $orden->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 30000]);

        $this->guardar($orden, [], ['trabajos_extra' => 'cambio de estanque']);
        $this->assertSame(0, (int) $orden->fresh()->mano_obra);

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden))
            ->assertRedirect();

        $this->assertSame(0, \App\Models\OrdenServicioCotizacion::count());
        Mail::assertNothingSent();
    }

    /**
     * Lo escrito a mano se le muestra JUNTO a jefatura, con cuántas veces se repitió: es la cola
     * de trabajo que hace que el catálogo se calibre con el uso real. Un trabajo que aparece
     * seguido acá es un trabajo que le falta al catálogo.
     */
    public function test_jefatura_ve_los_trabajos_escritos_a_mano_con_su_frecuencia(): void
    {
        $this->trabajo('Cambio de caldera', 1.5);

        // Tres órdenes escriben lo mismo con distinta capitalización y espacios: es el MISMO
        // trabajo y tiene que contarse junto, o el más repetido queda escondido en tres filas.
        $this->orden(['trabajos_extra' => 'cambio de estanque']);
        $this->orden(['trabajos_extra' => 'Cambio de Estanque']);
        $this->orden(['trabajos_extra' => "cambio  de   estanque\nse agrega espigón"]);

        $html = $this->actingAs($this->jefeVentas())
            ->get(route('admin.tiempos-reparacion.index'))
            ->assertOk()
            ->assertSee('Escritos a mano por los técnicos')
            ->assertSee('se agrega espigón')
            ->getContent();

        // El estanque aparece UNA vez, con «3 veces» al lado.
        $this->assertSame(1, preg_match_all('/3 veces/', $html), 'El conteo agrupado del trabajo repetido no aparece exactamente una vez.');
        $this->assertSame(1, preg_match_all('/1 vez\b/', $html), 'El espigón (una sola vez) debería aparecer con «1 vez».');
    }

    /** Un trabajo que jefatura YA catalogó deja de aparecer como pendiente. */
    public function test_lo_ya_catalogado_no_sigue_apareciendo_como_pendiente(): void
    {
        $this->orden(['trabajos_extra' => 'cambio de estanque']);

        $this->actingAs($this->jefeVentas())
            ->get(route('admin.tiempos-reparacion.index'))
            ->assertSee('cambio de estanque');

        // Jefatura lo agrega — con otra capitalización y con remate, como pasaría de verdad.
        $this->trabajo('Cambio de estanque — funciona normal', 1.0);

        $this->actingAs($this->jefeVentas())
            ->get(route('admin.tiempos-reparacion.index'))
            ->assertDontSee('Escritos a mano por los técnicos');
    }

    // ─────────────────────────────────────────── el borrado silencioso

    /**
     * LA GUARDA CONTRA EL BORRADO SILENCIOSO. Si el payload NO trae la clave `trabajos`, no
     * significa «ningún trabajo»: significa «esta pantalla no lo preguntó», y desmarcar todo
     * bajaría la mano de obra a $0 sin que nadie lo pida — la familia de defecto de la bitácora
     * [2026-08-20]. Ausente ≠ vacío.
     */
    public function test_guardar_sin_la_clave_trabajos_conserva_la_mano_de_obra(): void
    {
        $this->conValorHora(4000);
        $caldera = $this->trabajo('Cambio de caldera', 1.5);
        $orden = $this->orden();

        $this->guardar($orden, [$caldera->id]);
        $this->assertSame(6000, (int) $orden->fresh()->mano_obra);

        // Un PUT que no menciona los trabajos (otra pantalla, un cliente viejo).
        $this->actingAs($this->tecnico())->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            [
                'estado' => 'cotizacion',
                'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
                'trabajo_realizado_otro' => 'Cambio de caldera — funciona normal',
            ],
        )->assertSessionHasNoErrors();

        $fresh = $orden->fresh()->load('trabajos');
        $this->assertSame(6000, (int) $fresh->mano_obra, 'Un payload sin la clave `trabajos` borró la mano de obra.');
        $this->assertCount(1, $fresh->trabajos);
    }

    /** Y desmarcar todo A PROPÓSITO (clave presente, vacía) SÍ la baja: es una decisión del técnico. */
    public function test_desmarcar_todo_si_baja_la_mano_de_obra(): void
    {
        $this->conValorHora(4000);
        $caldera = $this->trabajo('Cambio de caldera', 1.5);
        $orden = $this->orden();

        $this->guardar($orden, [$caldera->id]);
        $this->assertSame(6000, (int) $orden->fresh()->mano_obra);

        $this->guardar($orden, [])->assertSessionHasNoErrors();

        $fresh = $orden->fresh()->load('trabajos');
        $this->assertSame(0, (int) $fresh->mano_obra);
        $this->assertCount(0, $fresh->trabajos);
    }

    // ─────────────────────────────────────────── el default no depende de la BD

    /**
     * Con la tabla de configuración vacía, el tope sigue siendo 2 h: el default vive en el
     * modelo y parametrizarlo no cambió el comportamiento con base virgen (regla de oro de
     * PLAN-PARAMETRICOS). Sin esto, una base sin sembrar cobraría la suma sin tope.
     */
    public function test_sin_la_clave_en_la_base_el_tope_sigue_siendo_dos_horas(): void
    {
        $this->assertSame(0, Configuracion::where('clave', TiempoReparacion::CLAVE_TOPE_HORAS)->count());
        $this->assertSame(2.0, TiempoReparacion::topeHoras());

        $this->conValorHora(4000);
        $ids = [
            $this->trabajo('Cambio de llave de agua', 1.5)->id,
            $this->trabajo('Cambio de caldera', 1.5)->id,
        ];
        $orden = $this->orden();
        $this->guardar($orden, $ids);

        $this->assertSame(8000, (int) $orden->fresh()->mano_obra);   // 2 h, no 3
    }

    /** Y el seeder siembra ese MISMO valor: si divergen, cambiar la BD alteraría el default. */
    public function test_el_seeder_siembra_el_mismo_default_que_el_modelo(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $this->assertSame(
            TiempoReparacion::TOPE_HORAS_DEFAULT,
            (float) Configuracion::get(TiempoReparacion::CLAVE_TOPE_HORAS),
            'El tope sembrado dejó de coincidir con TOPE_HORAS_DEFAULT: una base sembrada y una virgen cobrarían distinto.',
        );
    }
}
