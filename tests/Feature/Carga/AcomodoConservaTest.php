<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use App\Services\Carga\AcomodoManual;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CAMBIAR UNA CANTIDAD NO BORRA LO QUE SE ACOMODÓ A MANO.
 *
 * Decisión del dueño (13-08-2026), textual: *«muchas veces los botellones se acomodan por
 * cantidad y las cajas se acomodan a mano, yo creo que lo mejor es conservar ambas»*.
 *
 * Antes el acomodo viajaba con un CONTADOR de bloques (`acomodo_de`) y cualquier cambio de
 * cantidad lo tiraba entero: subir 20 botellones borraba el acomodo de las cajas, que es
 * justo lo que él había hecho a mano. Ahora viaja con los PRODUCTOS (`acomodo_para`: un id
 * por bloque) y cada posición se compara con el producto del bloque que hoy ocupa ese lugar.
 *
 * Los dos lados de la misma moneda, y por eso están en el mismo archivo:
 *   · se CONSERVA lo acomodado cuando cambia una cantidad;
 *   · NO se aplica una posición sobre un producto distinto — el contador dejaba pasar eso
 *     (mismo número de bloques, otros productos) y era carga ajena movida en silencio.
 */
class AcomodoConservaTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private CamionSimulacion $camion;

    private TipoBulto $caja;

    private TipoBulto $bolsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->camion = CamionSimulacion::create([
            'nombre' => 'Chevy 3', 'largo_cm' => 790, 'ancho_cm' => 220, 'alto_cm' => 230,
            'peso_max_kg' => 6430, 'pasillo_cm' => 0, 'activo' => true,
        ]);
        $this->caja = TipoBulto::create([
            'nombre' => 'Caja de tapas', 'categoria' => 'cajas',
            'largo_cm' => 46, 'ancho_cm' => 37, 'alto_cm' => 42, 'peso_kg' => 10,
            'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);
        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 3.75,
            'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true,
            'orientacion_fija' => true, 'activo' => true,
        ]);
    }

    /** Un bloque como los que emite el motor, con la línea a la que pertenece. */
    private function bloque(int $linea, int $x = 0, int $y = 0): array
    {
        return [
            'x' => $x, 'y' => $y, 'linea' => $linea,
            'orientacion' => ['largo' => 40, 'ancho' => 30, 'alto' => 20],
            'rejilla' => ['largo' => 2, 'ancho' => 3, 'alto' => 4],
            'cantidad' => 24,
        ];
    }

    // ─────────────────────────────────────────────────────────── el servicio

    public function test_la_posicion_se_aplica_cuando_el_bloque_sigue_siendo_del_mismo_producto(): void
    {
        $r = (new AcomodoManual(['0' => '120,50', '1' => '300,10'], paraProductos: '7,9'))
            ->aplicar([$this->bloque(0), $this->bloque(1)], 790, 220, [0 => 7, 1 => 9]);

        $this->assertSame([120, 50], [$r['bloques'][0]['x'], $r['bloques'][0]['y']]);
        $this->assertSame([300, 10], [$r['bloques'][1]['x'], $r['bloques'][1]['y']]);
        $this->assertSame(0, $r['ignorados']);
        $this->assertFalse($r['descartado']);
    }

    /**
     * EL AGUJERO QUE TENÍA EL CONTADOR: dos bloques antes y dos ahora, pero el segundo ya
     * es de otro producto. El control por cantidad lo dejaba pasar y la posición del
     * botellón caía sobre las cajas — la «carga ajena movida en silencio» que el encabezado
     * de `AcomodoManual` dice evitar desde el primer día.
     */
    public function test_una_posicion_no_se_aplica_sobre_un_producto_distinto(): void
    {
        // Dos bloques antes y dos ahora, pero el de la segunda línea ya es otro producto.
        $r = (new AcomodoManual(['0' => '120,50', '1' => '300,10'], paraProductos: '7,9'))
            ->aplicar([$this->bloque(0), $this->bloque(1, 400, 0)], 790, 220, [0 => 7, 1 => 4]);

        $this->assertSame(120, $r['bloques'][0]['x'], 'La posición del producto que no cambió se aplica igual.');
        $this->assertSame(400, $r['bloques'][1]['x'], 'El bloque del producto nuevo se queda donde lo puso el motor.');
        $this->assertSame(1, $r['ignorados']);
        $this->assertTrue($r['activo'], 'Que una posición no sirva no invalida las otras: eso es «conservar ambas».');
    }

    /**
     * EL CASO QUE EL CONTADOR MATABA. Se acomodó con 2 bloques y ahora el motor reparte la
     * primera línea en 2 (misma carga, otra cantidad): 3 bloques. El contador tiraba TODO
     * porque 3 ≠ 2. Ahora sobrevive lo que sigue siendo del mismo producto en su lugar, y lo
     * que se corrió vuelve al lugar del cálculo y se cuenta.
     */
    public function test_un_reparto_en_mas_bloques_no_tira_las_posiciones_que_siguen_sirviendo(): void
    {
        $r = (new AcomodoManual(['0' => '120,50', '1' => '300,10'], hechoPara: 2, paraProductos: '7,9'))
            ->aplicar(
                [$this->bloque(0), $this->bloque(0, 200, 0), $this->bloque(1, 400, 0)],
                790, 220, [0 => 7, 1 => 9],
            );

        $this->assertFalse($r['descartado'], 'Un reparto en más bloques volvió a tirar el acomodo entero.');
        $this->assertSame(120, $r['bloques'][0]['x'], 'La posición del bloque que sigue siendo del mismo producto se perdió.');
        $this->assertSame(200, $r['bloques'][1]['x'], 'La posición del otro producto se aplicó sobre este bloque.');
        $this->assertSame(400, $r['bloques'][2]['x']);
        $this->assertSame(1, $r['movidos']);
        $this->assertSame(1, $r['ignorados']);
    }

    public function test_si_ninguna_posicion_sobrevive_se_dice_que_se_descarto(): void
    {
        // Media docena de bloques volviendo solos a su lugar, sin cartel, se lee como un
        // bug. Cuando no queda nada del acomodo se dice con las palabras de siempre.
        $r = (new AcomodoManual(['0' => '120,50'], paraProductos: '7'))
            ->aplicar([$this->bloque(0)], 790, 220, [0 => 4]);

        $this->assertTrue($r['descartado']);
        $this->assertFalse($r['activo']);
        $this->assertSame(0, $r['bloques'][0]['x']);
    }

    /**
     * El contador viejo sigue valiendo para los links YA COMPARTIDOS: el link es el
     * escenario y hay planes acomodados a mano circulando por WhatsApp desde el 11-08.
     */
    public function test_el_contador_viejo_sigue_descartando_en_los_links_compartidos(): void
    {
        $r = (new AcomodoManual(['0' => '120,50'], hechoPara: 3))
            ->aplicar([$this->bloque(0)], 790, 220, [0 => 7]);

        $this->assertTrue($r['descartado']);
        $this->assertSame(0, $r['bloques'][0]['x']);
    }

    /**
     * Y cuando llega la lista de líneas, el contador NO se consulta. Es el corazón de la
     * decisión: el número de bloques puede cambiar —una cantidad distinta reparte distinto—
     * y las posiciones que siguen siendo del mismo producto se aplican igual.
     */
    public function test_con_la_lista_de_productos_el_contador_ya_no_manda(): void
    {
        $r = (new AcomodoManual(['0' => '120,50'], hechoPara: 7, paraProductos: '7'))
            ->aplicar([$this->bloque(0)], 790, 220, [0 => 7]);

        $this->assertFalse($r['descartado'], 'El contador de un link viejo no puede tirar un acomodo que sí corresponde.');
        $this->assertSame(120, $r['bloques'][0]['x']);
    }

    public function test_una_lista_de_productos_inventada_no_rompe_la_pantalla(): void
    {
        // La URL se edita a mano. Una lista con basura se ignora y manda el camino viejo:
        // el bloque se queda en el lugar VERIFICADO por el motor.
        $r = (new AcomodoManual(['0' => '120,50'], paraProductos: 'A,B'))
            ->aplicar([$this->bloque(0)], 790, 220, [0 => 7]);

        $this->assertSame(120, $r['bloques'][0]['x'], 'Sin lista válida se aplica como antes, sin comparar productos.');
        $this->assertSame(0, $r['ignorados']);
    }

    // ─────────────────────────────────────────────────────────── la pantalla

    /** @param  array<string, mixed>  $extra */
    private function pantalla(array $lineas, array $extra = [])
    {
        return $this->actingAs($this->vendedor)->get(route('admin.carga.index', $extra + [
            'camion_id' => $this->camion->id,
            'lineas' => $lineas,
        ]));
    }

    /**
     * EL PEDIDO, DE PUNTA A PUNTA: se acomoda a mano, después se cambia una cantidad —lo que
     * él hace todo el tiempo— y el acomodo sigue ahí.
     *
     * La firma se lee del propio tablero en vez de escribirla a mano: así el candado prueba
     * el camino que recorre la pantalla y no una suposición mía sobre cómo reparte el motor.
     *
     * LAS CANTIDADES ESTÁN ELEGIDAS (medidas contra el motor, 13-08): con 40 cajas y 100
     * bolsas el motor emite TRES bloques —parte las cajas en dos— y con 300 bolsas emite
     * DOS. O sea que este cambio de cantidad sí cambia el reparto, que es exactamente el
     * caso donde el contador de bloques tiraba el acomodo entero. El final del test lo
     * comprueba con la clave vieja, para que quede escrito qué se ganó.
     */
    public function test_cambiar_una_cantidad_conserva_lo_acomodado_a_mano(): void
    {
        $antes = $this->pantalla([
            ['tipo' => $this->caja->id, 'cantidad' => 40],
            ['tipo' => $this->bolsa->id, 'cantidad' => 100],
        ])->assertOk()->viewData('escena')['acomodo'];

        $firma = implode(',', array_column($antes['piezas'], 'producto'));
        $bloquesAntes = count($antes['piezas']);

        // Se acomodan dos bloques a mano y se triplican los botellones.
        $conMasBolsas = [
            ['tipo' => $this->caja->id, 'cantidad' => 40],
            ['tipo' => $this->bolsa->id, 'cantidad' => 300],
        ];
        $aMano = ['acomodo' => ['0' => '300,60', '1' => '0,150']];

        $despues = $this->pantalla($conMasBolsas, $aMano + ['acomodo_para' => $firma])
            ->assertOk()->viewData('escena')['acomodo'];

        $this->assertNotSame($bloquesAntes, count($despues['piezas']),
            'La cantidad nueva ya no cambia el reparto en bloques: hay que volver a elegirlas o este candado prueba el caso fácil.');
        $this->assertSame(300, $despues['piezas'][0]['x'],
            'Cambiar la cantidad borró el acomodo a mano: es lo que el dueño pidió que dejara de pasar.');
        $this->assertSame([0, 150], [$despues['piezas'][1]['x'], $despues['piezas'][1]['y']]);
        $this->assertTrue($despues['activo']);
        $this->assertFalse($despues['descartado']);

        // LA MISMA CARGA CON LA CLAVE VIEJA: el contador no coincide y se pierde todo. Es el
        // link que el dueño tenía abierto ayer, y sigue funcionando así a propósito.
        $conContador = $this->pantalla($conMasBolsas, $aMano + ['acomodo_de' => $bloquesAntes])
            ->assertOk()->viewData('escena')['acomodo'];

        $this->assertTrue($conContador['descartado']);
        $this->assertSame(0, $conContador['piezas'][0]['x']);
    }

    /**
     * Y el otro lado: si se cambia el PRODUCTO, la posición no se le pasa al nuevo. Con el
     * contador viejo —mismo número de bloques— esto se aplicaba igual.
     */
    public function test_cambiar_el_producto_no_le_pasa_la_posicion_al_nuevo(): void
    {
        $firma = implode(',', array_column(
            $this->pantalla([['tipo' => $this->caja->id, 'cantidad' => 6]])
                ->assertOk()->viewData('escena')['acomodo']['piezas'],
            'producto',
        ));

        // MISMA línea —la 0— y mismo número de bloques, pero ahora tiene otro producto:
        // es el caso que el contador viejo dejaba pasar, y el que la firma por índice de
        // línea tampoco veía, porque la fila sigue siendo la primera.
        $respuesta = $this->pantalla([
            ['tipo' => $this->bolsa->id, 'cantidad' => 20],
        ], [
            'acomodo' => ['0' => '300,60'],
            'acomodo_para' => $firma,
        ])->assertOk();

        $acomodo = $respuesta->viewData('escena')['acomodo'];

        $this->assertSame(0, $acomodo['piezas'][0]['x'], 'La posición cayó sobre otro producto.');
        $this->assertTrue($acomodo['descartado']);
        $respuesta->assertSee('Se descartó el acomodo a mano');
    }

    /**
     * EL PUENTE. Un candado sobre el servicio no prueba que la pantalla lo use: el tablero
     * es quien escribe la clave, y si sigue escribiendo el contador viejo todo lo de arriba
     * queda verde mientras el dueño pierde el acomodo igual. Ya pasó tres veces en este
     * módulo.
     */
    public function test_el_tablero_escribe_para_que_productos_se_acomodo(): void
    {
        $panel = file_get_contents(resource_path('views/admin/carga/_acomodo.blade.php'));

        $this->assertStringContainsString("u.searchParams.set('acomodo_para', this.piezas.map(p => p.producto).join(','))", $panel,
            'El tablero dejó de escribir para qué productos se acomodó: el servidor no puede conservar nada.');
        $this->assertStringNotContainsString("set('acomodo_de'", $panel,
            'El tablero volvió a escribir el contador viejo: cualquier cambio de cantidad tira el acomodo entero.');
        $this->assertStringContainsString("k === 'acomodo_para'", $panel,
            'Al volver al automático no se limpia `acomodo_para`: la clave queda pegada en la URL.');
    }

    /** Y la pieza tiene que TRAER su producto, o el tablero escribiría una lista de vacíos. */
    public function test_cada_pieza_del_tablero_dice_de_que_producto_es(): void
    {
        $piezas = $this->pantalla([
            ['tipo' => $this->caja->id, 'cantidad' => 6],
            ['tipo' => $this->bolsa->id, 'cantidad' => 20],
        ])->assertOk()->viewData('escena')['acomodo']['piezas'];

        foreach ($piezas as $i => $p) {
            $this->assertArrayHasKey('producto', $p, "La pieza {$i} del tablero no dice de qué producto es.");
            $this->assertIsInt($p['producto']);
        }

        $productos = array_column($piezas, 'producto');
        $this->assertContains($this->caja->id, $productos);
        $this->assertContains($this->bolsa->id, $productos, 'Ninguna pieza es del segundo producto: el candado no probaría nada.');
    }

    /**
     * Y el modo de UN solo producto —«¿cuánto entra?»— también acomoda a mano. Ahí el bloque
     * no trae línea, así que si el default se rompiera el acomodo se ignoraría entero: el
     * mismo agujero silencioso de siempre, en el modo que más se usa.
     */
    public function test_el_modo_de_un_producto_tambien_conserva_su_acomodo(): void
    {
        $acomodo = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id,
            'tipo_bulto_id' => $this->caja->id,
            'acomodo' => ['0' => '150,40'],
            'acomodo_para' => (string) $this->caja->id,
        ]))->assertOk()->viewData('escena')['acomodo'];

        $this->assertSame(150, $acomodo['piezas'][0]['x']);
        $this->assertTrue($acomodo['activo']);
        $this->assertSame(0, $acomodo['ignorados']);
    }
}
