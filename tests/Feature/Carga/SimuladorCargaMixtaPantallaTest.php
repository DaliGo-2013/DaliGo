<?php

namespace Tests\Feature\Carga;

use App\Http\Controllers\Admin\SimuladorCargaController;
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

        $controles = [
            // Vista
            'carga3dMas', 'carga3dMenos', 'carga3dReset', 'carga3dNombres', 'carga3dCodigos',
            // Vistas fijas (el panel «Views» de EasyCargo, pedido del dueño 05-08).
            'carga3dVista3d', 'carga3dVistacostado', 'carga3dVistaplanta', 'carga3dVistapuerta',
            // Cuánto se ve cargado: animación + pasos a mano (pedido del dueño 05-08).
            'carga3dPlay', 'carga3dVaciar', 'carga3dQuita1',
            'carga3dSuma1', 'carga3dSuma5', 'carga3dSuma10', 'carga3dTodo',
        ];

        foreach (['mixta' => $mixta, 'cupo máximo' => $cupo] as $modo => $html) {
            foreach ($controles as $control) {
                $this->assertStringContainsString(
                    'id="'.$control.'"', $html,
                    "Al modo «{$modo}» le falta el control [{$control}] del visor.",
                );
            }
        }
    }

    public function test_la_escena_dice_con_que_forma_dibujar_cada_bulto(): void
    {
        // Los botellones se dibujan como los bidones dentro de la bolsa (foto del
        // dueño 05-08: es la carga diaria y era la que menos se parecía a la realidad);
        // las cajas y los dispensadores siguen siendo bultos rectangulares. Es dato de
        // DIBUJO: no toca el cupo.
        $escena = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 100],
            ['tipo' => $this->caja->id, 'cantidad' => 20],
        ])->assertOk()->viewData('escena');

        $formas = collect($escena['bloques'])->pluck('forma', 'nombre');

        $this->assertSame('botellones', $formas[$this->bolsa->nombre]);
        $this->assertSame('caja', $formas[$this->caja->nombre]);
    }

    public function test_la_forma_no_cambia_el_cupo(): void
    {
        // Candado del credo: `forma` es dibujo. Si alguien la mete en paraCalculo()
        // —el array que ve el motor— empezaría a mover números.
        $this->assertArrayNotHasKey('forma', $this->bolsa->paraCalculo());
        $this->assertSame('botellones', $this->bolsa->formaVisor());
        $this->assertSame('caja', $this->caja->formaVisor());
    }

    public function test_la_escena_lleva_el_nombre_para_la_chapa_de_atras(): void
    {
        // El visor pinta una chapa atrás con el modelo (pedido del dueño 05-08) y lo
        // saca de `vehiculo.nombre`. Si alguien lo quita del payload, la chapa
        // desaparece en silencio: nada se rompe, solo deja de estar.
        $escena = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])
            ->assertOk()->viewData('escena');

        $this->assertSame('Hyundai HD35', $escena['vehiculo']['nombre']);
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

    public function test_cada_producto_lleva_su_letra_y_es_la_misma_en_la_lista_y_en_el_lienzo(): void
    {
        // La letra es el vínculo entre el renglón de la lista y las cajas del lienzo
        // (idea tomada de EasyCargo, 05-08). Si la del payload se desalineara del orden
        // de la lista, el visor diría «B» sobre lo que la lista llama «A» — el error
        // más caro posible acá, porque se cargaría el camión mirando la letra.
        $escena = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 100],
            ['tipo' => $this->caja->id, 'cantidad' => 20],
        ])->assertOk()->viewData('escena');

        $letras = collect($escena['bloques'])->pluck('letra', 'nombre');

        $this->assertSame('A', $letras[$this->bolsa->nombre], 'El primer producto de la lista es la A.');
        $this->assertSame('B', $letras[$this->caja->nombre], 'El segundo es la B.');

        // Y la letra sale de la MISMA función que usa la vista para el renglón.
        $this->assertSame('A', SimuladorCargaController::letra(0));
        $this->assertSame('B', SimuladorCargaController::letra(1));
        $this->assertSame('H', SimuladorCargaController::letra(7));
    }

    public function test_un_producto_partido_en_dos_zonas_conserva_una_sola_letra(): void
    {
        // El acomodo por zonas puede partir un producto en dos bloques. Los dos son el
        // MISMO renglón de la lista, así que tienen que llevar la misma letra: dos
        // letras para un producto haría creer que son dos cosas distintas.
        $escena = $this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 60],
            ['tipo' => $this->bolsa->id, 'cantidad' => 50],
        ])->assertOk()->viewData('escena');

        foreach (collect($escena['bloques'])->groupBy('nombre') as $nombre => $bloques) {
            $this->assertCount(
                1, $bloques->pluck('letra')->unique(),
                "El producto «{$nombre}» quedó con más de una letra.",
            );
        }
    }

    public function test_la_escena_dice_cuantos_metros_de_piso_quedan_libres(): void
    {
        // El «Free meters» de EasyCargo. Con 100 botellones (20 bolsas) en el HD35, la
        // bolsa entra con su lado de 130 cm a lo largo del camión, así que el muro come
        // 1,30 m de los 4,30 m y quedan 3,00 m. Es el número que decide si al viaje le
        // cabe algo más.
        $escena = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])
            ->assertOk()->viewData('escena');

        $this->assertSame(3.0, $escena['libre_m']);

        // Y cuadra con el bloque que dibuja el visor: el piso libre no es un número
        // aparte, es el largo menos hasta donde llega la carga.
        $bloque = $escena['bloques'][0];
        $this->assertSame(
            round(4.30 - ($bloque['x'] + $bloque['rejilla']['largo'] * $bloque['orientacion']['largo']), 2),
            $escena['libre_m'],
        );
    }

    public function test_el_piso_libre_es_todo_el_largo_cuando_no_entra_nada(): void
    {
        // Un bulto que no entra por medidas deja el camión vacío: los metros libres son
        // TODO el largo, no cero. Un cero acá se leería como «camión lleno».
        $gigante = TipoBulto::create([
            'nombre' => 'Estanque 5 m', 'categoria' => 'otros',
            'largo_cm' => 500, 'ancho_cm' => 190, 'alto_cm' => 190, 'peso_kg' => 80,
            'unidades' => 1, 'apilable_max' => 1, 'soporta_peso_encima' => false,
            'orientacion_fija' => true, 'activo' => true,
        ]);

        $escena = $this->verMixta([['tipo' => $gigante->id, 'cantidad' => 1]])
            ->assertOk()->viewData('escena');

        $this->assertSame([], $escena['bloques']);
        $this->assertSame(4.3, $escena['libre_m']);
    }

    public function test_el_piso_libre_se_muestra_en_la_pantalla(): void
    {
        $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])
            ->assertOk()
            ->assertSee('Piso libre en la puerta')
            ->assertSee('3,00 m');
    }

    public function test_el_recuadro_del_visor_fija_su_alto_por_css_y_no_por_el_lienzo(): void
    {
        // El visor ajusta el mapa de bits al recuadro real (para que no salga borroso
        // en un monitor ancho). Eso SOLO es estable si el alto del recuadro lo fija el
        // CSS: si saliera del atributo `height`, tocar el mapa de bits movería el
        // recuadro, que volvería a mover el mapa de bits — un bucle. Este candado
        // cuida esa condición, que no se ve en ninguna pantalla hasta que se rompe.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])->assertOk()->getContent();

        $this->assertStringContainsString('aspect-ratio: 1240 / 660', $html);
        $this->assertStringContainsString('id="carga3d"', $html);
    }

    public function test_el_encuadre_mide_el_dibujo_y_no_una_caja_supuesta(): void
    {
        // El camión se veía chico y corrido a la derecha (reporte del dueño 05-08):
        // el encuadre medía los 8 vértices de la caja de carga, que no es lo que se
        // pinta. Medido en el HINO: 221 px muertos a la izquierda contra 23 a la
        // derecha, y la sombra cortada abajo. Ahora dibuja la silueta a una cola
        // descartable y mide sus vértices. Si alguien vuelve a la caja supuesta, el
        // síntoma es sutil (se ve «casi bien») y nadie lo ataja: esto lo ataja.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('function medirEncuadre', $js);
        $this->assertMatchesRegularExpression(
            '/midiendo = \{[^}]*\};\s*\n\s*if \(M\.semi\) siluetaSemirremolque\(\); else siluetaCamion\(\);/',
            $js,
            'El encuadre ya no dibuja la silueta para medirla.',
        );
    }
}
