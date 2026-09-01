<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\TiempoReparacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TiemposReparacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * EL TÉCNICO YA NO ESCRIBE: LA FRASE DEL CLIENTE SE ARMA SOLA (dueño, 01-09-2026).
 *
 * El pedido, con el gerente al lado: «que al cliquear los trabajos que realice se forme la
 * respuesta de lo que el técnico hizo, ya que el gerente no quiere que escriban por mala
 * ortografía y agregar más información de la que no es necesaria». Y en la misma pantalla, sobre
 * las horas de cada chip: «no le pongas hora a todos los arreglos porque va a generar un problema
 * cuando se sume al cobro total».
 *
 * REEMPLAZA a TrabajoManualTest y TrabajoRealizadoDosFormasTest, que fijaban la conducta
 * contraria (el técnico escribe, la lista solo rellena) y se retiraron con este cambio. Lo que de
 * ellos seguía vivo se rescató acá: la reparación mixta que no se traba, y que a una orden vieja
 * no se le pierda el texto.
 *
 * Lo que este archivo fija:
 *   1. La pantalla no tiene DÓNDE escribir, y los chips no muestran horas.
 *   2. La frase la arma el SERVIDOR con los trabajos marcados, no el formulario.
 *   3. Un texto que llegue por el POST se ignora (la puerta de atrás, cerrada).
 *   4. Una orden que ya tenía frase no la pierde.
 *   5. El cierre de la frase se elige de una lista, y se recupera al reabrir.
 *   6. La frase más larga posible entra en la columna donde la cotización la guarda.
 */
