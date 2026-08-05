<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La pantalla del simulador en modo CARGA MIXTA: que el armador de carga llegue
 * al motor, que el veredicto se diga sin rodeos, y que la escena del visor
 * cuadre con lo calculado.
 */
class SimuladorCargaMixtaPantallaTest extends TestCase
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

        // El camión es del catálogo PROPIO del simulador (decisión del dueño
        // 05-08): la flota real no participa en esta pantalla.
        $this->hd35 = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35',
            'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1400, 'pasillo_cm' => 0, 'activo' => true,
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

    private function verMixta(array $lineas)
    {
        return $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'lineas' => $lineas,
        ]));
    }

    public function test_el_caso_del_pedido_del_dueno_responde_con_el_veredicto(): void
    {
        // «200 botellones + 20 cajas → ¿entra en el HD35?» — 200 botellones son
        // 40 bolsas; caben las 40 (muro de 2 rebanadas) y las 20 cajas.
        $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 200],
            ['tipo' => $this->caja->id, 'cantidad' => 20],
        ])
            ->assertOk()
            ->assertSee('Cabe todo')
            ->assertSee('Bolsa 5× botellón 20 L (vacío)')
            ->assertSee('Caja de tapas');
    }

    public function test_cuando_no_cabe_dice_cuanto_queda_afuera_y_por_que(): void
    {
        // 600 botellones son 120 bolsas y el HD35 admite 84 (420 botellones).
        $res = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]]);

        $res->assertOk()
            ->assertSee('No cabe todo')
            ->assertSee('quedan 180 afuera', false)
            ->assertSee('no queda espacio');
    }

    public function test_los_botellones_se_piden_en_unidades_y_se_redondea_a_la_bolsa(): void
    {
        // 198 botellones = 40 bolsas (la bolsa viaja completa). Se cargan las 40,
        // pero lo CARGADO se reporta capado a lo pedido: 198, no 200.
        $res = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 198]]);

        $res->assertOk()->assertSee('Cabe todo');
        $this->assertSame(198, $res->viewData('mixta')['lineas'][0]['cargadas_unidades']);
        $this->assertSame(40, $res->viewData('mixta')['lineas'][0]['bultos_colocados']);
    }

    public function test_la_escena_del_visor_cuadra_con_lo_calculado(): void
    {
        $res = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 140],
            ['tipo' => $this->caja->id, 'cantidad' => 50],
        ]);

        $escena = $res->viewData('escena');

        // Un bloque por tipo colocado, la suma es el tope de la animación, y
        // cada bloque lleva su color (la leyenda del visor).
        $this->assertNotNull($escena);
        $this->assertSame(28 + 50, $escena['tope']);
        $this->assertCount(2, $escena['bloques']);
        foreach ($escena['bloques'] as $bloque) {
            $this->assertCount(3, $bloque['color']);
            $this->assertNotSame('', $bloque['nombre']);
        }
        // Ordenados fondo → puerta para que la animación cargue como en la vida real.
        $this->assertLessThanOrEqual($escena['bloques'][1]['x'], $escena['bloques'][0]['x']);
    }

    public function test_una_linea_con_bulto_peligroso_muestra_el_aviso(): void
    {
        $baterias = TipoBulto::create([
            'nombre' => 'Cajón baterías de litio', 'categoria' => 'maquinas',
            'largo_cm' => 80, 'ancho_cm' => 60, 'alto_cm' => 60, 'peso_kg' => 40,
            'unidades' => 1, 'apilable_max' => 1, 'soporta_peso_encima' => false,
            'orientacion_fija' => true, 'peligrosa' => true, 'peligrosa_codigo' => 'UN3480',
            'activo' => true,
        ]);

        $this->verMixta([['tipo' => $baterias->id, 'cantidad' => 2]])
            ->assertOk()
            ->assertSee('UN3480')
            ->assertSee('Mercancía peligrosa');
    }

    public function test_sin_lineas_la_pantalla_sigue_siendo_el_cupo_maximo(): void
    {
        $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk()
            ->assertSee('Entran')
            ->assertViewHas('mixta', null);
    }

    public function test_lineas_invalidas_se_rechazan(): void
    {
        $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id,
                'lineas' => [['tipo' => 999999, 'cantidad' => 10]],
            ]))
            ->assertSessionHasErrors('lineas.0.tipo');

        $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id,
                'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 0]],
            ]))
            ->assertSessionHasErrors('lineas.0.cantidad');
    }

    public function test_sin_permiso_no_se_entra(): void
    {
        $ajeno = User::factory()->create();

        $this->actingAs($ajeno)
            ->get(route('admin.carga.index'))
            ->assertRedirect(route('dashboard'));
    }

    // --- Silueta del visor (05-08-2026) ------------------------------------
    //
    // El visor dibujaba los cuatro camiones idénticos. Estos candados fijan que
    // la escena diga CON QUÉ dibujar, y que un acoplado y un camión de reparto
    // no vuelvan a salir iguales.

    /** @return array{silueta:string,ejes:int} */
    private function siluetaDe(CamionSimulacion $camion): array
    {
        $escena = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $camion->id,
                'tipo_bulto_id' => $this->bolsa->id,
            ]))
            ->assertOk()
            ->viewData('escena');

        return ['silueta' => $escena['vehiculo']['silueta'], 'ejes' => $escena['vehiculo']['ejes']];
    }

    private function camion(string $nombre, int $largoCm, ?string $silueta): CamionSimulacion
    {
        return CamionSimulacion::create([
            'nombre' => $nombre,
            'largo_cm' => $largoCm, 'ancho_cm' => 235, 'alto_cm' => 239,
            'pasillo_cm' => 0, 'activo' => true, 'silueta' => $silueta,
        ]);
    }

    public function test_la_escena_dice_con_que_silueta_dibujar(): void
    {
        // El HD35 del setUp no declara silueta: se deduce de su largo (4,3 m).
        $this->assertSame(
            ['silueta' => 'camion_liviano', 'ejes' => 2],
            $this->siluetaDe($this->hd35),
        );
    }

    public function test_un_acoplado_no_se_dibuja_igual_que_un_camion_de_reparto(): void
    {
        // El bug que motivó todo esto: el Contenedor 40' (que viaja sobre el
        // semirremolque, sin cabina propia) salía con la misma silueta que un
        // camión de reparto.
        $acoplado = $this->siluetaDe($this->camion("Contenedor 40'", 1203, 'semirremolque'));
        $reparto = $this->siluetaDe($this->camion('HINO 500', 797, 'camion'));

        $this->assertSame('semirremolque', $acoplado['silueta']);
        $this->assertSame(3, $acoplado['ejes'], 'Un acoplado se dibuja con tren de tres ejes.');
        $this->assertSame('camion', $reparto['silueta']);
        $this->assertSame(2, $reparto['ejes']);
        $this->assertNotSame($acoplado['silueta'], $reparto['silueta']);
    }

    public function test_la_silueta_declarada_manda_sobre_la_deducida_del_largo(): void
    {
        // 5 m de caja: por largo se deduciría «liviano», pero lo declarado gana.
        $this->assertSame('semirremolque', $this->siluetaDe($this->camion('Acoplado corto', 500, 'semirremolque'))['silueta']);
    }

    // --- Controles del visor (05-08-2026) -----------------------------------

    public function test_los_dos_modos_ofrecen_los_mismos_controles_del_visor(): void
    {
        // El bloque del visor vivía COPIADO en los dos modos de la pantalla; al
        // sumar los controles de zoom se extrajo a un partial. Este candado mira
        // que ninguno de los dos modos quede sin controles.
        $mixta = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])->assertOk()->getContent();
        $cupo = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk()->getContent();

        foreach (['mixta' => $mixta, 'cupo máximo' => $cupo] as $modo => $html) {
            foreach (['carga3dPlay', 'carga3dMas', 'carga3dMenos', 'carga3dReset', 'carga3dNombres'] as $control) {
                $this->assertStringContainsString(
                    'id="'.$control.'"', $html,
                    "Al modo «{$modo}» le falta el control [{$control}] del visor.",
                );
            }
        }
    }

    public function test_el_zoom_se_ofrece_solo_en_escritorio(): void
    {
        // Pedido del dueño 05-08: «no lo quiero para celular, no quiero que se
        // quede pegada o se ponga lento». Los botones + / − viven en un contenedor
        // oculto hasta `lg`; si alguien los saca de ahí, esto se pone rojo y
        // obliga a leer el porqué antes de exponerlos en el teléfono.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])->assertOk()->getContent();

        $this->assertStringContainsString('hidden items-center gap-1.5 text-xs lg:flex', $html);
    }

    public function test_el_visor_no_registra_gestos_tactiles(): void
    {
        // La otra mitad del pedido: el zoom entra por la RUEDA del mouse, que un
        // táctil no emite. Nada de touch ni de pinza — si algún día se quiere en
        // celular, que sea a propósito y midiendo que no se ponga lento.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString("addEventListener('wheel'", $js);
        foreach (['touchstart', 'touchmove', 'gesturestart', 'gesturechange'] as $gesto) {
            $this->assertStringNotContainsString($gesto, $js, "El visor registra [{$gesto}]: el zoom era solo de escritorio.");
        }
    }

    public function test_una_silueta_que_el_visor_no_conoce_cae_a_la_deducida(): void
    {
        // Un dato viejo o mal escrito no puede dejar el lienzo sin dibujo: cae a
        // la deducción por largo (12 m → acoplado) en vez de romper la pantalla.
        $this->assertSame(
            ['silueta' => 'semirremolque', 'ejes' => 3],
            $this->siluetaDe($this->camion('Raro', 1203, 'nave-espacial')),
        );
    }
}
