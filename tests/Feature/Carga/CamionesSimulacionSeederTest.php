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

        // TODAS MEDIDAS DE HUINCHA desde el 11-08-2026: el dueño midió el interior de
        // las cuatro cajas de carga. Antes eran medidas dictadas de memoria y una
        // (el ancho del HD35) era directamente una deducción.
        $esperado = [
            "Contenedor 40'" => [1203, 235, 239, 28800],
            'HINO 500 (FC 1118)' => [797, 260, 266, 11000],
            // VUELVE A 200 (era 204 entre el 07 y el 11-08, deducido para que cerraran
            // sus dos cupos de terreno). La huincha mandó. Ver §3.5 de las reglas y
            // CalculoDeCargaTest::test_el_hd35_medido_da_420_de_pie_y_360_acostado.
            'Hyundai HD35' => [430, 200, 220, 1500],
            // UN camión con dos nombres (confirmado 11-08): «Chevy 3» y «H3» son el mismo
            // furgón. Las medidas son el juego MENOR de los dos que dictó — ver el seeder.
            // Los 6.430 kg son su tonelaje oficial, que llegó el mismo día.
            'Chevy 3 (NQR 919 · H3)' => [790, 220, 230, 6430],
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

    public function test_la_fila_con_el_nombre_viejo_se_borra_y_no_deja_el_camion_duplicado(): void
    {
        // El `Chevy 3 (NQR 919)` estuvo sembrado y LLEGÓ A PRODUCCIÓN. Sacarlo del array
        // del seeder no alcanza: `updateOrCreate` no borra lo que dejó de estar en la
        // lista, así que la fila sobreviviría al deploy.
        //
        // Y acá el daño ya no es «cotizar contra un camión que no existe» —el 11-08 se
        // confirmó que nunca se vendió— sino peor de explicar: el MISMO furgón aparecería
        // DOS VECES en el selector, con dos nombres y dos juegos de medidas, y el
        // vendedor no tendría forma de saber cuál es el bueno.
        $this->seed(CamionesSimulacionSeeder::class);
        CamionSimulacion::create([
            'nombre' => 'Chevy 3 (NQR 919)',
            'largo_cm' => 800, 'ancho_cm' => 230, 'alto_cm' => 245,
            'pasillo_cm' => 0, 'activo' => true, 'silueta' => 'camion',
        ]);

        $this->seed(CamionesSimulacionSeeder::class);

        $this->assertNull(CamionSimulacion::where('nombre', 'Chevy 3 (NQR 919)')->first());
        $this->assertSame(4, CamionSimulacion::count());

        // Queda UNA sola fila para ese camión, la unificada.
        $this->assertSame(
            1,
            CamionSimulacion::where('nombre', 'like', '%Chevy 3%')->count(),
            'El mismo furgón no puede estar dos veces en el selector.',
        );
        $this->assertNotNull(CamionSimulacion::where('nombre', 'Chevy 3 (NQR 919 · H3)')->first());
    }

    public function test_resembrar_no_duplica_y_una_correccion_de_medida_viaja(): void
    {
        $this->seed(CamionesSimulacionSeeder::class);

        // Alguien "corrige" una medida en la base por fuera; el próximo deploy
        // la vuelve a la fuente de verdad (el repo) — updateOrCreate, no
        // firstOrCreate, porque estas medidas están verificadas contra cálculo.
        CamionSimulacion::where('nombre', 'Hyundai HD35')->update(['largo_cm' => 999]);
        $this->seed(CamionesSimulacionSeeder::class);

        $this->assertSame(4, CamionSimulacion::count());
        $this->assertSame(430, CamionSimulacion::where('nombre', 'Hyundai HD35')->value('largo_cm'));
    }

    public function test_la_rejilla_reproduce_los_cupos_de_referencia_del_dueno(): void
    {
        // La verificación completa: sembrado el catálogo, el motor da EXACTO
        // los botellones de 20 L que el dueño dio como referencia por camión.
        $this->seed(CamionesSimulacionSeeder::class);

        $bolsa = ['largo' => 130, 'ancho' => 26, 'alto' => 51, 'unidades' => 5, 'apilable_max' => 6, 'orientacion_fija' => true];

        // LOS CUATRO cupos que dictó el 04-08, y las CUATRO medidas de huincha del
        // 11-08 los reproducen exactos. Es la validación más fuerte que tiene el módulo:
        // dos fuentes independientes —lo que él contó cargando y lo que dio la cinta—
        // llegando al mismo número por cuatro caminos distintos.
        //
        // El 960 del Chevy 3 estuvo HUÉRFANO entre el 05 y el 11-08: era un cupo de
        // referencia sin camión al que corresponder, porque el camión se había vendido.
        // Que al volver con medidas reales dé exactamente 960 es lo que confirma que la
        // lista original estaba bien tomada.
        $referencia = [
            "Contenedor 40'" => 1620,
            'HINO 500 (FC 1118)' => 1500,
            'Chevy 3 (NQR 919 · H3)' => 960,
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
        // flota, de a una (05-08). Con las fotos del NQR (11-08) el catálogo vuelve a
        // cumplirlo ENTERO: cuatro camiones, cuatro cabinas propias, ninguna compartida
        // y ninguno en la genérica. Si alguien vuelve a colapsarlos, esto se pone rojo.
        $this->seed(CamionesSimulacionSeeder::class);

        $siluetas = CamionSimulacion::pluck('silueta', 'nombre');

        $esperadas = [
            "Contenedor 40'" => 'semirremolque',
            'HINO 500 (FC 1118)' => 'camion_hino',
            'Hyundai HD35' => 'camion_liviano',
            // Sus fotos llegaron el 11-08: cab-over Chevrolet NQR. Fueron las que
            // destaparon que «Chevy 3» y «H3» eran el mismo camión — llevan pintado
            // «NQR 919», que era el identificador del supuesto vendido.
            'Chevy 3 (NQR 919 · H3)' => 'camion_nqr',
        ];

        // Los NOMBRES también se fijan: si mañana entra un camión nuevo sin silueta
        // propia, esta lista queda corta y el test avisa en vez de dejarlo caer en la
        // genérica sin que nadie se entere.
        $nombres = array_keys($esperadas);
        sort($nombres);
        $this->assertSame($nombres, $siluetas->keys()->sort()->values()->all());

        foreach ($esperadas as $nombre => $silueta) {
            $this->assertSame($silueta, $siluetas[$nombre], "La silueta de {$nombre} no es la suya.");
        }

        // Ninguna se repite y NINGUNO cae en la genérica: `camion` queda como respaldo
        // para un camión que llegue sin fotos —como estuvieron el Chevy 3 y el H3 unas
        // horas el 11-08—, porque inventarle una cabina a un furgón que no vimos sería
        // dibujar algo que no existe.
        $this->assertSame($siluetas->count(), $siluetas->unique()->count());
        $this->assertNotContains('camion', $siluetas->all());
    }
}
