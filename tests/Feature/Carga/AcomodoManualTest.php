<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use App\Services\Carga\AcomodoManual;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * EL ACOMODO A MANO (pedido del dueño 11-08-2026: «que te dé la opción de dar vuelta la
 * caja y acomodar como uno quiero»).
 *
 * Estos candados no cuidan que se pueda mover —eso es una línea de código— sino las tres
 * cosas que hacen que mover no vuelva mentiroso al plan: que las CUENTAS no cambien, que
 * lo que quedó mal SE DIGA, y que un acomodo viejo se descarte entero en vez de caer
 * torcido sobre otros productos. Ver `App\Services\Carga\AcomodoManual`.
 */
class AcomodoManualTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private CamionSimulacion $hd35;

    private TipoBulto $caja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->hd35 = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35',
            'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1400, 'pasillo_cm' => 0, 'activo' => true,
        ]);

        $this->caja = TipoBulto::create([
            'nombre' => 'Caja de tapas', 'categoria' => 'cajas',
            'largo_cm' => 46, 'ancho_cm' => 37, 'alto_cm' => 42, 'peso_kg' => 10,
            'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);
    }

    /** Un bloque cualquiera, en centímetros, como los que emite el motor. */
    private function bloque(int $x = 0, int $y = 0, int $largo = 40, int $ancho = 30): array
    {
        return [
            'x' => $x, 'y' => $y,
            'orientacion' => ['largo' => $largo, 'ancho' => $ancho, 'alto' => 20],
            'rejilla' => ['largo' => 2, 'ancho' => 3, 'alto' => 4],
            'cantidad' => 24,
        ];
    }

    // ─────────────────────────────────────────────────────────── el servicio

    public function test_mover_cambia_la_posicion_y_nada_mas(): void
    {
        $r = (new AcomodoManual(['0' => '120,50']))->aplicar([$this->bloque()], 430, 200);

        $this->assertSame(120, $r['bloques'][0]['x']);
        $this->assertSame(50, $r['bloques'][0]['y']);
        // La cuenta es del motor y mover no descubre lugar nuevo.
        $this->assertSame(24, $r['bloques'][0]['cantidad']);
        $this->assertSame(['largo' => 2, 'ancho' => 3, 'alto' => 4], $r['bloques'][0]['rejilla']);
        $this->assertTrue($r['activo']);
    }

    public function test_girar_intercambia_largo_y_ancho_pero_no_toca_el_alto(): void
    {
        // Girar es sobre el PISO. Volcar la caja cambiaría cuántas se apilan, y eso es
        // una pregunta para el motor («Cómo viaja»), no para el mouse.
        $r = (new AcomodoManual(['0' => '0,0,g']))->aplicar([$this->bloque()], 430, 200);
        $b = $r['bloques'][0];

        $this->assertSame(['largo' => 30, 'ancho' => 40, 'alto' => 20], $b['orientacion']);
        $this->assertSame(['largo' => 3, 'ancho' => 2, 'alto' => 4], $b['rejilla']);
        $this->assertTrue($b['girado']);
    }

    public function test_dos_bloques_encimados_se_reportan_y_tocarse_no_cuenta(): void
    {
        // Pegados canto con canto: el primero ocupa de 0 a 80 de largo (2 × 40).
        $pegados = (new AcomodoManual(['0' => '0,0', '1' => '80,0']))
            ->aplicar([$this->bloque(), $this->bloque()], 430, 200);
        $this->assertSame([], $pegados['choques'], 'Dos bloques pegados no se pisan: así se carga.');

        $encimados = (new AcomodoManual(['0' => '0,0', '1' => '79,0']))
            ->aplicar([$this->bloque(), $this->bloque()], 430, 200);
        $this->assertSame([[0, 1]], $encimados['choques']);
    }

    public function test_lo_que_sobresale_de_la_caja_se_reporta(): void
    {
        // La huella mide 80 × 90; puesto en 400 se pasa de los 430 de largo.
        $r = (new AcomodoManual(['0' => '400,0']))->aplicar([$this->bloque()], 430, 200);

        $this->assertSame([0], $r['fuera']);
    }

    public function test_un_acomodo_armado_para_otra_cantidad_de_bloques_se_descarta_entero(): void
    {
        // Se guardó con tres bloques y ahora hay uno: aplicar la primera posición
        // pondría la carga de otro producto en el lugar equivocado, en silencio.
        $r = (new AcomodoManual(['0' => '120,50'], hechoPara: 3))->aplicar([$this->bloque()], 430, 200);

        $this->assertTrue($r['descartado']);
        $this->assertFalse($r['activo']);
        $this->assertSame(0, $r['bloques'][0]['x'], 'El bloque se queda donde lo puso el motor.');
    }

    public function test_una_posicion_con_formato_invalido_deja_el_bloque_donde_estaba(): void
    {
        // La URL se puede editar a mano: una coordenada inventada tiene que caer en el
        // lugar VERIFICADO, no en un 0,0 que nadie pidió ni en un error de pantalla.
        $r = (new AcomodoManual(['0' => 'a,b', '1' => '10']))->aplicar([$this->bloque(300, 100)], 430, 200);

        $this->assertSame(300, $r['bloques'][0]['x']);
        $this->assertSame(100, $r['bloques'][0]['y']);
        $this->assertFalse($r['activo']);
    }

    // ─────────────────────────────────────────────────────────── la pantalla

    private function verConAcomodo(array $acomodo = [], array $extra = [])
    {
        return $this->actingAs($this->vendedor)->get(route('admin.carga.index', array_merge([
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->caja->id,
        ], $acomodo, $extra)));
    }

    public function test_la_escena_toma_la_posicion_acomodada_a_mano(): void
    {
        $auto = $this->verConAcomodo();
        $this->assertEquals(0, $auto->viewData('escena')['bloques'][0]['x']);
        $this->assertFalse($auto->viewData('escena')['acomodo']['activo']);

        $mano = $this->verConAcomodo(['acomodo' => ['0' => '150,40'], 'acomodo_de' => 1]);
        $escena = $mano->viewData('escena');

        $mano->assertOk();
        $this->assertSame(1.5, $escena['bloques'][0]['x'], 'El visor recibe metros.');
        $this->assertSame(0.4, $escena['bloques'][0]['y']);
        $this->assertSame(150, $escena['acomodo']['piezas'][0]['x'], 'El tablero trabaja en centímetros enteros.');
        $this->assertTrue($escena['acomodo']['activo']);
    }

    public function test_acomodar_no_cambia_cuantos_entran(): void
    {
        // El corazón del reparo que se le puso al pedido: arrastrar cambia DÓNDE va la
        // carga, no cuánta entra. Si mover subiera el cupo, el tablero sería una forma
        // de sacarle al motor un número que no calculó.
        // Se lee de la FILA de la carga y no de `viewData('resultado')`: desde la fusión
        // de las dos preguntas (21-08) un `tipo_bulto_id` sin líneas se traduce a una
        // línea, así que el número vive ahí. La regla que vigila el candado no cambió.
        $fila = fn ($r) => $r->viewData('mixta')['lineas'][array_key_first($r->viewData('mixta')['lineas'])];

        $auto = $fila($this->verConAcomodo());
        $mano = $fila($this->verConAcomodo(['acomodo' => ['0' => '150,40'], 'acomodo_de' => 1]));

        $this->assertSame($auto['bultos_colocados'], $mano['bultos_colocados']);
        $this->assertSame($auto['cargadas_unidades'], $mano['cargadas_unidades']);
    }

    public function test_la_pantalla_avisa_que_el_calculo_no_verifico_el_acomodo(): void
    {
        $this->verConAcomodo(['acomodo' => ['0' => '150,40'], 'acomodo_de' => 1])
            ->assertOk()
            ->assertSee('Acomodo a mano')
            ->assertSee('El cálculo no verificó estas posiciones');
    }

    public function test_la_pantalla_avisa_cuando_el_acomodo_dejo_carga_afuera_de_la_caja(): void
    {
        // Un solo bloque no se puede pisar con nadie, pero sí sobresalir.
        $this->verConAcomodo(['acomodo' => ['0' => '420,0'], 'acomodo_de' => 1])
            ->assertOk()
            ->assertSee('sobresalen de la caja');
    }

    public function test_un_acomodo_viejo_se_descarta_y_la_pantalla_lo_dice(): void
    {
        $this->verConAcomodo(['acomodo' => ['0' => '150,40'], 'acomodo_de' => 7])
            ->assertOk()
            ->assertSee('Se descartó el acomodo a mano')
            ->assertDontSee('El cálculo no verificó estas posiciones');
    }

    public function test_el_tablero_esta_para_quien_edita_y_no_en_el_link_compartido(): void
    {
        // Quien recibe el plan lo MIRA. El aviso de que se acomodó a mano sí tiene que
        // llegarle —es lo que distingue un plan verificado de uno armado a ojo— pero el
        // tablero para reacomodarlo no.
        $this->verConAcomodo(['acomodo' => ['0' => '150,40'], 'acomodo_de' => 1])
            ->assertOk()
            ->assertSee('Acomodar a mano · vista de planta');

        $link = URL::temporarySignedRoute('publico.plan-carga', now()->addDay(), [
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->caja->id,
            'acomodo' => ['0' => '150,40'],
            'acomodo_de' => 1,
        ]);

        $this->get($link)
            ->assertOk()
            ->assertSee('El cálculo no verificó estas posiciones')
            ->assertDontSee('Acomodar a mano · vista de planta');
    }

    public function test_al_girar_un_pallet_la_carga_de_arriba_gira_con_la_tarima(): void
    {
        // Si la tarima gira y su carga no, el dibujo muestra cajas colgando en el aire
        // fuera del pallet. El giro del interior NO se programó de nuevo para el acomodo:
        // reusa el que ya hacía falta cuando el motor rotaba el pallet para que entrara.
        $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->caja->id,
            'sobre_pallet' => 1,
            'pallet_tipo' => 'industrial',
            'acomodo' => ['0' => '0,0,g'],
            'acomodo_de' => 1,
        ]));

        $res->assertOk();
        $b = $res->viewData('escena')['bloques'][0];
        $it = $b['interior'];

        $this->assertSame('pallet', $b['forma']);
        $this->assertEqualsWithDelta(1.00, $b['orientacion']['largo'], 1e-9, 'La tarima industrial de 120×100 no giró.');
        $this->assertEqualsWithDelta(1.20, $b['orientacion']['ancho'], 1e-9);
        $this->assertLessThanOrEqual(
            $b['orientacion']['largo'] + 1e-9,
            $it['rejilla']['largo'] * $it['orientacion']['largo'],
            'La carga de arriba se sale de la tarima girada.',
        );
        $this->assertLessThanOrEqual(
            $b['orientacion']['ancho'] + 1e-9,
            $it['rejilla']['ancho'] * $it['orientacion']['ancho'],
        );
    }

    // El aviso EN EL EXCEL se cuida en `PlanDeCargaExcelTest`, que es donde vive el
    // lector de .xlsx: una segunda copia del abridor de zip acá sería la forma clásica
    // de que las dos se separen.
}
