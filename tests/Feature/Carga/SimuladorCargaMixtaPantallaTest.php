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

    /**
     * CUBICAR: un bulto escrito a mano entra en la carga como cualquier producto.
     *
     * Pedido del dueño 07-08 mirando EasyCargo: escribir L × An × Al de algo que no
     * está en el catálogo y ver si entra.
     */
    public function test_un_bulto_a_medida_se_cubica_como_cualquier_producto(): void
    {
        // Una caja de 100 × 100 × 100 en el HD35 (430 × 204 × 220): 4 × 2 × 2 = 16.
        $html = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id,
                'lineas' => [[
                    'cantidad' => 16,
                    'medida_nombre' => 'Heladera exhibidora',
                    'medida_largo' => 100, 'medida_ancho' => 100, 'medida_alto' => 100,
                    'medida_peso' => 40,
                ]],
            ]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Heladera exhibidora', $html,
            'El bulto a medida no aparece en el detalle de la carga.');
    }

    /**
     * EL CANDADO DE LA DECISIÓN: el bulto a medida NO se guarda.
     *
     * Es descartable por elección del dueño (07-08). Importa porque el catálogo es
     * de donde salen los cupos que se le prometen a un cliente: si cada prueba
     * sembrara una fila, en un mes el catálogo sería medidas tipeadas al voleo y la
     * regla «no se inventan números» quedaría en la nada.
     */
    public function test_el_bulto_a_medida_no_se_guarda_en_el_catalogo(): void
    {
        $antes = TipoBulto::count();

        $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id,
                'lineas' => [[
                    'cantidad' => 5,
                    'medida_nombre' => 'Prueba que no debe quedar',
                    'medida_largo' => 80, 'medida_ancho' => 60, 'medida_alto' => 50,
                ]],
            ]))
            ->assertOk();

        $this->assertSame($antes, TipoBulto::count(), 'El bulto a medida se guardó en el catálogo.');
        $this->assertDatabaseMissing('tipos_bulto', ['nombre' => 'Prueba que no debe quedar']);
    }

    /** Una línea sin producto NI medidas no es una línea: se ignora, no revienta. */
    public function test_una_linea_vacia_no_rompe_la_pantalla(): void
    {
        $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id,
                'lineas' => [
                    ['tipo' => $this->bolsa->id, 'cantidad' => 10],
                    ['cantidad' => 3],   // sin tipo y sin medidas
                ],
            ]))
            ->assertOk();
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
            // Cuánto se ve cargado: animación + DOS pasos a mano. Eran seis
            // (−10/−5/−1/+1/+5/+10) hasta el 07-08: el dueño pidió dejar solo + y −
            // («es mucho número») y el recorrido largo lo cubre el mantener
            // apretado, que acelera.
            'carga3dPlay', 'carga3dVaciar', 'carga3dQuita1', 'carga3dCantidad', 'carga3dSuma1', 'carga3dBarra', 'carga3dTodo',
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
        //
        // La aserción mira el bloque de `Acercar` dentro del menú lateral: al pasar los
        // controles al menú (06-08) el contenedor cambió de clases pero NO la regla.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])->assertOk()->getContent();

        $zoom = strpos($html, 'id="carga3dMenos"');
        $this->assertNotFalse($zoom, 'Desapareció el control de zoom.');

        // El envoltorio `hidden lg:block` tiene que estar ANTES del primer botón de zoom y
        // después del bloque de vistas: si no, el zoom quedaría visible en celular.
        $antes = substr($html, strpos($html, 'id="carga3dVistapuerta"'), $zoom - strpos($html, 'id="carga3dVistapuerta"'));
        $this->assertStringContainsString('hidden lg:block', $antes);
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

    /**
     * Los pasos de carga son DOS, y se pueden mantener apretados.
     *
     * Las dos mitades van juntas en un solo candado a propósito. El dueño pidió
     * bajar de seis botones a dos el 07-08 («es mucho número»), pero los seis
     * existían por un motivo real: con un paso fijo de a uno, llenar el
     * contenedor de 324 bultos era repetir el clic 324 veces. Lo que hace que la
     * simplificación no sea un retroceso es la REPETICIÓN AL MANTENER APRETADO.
     * Si alguien la saca «porque no se usa», vuelve el problema de 2026-08-06 y
     * este test es lo único que lo dice.
     *
     * Los handlers son `pointer*` y van sobre los BOTONES, no sobre el lienzo:
     * no contradicen test_el_visor_no_registra_gestos_tactiles, que protege que
     * el zoom no entre por gestos en el canvas.
     */
    public function test_los_pasos_de_carga_son_dos_y_se_mantienen_apretados(): void
    {
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('pasoRepetible', $js, 'Se perdió la repetición al mantener apretado.');
        $this->assertStringContainsString("addEventListener('pointerdown'", $js);
        foreach (['pointerup', 'pointercancel', 'pointerleave'] as $corte) {
            $this->assertStringContainsString($corte, $js,
                "Sin [{$corte}] el contador sigue corriendo solo después de soltar.");
        }

        // Y los cuatro pasos viejos ya no existen: si vuelven, es que alguien
        // deshizo el pedido sin enterarse.
        $html = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk()->getContent();

        foreach (['carga3dQuita5', 'carga3dQuita10', 'carga3dSuma5', 'carga3dSuma10'] as $viejo) {
            $this->assertStringNotContainsString('id="'.$viejo.'"', $html,
                "Volvió el botón [{$viejo}]: el dueño pidió dejar solo + y −.");
        }
    }

    /**
     * Los TRES controles de cantidad mueven el MISMO número.
     *
     * Pasos (− +), campo escrito y barra deslizante son tres formas de tocar `cant`,
     * no tres estados. Lo que se vigila es que los tres entren por `fijar()` —que es
     * quien capa contra 0 y el tope y corta la animación— y que los dos que muestran
     * valor se sincronicen dentro de `dibujar()`, el único lugar por el que pasa
     * todo. Si alguno se cableara por su cuenta, la pantalla mostraría un número y
     * dibujaría otro, que es el defecto que nadie reporta pero que hace desconfiar
     * de la herramienta.
     */
    /**
     * EN PALLET SOLO VAN CAJAS (dueño, 07-08-2026: «los botellones nunca van a ir en
     * pallet, solo cajas»).
     *
     * Las dos mitades importan y por eso van juntas. Que el selector no OFREZCA una
     * bolsa es la mitad visible; la que de verdad arregla el defecto es la segunda:
     * a este modo se llega desde los otros dos con un `tipo_bulto_id` ya puesto en la
     * URL, así que sin corregir el producto elegido el pallet se seguía calculando
     * con la bolsa y devolvía «0 pallets» — que se lee como que la app falló, cuando
     * en realidad ese producto no va en pallet (mide 130 cm y el pallet 120).
     */
    /**
     * MULTI-CAMIÓN: la misma pregunta, respondida para toda la flota.
     *
     * La pregunta real de Comercial no es «¿entra en este?» sino «¿en cuál conviene
     * mandarlo?». Hasta ahora había que cambiar de camión y recalcular de a uno.
     *
     * Lo que se fija: que compare TODOS los camiones activos, que ordene de mayor a
     * menor —que es el orden en que se toma la decisión—, que marque el actual, y
     * que la tabla viva DENTRO del menú lateral y no suelta en la pantalla (doctrina
     * del 06-08, la misma que vigila `test_los_controles_viven_en_un_solo_menu…`).
     */
    public function test_compara_todos_los_camiones_y_ordena_por_lo_que_entra(): void
    {
        $hino = CamionSimulacion::create([
            'nombre' => 'HINO 500 (FC 1118)',
            'largo_cm' => 797, 'ancho_cm' => 260, 'alto_cm' => 266,
            'peso_max_kg' => 11000, 'pasillo_cm' => 0, 'activo' => true,
        ]);

        $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->bolsa->id,
        ]))->assertOk();

        $comparativa = $res->viewData('comparativa');
        $this->assertNotNull($comparativa, 'No se comparó nada habiendo dos camiones.');
        $this->assertCount(2, $comparativa);

        // El HINO lleva más que el HD35, así que va primero.
        $this->assertSame($hino->id, $comparativa[0]['camion']->id, 'No ordenó por lo que entra.');
        $this->assertGreaterThan($comparativa[1]['unidades'], $comparativa[0]['unidades']);

        // El camión elegido queda marcado, para no perderse en la lista.
        $this->assertFalse($comparativa[0]['actual']);
        $this->assertTrue($comparativa[1]['actual']);

        // Y la tabla vive dentro del menú lateral.
        $html = $res->getContent();
        $desde = strpos($html, 'Herramientas');
        $menu = substr($html, $desde, strpos($html, '</aside>', $desde) - $desde);
        $this->assertStringContainsString('HINO 500 (FC 1118)', $menu,
            'La comparativa de camiones quedó fuera del menú lateral.');
    }

    /**
     * CARGA MIXTA con VARIOS camiones — la combinación que faltaba.
     *
     * Este candado nace de un 500 en producción (10-08). La comparativa llamaba a
     * `calcularMixta` con una variable que su método no recibía, y no lo cazó
     * ningún test porque los dos casos estaban cubiertos POR SEPARADO: el de
     * varios camiones usaba producto único, y los de líneas tenían un solo camión.
     * El cruce de los dos no lo probaba nadie, y es justo el caso de producción —
     * donde hay tres camiones sembrados.
     *
     * La lección que deja: cuando dos features tienen cada una su test, el que
     * falta es el de las dos juntas.
     */
    public function test_la_carga_mixta_con_varios_camiones_no_revienta(): void
    {
        CamionSimulacion::create([
            'nombre' => 'HINO 500 (FC 1118)',
            'largo_cm' => 797, 'ancho_cm' => 260, 'alto_cm' => 266,
            'peso_max_kg' => 11000, 'pasillo_cm' => 0, 'activo' => true,
        ]);

        foreach ([[], ['aprovechar' => 1]] as $extra) {
            $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', $extra + [
                'camion_id' => $this->hd35->id,
                'lineas' => [
                    ['tipo' => $this->bolsa->id, 'cantidad' => 200],
                    ['tipo' => $this->caja->id, 'cantidad' => 30],
                ],
            ]))->assertOk();

            // Y la comparativa se calculó de verdad para los dos camiones.
            $this->assertCount(2, $res->viewData('comparativa'));
        }
    }

    /**
     * CADA PESTAÑA DICE CUÁNTOS PRODUCTOS ACEPTA.
     *
     * Nace de una pregunta del dueño (10-08): «¿y dónde agrego otro bulto?»,
     * estando en «¿Cuánto entra?», que es de UN producto. El nombre decía la
     * PREGUNTA pero no la CAPACIDAD, así que desde ahí no había forma de saber que
     * lo de varios productos vivía en la pestaña de al lado.
     *
     * Es un candado de DESCUBRIBILIDAD y por eso vale escribirlo: la función
     * existía y funcionaba, y aun así el usuario no la encontraba. Eso no lo
     * detecta ningún test de comportamiento.
     */
    public function test_las_pestanas_dicen_cuantos_productos_acepta_cada_una(): void
    {
        $html = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('un producto', $html);
        $this->assertStringContainsString('varios productos', $html);
        // Y desde el modo de un producto hay un camino explícito al de varios.
        $this->assertStringContainsString('más de un producto', $html);
    }

    /**
     * EL PANEL: una tarjeta por producto, con el bulto a medida adentro.
     *
     * Lo que se fija es lo que hace útil al panel, no su estética: que la línea
     * traiga los campos de CUBICAR. El motor los acepta desde el 07-08 pero hasta
     * el 10-08 solo se llegaba por URL, porque la fila plana anterior no tenía
     * dónde ponerlos. Si alguien vuelve a la fila plana, esa función se pierde
     * otra vez en silencio.
     */
    public function test_el_panel_trae_los_campos_del_bulto_a_medida(): void
    {
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])
            ->assertOk()->getContent();

        // El botón que crea la línea a medida.
        $this->assertStringContainsString('Bulto a medida', $html);
        // Y los cinco campos que el controlador valida, con su nombre exacto.
        foreach (['medida_nombre', 'medida_largo', 'medida_ancho', 'medida_alto', 'medida_peso'] as $campo) {
            $this->assertStringContainsString('medida_'.explode('_', $campo)[1], $html,
                "El panel no ofrece el campo [{$campo}] del bulto a medida.");
        }

        // El chip de color de cada línea usa la MISMA paleta que el lienzo: si
        // divergieran, la lista mentiría sobre cuál bloque es cuál.
        $primerColor = sprintf('#%02x%02x%02x', ...SimuladorCargaController::COLORES_3D[0]);
        $this->assertStringContainsString($primerColor, $html);
    }

    /** Con un solo camión no hay nada que comparar: la sección no se dibuja. */
    public function test_con_un_solo_camion_no_se_muestra_la_comparativa(): void
    {
        $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id,
                'tipo_bulto_id' => $this->bolsa->id,
            ]))
            ->assertOk()
            ->assertViewHas('comparativa', null);
    }

    public function test_en_pallet_solo_se_ofrecen_cajas_y_una_bolsa_se_corrige_sola(): void
    {
        $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'sobre_pallet' => 1,
            // Se llega con la BOLSA elegida, que es como pasa en la pantalla real.
            'tipo_bulto_id' => $this->bolsa->id,
        ]))->assertOk();

        // El selector solo trae cajas.
        $paletizables = $res->viewData('paletizables');
        $this->assertTrue($paletizables->isNotEmpty(), 'No quedó ninguna caja paletizable.');
        foreach ($paletizables as $b) {
            $this->assertSame('cajas', $b->categoria, "[{$b->nombre}] no es una caja y se ofrece para paletizar.");
        }

        // Y el producto elegido se corrigió a una caja: el resultado ya no es 0.
        $this->assertSame('cajas', $res->viewData('bulto')->categoria);
        $enPallet = $res->viewData('enPallet');
        $this->assertTrue($enPallet['entraEnPallet'], 'El producto elegido sigue sin entrar en el pallet.');
        $this->assertGreaterThan(0, $enPallet['unidadesTotales'],
            'El pallet sigue dando 0: no se corrigió el producto que no va en pallet.');
    }

    public function test_los_tres_controles_de_cantidad_mueven_el_mismo_numero(): void
    {
        $js = file_get_contents(resource_path('js/carga3d.js'));

        // Los tres entran por fijar(), no tocan `cant` a mano.
        $this->assertMatchesRegularExpression('/carga3dCantidad[\s\S]{0,600}?fijar\(/', $js);
        $this->assertMatchesRegularExpression('/carga3dBarra[\s\S]{0,600}?fijar\(/', $js);
        $this->assertStringContainsString('pasoRepetible', $js);

        // Y los dos que muestran valor se sincronizan al dibujar.
        foreach (['carga3dCantidad', 'carga3dBarra'] as $id) {
            $this->assertMatchesRegularExpression(
                '/'.$id.'[\s\S]{0,300}?\.value = cant/', $js,
                "El control [{$id}] no se sincroniza con el número real.",
            );
        }

        // La barra escucha `input`: la carga se dibuja MIENTRAS se arrastra, que es
        // todo el valor de tener una barra. Con `change` recién se movería al soltar.
        $this->assertMatchesRegularExpression("/barra\.addEventListener\('input'/", $js);
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

    public function test_las_tres_estibas_son_tres_rotaciones_del_mismo_pack(): void
    {
        // Pedido del dueño 05-08 («necesito la opción de poder acostar el pack») ampliado
        // el 06-08 («hacé la de pico a la puerta hasta donde se pueda»). Las tres salen de
        // la bolsa medida, 130 × 26 × 51 = cinco botellones PARADOS —51 el alto del
        // botellón, 26 su diámetro, 130 la fila de cinco—.
        $de = fn (string $e) => array_values(array_intersect_key(
            $this->bolsa->paraCalculo($e), array_flip(['largo', 'ancho', 'alto']),
        ));

        $this->assertSame([130, 26, 51], $de('pie'), 'Parados.');
        $this->assertSame([130, 51, 26], $de('costado'), 'Tumbados, el eje cruzando el camión.');
        $this->assertSame([51, 130, 26], $de('pico'), 'Tumbados, el eje mirando a la puerta.');

        // Una estiba inventada cae a `pie` en vez de calcular con medidas que nadie pidió.
        $this->assertSame($de('pie'), $de('de-costadito'));
    }

    public function test_cada_estiba_da_un_numero_distinto_y_de_pie_es_el_predeterminado(): void
    {
        // El número CAMBIA con la estiba, y hacia abajo. De pie tiene que seguir siendo el
        // predeterminado porque es la orientación con la que el dueño verificó sus 420: si
        // el default se diera vuelta, su referencia dejaría de cuadrar y nadie sabría por
        // qué.
        //
        // «Pico a la puerta» da el peor cupo porque cruza la fila de 130 cm en una caja de
        // 200: se pierden 70 cm de ancho. No es un error — es la razón por la que en la
        // práctica se elige según el camión.
        $calculo = new \App\Services\Carga\CalculoDeCarga;
        $camion = $this->hd35->paraCalculo();
        $cupo = fn (string $e) => $calculo->cupo($camion, $this->bolsa->paraCalculo($e))['unidades'];

        $this->assertSame(420, $cupo('pie'));
        $this->assertSame(270, $cupo('costado'));
        $this->assertSame(240, $cupo('pico'));

        // Sin pedir nada, la pantalla responde en AUTOMÁTICO — que en un pack de
        // orientación fija ES de pie, así que el 420 verificado no se mueve.
        $sinPedir = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk();

        $this->assertSame(420, $sinPedir->viewData('resultado')['unidades']);
        $this->assertSame('auto', $sinPedir->viewData('estiba'));
        $this->assertSame(420, $calculo->cupo($camion, $this->bolsa->paraCalculo('auto'))['unidades']);
    }

    public function test_la_pantalla_calcula_la_estiba_pedida_y_dice_cual_uso(): void
    {
        foreach (['costado' => 270, 'pico' => 240] as $estiba => $unidades) {
            $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id,
                'tipo_bulto_id' => $this->bolsa->id,
                'estiba' => $estiba,
            ]))->assertOk();

            $this->assertSame($unidades, $res->viewData('resultado')['unidades']);
            $this->assertSame($estiba, $res->viewData('estiba'));
            // Y lo DICE: «entran 240» sin decir con qué estiba invita a compararlo con los
            // 420 de pie y a pensar que el simulador se equivocó.
            $res->assertSee('Cómo viaja')->assertSee(TipoBulto::ESTIBAS[$estiba]);
        }
    }

    public function test_la_estiba_se_elige_por_linea_en_la_carga_mixta(): void
    {
        // En la misma carga puede ir un pack acostado y otro de pie, así que la elección
        // es POR LÍNEA y no de la pantalla.
        $escena = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 50, 'estiba' => 'costado'],
            ['tipo' => $this->caja->id, 'cantidad' => 10],
        ])->assertOk()->viewData('escena');

        $porNombre = collect($escena['bloques'])->keyBy('nombre');

        $this->assertSame('costado', $porNombre[$this->bolsa->nombre]['estiba']);
        $this->assertSame('pie', $porNombre[$this->caja->nombre]['estiba']);

        // El bloque acostado mide 26 cm de alto y 51 de ancho, al revés que de pie.
        $this->assertSame(0.51, $porNombre[$this->bolsa->nombre]['orientacion']['ancho']);
        $this->assertSame(0.26, $porNombre[$this->bolsa->nombre]['orientacion']['alto']);
    }

    public function test_el_tope_de_apilado_se_elige_por_linea_y_vacio_deja_el_del_catalogo(): void
    {
        // Reporte del dueño (10-08) mirando el HINO cargado: «necesito que los bidones
        // también lleguen hasta el techo, ahí solo veo las cajas de tapas que llegan».
        //
        // No era el dibujo ni el acomodo: era que el tope de apilado se podía pisar en el
        // modo de UN producto (§3.4) y en la carga mixta NO, así que cada línea se quedaba
        // con el 6 de su catálogo. Con productos de distinto alto ese mismo 6 llega a
        // alturas muy distintas: seis cajas de 42 cm tocan el techo y seis bolsas
        // acostadas de 26 se quedan a media caja. De ahí el hueco que él vio.
        $bolsas = fn ($r) => $r->assertOk()->viewData('mixta')['lineas'][0]['cargadas_unidades'];

        // Acostada, la bolsa mide 26 cm: en 220 de caja entran 8 capas y el tope de 6
        // muerde. Es exactamente el caso del reporte.
        $catalogo = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 5000, 'estiba' => 'costado'],
        ]);
        $hastaElTecho = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 5000, 'estiba' => 'costado', 'apilado' => 8],
        ]);

        $this->assertSame(270, $bolsas($catalogo), '6 capas de 26 cm dejan 64 cm de aire.');
        $this->assertSame(360, $bolsas($hastaElTecho), '8 capas llenan los 220 cm.');

        // POR LÍNEA: subirle el tope a un producto no puede tocarle el suyo al de al lado.
        // Sin esto el control sería de pantalla y prometería en la caja de tapas una
        // altura que nadie pidió.
        $solas = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 50, 'estiba' => 'costado'],
            ['tipo' => $this->caja->id, 'cantidad' => 40],
        ])->assertOk()->viewData('mixta')['lineas'];
        $conTope = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 50, 'estiba' => 'costado', 'apilado' => 8],
            ['tipo' => $this->caja->id, 'cantidad' => 40],
        ])->assertOk()->viewData('mixta')['lineas'];

        $this->assertSame(6, $solas[0]['apiladas']);
        $this->assertSame(8, $conTope[0]['apiladas'], 'La línea que pidió 8 apila 8.');
        $this->assertSame(
            $solas[1]['apiladas'],
            $conTope[1]['apiladas'],
            'La caja de tapas no pidió nada: su tope no se mueve.',
        );
    }

    public function test_la_fila_dice_cuanto_aire_queda_arriba_y_ofrece_llenarlo(): void
    {
        // El hueco no se explicaba solo, y ese era el defecto de fondo: dos productos
        // apilados los MISMOS 6 del catálogo llegan a alturas distintas, así que en
        // pantalla parecía un error del dibujo. Ahora la fila dice los dos números —los
        // que van y los que darían— y el botón sube el tope de un toque.
        $conAire = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 100, 'estiba' => 'costado'],
        ])->assertOk();

        $fila = $conAire->viewData('mixta')['lineas'][0];
        $this->assertSame(6, $fila['apiladas'], 'El tope del catálogo.');
        $this->assertSame(8, $fila['apilables_por_alto'], '220 / 26 = 8 capas.');

        $html = $conAire->getContent();
        $this->assertStringContainsString('la caja da para', $html);
        $this->assertStringContainsString('Apilar 8', $html);

        // MUTADO: pedido el tope que la altura permite, el aviso NO tiene por qué seguir
        // ahí. Un aviso que no se apaga al resolverlo deja de leerse.
        $lleno = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 100, 'estiba' => 'costado', 'apilado' => 8],
        ])->assertOk();

        $this->assertSame(8, $lleno->viewData('mixta')['lineas'][0]['apilables_por_alto']);
        $this->assertStringNotContainsString('Apilar 8', $lleno->getContent());
    }

    public function test_una_linea_descartada_no_le_corre_la_letra_a_las_de_abajo(): void
    {
        // Una línea sin producto ni medidas NO llega al resultado, así que la lista de
        // productos perdía un lugar mientras los bloques seguían viajando con el índice
        // original. Los cuatro lugares que resuelven «de quién es este bloque» —el nombre
        // en el lienzo, la letra, el color y el Excel— se desalineaban, y `escena()`
        // directamente reventaba con «Undefined array key».
        //
        // Mutado: con la lista reindexada, esta petición devuelve 500.
        $r = $this->verMixta([
            ['cantidad' => 3],   // sin tipo y sin medidas: no es una línea
            ['tipo' => $this->bolsa->id, 'cantidad' => 100, 'estiba' => 'costado'],
        ])->assertOk();

        $lineas = $r->viewData('mixta')['lineas'];
        $this->assertSame([1], array_keys($lineas), 'La bolsa es la SEGUNDA línea y lo sigue siendo.');

        // Y el bloque del lienzo resuelve su producto por esa misma clave.
        $bloques = $r->viewData('escena')['bloques'];
        $this->assertNotEmpty($bloques);
        $this->assertSame($this->bolsa->nombre, $bloques[0]['nombre']);
        $this->assertSame(SimuladorCargaController::letra(1), $bloques[0]['letra']);
    }

    public function test_las_lineas_se_calculan_en_el_orden_en_que_se_enviaron(): void
    {
        // `validate()` NO devuelve las líneas en el orden en que llegaron: arma el
        // resultado recorriendo REGLA por regla, así que la primera línea que aparece es
        // la que trae la primera clave con la que se topó. Una línea SIN `tipo` —un bulto
        // a medida— queda detrás de las del catálogo aunque se haya escrito antes.
        //
        // No es cosmético: con «Como armé la lista» el primero va al FONDO del camión, y
        // de esa posición salen la letra y el color de cada producto en el lienzo. Mutado:
        // sin el `ksort` del controlador, la bolsa se adelanta y se lleva el fondo y la A.
        $lineas = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'orden' => 'lista',
            'lineas' => [
                ['medida_nombre' => 'Heladera exhibidora', 'medida_largo' => 60,
                    'medida_ancho' => 60, 'medida_alto' => 150, 'cantidad' => 2],
                ['tipo' => $this->bolsa->id, 'cantidad' => 50, 'estiba' => 'costado'],
            ],
        ]))->assertOk()->viewData('mixta')['lineas'];

        $this->assertSame('Heladera exhibidora', $lineas[0]['modelo']->nombre, 'Se escribió primera.');
        $this->assertSame($this->bolsa->nombre, $lineas[1]['modelo']->nombre);
    }

    public function test_forzar_la_estiba_de_un_bulto_libre_le_saca_la_rotacion_al_motor(): void
    {
        // REGLA DADA VUELTA por el dueño (06-08): «que los dispensadores, cualesquiera
        // que sea, tengan la opción de pie y acostado». Antes a los bultos libres no se
        // les ofrecía estiba (el motor ya rotaba); ahora forzarla significa exactamente
        // sacarle esa libertad — un dispensador viaja PARADO aunque tumbado entren más.
        //
        // Lo que protege el número verificado es el default: en `auto` el bulto libre
        // sigue libre, idéntico a como era.
        $auto = $this->caja->paraCalculo();

        $this->assertFalse($auto['orientacion_fija'], 'En auto, el bulto libre sigue libre.');
        $this->assertSame([46, 37, 42], [$auto['largo'], $auto['ancho'], $auto['alto']]);

        $forzada = $this->caja->paraCalculo('costado');

        $this->assertTrue($forzada['orientacion_fija'], 'Forzar la estiba fija la orientación.');
        $this->assertSame([46, 42, 37], [$forzada['largo'], $forzada['ancho'], $forzada['alto']]);

        // Y el efecto es medible: de pie FORZADO el dispensador de 87 cm solo apila 2 en
        // 220 cm de alto; en auto el motor lo tumba y aprovecha mejor. Que den distinto
        // es la prueba de que la opción hace algo.
        $alto = TipoBulto::create([
            'nombre' => 'Dispensador de prueba', 'categoria' => 'maquinas',
            'largo_cm' => 29, 'ancho_cm' => 33, 'alto_cm' => 87, 'peso_kg' => 8,
            'unidades' => 1, 'apilable_max' => 10, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);
        $calculo = new \App\Services\Carga\CalculoDeCarga;
        $camion = $this->hd35->paraCalculo();

        $this->assertGreaterThan(
            $calculo->cupo($camion, $alto->paraCalculo('pie'))['bultos'],
            $calculo->cupo($camion, $alto->paraCalculo('auto'))['bultos'],
            'Forzar de pie tiene que dar menos que dejar rotar al motor.',
        );
    }

    public function test_el_visor_dibuja_la_estiba_que_se_calculo(): void
    {
        // Si el lienzo mostrara los botellones parados mientras el cálculo dice «pico a la
        // puerta», dejaría de ser la prueba de lo que el motor hizo — que es todo lo que
        // aporta. El cilindro tumbado es una función APARTE de la vertical (el sombreado y
        // la tapa van sobre otros planos) y recibe el EJE, porque «de costado» y «pico a la
        // puerta» se dibujan con el botellón mirando a distinto lado.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('function cilindroTumbado', $js);
        $this->assertStringContainsString('bolsaDeBidones(px, py, pz, ba, bb, bc, col, blq.estiba)', $js);
        // Las tres estibas tienen que estar contempladas en el dibujo, no solo dos.
        $this->assertStringContainsString("estiba === 'pico'", $js);
        $this->assertStringContainsString("estiba === 'costado'", $js);
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

        $panel = substr($html, strpos($html, 'Cubicaje'), 3000);

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

        $panel = substr($html, strpos($html, 'Cubicaje'), 3000);

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

    public function test_los_controles_viven_en_un_solo_menu_y_no_en_las_cuatro_esquinas(): void
    {
        // Pedido del dueño 06-08: «organizá los botones en un menú, en un lateral… y que no
        // tenga tantos botones por toda la pantalla, siento que genera confusión». Antes
        // los controles estaban repartidos en las cuatro esquinas del lienzo y cada pedido
        // nuevo sumaba una esquina.
        //
        // El candado mira que TODOS los controles estén dentro del <aside> del menú, que es
        // lo que se rompe sin darse cuenta al agregar el próximo botón «rápido» en una
        // esquina.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])->assertOk()->getContent();

        // Se ancla en el rótulo «Herramientas» y NO en el primer `<aside>`: el layout de la
        // app tiene su propio aside (el menú de navegación) y la rebanada caía ahí.
        $desde = strpos($html, 'Herramientas');
        $this->assertNotFalse($desde, 'Ya no hay menú lateral en el visor.');
        $hasta = strpos($html, '</aside>', $desde);
        $menu = substr($html, $desde, $hasta - $desde);

        foreach ([
            'carga3dVista3d', 'carga3dVistacostado', 'carga3dVistaplanta', 'carga3dVistapuerta',
            'carga3dMenos', 'carga3dMas', 'carga3dReset',
            'carga3dPlay', 'carga3dVaciar', 'carga3dQuita1', 'carga3dCantidad', 'carga3dSuma1', 'carga3dBarra', 'carga3dTodo',
            'carga3dCodigos', 'carga3dNombres', 'carga3dImportar',
        ] as $control) {
            $this->assertStringContainsString(
                'id="'.$control.'"', $menu,
                "El control [{$control}] quedó fuera del menú lateral.",
            );
        }

        // Y el lienzo NO puede quedar tapado: el menú es una barra que le come ancho
        // (`flex` + `shrink-0`), no un panel flotante encima del camión.
        $this->assertStringContainsString('shrink-0', $menu);
        $this->assertStringNotContainsString('absolute', $menu);

        // Las secciones son DESPLEGABLES (pedido del dueño 06-08: «que se puedan
        // desplegar como dropdown») — <details> nativos, sin JS.
        $this->assertGreaterThanOrEqual(5, substr_count($menu, '<details'));

        // Y el PALLET también se ofrece desde el menú, con los dos tipos estándar.
        $this->assertStringContainsString('Industrial 120 × 100', $menu);
        $this->assertStringContainsString('EUR/EPAL 120 × 80', $menu);
        $this->assertStringContainsString('sobre_pallet=1', $menu);
    }

    public function test_la_cantidad_a_probar_capa_el_resultado_y_el_dibujo(): void
    {
        // Pedido del dueño 06-08: «me falta la opción de cuánto cargo, 1, 20, 50, para
        // realizar la prueba». No toca el motor: capa el dibujo a lo pedido y el
        // veredicto sale de comparar contra el máximo.
        $ver = fn (?int $c) => $this->actingAs($this->vendedor)->get(route('admin.carga.index', array_filter([
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->bolsa->id,
            'cantidad' => $c,
        ])))->assertOk();

        // 50 botellones = 10 bolsas: entran (el máximo es 420) y el dibujo muestra 10.
        $conPrueba = $ver(50);
        $this->assertTrue($conPrueba->viewData('prueba')['caben']);
        $this->assertSame(10, $conPrueba->viewData('escena')['tope']);
        $conPrueba->assertSee('Tus 50 entran');

        // 600 no entran: el veredicto dice cuántas sí, y el dibujo muestra el máximo.
        $sinCupo = $ver(600);
        $this->assertFalse($sinCupo->viewData('prueba')['caben']);
        $this->assertSame(420, $sinCupo->viewData('prueba')['cargadas']);
        $this->assertSame(84, $sinCupo->viewData('escena')['tope']);

        // Sin cantidad, todo sigue como siempre: máximo y sin veredicto de prueba.
        $sinPrueba = $ver(null);
        $this->assertNull($sinPrueba->viewData('prueba'));
        $this->assertSame(84, $sinPrueba->viewData('escena')['tope']);
    }

    public function test_importar_de_excel_esta_ofrecido_y_dice_que_no_lee_facturas(): void
    {
        // El dueño lo pidió para «generar una ruta con facturas, cargar y hacer una prueba
        // si alcanza todo o no». Lo que entró lee productos y cantidades pegados de la
        // planilla; las facturas y la ruta son otra pieza. La pantalla lo DICE, porque un
        // botón que promete más de lo que hace se descubre en el peor momento.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]])->assertOk()->getContent();

        $this->assertStringContainsString('id="carga3dImportar"', $html);
        $this->assertStringContainsString('Traer la carga de una planilla', $html);
        $this->assertStringContainsString('Todavía no lee facturas ni arma la ruta', $html);
    }

    public function test_apilar_mas_alto_usa_el_espacio_que_quedaba_libre(): void
    {
        // El dueño marcó el hueco arriba de la carga: «ahí también se pueda cargar bidones
        // porque en la vida cotidiana se usa todo el espacio». No era un error del dibujo
        // ni del acomodo: era el tope de apilado del catálogo (6), que corta antes que la
        // altura de la caja. Ahora se puede pisar POR SIMULACIÓN.
        $ver = fn (?int $apilado) => $this->actingAs($this->vendedor)->get(route('admin.carga.index', array_filter([
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->bolsa->id,
            'apilado' => $apilado,
        ])))->assertOk();

        // De pie, la bolsa mide 51 cm: en 220 cm de caja entran 4 capas, así que el tope
        // de 6 no muerde y subirlo no cambia nada. Acostada mide 26 y entran 8 capas: ahí
        // el tope SÍ manda, y es el caso que el dueño estaba mirando.
        $conTope = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id, 'estiba' => 'costado',
        ]))->assertOk();
        $sinTope = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id, 'estiba' => 'costado', 'apilado' => 8,
        ]))->assertOk();

        $this->assertSame(270, $conTope->viewData('resultado')['unidades'], '6 capas de 26 cm.');
        $this->assertSame(360, $sinTope->viewData('resultado')['unidades'], '8 capas llenan los 220 cm.');

        // Sin pedir nada manda el catálogo: es el dato que él dictó y con el que se
        // verificaron las referencias.
        $this->assertNull($ver(null)->viewData('apilado'));
        $this->assertSame(420, $ver(null)->viewData('resultado')['unidades']);
    }

    public function test_el_pallet_se_resuelve_con_el_mismo_cupo_dos_veces(): void
    {
        // «A veces cargamos en pallet» + «que el pallet aparezca al lado del camión con la
        // opción de armarlo y luego subirlo». La idea clave: un pallet ES una caja de
        // carga, así que son dos llamadas al mismo `cupo()` verificado y ni una línea de
        // cálculo nueva.
        $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->caja->id,
            'sobre_pallet' => 1,
            'pallet_tipo' => 'industrial',
            'pallet_alto' => 180,
        ]))->assertOk();

        $enPallet = $res->viewData('enPallet');
        $calculo = new \App\Services\Carga\CalculoDeCarga;
        $pallet = $res->viewData('pallet');

        // 1) Lo que entra ENCIMA del pallet sale de cupo() con el pallet como caja.
        $this->assertSame(
            $calculo->cupo($pallet->comoCajaDeCarga(), $this->caja->paraCalculo())['unidades'],
            $enPallet['unidadesPorPallet'],
        );
        // 2) Y los pallets en el camión, del mismo cupo() con el pallet armado como bulto.
        $this->assertSame($enPallet['enCamion']['bultos'], $enPallet['cabenPallets']);
        $this->assertSame(
            $enPallet['cabenPallets'] * $enPallet['unidadesPorPallet'],
            $enPallet['unidadesTotales'],
        );
        // La tarima se descuenta del alto útil: 180 − 15.
        $this->assertSame(165, $pallet->altoUtilCm());
    }

    public function test_un_pallet_gira_90_grados_pero_no_se_tumba(): void
    {
        // Un pallet se puede poner a lo largo o a lo ancho, pero acostarlo volcaría la
        // carga. Sin esta regla había que elegir entre dos mentiras: fijarlo perdía el giro
        // válido (cupo más bajo que el real) y liberarlo dejaba al motor tumbarlo.
        $calculo = new \App\Services\Carga\CalculoDeCarga;
        $pallet = \App\Services\Carga\PalletSimulado::desdeFormulario('industrial', null, null, 180);
        $bulto = $pallet->comoBulto(500.0, 40);

        // Cabe de las dos formas horizontales, y NUNCA de canto: el alto del bulto
        // colocado tiene que ser siempre el alto del pallet.
        $caja = ['largo' => 430, 'ancho' => 200, 'alto' => 220, 'peso_max_kg' => null, 'pasillo' => 0];
        $r = $calculo->cupo($caja, $bulto);

        $this->assertSame(180, $r['orientacion']['alto'], 'El pallet quedó tumbado.');
        $this->assertContains($r['orientacion']['largo'], [120, 100]);
        $this->assertSame(1, $r['rejilla']['alto'], 'No se apila un pallet sobre otro.');
    }

    public function test_la_escena_lleva_el_pallet_para_dibujarlo_en_el_piso(): void
    {
        // El visor dibuja el pallet AL LADO del camión mientras se arma, así que la escena
        // tiene que llevarlo con su carga encima. Si esto se cae, el botón «Subir al
        // camión» queda sin nada que subir y no se rompe nada — desaparece en silencio.
        $escena = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->caja->id,
            'sobre_pallet' => 1,
        ]))->assertOk()->viewData('escena');

        $this->assertArrayHasKey('pallet', $escena);
        $this->assertSame(1.20, $escena['pallet']['largo']);
        $this->assertSame(0.15, $escena['pallet']['base']);
        $this->assertGreaterThan(0, $escena['pallet']['interior']['cantidad']);
        // Y los bultos del camión son PALLETS, con su base y su interior para dibujarlos.
        $this->assertSame('pallet', $escena['bloques'][0]['forma']);
        $this->assertArrayHasKey('interior', $escena['bloques'][0]);
    }

    public function test_medidas_de_pallet_disparatadas_se_acotan_en_vez_de_reventar(): void
    {
        // Los campos son un `<input number>` que el usuario tipea. Un 5000 de más no puede
        // reventar la pantalla ni entrar al cálculo.
        $p = \App\Services\Carga\PalletSimulado::desdeFormulario('industrial', 9999, 0, 9999, 999);

        $this->assertSame(300, $p->largo_cm);
        $this->assertSame(100, $p->ancho_cm, 'Un 0 cae al estándar del tipo elegido.');
        $this->assertSame(\App\Services\Carga\PalletSimulado::ALTO_MAX, $p->alto_cm);
        $this->assertSame(30, $p->base_cm);
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

    /**
     * NO se dibuja puerta lateral. El candado quedó dado vuelta a propósito.
     *
     * La pidió el dueño el 06-08 («el lateral, ¿hay chance de ponerle puertas?») y la
     * mandó sacar el 07-08 («sacame la puerta de la caja que no queda bien»). El
     * motivo se ve en el lienzo: dibujada translúcida sobre una pared que ya deja ver
     * la carga, no se leía como puerta sino como una mancha sobre los bultos.
     *
     * Se vigila que no vuelva sola —es el tipo de detalle que alguien reintroduce
     * «para que se vea más real»— y que el costado NO quede liso: el detalle de la
     * pared lo siguen dando los nervios, que sí se quedan.
     */
    public function test_el_costado_no_lleva_puerta_pero_si_nervios(): void
    {
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringNotContainsString('puertaLateral', $js,
            'Volvió la puerta lateral: el dueño la mandó sacar el 07-08.');
        // La pared lleva nervios: de costado era una sábana lisa de punta a punta.
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
