<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Services\Carga\CalculoDeCarga;
use Database\Seeders\TiposBultoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo de bultos es FUENTE DE VERDAD DEL REPO (§0 de las reglas): viaja en el
 * deploy y una edición externa se revierte. Por eso sus números necesitan candado —
 * son datos verificados contra cargas reales, no preferencias.
 *
 * Hasta el 11-08-2026 el seeder no tenía ninguno, y el `apilable_max` de la bolsa era
 * un 6 prudente puesto sin medir que dejaba medio HINO de aire.
 */
class TiposBultoSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Las cuatro cajas del catálogo, medidas con huincha el 11-08-2026. */
    private const CAMIONES = [
        "Contenedor 40'" => [1203, 235, 239],
        'HINO 500' => [797, 260, 266],
        'Chevy 3' => [790, 220, 230],
        'Hyundai HD35' => [430, 200, 220],
    ];

    private CalculoDeCarga $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TiposBultoSeeder::class);
        $this->calc = new CalculoDeCarga;
    }

    private function bolsa(): TipoBulto
    {
        return TipoBulto::where('nombre', 'like', 'Bolsa 5× botellón 20 L%')->firstOrFail();
    }

    public function test_el_tope_de_la_bolsa_no_muerde_en_ningun_camion_del_catalogo(): void
    {
        // Dato de terreno del dueño (11-08-2026): «no hay un máximo para apilar, se llenan
        // todos los camiones siempre y no pasa nada». O sea que el que manda es SIEMPRE la
        // altura del camión, nunca el tope.
        //
        // El candado NO fija el número —30 es solo «bien alto»— sino la PROPIEDAD, que es
        // lo que él dijo. Fijar el 30 dejaría pasar el error que ya pasó dos veces: con 6
        // y con 10 el tope mordía, y con 10 encima parecía correcto por casualidad porque
        // en el HINO la bolsa acostada da exactamente 10 capas.
        foreach (['Bolsa 5× botellón 20 L%', 'Bolsa 5× botellón 10 L%'] as $patron) {
            $bolsa = TipoBulto::where('nombre', 'like', $patron)->firstOrFail();

            foreach (self::CAMIONES as $nombre => [$largo, $ancho, $alto]) {
                foreach (['pie', 'costado', 'pico'] as $estiba) {
                    $b = $bolsa->paraCalculo($estiba);
                    $porAltura = intdiv($alto, $b['alto']);

                    $this->assertLessThanOrEqual(
                        $b['apilable_max'],
                        $porAltura,
                        "En {$nombre} con la bolsa {$estiba}, el tope ({$b['apilable_max']}) corta antes que la altura ({$porAltura}).",
                    );
                }
            }
        }
    }

    public function test_la_bolsa_pesa_lo_que_pesan_sus_cinco_botellones(): void
    {
        // 750 g por botellón soplado × 5 = 3,75 kg (dueño, 11-08-2026). Viajaba SIN peso
        // —0 kg para el motor—, que era inofensivo hasta que existió el cartel de
        // sobrepeso: una carga de botellones no lo habría disparado nunca.
        $this->assertSame('3.75', $this->bolsa()->peso_kg);

        // Y sigue sin mover ningún cupo, que es lo que confirma la nota del catálogo:
        // acá el límite es el volumen, no el peso. El contenedor aguanta 7.680 bolsas por
        // kilos y el espacio deja 324.
        $contenedor = new CamionSimulacion(['largo_cm' => 1203, 'ancho_cm' => 235, 'alto_cm' => 239, 'peso_max_kg' => 28800, 'pasillo_cm' => 0]);
        $r = $this->calc->cupo($contenedor->paraCalculo(), $this->bolsa()->paraCalculo());

        $this->assertSame(1620, $r['unidades']);
        $this->assertNotSame('peso', $r['limite'], 'Con botellones vacíos el peso no puede ser el límite.');
    }

    /**
     * YA NO QUEDA NINGÚN BULTO SIN PESO (dueño, 12-08-2026).
     *
     * Un bulto sin peso no es un detalle cosmético: no dispara el cartel de sobrepeso y
     * no se puede repartir entre los ejes, así que una carga entera de cajas quedaba sin
     * ninguna de las dos cosas. Estuvieron en null a propósito mientras no había medida
     * —no se inventan números— y este candado marca el momento en que dejó de faltar.
     */
    public function test_ningun_bulto_del_catalogo_queda_sin_peso(): void
    {
        $sinPeso = TipoBulto::whereNull('peso_kg')->orWhere('peso_kg', 0)->pluck('nombre');

        $this->assertSame([], $sinPeso->all(), 'Volvió a haber bultos sin peso: no reparten ni avisan sobrepeso.');

        // Los tres que llegaron el 12-08, con su número exacto.
        $pesos = TipoBulto::pluck('peso_kg', 'nombre');
        $this->assertSame('2.00', $pesos['Bolsa 5× botellón 10 L (vacío)'], '400 g por botellón × 5.');
        $this->assertSame('6.00', $pesos['Caja de soportes']);
        $this->assertSame('5.50', $pesos['Caja de tapas']);
    }

    public function test_el_tope_nuevo_llena_el_hino_y_no_toca_los_cupos_del_hd35(): void
    {
        $hd35 = new CamionSimulacion(['largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220, 'pasillo_cm' => 0]);
        $hino = new CamionSimulacion(['largo_cm' => 797, 'ancho_cm' => 260, 'alto_cm' => 266, 'pasillo_cm' => 0]);
        $bolsa = $this->bolsa();

        // EN EL HD35 NO CAMBIA NADA, y eso es lo que protege su referencia de terreno:
        // sus 220 cm solo dan para 4 capas de pie (4 × 51 = 204) y 8 acostadas
        // (8 × 26 = 208), así que ahí manda la ALTURA y no el tope.
        $this->assertSame(
            420,
            $this->calc->cupo($hd35->paraCalculo(), $bolsa->paraCalculo())['unidades'],
            'Los 420 de pie que el dueño verificó.',
        );
        // Acostado da 360 con el ancho MEDIDO de 200 (11-08). No son los 480 que él
        // reportó el 07-08 — ese número quedó sin explicar y el simulador no lo persigue:
        // ver CalculoDeCargaTest::test_el_hd35_medido_da_420_de_pie_y_360_acostado.
        $this->assertSame(
            360,
            $this->calc->cupo($hd35->paraCalculo(), $bolsa->paraCalculo('costado'))['unidades'],
            '3 columnas acostadas de 51 en 200 cm; la cuarta pediría 204.',
        );

        // EN EL HINO SÍ: es más alto, así que el tope era el que cortaba. Es el hueco que
        // el dueño venía marcando desde el 06-08.
        $this->assertSame(
            1500,
            $this->calc->cupo($hino->paraCalculo(), $bolsa->paraCalculo('costado'))['unidades'],
            '10 capas de 26 cm llenan los 266.',
        );
    }

    public function test_ningun_bulto_se_siembra_con_medidas_en_cero(): void
    {
        // Un bulto con medida inventada es peor que un bulto ausente, porque el ausente se
        // nota y el inventado se cotiza. Las jaulas de máquinas siguen fuera del catálogo
        // por esto mismo (bloque «PENDIENTE DE MEDIR» del seeder).
        foreach (TipoBulto::all() as $b) {
            $this->assertGreaterThan(0, $b->largo_cm, "{$b->nombre} sin largo.");
            $this->assertGreaterThan(0, $b->ancho_cm, "{$b->nombre} sin ancho.");
            $this->assertGreaterThan(0, $b->alto_cm, "{$b->nombre} sin alto.");
            $this->assertGreaterThanOrEqual(1, $b->apilable_max, "{$b->nombre} sin tope de apilado.");
        }
    }

    public function test_el_seeder_es_idempotente(): void
    {
        $antes = TipoBulto::count();
        $this->seed(TiposBultoSeeder::class);

        $this->assertSame($antes, TipoBulto::count(), 'Corre en cada deploy: no puede duplicar.');
    }
}