class TrabajoArmadoTest extends TestCase
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

    private function conValorHora(int $valor = 4000): void
    {
        $p = Producto::factory()->create(['sku' => config('servicio_tecnico.sku_hora_servicio')]);
        Precio::factory()->create(['producto_id' => $p->id, 'precio_con_iva' => $valor]);
    }

    private function trabajo(string $nombre, float $horas = 1.0, string $grupo = 'Reparada'): TiempoReparacion
    {
        return TiempoReparacion::create(['trabajo' => $nombre, 'horas' => $horas, 'grupo' => $grupo, 'activo' => true]);
    }

    private function orden(array $overrides = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'facturacion' => 'reparacion',
            'estado' => 'cotizacion',
            'cliente_email' => 'cliente@example.com',
        ], $overrides));
    }

    private function guardar(OrdenServicio $orden, array $payload = [])
    {
        return $this->actingAs($this->tecnico())->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            array_merge(['estado' => 'cotizacion'], $payload),
        );
    }

    private function pantalla(OrdenServicio $orden): string
    {
        return $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->getContent();
    }

    // ─────────────────────────────────── 1. la pantalla no ofrece dónde escribir

    /**
     * El corazón del pedido. Los dos campos que había —«algo que no está en la lista» y el
     * editable «lo que va a leer el cliente»— se fueron, y no queda ningún otro por donde entre
     * texto del técnico a la frase del cliente.
     */
    public function test_la_pantalla_no_tiene_donde_escribir_el_trabajo(): void
    {
        $this->trabajo('Cambio de caldera — funciona normal', 1.5);
        $html = $this->pantalla($this->orden());

        $this->assertStringNotContainsString('name="trabajo_realizado_otro"', $html);
        $this->assertStringNotContainsString('name="trabajos_extra"', $html);
        $this->assertStringNotContainsString('name="trabajo_realizado"', $html);

        // Y el control que sí queda es de marcar, no de escribir.
        $this->assertStringContainsString('name="trabajos[]"', $html);
    }

    /**
     * Las horas fuera de los chips. El dueño las marcó una por una en la pantalla: son 21
     * números sueltos que invitan a sumar mentalmente un acumulado que el tope nunca permitió, y
     * el técnico no los decide ni los edita.
     *
     * Se assertea la forma CONTIGUA que emitía el chip y no el número suelto: «1,5» aparece
     * también en la ayuda del tope y en el recuadro de mano de obra, que sí deben conservarlo
     * (doctrina del verde-engañoso, bitácora [2026-07-20]).
     */
    public function test_los_chips_ya_no_muestran_las_horas_de_cada_trabajo(): void
    {
        $this->trabajo('Cambio de caldera — funciona normal', 1.5);
        $this->trabajo('Cambio de relé — funciona normal', 1.0);

        $html = $this->pantalla($this->orden());

        $this->assertStringNotContainsString('opacity-60">1,5 h', $html);
        $this->assertStringNotContainsString('opacity-60">1 h', $html);
    }

    // ─────────────────────────────────── 2. la frase la arma el servidor

    public function test_la_frase_se_arma_con_los_trabajos_marcados(): void
    {
        $this->conValorHora();
        $llave = $this->trabajo('Cambio de llave de agua — funciona normal');
        $caldera = $this->trabajo('Cambio de caldera — funciona normal', 1.5);
        $rele = $this->trabajo('Cambio de relé — funciona normal');
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajos' => [$llave->id, $caldera->id, $rele->id],
            'remate' => 'funciona normal',
        ])->assertSessionHasNoErrors();

        // Primera en mayúscula, el resto en minúscula, «y» antes del último y el cierre una vez.
        $this->assertSame(
            'Cambio de caldera, cambio de llave de agua y cambio de relé — funciona normal',
            $orden->fresh()->trabajo_realizado,
        );
    }

    /**
     * Dos técnicos que hacen lo mismo tienen que producir la MISMA frase. Si el orden saliera del
     * POST, dependería de en qué orden tocó los chips cada uno — y eso llega al cliente.
     */
    public function test_la_frase_no_depende_del_orden_en_que_se_marcaron_los_chips(): void
    {
        $this->conValorHora();
        $a = $this->trabajo('Cambio de caldera — funciona normal', 1.5);
        $b = $this->trabajo('Cambio de relé — funciona normal');

        $uno = $this->orden();
        $otro = $this->orden();

        $this->guardar($uno, ['trabajos' => [$a->id, $b->id], 'remate' => 'funciona normal']);
        $this->guardar($otro, ['trabajos' => [$b->id, $a->id], 'remate' => 'funciona normal']);

        $this->assertSame($uno->fresh()->trabajo_realizado, $otro->fresh()->trabajo_realizado);
    }

    public function test_un_solo_trabajo_no_lleva_conector(): void
    {
        $this->conValorHora();
        $caldera = $this->trabajo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->orden();

        $this->guardar($orden, ['trabajos' => [$caldera->id], 'remate' => 'funciona normal']);

        $this->assertSame('Cambio de caldera — funciona normal', $orden->fresh()->trabajo_realizado);
    }

    // ─────────────────────────────────── 3. la puerta de atrás, cerrada

    /**
     * EL CANDADO QUE HACE QUE ESTO SEA UN CAMBIO REAL Y NO COSMÉTICO. Quitar el campo de la
     * pantalla no quita la capacidad de escribir mientras el texto siga viajando en el POST:
     * cualquiera puede mandar lo que quiera y la ortografía vuelve por la puerta de atrás. La
     * frase se arma en el servidor, así que lo que llegue se ignora.
     */
    public function test_un_texto_mandado_por_el_post_se_ignora(): void
    {
        $this->conValorHora();
        $caldera = $this->trabajo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajos' => [$caldera->id],
            'remate' => 'funciona normal',
            'trabajo_realizado' => 'lo que se me ocurra escribir aqui sin ortografia',
            'trabajo_realizado_otro' => 'ni esto tampoco',
            'trabajos_extra' => 'ni esto',
        ])->assertSessionHasNoErrors();

        $fresh = $orden->fresh();
        $this->assertSame('Cambio de caldera — funciona normal', $fresh->trabajo_realizado);
        $this->assertNull($fresh->trabajos_extra);
    }

    // ─────────────────────────────────── 4. a una orden vieja no se le pierde el texto

    /**
     * LAS ÓRDENES ANTERIORES AL PIVOTE (28-08) tienen su frase escrita a mano y CERO trabajos
     * marcados. Si guardar el parte re-armara la frase igual, quedarían mudas: se perdería lo que
     * el cliente ya tenía por escrito, en silencio y sin que nadie se entere — la familia de
     * defecto de la bitácora [2026-08-20].
     */
    public function test_una_orden_sin_trabajos_marcados_conserva_su_frase(): void
    {
        $orden = $this->orden(['trabajo_realizado' => 'Cambio de bonba de agua']);

        $this->guardar($orden, ['trabajos' => ['']])->assertSessionHasNoErrors();

        $this->assertSame('Cambio de bonba de agua', $orden->fresh()->trabajo_realizado);
    }

    /** Y si la pantalla ni siquiera preguntó por los trabajos, tampoco se toca. */
    public function test_guardar_sin_la_clave_trabajos_conserva_la_frase(): void
    {
        $orden = $this->orden(['trabajo_realizado' => 'Cambio de caldera — funciona normal']);

        $this->guardar($orden)->assertSessionHasNoErrors();

        $this->assertSame('Cambio de caldera — funciona normal', $orden->fresh()->trabajo_realizado);
    }

    // ─────────────────────────────────── 5. el cierre de la frase

    public function test_el_cierre_no_acepta_texto_libre(): void
    {
        $this->conValorHora();
        $caldera = $this->trabajo('Cambio de caldera — funciona normal', 1.5);

        $this->guardar($this->orden(), [
            'trabajos' => [$caldera->id],
            'remate' => 'lo que se me ocurra',
        ])->assertSessionHasErrors('remate');
    }

    /**
     * Al reabrir una orden cerrada con un cierre distinto del más común, la pantalla tiene que
     * arrancar con EL SUYO. Antes esto se deducía en JS mirando el final del texto; ese texto
     * dejó de existir como campo, así que ahora lo resuelve el servidor. Sin esto, abrir y volver
     * a guardar le cambiaría el cierre al cliente en silencio.
     */
    public function test_el_cierre_guardado_se_recupera_al_reabrir(): void
    {
        $this->trabajo('Reacondicionamiento completo — queda en óptimas condiciones', 1.5);
        $this->trabajo('Cambio de relé — funciona normal');
        $orden = $this->orden(['trabajo_realizado' => 'Reacondicionamiento completo — queda en óptimas condiciones']);

        $html = $this->pantalla($orden);

        $this->assertStringContainsString('remateInicial: '.\Illuminate\Support\Js::from('queda en óptimas condiciones'), $html);
    }

    // ─────────────────────────────────── 6. lo rescatado y el largo

    /**
     * EL CASO DE FERNANDO, que originó todo esto: una reparación con varios trabajos cobra el
     * tope y no traba el envío de la cotización.
     */
    public function test_una_reparacion_mixta_cobra_el_tope_y_no_se_traba(): void
    {
        $this->conValorHora(4000);
        $llave = $this->trabajo('Cambio de llave de agua — funciona normal', 1.0);
        $caldera = $this->trabajo('Cambio de caldera — funciona normal', 1.5);
        $orden = $this->orden();

        $this->guardar($orden, [
            'trabajos' => [$llave->id, $caldera->id],
            'remate' => 'funciona normal',
        ])->assertSessionHasNoErrors();

        $fresh = $orden->fresh()->load('trabajos');

        // 1 h + 1,5 h = 2,5 h, pero el tope del taller son 2 h: 2 × $4.000 = $8.000, no $10.000.
        $this->assertSame(8000, (int) $fresh->mano_obra);
        $this->assertCount(2, $fresh->trabajos);
    }

    /**
     * EL DEFECTO QUE ESTE CAMBIO ESTUVO A PUNTO DE INTRODUCIR. El largo de la frase lo contenía
     * el `max:` del campo de texto; al sacar el campo, dejó de tener quién lo contenga y pasó a
     * depender de CUÁNTOS trabajos se marquen. Con el catálogo real: 10 marcados dan 511
     * caracteres y los 21 dan 793, contra un VARCHAR(500) en la columna donde la cotización
     * guarda su snapshot. SQLite lo deja pasar —o sea que local y la suite no lo verían— y MySQL
     * revienta con «Data too long» al ENVIAR la cotización, lejos de donde se marcó.
     *
     * Por eso esa columna pasó a TEXT. Este candado verifica que el peor caso ENTRA, y como el
     * peor caso crece con el catálogo (el dueño va a seguir agregándole trabajos), se mide contra
     * el catálogo real y no contra un número escrito a mano.
     */
    public function test_la_frase_mas_larga_posible_entra_en_la_columna_del_snapshot(): void
    {
        $this->seed(TiemposReparacionSeeder::class);

        $cortos = TiempoReparacion::orderBy('grupo')->orderBy('trabajo')->pluck('trabajo')
            ->map(fn ($t) => TiempoReparacion::sinRemate($t));
        $peor = OrdenServicio::fraseDeTrabajos($cortos, 'queda en óptimas condiciones');

        // El control positivo: sin él, un catálogo vacío daría «entra» por vacío.
        $this->assertGreaterThan(500, mb_strlen($peor), 'El peor caso ya no supera el VARCHAR viejo: revisa si este candado sigue midiendo algo.');

        $orden = $this->orden();
        $orden->cotizaciones()->create([
            'token' => \Illuminate\Support\Str::random(32),
            'estado' => 'enviada',
            'cliente_email' => 'cliente@example.com',
            'trabajo_realizado' => $peor,
            'costo_total' => 1000,
            'enviada_por' => $this->tecnico()->id,
        ]);

        $this->assertSame($peor, $orden->cotizaciones()->first()->trabajo_realizado);
    }

    /**
     * El espejo en JS (`textoCliente` de reparacionForm) tiene que dar la MISMA frase que el
     * servidor, o la pantalla prometería una y se guardaría otra — el defecto de las dos fuentes
     * de la bitácora [2026-08-07]. La suite de PHP no evalúa Alpine, así que lo que se puede
     * verificar acá es que la regla siga escrita en los dos lados; el candado estructural es lo
     * único que existe (bitácora [2026-08-25]).
     */
    public function test_el_espejo_en_js_arma_la_misma_frase_que_el_servidor(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('get textoCliente()', $js);
        // Las tres piezas de la regla: el conector, la minúscula del encadenado y el cierre.
        $this->assertStringContainsString("' y '", $js);
        $this->assertStringContainsString('toLowerCase()', $js);
        $this->assertStringContainsString("' — '", $js);
        // Y que no haya vuelto a existir un texto editable en el componente.
        $this->assertStringNotContainsString('textoTocado', $js);
    }
}
