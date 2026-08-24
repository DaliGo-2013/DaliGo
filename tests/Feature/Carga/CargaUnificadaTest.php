<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UNA SOLA PREGUNTA: la cantidad vacía significa «lo que quepa».
 *
 * Pedido del dueño, dos veces. El 11-08: «los dos apartados se repiten, sería bueno
 * juntar ambas opciones en una sola y que ahí se pueda calcular sobre un producto o
 * varios». El 21-08, mirando la pantalla ya rediseñada: «hay que unificar los dos
 * puntos ¿Cuánto entra? y ¿cabe esta carga?, la única diferencia que veo es un
 * producto o varios y es lo mismo».
 *
 * EL MOTOR YA SABÍA HACERLO. Las líneas abiertas se construyeron el 11-08 con sus
 * seis candados (`LineaAbiertaTest`) justamente para esta fusión, y la pantalla nunca
 * se cableó: `grep abierta` en `app/Http/Controllers` y `resources/views` daba CERO.
 * Estos candados son la otra mitad — que el formulario deje mandar la cantidad vacía
 * y que lo que vuelve de ahí no mienta.
 *
 * Lo que se vigila, en orden de qué dolería más si se rompe:
 *  · que una línea sin cantidad no se lea como «pediste 0» ni como «quedó afuera»;
 *  · que la cantidad vacía SOBREVIVA al viaje de vuelta al formulario (un `(int)` la
 *    convertía en 0, que para el motor es «no coloques nada» — lo contrario exacto);
 *  · que el aviso de sobrepeso siga contando los kilos de la línea abierta;
 *  · que el CERO explícito siga significando cero.
 */
class CargaUnificadaTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private CamionSimulacion $hd35;

    private TipoBulto $bolsa;

    private TipoBulto $caja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->vendedor = tap(User::factory()->create())->assignRole('vendedor');

        // El HD35 medido con huincha y la bolsa de los cupos de referencia: así el
        // número que sale acá se puede comparar con el que dictó el dueño (420 de pie).
        $this->hd35 = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35',
            'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1500, 'pasillo_cm' => 0, 'activo' => true,
        ]);
        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 5,
            'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => true, 'activo' => true,
        ]);
        $this->caja = TipoBulto::create([
            'nombre' => 'Caja de tapas', 'categoria' => 'cajas',
            'largo_cm' => 46, 'ancho_cm' => 37, 'alto_cm' => 42, 'peso_kg' => 10,
            'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);
    }

    private function ver(array $lineas)
    {
        return $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'lineas' => $lineas,
        ]));
    }

    /**
     * LA FUSIÓN, EN UN NÚMERO. Un producto sin cantidad tiene que contestar lo mismo
     * que contestaba la pestaña «¿Cuánto entra?»: los 420 botellones de pie del HD35,
     * el primero de los cupos de referencia que dictó el dueño el 04-08.
     *
     * Si esto se mueve, la pantalla nueva dejó de reproducir un número verificado y
     * toda la fusión queda en duda.
     */
    public function test_un_producto_sin_cantidad_contesta_el_cupo_maximo(): void
    {
        $mixta = $this->ver([['tipo' => $this->bolsa->id]])->assertOk()->viewData('mixta');

        $this->assertNotNull($mixta, 'Una línea sin cantidad no llegó al motor.');
        $fila = $mixta['lineas'][0];

        $this->assertSame(420, $fila['cargadas_unidades'], 'Cambió el cupo de referencia del HD35.');
        $this->assertSame(84, $fila['bultos_colocados'], '84 bolsas de 5 = 420 botellones.');
        $this->assertTrue($fila['abierta'], 'La línea no se marcó como abierta.');
    }

    /**
     * Y NO SE LEE COMO UN INCUMPLIMIENTO. Es la mitad que hace que la fusión sea
     * usable: pedir «lo que quepa» y que la pantalla conteste «quedaron 900 afuera»
     * sería inventar una promesa incumplida.
     */
    public function test_lo_que_quepa_no_se_reporta_como_carga_afuera(): void
    {
        $mixta = $this->ver([['tipo' => $this->bolsa->id]])->assertOk()->viewData('mixta');
        $fila = $mixta['lineas'][0];

        $this->assertNull($fila['pedidas_unidades'], 'Una línea abierta no tiene cantidad pedida.');
        $this->assertNull($fila['motivo'], 'La línea abierta reportó carga afuera.');
        $this->assertTrue($mixta['cabeTodo'], 'Una línea abierta tumbó el «cabe todo».');
        $this->assertSame('espacio', $fila['lleno_por'], 'No dice con qué se llenó.');
    }

    /**
     * LO QUE ANTES NO SE PODÍA PREGUNTAR: «esto va firme, y con lo que sobre lléname
     * de esto otro». Es el premio de fusionar, y el caso que ninguna de las dos
     * pestañas viejas podía contestar.
     *
     * La línea con cantidad se respeta ENTERA (el relleno no se come carga vendida) y
     * la abierta se lleva lo que sobra — que es más que cero, o el caso no probaría nada.
     */
    public function test_una_linea_firme_y_otra_hasta_llenar_conviven(): void
    {
        $mixta = $this->ver([
            ['tipo' => $this->caja->id, 'cantidad' => 40],
            ['tipo' => $this->bolsa->id],
        ])->assertOk()->viewData('mixta');

        $firme = $mixta['lineas'][0];
        $relleno = $mixta['lineas'][1];

        $this->assertSame(40, $firme['cargadas_unidades'], 'El relleno se comió carga ya vendida.');
        $this->assertNull($firme['motivo']);
        $this->assertGreaterThan(0, $relleno['cargadas_unidades'], 'El relleno no cargó nada.');
        $this->assertTrue($mixta['cabeTodo']);

        // Y el relleno se llevó MENOS que si fuera solo: las 40 cajas le comieron piso.
        $solo = $this->ver([['tipo' => $this->bolsa->id]])->assertOk()->viewData('mixta');
        $this->assertLessThan($solo['lineas'][0]['cargadas_unidades'], $relleno['cargadas_unidades'],
            'El relleno ignoró la carga que ya estaba arriba.');
    }

    /**
     * LA CANTIDAD VACÍA SOBREVIVE AL VIAJE DE VUELTA.
     *
     * El formulario se re-siembra con `lineasSel`, y ahí un `(int) $l['cantidad']`
     * convertía el vacío en 0. No es cosmético: para el motor el 0 significa «no
     * coloques nada» (candado propio en `LineaAbiertaTest`), o sea que la línea que el
     * usuario dejó como «lléname» volvía convertida en «no cargues nada» — en silencio
     * y sin que nada fallara. Misma familia que el `?? 0` de la bitácora [2026-08-20].
     */
    public function test_la_cantidad_vacia_no_vuelve_convertida_en_cero(): void
    {
        $res = $this->ver([
            ['tipo' => $this->bolsa->id],
            ['tipo' => $this->caja->id, 'cantidad' => 12],
        ])->assertOk();

        $sel = $res->viewData('lineasSel')->values();

        $this->assertSame('', $sel[0]['cantidad'], 'La cantidad vacía volvió como otra cosa (¿0?).');
        $this->assertSame(12, $sel[1]['cantidad'], 'La cantidad escrita se perdió.');
    }

    /**
     * EL CERO EXPLÍCITO SIGUE SIENDO CERO. La asimetría es deliberada y es del motor:
     * vacío = «lo que quepa», cero = «nada». Si se confundieran, escribir 0 llenaría el
     * camión — el error más caro posible de esta fusión, porque va hacia ARRIBA.
     *
     * El formulario ni siquiera deja mandar 0 (`min:1`), así que el candado mira las
     * dos mitades: que el 0 rebote en la validación y que el vacío pase.
     */
    public function test_cero_no_es_lo_mismo_que_vacio(): void
    {
        $this->ver([['tipo' => $this->bolsa->id, 'cantidad' => 0]])
            ->assertSessionHasErrors('lineas.0.cantidad');

        $this->ver([['tipo' => $this->bolsa->id]])->assertOk()->assertSessionHasNoErrors();
    }

    /**
     * NO SE PIERDE «DE DÓNDE SALE ESE NÚMERO».
     *
     * Era lo ÚNICO que la pantalla de un producto tenía y la de varios no: la rejilla
     * con que se acomodó y qué se agotó primero. Al fusionar, ese es exactamente el
     * caso que se cuela — la lección de la bitácora [2026-08-20]: «al unificar dos
     * pantallas, listar los casos que atendía CADA una y probar el que solo tenía una».
     *
     * Y el límite fino («el largo de la caja», no «espacio») solo se pide a `cupo()`
     * cuando la línea abierta está SOLA, porque `cupo()` describe el camión vacío:
     * mostrarlo junto a una carga mixta sería explicar un camión que no se dibujó.
     */
    public function test_la_fila_dice_con_que_rejilla_y_que_se_agoto_primero(): void
    {
        $sola = $this->ver([['tipo' => $this->bolsa->id]])->assertOk()->viewData('mixta');
        $fila = $sola['lineas'][0];

        // La rejilla del acomodo que el motor dibujó: 3 de largo × 7 de ancho × 4 de alto.
        $this->assertNotNull($fila['rejilla'], 'La fila no dice con qué rejilla se acomodó.');
        $this->assertSame(84, $fila['rejilla']['largo'] * $fila['rejilla']['ancho'] * $fila['rejilla']['alto'],
            'La rejilla no cuadra con las 84 bolsas colocadas.');

        // Sola y abierta = la vieja pregunta, así que el límite viene con el detalle
        // fino de cupo() y no con el «espacio» grueso.
        $this->assertContains($fila['limita'], ['largo', 'ancho', 'alto', 'peso'],
            'Se perdió el límite fino de la pantalla de un producto.');

        // Con OTRA línea arriba ya no se usa cupo(): el camión no está vacío.
        $conOtra = $this->ver([
            ['tipo' => $this->caja->id, 'cantidad' => 20],
            ['tipo' => $this->bolsa->id],
        ])->assertOk()->viewData('mixta');

        $this->assertSame('espacio', $conOtra['lineas'][1]['limita'],
            'Con una carga mixta se está mostrando el límite del camión VACÍO.');
    }

    /**
     * EL AVISO DE SOBREPESO CUENTA LOS KILOS DE LA ABIERTA.
     *
     * El peso «pedido» se suma con la cantidad de cada línea, y una abierta no tiene.
     * Contarla como 0 dejaría fuera de la cuenta kilos que SÍ van arriba del camión —
     * justo en el aviso que el dueño pidió el 11-08 («que cuando se pase el límite de
     * carga aparezca un cartel»).
     */
    public function test_el_peso_de_una_linea_abierta_entra_en_la_cuenta(): void
    {
        $mixta = $this->ver([['tipo' => $this->bolsa->id]])->assertOk()->viewData('mixta');

        // 84 bolsas × 5 kg = 420 kg, y el motor las colocó todas.
        $this->assertSame(420.0, round($mixta['peso']['pedido_kg'], 1),
            'El peso de la línea abierta quedó fuera de la cuenta.');
    }
}
