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
 * GRUPOS DE CARGA POR PARADA: lo que se entrega primero va contra la puerta.
 *
 * Pedido del dueño (12-08-2026) sobre los ejemplos de EasyCargo —«Hamburg – Dresden –
 * Antwerp», con la nota «los artículos del primer grupo fueron cargados primero»—. Es su
 * realidad: las hojas de ruta tienen varias entregas y el camión se carga al revés del
 * orden en que se descarga.
 *
 * El motor coloca cada bloque en la región más al FONDO que le sirva (x = 0 es la cabina y
 * la puerta está al final), así que para que la parada 1 quede contra la puerta hay que
 * colocar la ÚLTIMA parada PRIMERO.
 */
class GruposPorParadaTest extends TestCase
{
    use RefreshDatabase;

    private const CHEVY = ['largo' => 790, 'ancho' => 220, 'alto' => 230, 'peso_max_kg' => 6430];

    /** Cajas iguales: así lo único que puede decidir el orden es la parada. */
    private const CAJA = [
        'categoria' => 'cajas', 'largo' => 100, 'ancho' => 100, 'alto' => 100, 'peso' => 10,
        'unidades' => 1, 'apilable_max' => 1, 'soporta_peso_encima' => true, 'orientacion_fija' => true,
    ];

    private CalculoDeCarga $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CalculoDeCarga;
    }

    /** El x (distancia al fondo) de cada línea, para leer el orden del acomodo. */
    private function fondoPorLinea(array $r): array
    {
        $x = [];
        foreach ($r['bloques'] as $b) {
            $x[$b['linea']] = min($x[$b['linea']] ?? PHP_INT_MAX, $b['x']);
        }
        ksort($x);

        return $x;
    }

    public function test_la_ultima_parada_va_al_fondo_y_la_primera_contra_la_puerta(): void
    {
        $r = $this->calc->carga(self::CHEVY, [
            ['bulto' => self::CAJA, 'cantidad' => 2, 'grupo' => 1],   // primera entrega
            ['bulto' => self::CAJA, 'cantidad' => 2, 'grupo' => 2],
            ['bulto' => self::CAJA, 'cantidad' => 2, 'grupo' => 3],   // última entrega
        ]);

        $x = $this->fondoPorLinea($r);

        $this->assertLessThan($x[1], $x[2], 'La parada 3 tiene que quedar más al fondo que la 2.');
        $this->assertLessThan($x[0], $x[1], 'La parada 2 tiene que quedar más al fondo que la 1.');
        $this->assertSame(0, $x[2], 'La última parada no arrancó pegada al fondo.');
    }

    /**
     * LA PARADA MANDA SOBRE EL VOLUMEN. Sin esta regla, «lo grande primero» pondría el
     * bulto grande de la última entrega al fondo y el chico de la primera también al fondo,
     * y el conductor tendría que vaciar media carga en la primera parada.
     */
    public function test_la_parada_manda_sobre_el_orden_por_volumen(): void
    {
        $grande = ['largo' => 200, 'ancho' => 200, 'alto' => 200] + self::CAJA;

        $r = $this->calc->carga(self::CHEVY, [
            ['bulto' => $grande, 'cantidad' => 1, 'grupo' => 1],   // grande, pero se entrega PRIMERO
            ['bulto' => self::CAJA, 'cantidad' => 1, 'grupo' => 2],
        ]);

        $x = $this->fondoPorLinea($r);

        $this->assertLessThan($x[0], $x[1], 'El bulto grande de la primera entrega se fue al fondo: la parada no mandó.');
    }

    /**
     * Y MANDA SOBRE EL BUSCADOR DE ACOMODOS. Los planes reordenan DENTRO de la parada; si
     * uno pudiera reordenar entre paradas, un acomodo «con más volumen» rompería la ruta en
     * silencio, que es el peor de los dos errores: el volumen se recupera en el viaje
     * siguiente, la descarga en la vereda no.
     */
    public function test_ningun_plan_puede_reordenar_entre_paradas(): void
    {
        // La caja aguanta peso y la bolsa sube: es el caso donde el plan «base primero»
        // querría mover las cajas al fondo. Pero la caja se entrega PRIMERO.
        $bolsa = ['categoria' => 'botellones', 'largo' => 130, 'ancho' => 26, 'alto' => 51,
            'peso' => 3.75, 'unidades' => 5, 'apilable_max' => 30,
            'soporta_peso_encima' => true, 'orientacion_fija' => true];

        $r = $this->calc->carga(self::CHEVY, [
            ['bulto' => self::CAJA, 'cantidad' => 6, 'grupo' => 1],
            ['bulto' => $bolsa, 'cantidad' => 30, 'grupo' => 2],
        ]);

        $x = $this->fondoPorLinea($r);

        $this->assertLessThan($x[0], $x[1], 'Un plan reordenó entre paradas: la carga de la segunda entrega no quedó al fondo.');
    }

    /**
     * EL PUENTE: la PANTALLA manda la parada al motor.
     *
     * La lección del día (12-08): un candado sobre el motor no prueba que la pantalla lo
     * use. El segundo piso estuvo MUERTO en la app con seis candados en verde porque
     * `paraCalculo()` no mandaba un campo. Así que la parada se prueba de punta a punta:
     * entra por la query como la manda el formulario y se lee en la escena que dibuja el
     * lienzo.
     *
     * En la escena cada bloque se identifica por su LETRA (A = primera línea, B = segunda),
     * que es lo mismo que se ve escrito sobre las cajas.
     */
    public function test_la_pantalla_manda_la_parada_al_motor(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $camion = CamionSimulacion::create([
            'nombre' => 'Chevy 3', 'largo_cm' => 790, 'ancho_cm' => 220, 'alto_cm' => 230,
            'peso_max_kg' => 6430, 'pasillo_cm' => 0, 'activo' => true,
        ]);
        $caja = TipoBulto::create([
            'nombre' => 'Caja de prueba', 'categoria' => 'cajas',
            'largo_cm' => 100, 'ancho_cm' => 100, 'alto_cm' => 100, 'peso_kg' => 10,
            'unidades' => 1, 'apilable_max' => 1, 'soporta_peso_encima' => true,
            'orientacion_fija' => true, 'activo' => true,
        ]);

        $escena = $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $camion->id,
            'lineas' => [
                ['tipo' => $caja->id, 'cantidad' => 2, 'grupo' => 1],   // se entrega primero
                ['tipo' => $caja->id, 'cantidad' => 2, 'grupo' => 3],   // se entrega al final
            ],
        ]))->assertOk()->viewData('escena');

        $x = [];
        foreach ($escena['bloques'] as $b) {
            $x[$b['letra']] = min($x[$b['letra']] ?? PHP_INT_MAX, $b['x']);
        }

        $this->assertArrayHasKey('B', $x, 'La segunda línea no llegó al dibujo.');
        $this->assertSame(0.0, (float) $x['B'], 'La última parada no quedó pegada al fondo en la escena.');
        $this->assertGreaterThan($x['B'], $x['A'], 'La primera entrega no quedó más cerca de la puerta.');
    }

    /** Sin grupos declarados, todo es la misma parada y no cambia nada de lo de antes. */
    public function test_sin_grupos_el_orden_es_el_de_siempre(): void
    {
        $chico = ['largo' => 60, 'ancho' => 60, 'alto' => 60] + self::CAJA;

        $conGrupos = $this->calc->carga(self::CHEVY, [
            ['bulto' => $chico, 'cantidad' => 4, 'grupo' => 1],
            ['bulto' => self::CAJA, 'cantidad' => 4, 'grupo' => 1],
        ]);
        $sinGrupos = $this->calc->carga(self::CHEVY, [
            ['bulto' => $chico, 'cantidad' => 4],
            ['bulto' => self::CAJA, 'cantidad' => 4],
        ]);

        $this->assertSame($this->fondoPorLinea($sinGrupos), $this->fondoPorLinea($conGrupos));
        // Y lo grande sigue yendo primero, que es la regla de siempre dentro de una parada.
        $this->assertSame(0, $this->fondoPorLinea($sinGrupos)[1]);
    }
}
