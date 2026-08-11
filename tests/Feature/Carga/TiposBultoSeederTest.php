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

    public function test_la_bolsa_de_botellones_apila_10_porque_va_vacia(): void
    {
        // Dato de terreno del dueño (11-08-2026): «las bolsas aguantan 9 encima porque
        // están vacías, nada se rompe». Nueve encima de la de abajo son DIEZ de alto.
        //
        // Cuántas aguanta la de abajo NO es geometría: es lo único de este cálculo que el
        // código no puede deducir, así que queda fijado acá con su origen.
        $this->assertSame(10, $this->bolsa()->apilable_max);
        $this->assertSame(
            10,
            TipoBulto::where('nombre', 'like', 'Bolsa 5× botellón 10 L%')->firstOrFail()->apilable_max,
            'La de 10 L va vacía igual.',
        );
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
