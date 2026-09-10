<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use App\Services\Carga\CalculoDeCarga;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EL ESPACIO QUE QUEDA AL COSTADO Y ARRIBA DE LOS PALLETS.
 *
 * Pedido del jefe de logística (10-09-2026), con una captura del modo «Sobre pallet» y la
 * franja vacía marcada en rojo contra el lateral: *«ese espacio, ¿se podía poner más cosas
 * de forma manual de última para ocupar todo? Lo mismo que arriba de todos los pallets
 * sobra espacio y se puede poner cosas que no sean tan pesadas»*.
 *
 * SU LECTURA ERA CORRECTA Y EL HUECO ERA EL MÁS GRANDE DE TODOS. Su caso, exacto: 20
 * pallets de 120 × 100 × 180 en un contenedor de 40' (1203 × 235 × 239) → 10 × 2 pallets,
 * y sobran 35 cm de ancho y 59 cm de alto.
 *
 * Y NO LO DECÍA NADIE, que es lo que este archivo arregla. `pisoLibre()` —el único hueco
 * que la pantalla informaba— mide a TODO el ancho y TODO el alto, y su propio comentario
 * declara que el piso al costado no se cuenta «para nunca prometer de más». Este modo cae
 * justo en ese caso: los pallets llegan hasta la puerta (3 cm libres, que sí se informaban)
 * y todo lo que sobra está en las dos direcciones que esa medida calla.
 *
 * LO QUE SE INFORMA SON MEDIDAS, NO UN CUPO, y la distinción es la que sostiene el resto
 * del simulador: «sobran 35 cm de ancho» es geometría; «ahí entran 100 bolsas» es una
 * promesa que depende de qué se meta, de si aguanta ir encima y del peso que queda. Esa la
 * contesta el motor en «La carga», y a eso lleva el puente.
 */
class EspacioLibreSobrePalletTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private CamionSimulacion $contenedor;

    private TipoBulto $caja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->vendedor = tap(User::factory()->create())->assignRole('vendedor');

        // El contenedor de 40' con sus medidas de placa, las mismas del seeder.
        $this->contenedor = CamionSimulacion::create([
            'nombre' => "Contenedor 40'",
            'largo_cm' => 1203, 'ancho_cm' => 235, 'alto_cm' => 239,
            'peso_max_kg' => 28800, 'pasillo_cm' => 0, 'activo' => true,
        ]);

        // La caja de la captura: 20 por pallet, en rejilla 5 × 2 × 2.
        $this->caja = TipoBulto::create([
            'nombre' => 'Caja de soportes', 'categoria' => 'cajas',
            'largo_cm' => 24, 'ancho_cm' => 50, 'alto_cm' => 82, 'peso_kg' => 6.5,
            'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);
    }

    /** @return array{0:\Illuminate\Testing\TestResponse,1:array} */
    private function verPallet(array $extra = []): array
    {
        $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', array_merge([
            'camion_id' => $this->contenedor->id,
            'tipo_bulto_id' => $this->caja->id,
            'sobre_pallet' => 1,
            'pallet_tipo' => 'industrial',
            'pallet_alto' => 180,
        ], $extra)))->assertOk();

        return [$res, $res->viewData('enPallet')];
    }

    // ─────────────────────────────────────────────────── la medida

    /**
     * LOS DOS HUECOS, con los números de su caso. Un pallet de 100 cm de ancho en un
     * contenedor de 235 deja 35, y armado a 180 en uno de 239 deja 59.
     */
    public function test_informa_el_ancho_y_el_alto_que_quedan_libres(): void
    {
        [$res, $enPallet] = $this->verPallet();

        $this->assertSame(2, $enPallet['enCamion']['rejilla']['ancho'], 'Deberían entrar 2 pallets a lo ancho.');
        $this->assertSame(35, $enPallet['libreAnchoCm']);
        $this->assertSame(59, $enPallet['libreAltoCm']);

        // Y se ven en la pantalla, que es el punto: el dato existía en la geometría y
        // ninguna superficie lo mostraba.
        $res->assertSee('Libre al costado');
        $res->assertSee('35 cm de ancho');
        $res->assertSee('Libre encima');
        $res->assertSee('59 cm de alto');
    }

    /**
     * SIN PALLETS COLOCADOS NO SE INFORMA NINGUNA FRANJA. Con un pallet más alto que el
     * camión no entra ninguno, y ahí el hueco libre es el camión entero: decir «sobran
     * 235 cm de ancho» sería cierto y a la vez inútil, y al lado del «0 pallets» se
     * leería como que algo se puede rellenar.
     */
    public function test_si_no_entra_ningun_pallet_no_hay_franja_que_ofrecer(): void
    {
        [$res, $enPallet] = $this->verPallet(['pallet_alto' => 230, 'camion_id' => CamionSimulacion::create([
            'nombre' => 'Furgón bajo', 'largo_cm' => 400, 'ancho_cm' => 200, 'alto_cm' => 180,
            'peso_max_kg' => 1400, 'pasillo_cm' => 0, 'activo' => true,
        ])->id]);

        $this->assertSame(0, $enPallet['cabenPallets']);
        $this->assertSame(0, $enPallet['libreAnchoCm']);
        $this->assertSame(0, $enPallet['libreAltoCm']);
        $res->assertDontSee('Libre al costado');
        $res->assertDontSee('Queda espacio sin usar');
    }

    // ─────────────────────────────────────────────────── el puente

    /**
     * EL PUENTE LLEVA EL PALLET ARMADO TAL CUAL. Es la respuesta a las dos cosas que
     * pidió el jefe —rellenar la franja y que la app «autorrellene» el camión—: las dos
     * las hace el motor en «La carga» con una línea sin cantidad, y este modo no decía ni
     * que existían. Si el enlace no llevara el pallet, del otro lado habría que volver a
     * escribir todo y nadie lo usaría.
     */
    public function test_el_puente_lleva_camion_pallet_y_cuantos_entraron(): void
    {
        [$res, $enPallet] = $this->verPallet();
        $html = $res->getContent();

        $res->assertSee('Queda espacio sin usar');
        $res->assertSee('Sumar relleno en «La carga»', false);

        // El enlace se assertea des-escapado DOS VECES, y las dos hacen falta: Blade
        // emite `&amp;` dentro de un href (bitácora [2026-08-13]) y `route()` además
        // PERCENT-ENCODEA los corchetes del array, así que en el HTML dice
        // `lineas%5B0%5D%5Bpallet%5D`. Buscar `lineas[0][pallet]` a secas no encuentra
        // nada aunque el enlace esté perfecto — y un `assertDontSee` de esa misma forma
        // pasaría SIEMPRE, que es la versión peligrosa del mismo error.
        $crudo = urldecode(html_entity_decode($html));
        $this->assertStringContainsString('lineas[0][pallet]=industrial', $crudo);
        $this->assertStringContainsString('lineas[0][pallet_alto]=180', $crudo);
        $this->assertStringContainsString('lineas[0][tipo]='.$this->caja->id, $crudo);
        // Los 20 pallets que el motor colocó, no un número escrito a mano.
        $this->assertSame(20, $enPallet['cabenPallets']);
        $this->assertStringContainsString('lineas[0][cantidad]=20', $crudo);
    }

    /**
     * Y EL DESTINO DEL PUENTE CALCULA DE VERDAD lo que promete. Es la mitad que ningún
     * assert de markup puede cubrir: que el enlace exista no dice que del otro lado se
     * pueda rellenar. Se sigue el enlace con una segunda línea de relleno sin cantidad y
     * se comprueba que los 20 pallets siguen intactos Y que el relleno entró.
     */
    public function test_seguir_el_puente_y_sumar_relleno_no_saca_ningun_pallet(): void
    {
        $bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 5,
            'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);

        $mixta = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->contenedor->id,
            'lineas' => [
                ['pallet' => 'industrial', 'pallet_alto' => 180, 'tipo' => $this->caja->id, 'cantidad' => 20],
                // La línea del relleno: sin cantidad = «lo que quepa».
                ['tipo' => $bolsa->id],
            ],
        ]))->assertOk()->viewData('mixta');

        $filas = $mixta['lineas'];

        // Los 20 pallets siguen enteros: el relleno se acomoda en lo que sobra, nunca al
        // revés (es la regla de las líneas abiertas del motor).
        //
        // Una línea de pallet habla en PALLETS y no en las cajas que lleva —`palletDeLinea`
        // le pasa `unidadesEncima: 1` a propósito, con su razón escrita: «cargadas 54 de 3»
        // sería un número sin sentido—. Así que acá 20 son 20 pallets.
        $this->assertSame(20, $filas[0]['cargadas_unidades'],
            'El relleno no puede costarle un pallet a la carga que ya estaba.');

        // Y el relleno entró: si diera 0, el puente estaría ofreciendo un espacio que en
        // la práctica no se puede usar, que es peor que no ofrecerlo.
        $this->assertGreaterThan(0, $filas[1]['cargadas_unidades'] ?? 0,
            'En la franja y encima de los pallets tiene que entrar carga liviana.');
    }

    /**
     * CON LA HUELLA AJUSTADA A MANO NO SE OFRECE EL ENLACE, y no es un descuido: una
     * línea de carga expresa su pallet con la CLAVE del tipo estándar, así que el largo y
     * el ancho propios no viajan. El enlace armaría un pallet de otro tamaño y devolvería
     * otros números con cara de ser los mismos — exactamente lo que el simulador no hace
     * en ninguna otra parte. La franja se sigue informando; lo que se calla es el atajo.
     */
    public function test_con_medidas_propias_se_dice_la_franja_pero_no_se_ofrece_el_atajo(): void
    {
        [$res, $enPallet] = $this->verPallet(['pallet_largo' => 120, 'pallet_ancho' => 110]);

        $this->assertNull($enPallet['puenteTipo']);
        $res->assertSee('Libre al costado');
        $res->assertDontSee('Sumar relleno en «La carga»', false);
        $res->assertSee('este pallet tiene medidas propias');
    }
}
