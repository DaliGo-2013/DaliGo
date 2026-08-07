<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use Database\Seeders\CamionesSimulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo de camiones del simulador viene SEMBRADO — es lo que corrige la
 * lección del 05-08: la versión enganchada a la flota dependía de cargar
 * medidas por phpMyAdmin, ese paso manual nunca ocurrió, y producción quedó
 * mostrando «falta medir» para todo. Con el seeder, el deploy lo deja
 * funcionando solo.
 *
 * Las medidas son las que dictó el dueño el 04-08 y están VERIFICADAS: la
 * rejilla del motor reproduce exactamente sus cupos de referencia. Cambiar un
 * número acá exige cambiar el test — a propósito.
 */
class CamionesSimulacionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_los_camiones_con_las_medidas_del_dueno(): void
    {
        $this->seed(CamionesSimulacionSeeder::class);

        $esperado = [
            "Contenedor 40'" => [1203, 235, 239, 28800],
            'HINO 500 (FC 1118)' => [797, 260, 266, 11000],
            // Ancho 204 y no 200 desde el 07-08-2026: con 200 entraban 3 bolsas
            // acostadas a lo ancho y el cupo daba 360 contra los 480 que el dueño
            // carga a mano. Ver el comentario del seeder y
            // CalculoDeCargaTest::test_el_hd35_da_420_de_pie_y_480_acostado_con_la_misma_caja.
            'Hyundai HD35' => [430, 204, 220, 1500],
        ];

        $this->assertSame(count($esperado), CamionSimulacion::count());

        foreach ($esperado as $nombre => [$l, $w, $h, $peso]) {
            $c = CamionSimulacion::where('nombre', $nombre)->first();
            $this->assertNotNull($c, "Falta el camión {$nombre}.");
            $this->assertSame([$l, $w, $h], [$c->largo_cm, $c->ancho_cm, $c->alto_cm], "Las medidas de {$nombre} no son las dictadas.");
            $this->assertSame($peso, $c->peso_max_kg);
            $this->assertTrue($c->activo);
        }

        // El «H1» del dictado original NO se siembra: ese vehículo se vendió en
        // 2021 y el dueño pidió descartar su fila (04-08). Cotizar contra un
        // camión que no existe es prometer un viaje que no se puede hacer.
        $this->assertNull(CamionSimulacion::where('nombre', 'like', '%H1%')->first());
    }

    public function test_un_camion_vendido_se_borra_del_catalogo_aunque_ya_estuviera_sembrado(): void
    {
        // El Chevy 3 estuvo sembrado y LLEGÓ A PRODUCCIÓN; el dueño avisó el 05-08 que
        // lo vendieron. Sacarlo del array del seeder no alcanza: `updateOrCreate` no
        // borra lo que dejó de estar en la lista, así que la fila sobreviviría al deploy
        // y se seguiría cotizando contra un camión que la empresa ya no tiene.
        $this->seed(CamionesSimulacionSeeder::class);
        CamionSimulacion::create([
            'nombre' => 'Chevy 3 (NQR 919)',
            'largo_cm' => 800, 'ancho_cm' => 230, 'alto_cm' => 245,
            'pasillo_cm' => 0, 'activo' => true, 'silueta' => 'camion',
        ]);

        $this->seed(CamionesSimulacionSeeder::class);

        $this->assertNull(CamionSimulacion::where('nombre', 'Chevy 3 (NQR 919)')->first());
        $this->assertSame(3, CamionSimulacion::count());
    }

    public function test_resembrar_no_duplica_y_una_correccion_de_medida_viaja(): void
    {
        $this->seed(CamionesSimulacionSeeder::class);

        // Alguien "corrige" una medida en la base por fuera; el próximo deploy
        // la vuelve a la fuente de verdad (el repo) — updateOrCreate, no
        // firstOrCreate, porque estas medidas están verificadas contra cálculo.
        CamionSimulacion::where('nombre', 'Hyundai HD35')->update(['largo_cm' => 999]);
        $this->seed(CamionesSimulacionSeeder::class);

        $this->assertSame(3, CamionSimulacion::count());
        $this->assertSame(430, CamionSimulacion::where('nombre', 'Hyundai HD35')->value('largo_cm'));
    }

    public function test_la_rejilla_reproduce_los_cupos_de_referencia_del_dueno(): void
    {
        // La verificación completa: sembrado el catálogo, el motor da EXACTO
        // los botellones de 20 L que el dueño dio como referencia por camión.
        $this->seed(CamionesSimulacionSeeder::class);

        $bolsa = ['largo' => 130, 'ancho' => 26, 'alto' => 51, 'unidades' => 5, 'apilable_max' => 6, 'orientacion_fija' => true];
        $referencia = [
            "Contenedor 40'" => 1620,
            'HINO 500 (FC 1118)' => 1500,
            'Hyundai HD35' => 420,
        ];

        $calc = new \App\Services\Carga\CalculoDeCarga;
        foreach ($referencia as $nombre => $botellones) {
            $camion = CamionSimulacion::where('nombre', $nombre)->firstOrFail();
            $this->assertSame(
                $botellones,
                $calc->cupo($camion->paraCalculo(), $bolsa)['unidades'],
                "El cupo de {$nombre} no reproduce la referencia del dueño.",
            );
        }
    }

    public function test_todo_camion_sembrado_declara_una_silueta_que_el_visor_conoce(): void
    {
        // La silueta es dato de DIBUJO: un valor mal escrito no rompe el cálculo,
        // así que se caería en silencio (el visor lo deduciría del largo y nadie
        // se enteraría de que la declaración no servía). Este candado lo grita.
        $this->seed(CamionesSimulacionSeeder::class);

        foreach (CamionSimulacion::all() as $camion) {
            $this->assertArrayHasKey(
                $camion->silueta,
                CamionSimulacion::SILUETAS,
                "«{$camion->nombre}» declara la silueta «{$camion->silueta}», que el visor no sabe dibujar.",
            );
        }
    }

    public function test_el_contenedor_no_se_dibuja_como_camion_de_reparto(): void
    {
        // Su propia nota dice que va sobre el semirremolque: dibujarle cabina
        // propia era el error que se veía en pantalla.
        $this->seed(CamionesSimulacionSeeder::class);

        $this->assertSame(
            'semirremolque',
            CamionSimulacion::where('nombre', "Contenedor 40'")->firstOrFail()->silueta,
        );
        $this->assertSame(
            'camion_liviano',
            CamionSimulacion::where('nombre', 'Hyundai HD35')->firstOrFail()->silueta,
        );
    }

    public function test_cada_camion_moldeado_sobre_fotos_tiene_su_propia_silueta(): void
    {
        // El dueño pidió «un modelo por cada camión» y las moldeó sobre fotos de su
        // flota, de a una (05-08). Al venderse el Chevy quedaron los tres que tienen
        // fotos, así que YA NINGUNO usa la silueta genérica. Si alguien vuelve a
        // colapsarlos en una silueta compartida, esto se pone rojo.
        $this->seed(CamionesSimulacionSeeder::class);

        $siluetas = CamionSimulacion::pluck('silueta', 'nombre');

        $this->assertSame('semirremolque', $siluetas["Contenedor 40'"]);
        $this->assertSame('camion_hino', $siluetas['HINO 500 (FC 1118)']);
        $this->assertSame('camion_liviano', $siluetas['Hyundai HD35']);

        // Ninguno comparte silueta con otro, y ninguno cae en la genérica: `camion`
        // queda solo como respaldo para un camión sin silueta declarada.
        $this->assertSame($siluetas->count(), $siluetas->unique()->count());
        $this->assertNotContains('camion', $siluetas->all());
    }
}
