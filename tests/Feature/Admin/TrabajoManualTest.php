<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\TiempoReparacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «TRABAJO REALIZADO» ESCRITO A MANO, CON CORRECTOR.
 *
 * Pedido del dueño (14-08-2026): «que quede la respuesta manual, pero si se equivocan con
 * escribir mal que tenga ayuda o quede corrección por encima de la palabra, como subrayado rojo
 * abajo de la palabra, para que se ayude a corregir y el cliente no vea faltas de ortografía o
 * mala redacción».
 *
 * POR QUÉ IMPORTA QUE SEA ESTE CAMPO: el trabajo realizado no se queda adentro — sale en el
 * correo del retiro y en la cotización que el cliente lee y guarda. Una respuesta de la lista no
 * puede tener faltas; una escrita a mano sí, y por eso el campo manual lleva `spellcheck` y
 * `lang="es"` (el subrayado rojo lo dibuja el navegador, con sugerencias al clic derecho).
 *
 * EL LARGO NO ES DECORATIVO: la cotización guarda su propio snapshot del texto en un
 * VARCHAR(191). Un texto más largo entra en SQLite y revienta en MySQL al ENVIAR la cotización,
 * o sea lejos de donde se escribió. Se corta donde se escribe.
 */
class TrabajoManualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    private function orden(array $extra = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'estado' => 'en_revision',
            'facturacion' => 'reparacion',
            'cliente_rut' => '11111111-1',
            'tipo_equipo' => 'dispensador',
        ], $extra));
    }

    /** Guarda el parte del técnico con lo que se le pase del trabajo. */
    private function guardar(OrdenServicio $orden, array $trabajo, string $estado = 'en_revision')
    {
        return $this->actingAs($this->tecnico())->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            array_merge(['estado' => $estado, 'repuestos' => []], $trabajo),
        );
    }

    // ─────────────────────────────────────────── escribirlo a mano

    public function test_el_tecnico_puede_escribir_el_trabajo_a_mano(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => 'Se rearma el circuito de agua fría y se sella la unión — funciona normal',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Se rearma el circuito de agua fría y se sella la unión — funciona normal',
            $orden->fresh()->trabajo_realizado,
        );
    }

    /** El centinela del select NUNCA se guarda como si fuera el trabajo. */
    public function test_el_centinela_no_queda_guardado(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => 'Cambio de manguera interna — sin filtraciones',
        ]);

        $this->assertStringNotContainsString('__otro__', (string) $orden->fresh()->trabajo_realizado);
    }

    /** Elegir «Otro» y no escribir nada es un error, no un trabajo vacío. */
    public function test_elegir_otro_sin_escribir_es_un_error(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, ['trabajo_realizado' => OrdenServicio::TRABAJO_OTRO])
            ->assertSessionHasErrors('trabajo_realizado_otro');

        $this->assertNull($orden->fresh()->trabajo_realizado);
    }

    /**
     * EL LARGO SE CORTA ACÁ. El tope sale de la columna de la cotización, no de un número
     * elegido: si crece esa columna, crece este límite (y este candado lo dice).
     */
    public function test_el_texto_no_puede_pasar_el_largo_que_entra_en_la_cotizacion(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => str_repeat('a', OrdenServicio::TRABAJO_MAX + 1),
        ])->assertSessionHasErrors('trabajo_realizado_otro');

        // Y justo en el tope sí entra.
        $this->guardar($orden, [
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => str_repeat('a', OrdenServicio::TRABAJO_MAX),
        ])->assertSessionHasNoErrors();
    }

    /** El tope es EL DE LA COLUMNA de la cotización: si alguien la achica, este candado avisa. */
    public function test_el_tope_es_el_de_la_columna_del_snapshot_de_la_cotizacion(): void
    {
        $migracion = file_get_contents(database_path('migrations/2026_07_21_120000_create_orden_servicio_cotizaciones_table.php'));

        $this->assertStringContainsString(
            "string('trabajo_realizado', ".OrdenServicio::TRABAJO_MAX.')',
            $migracion,
            'El largo máximo del trabajo escrito a mano dejó de coincidir con la columna donde lo guarda la cotización.',
        );
    }

    /** Se pega desde WhatsApp con saltos de línea adentro: se colapsan al guardar. */
    public function test_los_saltos_de_linea_y_los_espacios_de_mas_se_limpian(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => "  Cambio de placa\n\n  eléctrica   — funciona normal  ",
        ])->assertSessionHasNoErrors();

        $this->assertSame('Cambio de placa eléctrica — funciona normal', $orden->fresh()->trabajo_realizado);
    }

    // ─────────────────────────────────────────── la lista sigue mandando

    public function test_las_respuestas_de_la_lista_siguen_funcionando_igual(): void
    {
        $orden = $this->orden();
        $deLaLista = collect(config('servicio_tecnico.respuestas_trabajo'))->flatten()->first();
        TiempoReparacion::create(['trabajo' => $deLaLista, 'horas' => 1.5, 'activo' => true]);
        // Valor hora: producto del SKU de config con precio con IVA (mismo montaje que
        // CotizacionGuardarTest). Sin él la mano de obra es $0 por falta de precio, no por el
        // trabajo, y el candado no probaría nada.
        $producto = Producto::factory()->create(['sku' => config('servicio_tecnico.sku_hora_servicio')]);
        Precio::factory()->create(['producto_id' => $producto->id, 'precio_con_iva' => 4000]);

        $this->guardar($orden, ['trabajo_realizado' => $deLaLista])->assertSessionHasNoErrors();

        $this->assertSame($deLaLista, $orden->fresh()->trabajo_realizado);
        // Y la mano de obra la sigue fijando el tiempo estándar del catálogo: 1,5 h × $4.000.
        $this->assertSame(6000, (int) $orden->fresh()->mano_obra);
    }

    /**
     * Un trabajo escrito a mano no tiene tiempo estándar → mano de obra $0. No es un olvido: es
     * la regla del dueño (07-08-2026) de que la mano de obra la fija jefatura. La pantalla lo
     * dice en el aviso ámbar, y jefatura puede cargarle su tiempo con ese mismo texto.
     */
    public function test_un_trabajo_escrito_a_mano_deja_la_mano_de_obra_en_cero(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => 'Trabajo especial no catalogado — funciona normal',
        ]);

        $this->assertSame(0, (int) $orden->fresh()->mano_obra);
    }

    // ─────────────────────────────────────────── la pantalla

    public function test_la_pantalla_ofrece_escribirlo_y_le_pone_corrector(): void
    {
        $orden = $this->orden();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Otro — lo escribo yo', $html);
        // El subrayado rojo que pidió el dueño lo dibuja el navegador: spellcheck + idioma.
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="trabajo_realizado_otro"[^>]*>/s',
            $html,
        );
        $campo = (string) preg_replace('/.*(<textarea[^>]*name="trabajo_realizado_otro"[^>]*>).*/s', '$1', $html);
        $this->assertStringContainsString('spellcheck="true"', $campo);
        $this->assertStringContainsString('lang="es"', $campo);
        $this->assertStringContainsString('maxlength="'.OrdenServicio::TRABAJO_MAX.'"', $campo);
    }

    /**
     * UN TEXTO VIEJO FUERA DE LA LISTA ABRE EL CAMPO MANUAL con ese texto, en vez de quedar como
     * una opción muerta del select. Es lo que permite corregirle la falta de ortografía que ya
     * tiene — que es justo el pedido.
     */
    public function test_un_trabajo_historico_se_puede_editar_para_corregirlo(): void
    {
        $orden = $this->orden(['trabajo_realizado' => 'Cambio de bonba de agua']);

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->getContent();

        // El texto viejo va DENTRO del textarea (editable), no en un <option>.
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="trabajo_realizado_otro".*?>\s*Cambio de bonba de agua\s*<\/textarea>/s',
            $html,
            'El trabajo histórico no quedó en el campo editable: no se le puede corregir la ortografía.',
        );
        $this->assertStringNotContainsString('<option value="Cambio de bonba de agua"', $html);
    }
}
