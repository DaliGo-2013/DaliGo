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

    public function test_acostar_el_pack_intercambia_ancho_y_alto(): void
    {
        // Pedido del dueño 05-08: «necesito la opción de poder acostar el pack de
        // botellones, en los camiones la mayoría se acuestan». La bolsa medida son
        // 130 × 26 × 51 = cinco botellones PARADOS (51 el alto del botellón, 26 su
        // diámetro). Acostarlos pone el eje en horizontal: 130 × 51 × 26.
        $dePie = $this->bolsa->paraCalculo();
        $acostado = $this->bolsa->paraCalculo(true);

        $this->assertSame([130, 26, 51], [$dePie['largo'], $dePie['ancho'], $dePie['alto']]);
        $this->assertSame([130, 51, 26], [$acostado['largo'], $acostado['ancho'], $acostado['alto']]);
    }

    public function test_acostado_da_menos_botellones_y_de_pie_sigue_siendo_el_predeterminado(): void
    {
        // El número CAMBIA y hacia abajo: acostada la bolsa mide 26 cm de alto y el tope
        // de apilado (6) corta antes que los 220 cm de la caja. De pie tiene que seguir
        // siendo el predeterminado porque es la orientación con la que el dueño verificó
        // sus 420 — si el default se diera vuelta, su referencia dejaría de cuadrar y
        // nadie sabría por qué.
        $calculo = new \App\Services\Carga\CalculoDeCarga;
        $camion = $this->hd35->paraCalculo();

        $this->assertSame(420, $calculo->cupo($camion, $this->bolsa->paraCalculo())['unidades']);
        $this->assertSame(270, $calculo->cupo($camion, $this->bolsa->paraCalculo(true))['unidades']);

        // Sin pedir nada, la pantalla responde DE PIE.
        $sinPedir = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk();

        $this->assertSame(420, $sinPedir->viewData('resultado')['unidades']);
        $this->assertFalse($sinPedir->viewData('acostado'));
    }

    public function test_la_pantalla_calcula_acostado_cuando_se_pide(): void
    {
        $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->bolsa->id,
            'acostado' => 1,
        ]))->assertOk();

        $this->assertSame(270, $res->viewData('resultado')['unidades']);
        $this->assertTrue($res->viewData('acostado'));
        // Y lo DICE: «entran 270» sin decir con qué estiba invita a compararlo con
        // los 420 de pie y a pensar que el simulador se equivocó.
        $res->assertSee('Cómo viaja')->assertSee('Acostado');
    }

    public function test_la_estiba_se_elige_por_linea_en_la_carga_mixta(): void
    {
        // En la misma carga puede ir un pack acostado y otro de pie, así que la elección
        // es POR LÍNEA y no de la pantalla.
        $escena = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 50, 'acostado' => 1],
            ['tipo' => $this->caja->id, 'cantidad' => 10],
        ])->assertOk()->viewData('escena');

        $porNombre = collect($escena['bloques'])->keyBy('nombre');

        $this->assertTrue($porNombre[$this->bolsa->nombre]['acostado']);
        $this->assertFalse($porNombre[$this->caja->nombre]['acostado']);

        // El bloque acostado mide 26 cm de alto y 51 de ancho, al revés que de pie.
        $this->assertSame(0.51, $porNombre[$this->bolsa->nombre]['orientacion']['ancho']);
        $this->assertSame(0.26, $porNombre[$this->bolsa->nombre]['orientacion']['alto']);
    }

    public function test_un_bulto_que_el_motor_ya_rota_no_ofrece_la_opcion_y_la_ignora(): void
    {
        // La caja NO es de orientación fija: el motor le prueba las 6 rotaciones y se
        // queda con la mejor. Ofrecerle «acostado» sería ofrecer empeorar el resultado,
        // y forzarlo por URL no debe cambiar nada.
        $this->assertFalse($this->caja->puedeAcostarse());
        $this->assertTrue($this->bolsa->puedeAcostarse());

        $this->assertSame(
            $this->caja->paraCalculo(),
            $this->caja->paraCalculo(true),
            'Acostar un bulto que el motor ya rota le cambió las medidas.',
        );
    }

    public function test_el_visor_dibuja_los_botellones_tumbados_y_no_una_caja_girada(): void
    {
        // Si el lienzo mostrara los botellones parados mientras el cálculo dice
        // «acostado», dejaría de ser la prueba de lo que el motor hizo — que es todo lo
        // que aporta. El cilindro tumbado es una función APARTE de la vertical: el
        // sombreado y la tapa se calculan sobre planos distintos.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('function cilindroAcostado', $js);
        $this->assertStringContainsString('bolsaDeBidones(px, py, pz, ba, bb, bc, col, blq.acostado)', $js);
    }

    public function test_el_orden_de_la_lista_decide_que_producto_va_al_fondo(): void
    {
        // «Mover la carga» (pedido del dueño 06-08, mirando EasyCargo) resuelto de la
        // forma honesta: se reordena la lista y el motor RECALCULA, así que el acomodo
        // sigue siendo uno que el motor verificó. Arrastrar bloques a mano dejaría armar
        // en pantalla una carga que el cálculo dice que no cabe.
        //
        // La caja tiene menos volumen que la bolsa, así que en automático la bolsa va al
        // fondo (x = 0). Pidiendo el orden de la lista con la caja primero, la caja pasa
        // al fondo.
        $lineas = [
            ['tipo' => $this->caja->id, 'cantidad' => 10],
            ['tipo' => $this->bolsa->id, 'cantidad' => 50],
        ];

        $auto = $this->verMixta($lineas)->assertOk()->viewData('escena');
        $conLista = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id, 'lineas' => $lineas, 'orden' => 'lista',
        ]))->assertOk()->viewData('escena');

        $alFondo = fn (array $escena) => collect($escena['bloques'])->sortBy('x')->first()['nombre'];

        $this->assertSame($this->bolsa->nombre, $alFondo($auto), 'En automático va lo grande al fondo.');
        $this->assertSame($this->caja->nombre, $alFondo($conLista), 'Con el orden de la lista manda el usuario.');
    }

    public function test_el_orden_automatico_sigue_siendo_el_predeterminado(): void
    {
        // El automático es el que reproduce las cargas verificadas contra fotos («lo
        // grande al fondo, como se estiba de verdad»). Si el default se diera vuelta,
        // cambiarían acomodos que ya estaban validados.
        $res = $this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 10],
            ['tipo' => $this->bolsa->id, 'cantidad' => 50],
        ])->assertOk();

        $this->assertSame('auto', $res->viewData('orden'));

        // Y un valor inventado no pasa la validación en vez de caer en silencio.
        $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 10]],
            'orden' => 'como-quiera',
        ]))->assertSessionHasErrors('orden');
    }

    public function test_el_panel_de_cubicaje_acompana_al_camion(): void
    {
        // El formato del panel izquierdo de EasyCargo, que el dueño pidió (06-08): por
        // producto, su letra, cuántas van de cuántas y un punto verde o rojo, AL LADO del
        // camión. Repite el detalle de abajo a propósito — el valor es no levantar la
        // vista del dibujo para saber qué es cada bloque.
        $html = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 200],
            ['tipo' => $this->caja->id, 'cantidad' => 20],
        ])->assertOk()->getContent();

        $panel = substr($html, strpos($html, 'La carga</p>'), 1400);

        $this->assertStringContainsString('200/200', $panel);
        $this->assertStringContainsString('20/20', $panel);
        // El punto: verde cuando entra completo.
        $this->assertStringContainsString('bg-brand-600', $panel);
    }

    public function test_el_panel_de_cubicaje_marca_en_rojo_lo_que_no_entra(): void
    {
        // 600 botellones son 120 bolsas y el HD35 admite 84: el punto tiene que avisar
        // sin que haya que leer el detalle de abajo.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]])
            ->assertOk()->getContent();

        $panel = substr($html, strpos($html, 'La carga</p>'), 1400);

        $this->assertStringContainsString('420/600', $panel);
        $this->assertStringContainsString('bg-red-500', $panel);
    }

    public function test_las_tres_cabinas_llevan_los_detalles_del_costado(): void
    {
        // Pedido del dueño 05-08: «la cabina del camión, ¿no hay chance de dejarla un
        // poco más real o con más detalle?». De frente ya tenían parrilla, faros,
        // paragolpes y espejos; de COSTADO —una de las vistas fijas— eran una lámina
        // blanca sin una sola línea. `costadoDeCabina()` pone vidrio de puerta, junta,
        // manija y zócalo, y lo llaman las TRES con sus propias medidas.
        //
        // Es un candado de fuente porque el visor no tiene tests de JS: si alguien suma
        // una cabina nueva y se olvida del costado, el defecto vuelve sin que nada avise.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('function costadoDeCabina', $js);
        $this->assertStringContainsString('function visera', $js);

        foreach (['cabinaHino', 'cabinaLiviana', 'cabinaTracto'] as $cabina) {
            $cuerpo = $this->cuerpoDeFuncion($js, $cabina);
            $this->assertStringContainsString(
                'costadoDeCabina(', $cuerpo,
                "La cabina [{$cabina}] no dibuja los detalles del costado.",
            );
        }
    }

    public function test_girar_reencuadra_para_que_el_camion_no_quede_cortado(): void
    {
        // Reporte del dueño (06-08): «quiero que en el cuadrado el camión esté en el
        // centro, ahí lo estoy girando y se ve cortado la última parte».
        //
        // El encuadre se medía UNA vez por vista y no se tocaba, para que girar no
        // cambiara el tamaño. Está mal porque al girar el ancho proyectado de un acoplado
        // de 12 m pasa de 12 m (de costado) a 2,4 m (desde la puerta): con escala fija,
        // cualquier ángulo distinto del medido queda cortado o diminuto. Verificado
        // midiendo la tinta en 7 ángulos: no toca ningún borde y queda centrado.
        //
        // La condición `zoom === 1` es la mitad importante: si el usuario se acercó a
        // mirar un bulto, reencuadrar le sacaría el zoom que acaba de hacer.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('if (zoom === 1) aplicar(medirEncuadre(yaw, pitch));', $js);
    }

    public function test_el_eje_delantero_tiene_una_sola_definicion(): void
    {
        // La rueda delantera y su guardabarro estaban escritos por separado, y el dueño
        // reportó que en el tracto quedaba pegada al tándem («las primeras no parecen
        // ruedas delanteras»). Al corregirla había que corregir los dos lugares o el
        // guardabarro quedaba flotando sobre la nada. Ahora sale de `M.ejeDel`.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('ejeDel:', $js);
        $this->assertDoesNotMatchRegularExpression(
            '/(rueda|guardabarro|guardabarroClaro)\(-M\.largoCab \* 0\.\d+/',
            $js,
            'Volvió a haber una posición de eje delantero escrita a mano.',
        );
    }

    public function test_la_puerta_lateral_es_del_furgon_y_no_del_contenedor(): void
    {
        // Pedido del dueño: «el lateral, ¿hay chance de ponerle puertas?». Va en los
        // furgones. Un contenedor 40' NO tiene puerta al costado —dibujársela sería
        // mostrarle algo que su contenedor no tiene—; ahí el detalle del costado es la
        // corrugación de la pared.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('function puertaLateral', $js);
        $this->assertStringContainsString('if (!M.semi) puertaLateral(', $js);
        // Y la pared lleva nervios: de costado era una sábana lisa de punta a punta.
        $this->assertStringContainsString('const nervio = M.semi', $js);
    }

    public function test_arrastrar_apaga_la_vista_fija_marcada(): void
    {
        // El dueño mandó una captura con «Costado» encendido y el camión en tres cuartos:
        // había arrastrado y el botón seguía marcado, o sea mentía sobre dónde está la
        // cámara.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('if (vistaActual) { vistaActual = null; marcarVista(); }', $js);
    }

    /** El cuerpo de una función del visor, para poder afirmar sobre UNA cabina y no
     *  sobre todo el archivo (donde cualquier otra llamada daría un falso verde). */
    private function cuerpoDeFuncion(string $js, string $nombre): string
    {
        $desde = strpos($js, "function {$nombre}(");
        $this->assertNotFalse($desde, "No existe la función [{$nombre}] en el visor.");

        $hasta = strpos($js, "\n    }\n", $desde);

        return substr($js, $desde, $hasta - $desde);
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
