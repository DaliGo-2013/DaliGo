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
     * caracteres y los 23 dan 836, contra un VARCHAR(500) en la columna donde la cotización
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

    /**
     * EL ORDEN DEL ESPEJO, que es lo que el candado de arriba NO mira (10-09-2026).
     *
     * Reproducido en el navegador en la orden #20 marcando caldera → espigón → tapa frontal: la
     * pantalla mostraba «Cambio de caldera, se agrega espigón y cambio de tapa frontal» y se
     * guardaba «Cambio de caldera, cambio de tapa frontal y se agrega espigón». El getter
     * recorría `marcados` —el orden en que se tocaron los chips— y el servidor ordena por
     * catálogo. El candado de arriba pasaba en verde porque solo mira los conectores.
     *
     * Se verifica la FORMA y no el resultado porque la suite de PHP no evalúa Alpine. Y la forma
     * que se exige es «recorrer el catálogo» y no «ordenar lo marcado»: `catalogo` ya llega en el
     * orden canónico, así que el orden se HEREDA. Una comparación escrita en JS sería una cuarta
     * copia de la regla y la más fácil de desalinear (el `sortBy` de PHP compara bytes; un
     * `localeCompare` no).
     *
     * El assert va sobre el CUERPO del getter, recortado por su cierre real, y no sobre el
     * archivo entero: `this.marcados.map(` es correcto en `horasMarcadas` y en `remateSugerido`
     * —son sumas y máximos, no les importa el orden— así que un assert global pasaría por el
     * método equivocado (bitácora [2026-08-21]).
     */
    public function test_el_espejo_en_js_encadena_en_el_orden_del_catalogo_y_no_del_click(): void
    {
        $cuerpo = $this->cuerpoDeTextoCliente();

        // La forma COMPLETA de la línea, con el `const partes =` incluido, y no la subcadena
        // suelta: `this.marcados.map(` es legítimo dos líneas más arriba —el propio arreglo lo usa
        // para armar el Set de búsqueda— y también en `horasMarcadas` y `remateSugerido`, que son
        // sumas y máximos a los que el orden no les importa. Un assert de la subcadena pasaría
        // por el elemento equivocado (bitácora [2026-08-21]).
        $this->assertStringContainsString(
            'const partes = this.catalogo',
            $cuerpo,
            'La vista previa dejó de recorrer el catálogo: si encadena `marcados`, muestra el orden en que se tocaron los chips y no el que se guarda.'
        );
        $this->assertStringNotContainsString(
            'const partes = this.marcados',
            $cuerpo,
            'La vista previa volvió a encadenar `marcados`: eso es el orden de los clicks, y el servidor ordena por catálogo (`TiempoReparacion::enOrden`).'
        );
    }

    /**
     * El cuerpo de `get textoCliente()`, recortado por su cierre real. Si el getter se renombra o
     * se va, esto falla en vez de asertar sobre vacío — un `assertStringNotContainsString` sobre
     * una cadena vacía pasa siempre (bitácora [2026-08-14]).
     */
    private function cuerpoDeTextoCliente(): string
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $ini = strpos($js, 'get textoCliente() {');
        $this->assertNotFalse($ini, 'No existe `get textoCliente()` en app.js: el espejo de la frase se fue de lugar.');

        $fin = strpos($js, "\n    },", $ini);
        $this->assertNotFalse($fin, 'No se encontró el cierre de `textoCliente`: el recorte no se puede hacer.');

        $cuerpo = substr($js, $ini, $fin - $ini);
        // Control positivo del recorte: sin esto, un recorte vacío o cortado daría verde por vacío.
        $this->assertStringContainsString("' y '", $cuerpo, 'El recorte de `textoCliente` no llegó al armado de la frase.');

        return $cuerpo;
    }

    /**
     * LO QUE SE GUARDA SIGUE EL ORDEN EN QUE SE DIBUJAN LOS CHIPS, punta a punta (10-09-2026).
     *
     * El candado hermano del de arriba, y el que sí puede fallar por conducta: la expectativa se
     * DERIVA de la pantalla —se leen los ids de los chips en el orden del DOM— en vez de
     * escribirse a mano, así que fija la relación entre las dos superficies y no dos veces la
     * misma frase. Es lo que hace que la vista previa del técnico signifique algo: la pantalla
     * puede mostrar cualquier orden con tal de que sea EL de lo que se guarda.
     *
     * El fixture está armado para que tres órdenes distintos no coincidan —creación (los ids),
     * alfabético ignorando el grupo, y el del POST— así que discrimina tres regresiones:
     * ordenar por id, olvidarse del grupo, y usar el orden en que llegaron los chips.
     */
    public function test_lo_guardado_sigue_el_orden_en_que_se_dibujan_los_chips(): void
    {
        $this->conValorHora();

        // Creados a propósito en un orden que NO es el canónico: por id sería zapata, aviso,
        // ajuste; alfabético sin mirar el grupo sería ajuste, aviso, zapata; el canónico
        // (grupo, luego trabajo) es ajuste, zapata, aviso.
        $zapata = $this->trabajo('Zapata cambiada — funciona normal', 1.0, 'Reparada');
        $aviso = $this->trabajo('Aviso previo revisado — funciona normal', 1.0, 'Revisada sin falla');
        $ajuste = $this->trabajo('Ajuste de termostato — funciona normal', 1.0, 'Reparada');

        $orden = $this->orden();
        $html = $this->pantalla($orden);

        // Los ids de los chips, en el orden en que la pantalla los dibuja. El hidden centinela
        // (`value=""`) no matchea porque exige dígitos.
        preg_match_all('/name="trabajos\[\]" value="(\d+)"/', $html, $m);
        $dibujados = array_map('intval', $m[1]);

        $this->assertSame(
            [$ajuste->id, $zapata->id, $aviso->id],
            $dibujados,
            'Los chips no se dibujan en el orden canónico (grupo, luego trabajo).'
        );

        // Se marcan al REVÉS de como se dibujan: es el orden de los clicks del técnico.
        $this->guardar($orden, [
            'trabajos' => array_reverse($dibujados),
            'remate' => 'funciona normal',
        ])->assertSessionHasNoErrors();

        // La expectativa sale de la PANTALLA, no de una frase escrita a mano.
        $esperada = OrdenServicio::fraseDeTrabajos(
            collect($dibujados)->map(fn ($id) => TiempoReparacion::find($id)->trabajo_corto),
            'funciona normal',
        );

        $this->assertSame($esperada, $orden->fresh()->trabajo_realizado);

        // Control positivo: sin esto, un `$dibujados` vacío o mal extraído daría dos frases
        // vacías iguales y el assert de arriba pasaría por vacío.
        $this->assertSame(
            'Ajuste de termostato, zapata cambiada y aviso previo revisado — funciona normal',
            $orden->fresh()->trabajo_realizado,
        );
    }

    /**
     * EL PAYLOAD QUE RECIBE LA VISTA PREVIA VA EN EL MISMO ORDEN QUE LOS CHIPS (10-09-2026).
     *
     * El eslabón que los otros dos candados NO cubren, y sin él el arreglo se apoya en una
     * coincidencia. La vista previa hereda el orden de `catalogoTrabajos` en vez de ordenar, así
     * que ese payload ES el criterio del lado del cliente — pero llega por un camino distinto del
     * de los chips: los chips salen del `@foreach` sobre la colección agrupada y el payload de un
     * `flatten(1)` de la misma. Un `sortBy('id')` metido en el `flatten` dejaría los chips
     * perfectos y la vista previa desordenada, y los dos candados de arriba seguirían verdes.
     *
     * El fixture ordena al revés que los ids («Alfa» se crea segunda) para que «va en orden de
     * catálogo» y «va en orden de creación» no puedan confundirse.
     */
    public function test_el_payload_de_la_vista_previa_va_en_el_orden_de_los_chips(): void
    {
        $this->trabajo('Zeta ultima — funciona normal', 1.0, 'Reparada');
        $this->trabajo('Alfa primera — funciona normal', 1.0, 'Reparada');

        $html = $this->pantalla($this->orden());

        preg_match_all('/name="trabajos\[\]" value="(\d+)"/', $html, $chips);

        // `@js()` emite el JSON con las comillas como `"` y todo entre comillas simples, así
        // que los ids se leen de esa forma y no del JSON crudo (que no existe en el HTML).
        $ini = strpos($html, 'catalogoTrabajos:');
        $this->assertNotFalse($ini, 'La pantalla ya no le pasa el catálogo a la vista previa.');
        preg_match_all('/u0022id.u0022:(\d+)/', substr($html, $ini, 4000), $payload);

        $this->assertNotEmpty($payload[1], 'No se pudieron leer los ids del payload: cambió la forma que emite `@js()`.');
        $this->assertSame(
            $chips[1],
            $payload[1],
            'El catálogo que recibe la vista previa no viene en el mismo orden que los chips: la frase de la pantalla se desordenaría aunque los chips se vean bien.'
        );
    }

    /**
     * UN SOLO CRITERIO DE ORDEN, y por eso vive en el modelo (10-09-2026).
     *
     * Hasta el 10-09 había tres para lo mismo: el `sortBy` de PHP que dibuja los chips, un
     * `orderBy` de SQL en `fraseDelCliente` para la frase que se guarda, y el orden de los clicks
     * en la vista previa. Los dos primeros parecían equivalentes y no lo son: la colación de
     * MySQL ignora los acentos y el `sortBy` compara bytes.
     *
     * ESTE CANDADO ES ESTRUCTURAL PORQUE NO PUEDE SER DE CONDUCTA: la BD de test es SQLite, que
     * ordena por bytes igual que PHP, así que un `orderBy` de vuelta acá daría VERDE en la suite
     * y divergiría solo en producción (la familia de la bitácora [2026-06-30]). Medido con el
     * catálogo real: las 23 filas de hoy coinciden en los dos criterios, porque ninguna pareja se
     * diferencia primero en una vocal acentuada — pero 12 de las 23 ya llevan acento.
     */
    public function test_la_frase_y_los_chips_ordenan_con_el_mismo_criterio(): void
    {
        // SIN LOS COMENTARIOS, y no es un detalle: los asserts negativos de abajo buscan literales
        // de sintaxis (`orderBy('trabajo')`) que el comentario de `fraseDelCliente` NOMBRA para
        // explicar por qué se fueron. Sobre el archivo crudo el candado se disparaba con el código
        // perfectamente bien — escribir sobre la sintaxis, dentro de la sintaxis, la rompe
        // (bitácora [2026-08-25]). Un candado de código mira código.
        $php = $this->sinComentarios(app_path('Http/Controllers/Admin/ServicioTecnicoController.php'));

        // Los DOS sitios por su forma completa y no por un conteo de `enOrden(`: los comentarios
        // de este mismo archivo la nombran, así que el número cambia al redactar y el candado
        // fallaría sin que nada esté roto.
        $this->assertStringContainsString(
            'TiempoReparacion::enOrden($this->trabajosMarcables($orden))',
            $php,
            'El catálogo de chips dejó de pedirle el orden a `TiempoReparacion::enOrden()`.'
        );
        $this->assertStringContainsString(
            'TiempoReparacion::enOrden(TiempoReparacion::whereIn(',
            $php,
            'La frase del cliente dejó de pedirle el orden a `TiempoReparacion::enOrden()`: es el mismo orden que dibuja los chips.'
        );
        $this->assertStringNotContainsString(
            "orderBy('trabajo')",
            $php,
            'Volvió un orden por SQL: la colación de MySQL ignora los acentos y el `sortBy` del catálogo compara bytes, así que las dos superficies se separarían (y SQLite no lo caza).'
        );
        $this->assertStringNotContainsString(
            "sortBy([['grupo'",
            $php,
            'El criterio de orden se volvió a escribir en el controlador: vive en `TiempoReparacion::enOrden()`, una sola vez.'
        );
    }

    /**
     * El código de un archivo PHP sin sus comentarios, para que un candado que busca literales de
     * sintaxis no se dispare con la prosa que los explica.
     */
    private function sinComentarios(string $ruta): string
    {
        $codigo = collect(token_get_all(file_get_contents($ruta)))
            ->reject(fn ($t) => is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true))
            ->map(fn ($t) => is_array($t) ? $t[1] : $t)
            ->implode('');

        // Control positivo: si el filtro se comiera el código, los asserts negativos pasarían por
        // vacío y este candado dejaría de vigilar.
        $this->assertStringContainsString('private function fraseDelCliente(', $codigo);

        return $codigo;
    }

    /**
     * Los dos trabajos que el dueño pidió el 10-09-2026 y que el técnico no tenía cómo marcar.
     * El espigón no es un olvido cualquiera: ya aparecía en su ejemplo del 28-08 («cambio de
     * llave, cambio de estanque, cambio de caldera y se agrega espigón»), o sea que el catálogo
     * llevaba dos semanas sin la pieza que motivó rehacer esta pantalla.
     *
     * Se verifican los TRES lados que hacen que un trabajo exista de verdad, porque cada uno
     * falla distinto: la semilla (si falta, una BD nueva o staging no lo tienen), el remate
     * pegado (uno mal escrito INVENTA una cuarta opción en «¿Cómo quedó el equipo?», que se
     * deriva del catálogo) y las horas (un trabajo sin horas deja la mano de obra en $0 y
     * BLOQUEA el envío de la cotización, bitácora [2026-08-07]).
     */
    public function test_el_catalogo_trae_el_espigon_y_la_tapa_frontal_marcables(): void
    {
        $this->seed(TiemposReparacionSeeder::class);

        foreach (['Se agrega espigón', 'Cambio de tapa frontal'] as $corto) {
            $fila = TiempoReparacion::all()->first(fn ($t) => $t->trabajo_corto === $corto);

            $this->assertNotNull($fila, "El catálogo no trae «{$corto}»: el técnico no puede marcarlo.");
            $this->assertTrue($fila->activo, "«{$corto}» está inactivo: no se ofrece para marcar.");
            $this->assertSame('Reparada', $fila->grupo, "«{$corto}» tiene que caer en el grupo Reparada.");
            $this->assertGreaterThan(0, (float) $fila->horas, "«{$corto}» sin horas deja la mano de obra en \$0 y bloquea el envío.");
        }
    }

    /**
     * El remate se DERIVA del catálogo, así que un trabajo nuevo con el remate mal escrito
     * («queda en optimas condiciones», «funciona bien») no rompe nada visible: agrega una cuarta
     * tarjeta a «¿Cómo quedó el equipo?» y el técnico elige entre dos que dicen lo mismo. Por eso
     * el candado va sobre el conjunto completo y no sobre las filas nuevas.
     */
    public function test_el_catalogo_no_inventa_remates_nuevos(): void
    {
        $this->seed(TiemposReparacionSeeder::class);

        $remates = TiempoReparacion::where('activo', true)->pluck('trabajo')
            ->map(fn ($t) => (new TiempoReparacion(['trabajo' => $t]))->remate)
            ->filter()->unique()->sort()->values()->all();

        $this->assertSame(
            ['funciona normal', 'irreparable', 'queda en óptimas condiciones'],
            $remates,
            'Apareció un remate nuevo en el catálogo: «¿Cómo quedó el equipo?» se deriva de acá y sumaría una tarjeta.'
        );
    }
}
