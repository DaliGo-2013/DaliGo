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

    /**
     * El tope es EL DE LA COLUMNA de la cotización: si alguien la achica, este candado avisa.
     *
     * MIRA LA SERIE, NO UN ARCHIVO FIJO. Antes apuntaba a la migración que CREA la tabla, y eso
     * solo puede ser cierto mientras nadie cambie el largo después — el 28-08 subió de 191 a 500
     * (el texto ya no es una respuesta de la lista, se arma con todos los trabajos marcados) y el
     * candado quedó señalando una migración histórica que es correcto que siga diciendo 191: ya
     * corrió en producción con ese valor. El invariante que sí se sostiene es que el tope coincida
     * con el largo que deja la ÚLTIMA migración que toca la columna — mismo criterio que
     * OneShotPlantillasCandadoTest (bitácora [2026-07-30]: modelar la cadena, no el par).
     *
     * Solo se lee el `up()` de cada migración: el `down()` de la que cambia el largo vuelve al
     * valor viejo, y tomarlo daría el número anterior.
     */
    public function test_el_tope_es_el_de_la_columna_del_snapshot_de_la_cotizacion(): void
    {
        $largos = [];

        foreach (glob(database_path('migrations/*.php')) as $ruta) {
            $php = file_get_contents($ruta);

            // El up(): desde su declaración hasta la de down() (o el fin del archivo).
            $desde = strpos($php, 'function up(');
            if ($desde === false) {
                continue;
            }
            $hasta = strpos($php, 'function down(', $desde);
            $up = substr($php, $desde, $hasta === false ? null : $hasta - $desde);

            if (preg_match_all("/string\('trabajo_realizado',\s*(\d+)\)/", $up, $m)) {
                $largos[basename($ruta)] = (int) end($m[1]);
            }
        }

        $this->assertNotEmpty($largos, 'Ninguna migración declara el largo de orden_servicio_cotizaciones.trabajo_realizado: el candado dejó de mirar algo.');

        // Cronológico por nombre de archivo (que es como Laravel ordena las migraciones).
        ksort($largos);
        $ultima = array_key_last($largos);

        $this->assertSame(
            OrdenServicio::TRABAJO_MAX,
            $largos[$ultima],
            "El largo máximo del trabajo (TRABAJO_MAX) dejó de coincidir con la columna donde lo guarda la cotización: la última migración que la toca es {$ultima} y la deja en {$largos[$ultima]}.",
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
        $t = TiempoReparacion::create(['trabajo' => $deLaLista, 'horas' => 1.5, 'activo' => true]);
        // Valor hora: producto del SKU de config con precio con IVA (mismo montaje que
        // CotizacionGuardarTest). Sin él la mano de obra es $0 por falta de precio, no por el
        // trabajo, y el candado no probaría nada.
        $producto = Producto::factory()->create(['sku' => config('servicio_tecnico.sku_hora_servicio')]);
        Precio::factory()->create(['producto_id' => $producto->id, 'precio_con_iva' => 4000]);

        // El trabajo se MARCA (desde el 28-08) y el texto viaja aparte. Antes el texto era la
        // única entrada y de él salía la mano de obra; ahora el texto es solo lo que lee el
        // cliente, y el dinero sale del chip marcado.
        $this->guardar($orden, [
            'trabajos' => [$t->id],
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => $deLaLista,
        ])->assertSessionHasNoErrors();

        $this->assertSame($deLaLista, $orden->fresh()->trabajo_realizado);
        // Y la mano de obra la sigue fijando el tiempo estándar del catálogo: 1,5 h × $4.000.
        $this->assertSame(6000, (int) $orden->fresh()->mano_obra);
    }

    /**
     * Un trabajo escrito a mano no aporta horas → si es lo ÚNICO que hay, la mano de obra es $0.
     * No es un olvido: es la regla del dueño (07-08-2026) de que la mano de obra la fija
     * jefatura, y jefatura puede cargarle su tiempo con ese mismo texto.
     *
     * LO QUE CAMBIÓ EL 28-08: escribir a mano ya no BLOQUEA la cotización cuando además hay
     * trabajos marcados (ver el test de abajo). Bloquea solo cuando no hay ninguno, que es el
     * caso que este test fija.
     */
    public function test_un_trabajo_escrito_a_mano_deja_la_mano_de_obra_en_cero(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajos' => [],
            'trabajos_extra' => 'Trabajo especial no catalogado',
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => 'Trabajo especial no catalogado — funciona normal',
        ]);

        $this->assertSame(0, (int) $orden->fresh()->mano_obra);
    }

    /**
     * EL CASO DE FERNANDO (28-08-2026), y el motivo de todo este cambio: una reparación MIXTA
     * —varios trabajos del catálogo más algo escrito a mano— cobra la mano de obra de los
     * marcados y NO se traba.
     *
     * Antes esto era imposible: la mano de obra exigía que el texto completo coincidiera con una
     * fila del catálogo, y «cambio de tapa lateral derecha, cambio de placa electrica, limpieza y
     * mantenimiento general» no coincide con nada ni puede coincidir (dueño: «la lista tendría
     * que ser una combinación infinita»). Resultado: mano de obra $0, envío bloqueado, y el
     * técnico cargando la hora de servicio como si fuera un repuesto para poder cobrarla.
     */
    public function test_una_reparacion_mixta_cobra_los_trabajos_marcados_y_no_se_traba(): void
    {
        $orden = $this->orden();
        $producto = Producto::factory()->create(['sku' => config('servicio_tecnico.sku_hora_servicio')]);
        Precio::factory()->create(['producto_id' => $producto->id, 'precio_con_iva' => 4000]);

        $llave = TiempoReparacion::create(['trabajo' => 'Cambio de llave de agua — funciona normal', 'horas' => 1.0, 'activo' => true]);
        $caldera = TiempoReparacion::create(['trabajo' => 'Cambio de caldera — funciona normal', 'horas' => 1.5, 'activo' => true]);

        $this->guardar($orden, [
            'trabajos' => [$llave->id, $caldera->id],
            'trabajos_extra' => "cambio de estanque\nse agrega espigón",
            'trabajo_realizado' => OrdenServicio::TRABAJO_OTRO,
            'trabajo_realizado_otro' => 'Cambio de llave de agua, cambio de caldera, cambio de estanque y se agrega espigón — funciona normal',
        ])->assertSessionHasNoErrors();

        $fresh = $orden->fresh()->load('trabajos');

        // 1 h + 1,5 h = 2,5 h, pero el tope del taller son 2 h (el desarme se paga una vez):
        // 2 × $4.000 = $8.000. NO son $10.000.
        $this->assertSame(8000, (int) $fresh->mano_obra);
        $this->assertCount(2, $fresh->trabajos);
        // Lo escrito a mano se guarda APARTE del texto final, para que jefatura pueda listarlo.
        $this->assertSame("cambio de estanque\nse agrega espigón", $fresh->trabajos_extra);
        $this->assertSame(['cambio de estanque', 'se agrega espigón'], $fresh->trabajosExtraLista());
    }

    // ─────────────────────────────────────────── la pantalla

    public function test_la_pantalla_ofrece_escribirlo_y_le_pone_corrector(): void
    {
        $orden = $this->orden();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->getContent();

        // Escribirlo YA NO EXIGE elegir «Otro» en la lista: desde el 20-08 el campo de
        // texto está siempre, y la lista solo lo COMPLETA con un clic (pedido del
        // dueño: «dejá las dos opciones»). Antes esta línea buscaba «Otro — lo escribo
        // yo», la puerta de entrada al campo, que dejó de existir porque ya no hace
        // falta una puerta. Se assertea la propiedad más fuerte —el campo está sin
        // tener que elegir nada— y la lista, que sigue ahí para rellenar.
        // Las dos formas conviviendo las cubre TrabajoRealizadoDosFormasTest.
        $this->assertStringNotContainsString('Otro — lo escribo yo', $html);
        // Desde el 28-08 la lista son CHIPS de selección múltiple (`trabajos[]`) y no un
        // `<select id="trabajo_realizado_lista">`: un parte puede llevar varios trabajos y un
        // select solo deja elegir uno. El campo de texto sigue estando y sigue siendo lo que
        // lee el cliente, que es lo que este test vigila.
        $this->assertStringContainsString('name="trabajos[]"', $html);
        $this->assertStringNotContainsString('id="trabajo_realizado_lista"', $html);

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
