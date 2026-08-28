<?php

namespace Tests\Feature\Carga;

use App\Http\Controllers\Admin\SimuladorCargaController;
use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use App\Services\Carga\CalculoDeCarga;
use App\Services\Carga\PalletSimulado;
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

    private function verMixta(array $lineas, array $extra = [])
    {
        return $this->actingAs($this->vendedor)->get(route('admin.carga.index', array_merge([
            'camion_id' => $this->hd35->id,
            'lineas' => $lineas,
        ], $extra)));
    }

    // ── El camión que sale a medio cargar (lote 5) ──────────────────────────

    /**
     * EL CAMIÓN NO SIEMPRE SALE VACÍO.
     *
     * Vuelve de un reparto con carga arriba, o se le suma un pedido a uno que ya está
     * armado. Hasta acá eso se simulaba a ojo eligiendo un camión más chico, que da un
     * número parecido por la razón equivocada.
     */
    public function test_lo_que_ya_lleva_le_come_piso_al_cupo(): void
    {
        // El HD35 mide 430 de largo. Con 200 cm tomados quedan 230 para lo nuevo.
        $vacio = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]]);
        $conCarga = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]], ['ocupado_cm' => 200]);

        $entranVacio = $vacio->viewData('mixta')['lineas'][0]['bultos_colocados'];
        $entranConCarga = $conCarga->viewData('mixta')['lineas'][0]['bultos_colocados'];

        $this->assertGreaterThan(0, $entranConCarga, 'Con 200 de 430 tomados todavía tiene que entrar algo.');
        $this->assertLessThan($entranVacio, $entranConCarga, 'El piso ocupado no le descontó nada al cupo.');
    }

    public function test_la_carga_nueva_arranca_donde_termina_la_que_ya_viaja(): void
    {
        // La carga vieja va contra la CABINA porque se subió primero. Si el motor
        // siguiera arrancando en x=0, dibujaría lo nuevo encima de lo que ya viaja.
        $escena = $this->verMixta(
            [['tipo' => $this->bolsa->id, 'cantidad' => 100]],
            ['ocupado_cm' => 200],
        )->viewData('escena');

        $this->assertGreaterThanOrEqual(2.0, $escena['bloques'][0]['x'],
            'El primer bloque se metió adentro del espacio que ya estaba ocupado.');
        // Y el visor recibe el metraje para dibujarlo en gris: sin eso, el hueco entre
        // la cabina y la carga se lee como un error del acomodo.
        $this->assertEquals(2, $escena['ocupado_m']);
    }

    public function test_los_kilos_que_ya_viajan_salen_del_tope_o_el_cartel_miente(): void
    {
        // EL punto de que los dos campos vayan juntos. El HD35 aguanta 1.400 kg; con
        // 1.200 ya arriba, un pedido de 300 kg SE PASA — y contra el tope entero
        // habría dado verde, que es el caso para el que se pidió el cartel (11-08).
        $p = $this->verMixta(
            // 60 bolsas de 5 kg = 300 kg.
            [['tipo' => $this->bolsa->id, 'cantidad' => 300]],
            ['ocupado_kg' => 1200],
        )->viewData('mixta')['peso'];

        $this->assertSame(200, $p['tope_kg'], 'El tope no descontó lo que ya viaja.');
        $this->assertSame(1400, $p['tope_chapa_kg'], 'Se perdió el tope de la chapa, que es lo que explica la resta.');
        $this->assertTrue($p['se_pasa'], 'Con 1.200 kg arriba y 300 más pedidos, el cartel tiene que saltar.');
    }

    public function test_la_pantalla_dice_lo_que_ya_llevaba_y_que_lo_descontó(): void
    {
        // Un cupo más chico sin decir por qué se lee como un error del cálculo.
        $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 100]], ['ocupado_cm' => 200, 'ocupado_kg' => 300])
            ->assertOk()
            ->assertSee('Ya lleva')
            ->assertSee('2,00 m · 300 kg')
            ->assertSee('descontado');
    }

    public function test_pedir_mas_piso_del_que_tiene_el_camion_se_recorta_y_no_revienta(): void
    {
        // El tope real depende de CUÁL camión es, así que no puede vivir en la
        // validación: cambiar de camión dejaría el formulario inválido de golpe.
        $res = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 10]], ['ocupado_cm' => 2000]);

        $res->assertOk();
        $this->assertSame(430, $res->viewData('mixta')['ocupado']['cm']);
        $this->assertTrue($res->viewData('mixta')['ocupado']['recortado']);
    }

    public function test_sin_declarar_nada_el_camion_sigue_saliendo_vacio(): void
    {
        // El caso normal no puede cambiar: los cupos verificados salen de acá.
        $mixta = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]])->viewData('mixta');

        $this->assertFalse($mixta['ocupado']['hay']);
        $this->assertSame(0, $mixta['ocupado']['cm']);
        $this->assertSame(1400, $mixta['peso']['tope_kg']);
        $this->assertSame(84, $mixta['lineas'][0]['bultos_colocados'], 'Se movió el cupo verificado del HD35.');
    }

    // ── Multi-drop LIFO (lote 6) ───────────────────────────────────────────

    /**
     * LO QUE BAJA PRIMERO SE CARGA ÚLTIMO.
     *
     * No es una preferencia de acomodo: es una restricción física. Si la mercadería de
     * la parada 3 viaja contra la puerta, en la parada 1 hay que bajarla a la vereda
     * para llegar a lo que sí se entrega ahí, volver a subirla, y repetirlo en cada
     * parada.
     */
    /**
     * Se mide el ORDEN DE COLOCACIÓN, no la coordenada x.
     *
     * Dos bloques angostos entran uno AL LADO del otro y los dos arrancan en x = 0, así
     * que comparar la x no dice nada. Lo que sí dice es quién se colocó primero: el
     * motor pone cada bloque en la región de menor x disponible, y los bloques de la
     * escena vienen ordenados fondo → puerta. El primero de la lista es el que quedó
     * más adentro, o sea el que se carga primero y se baja último.
     */
    private function ordenDeCarga($respuesta): array
    {
        return array_column($respuesta->viewData('escena')['bloques'], 'letra');
    }

    public function test_la_parada_1_queda_contra_la_puerta_y_la_ultima_contra_la_cabina(): void
    {
        $orden = $this->ordenDeCarga($this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 20, 'parada' => 1],
            ['tipo' => $this->bolsa->id, 'cantidad' => 40, 'parada' => 3],
        ]));

        $this->assertSame(['B', 'A'], $orden,
            'La parada 3 (B) se carga primero —queda al fondo— y la parada 1 (A) contra la puerta.');
    }

    public function test_la_parada_manda_sobre_el_orden_por_volumen(): void
    {
        // Sin paradas, la bolsa (130×26×51) es más voluminosa que la caja y va al
        // fondo. Con la caja en la parada 2, tiene que pasarle por delante: qué
        // producto va abajo se negocia, en qué orden se baja del camión no.
        $sinParadas = $this->ordenDeCarga($this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 20],
            ['tipo' => $this->bolsa->id, 'cantidad' => 40],
        ]));
        $this->assertSame(['B', 'A'], $sinParadas,
            'Sin paradas manda el volumen: la bolsa (B) se carga primero. Si esto cambia, la mitad de abajo no prueba nada.');

        // Ahora la caja baja DESPUÉS: tiene que pasarle por delante a la bolsa.
        $conParadas = $this->ordenDeCarga($this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 20, 'parada' => 2],
            ['tipo' => $this->bolsa->id, 'cantidad' => 40, 'parada' => 1],
        ]));
        $this->assertSame(['A', 'B'], $conParadas,
            'La caja (parada 2) tiene que cargarse primero aunque la bolsa sea más voluminosa.');
    }

    public function test_el_reparto_se_lista_en_orden_de_entrega(): void
    {
        // La lista la lee el CHOFER y él recorre las paradas en el orden en que
        // maneja: 1 primero. El orden de CARGA es el inverso y vive en el Excel.
        $mixta = $this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 20, 'parada' => 3],
            ['tipo' => $this->bolsa->id, 'cantidad' => 40, 'parada' => 1],
        ])->viewData('mixta');

        $this->assertSame([1, 3], array_column($mixta['paradas']['grupos'], 'parada'));
        $this->assertSame(0, $mixta['paradas']['sin_asignar']);
    }

    public function test_lo_que_no_tiene_parada_se_muestra_aparte_y_se_avisa(): void
    {
        // Sin declarar, viaja contra la puerta: sale en la PRIMERA entrega. Si iba a
        // otra, hay que decirlo — esconderlo dentro de la parada 1 sería mentir.
        $res = $this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 20, 'parada' => 1],
            ['tipo' => $this->bolsa->id, 'cantidad' => 40],
        ]);

        $paradas = $res->viewData('mixta')['paradas'];
        $this->assertSame([1, 0], array_column($paradas['grupos'], 'parada'), 'Lo no asignado va al final de la lista.');
        $this->assertSame(1, $paradas['sin_asignar']);
        $res->assertSee('sin parada asignada');
    }

    public function test_sin_paradas_no_aparece_la_seccion_ni_se_mueve_nada(): void
    {
        // El caso de siempre no puede cambiar: los cupos verificados salen de acá.
        $res = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]]);

        $this->assertNull($res->viewData('mixta')['paradas']);
        $res->assertDontSee('El reparto, parada por parada');
        $this->assertSame(84, $res->viewData('mixta')['lineas'][0]['bultos_colocados'],
            'Se movió el cupo verificado del HD35.');
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

        // Los dos datos siguen a la vista, en las dos superficies que quedaron al cerrar
        // el «todo en una pantalla» (21-08): el CUÁNTO en el cartel de arriba del dibujo,
        // con los números y no con la frase pelada, y el POR QUÉ en la fila de la carga
        // dentro del panel. La lista de abajo del camión —que decía las dos cosas otra
        // vez— se fue por duplicada, no por prescindible.
        $res->assertOk()
            ->assertSee('De tus 600 entran 420')
            ->assertSee('Quedan 180 afuera')
            ->assertSee('no queda espacio');
    }

    public function test_los_botellones_se_piden_en_unidades_y_se_redondea_a_la_bolsa(): void
    {
        // 198 botellones = 40 bolsas (la bolsa viaja completa). Se cargan las 40,
        // pero lo CARGADO se reporta capado a lo pedido: 198, no 200.
        $res = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 198]]);

        // El «sí» de un solo producto con cantidad se dice CON EL NÚMERO desde la fusión
        // (21-08): «Cabe todo ✓» es correcto y no contesta la pregunta que se hizo. Y el
        // número del cartel es lo PEDIDO —198— no las 200 de las 40 bolsas: decir 200
        // sería prometerle al cliente dos botellones que no encargó.
        $res->assertOk()->assertSee('Tus 198 entran');
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

    public function test_cada_bulto_lleva_su_linea_y_se_apaga_cuando_es_diminuto(): void
    {
        // Pedido del dueño (11-08): «las cajas bien marcadas con líneas negras, que se
        // entienda la separación; en la imagen se ven los espacios faltantes».
        //
        // Antes iban con el borde por defecto de `cuerpo()` —negro al 22%, pensado para
        // la chapa del camión— y una pared de 40 cajas del mismo color se leía como UN
        // bloque. Lo que se pierde con eso no es estética: es el HUECO, que es para lo
        // que se mira el dibujo.
        //
        // Candado de FUENTE porque el visor no tiene tests de JS. Las dos mitades van
        // juntas a propósito: sin el umbral, medido en el contenedor lleno, la línea se
        // comía el 17,9% del área de la carga y el dibujo se volvía una reja negra.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('const BORDE_BULTO', $js);
        $this->assertStringContainsString('borde: lado >= BORDE_MIN ? BORDE_BULTO : null', $js,
            'Los bultos dejaron de llevar su línea, o se la pusieron sin el umbral.');

        // El umbral se compara contra el LADO más largo proyectado y NO contra la
        // diagonal del cuerpo: la diagonal suma la profundidad, sobreestima el tamaño
        // aparente, y con ella pasaban cajas que en pantalla medían 13 px.
        $cuerpo = $this->cuerpoDeFuncion($js, 'rejillaDeBultos');
        $this->assertStringContainsString('Math.max(', $cuerpo);
        $this->assertStringNotContainsString('px + ba, py + bc, pz + bb', $cuerpo,
            'Volvió la diagonal del cuerpo como medida del tamaño aparente.');

        // Y la SEPARACIÓN sigue siendo la línea, no aire inventado: agrandar el hueco
        // entre cajas dibujaría un acomodo que el motor no calculó (§2 en versión visual).
        $this->assertStringContainsString('const SEPARACION = 0.985', $js,
            'Se agrandó el hueco entre bultos para «separar más»: eso miente sobre el acomodo.');
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
     * HAY UNA SOLA PESTAÑA DE CARGA, Y DICE QUE ACEPTA UNO O VARIOS.
     *
     * Nace de una pregunta del dueño (10-08): «¿y dónde agrego otro bulto?», estando en
     * «¿Cuánto entra?», que era de UN producto. El nombre decía la PREGUNTA pero no la
     * CAPACIDAD, así que desde ahí no había forma de saber que lo de varios productos
     * vivía en la pestaña de al lado. Era un candado de DESCUBRIBILIDAD: la función
     * existía y funcionaba, y el usuario no la encontraba.
     *
     * El 21-08 el dueño resolvió el problema de raíz —«hay que unificar los dos puntos…
     * la única diferencia que veo es un producto o varios y es lo mismo»— y con eso NO
     * QUEDA NADA QUE DESCUBRIR: no hay una segunda pestaña de carga a la que pasar. Así
     * que el candado cambia de forma y conserva su intención: lo que se vigila ahora es
     * que la pestaña única siga anunciando su capacidad, y que la segunda NO vuelva.
     * Reaparecer sería reabrir el problema del 10-08 con la fusión ya hecha.
     */
    public function test_hay_una_sola_pestana_de_carga_y_dice_que_acepta_uno_o_varios(): void
    {
        $html = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('uno o varios productos', $html,
            'La pestaña de carga dejó de anunciar su capacidad.');

        // Las tres pestañas y NADA MÁS. Se cuenta el `role="tab"`, que es lo que las hace
        // pestañas: buscar los nombres dejaría pasar una cuarta con otro rótulo.
        $this->assertSame(3, substr_count($html, 'role="tab"'), 'Cambió la cantidad de pestañas.');

        // Y la de un producto no volvió, ni con su nombre ni con su modo.
        $this->assertStringNotContainsString('¿Cuánto entra?', $html);
        $this->assertStringNotContainsString("modo = 'maximo'", $html);
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
        $calculo = new CalculoDeCarga;
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

        $this->assertSame(420, $this->unidadesQueEntran($sinPedir));
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

            $this->assertSame($unidades, $this->unidadesQueEntran($res));
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

        // El aviso se mudó a la fila DEL PANEL al cerrar el «todo en una pantalla»
        // (21-08): la lista de abajo del camión, donde vivía, se fue por duplicada. Lo que
        // dice es lo mismo y en menos palabras —el panel mide 224px—: los dos números y el
        // botón que sube el tope. Si alguna vez se cae, se cae con este candado, que es el
        // punto: los dos números seguían calculándose en la fila y ninguna vista los
        // mostraba, o sea un dato que parece cubierto y no se lee.
        $html = $conAire->getContent();
        // El «de 8 que caben» NO se puede asertar contiguo: el número va en su propio
        // `<span>` para llevar `tabular-nums`, así que en el HTML dice
        // `de <span …>8</span> que caben`. Se asertan las dos mitades que sí son contiguas.
        $this->assertStringContainsString('que caben', $html);
        // EL BOTÓN DICE LA ACCIÓN Y NO SOLO EL NÚMERO (dueño 25-08: «dale la opción entonces
        // de que indique lo que pasa»). Decía «Apilar 8», que no distingue entre apilar 8
        // MÁS, dejar 8 en total, o cambiar el tope sin recalcular.
        $this->assertStringContainsString('Subir el tope a 8 y recalcular', $html);
        $this->assertStringContainsString('apilarHasta(0, 8)', $html,
            'El botón dejó de escribir el tope en la línea: diría el número sin poder arreglarlo.');

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

    public function test_avisa_cuando_se_pasa_de_peso_aunque_sobre_espacio(): void
    {
        // Pedido del dueño (11-08): «que cuando se pase el límite de carga aparezca un
        // cartel de advertencia, aunque el camión no esté lleno completamente».
        //
        // El motor YA recortaba por kilos, así que el resultado nunca se pasaba y no había
        // nada que gritar: la pantalla mostraba 30% de ocupación, un renglón de peso
        // discreto y un «quedan N afuera». Con carga pesada eso se lee como que sobra
        // camión — y sobra, pero no sirve.
        //
        // Caja chica y MUY pesada: 40 × 40 × 40 a 300 kg. En el HD35 del fixture (1.400 kg) el espacio
        // daría para decenas y el peso deja 4.
        $plomo = TipoBulto::create([
            'nombre' => 'Caja de plomo', 'categoria' => 'cajas',
            'largo_cm' => 40, 'ancho_cm' => 40, 'alto_cm' => 40, 'peso_kg' => 300,
            'unidades' => 1, 'apilable_max' => 4, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);

        $r = $this->verMixta([['tipo' => $plomo->id, 'cantidad' => 20]])->assertOk();
        $peso = $r->viewData('mixta')['peso'];

        // El dato que no existía: cuánto pesa lo PEDIDO. Es lo único que dice de cuánto
        // te pasaste, porque lo cargado siempre entra.
        $this->assertSame(6000.0, $peso['pedido_kg'], '20 cajas de 300 kg.');
        $this->assertSame(1400, $peso['tope_kg'], 'El tope del fixture.');
        $this->assertTrue($peso['se_pasa']);
        $this->assertTrue($peso['recorto'], 'El motor recortó por kilos, no por espacio.');
        $this->assertLessThanOrEqual(1400.0, $peso['cargado_kg'], 'Nunca devuelve una carga pasada.');

        $html = $r->getContent();
        $this->assertStringContainsString('Se pasa de la carga máxima', $html);
        $this->assertStringContainsString('4.600 kg de más', $html);

        // Y el «aunque no esté lleno», que es el corazón del pedido: el cartel sale con el
        // camión casi vacío.
        $this->assertLessThan(
            0.25,
            $r->viewData('mixta')['resultado']['ocupacion'],
            'Si esto se llenara, el caso no probaría lo que dice probar.',
        );
    }

    public function test_sin_pasarse_de_peso_no_hay_cartel(): void
    {
        // Mutado del anterior: el cartel tiene que APAGARSE. Un aviso que está siempre
        // deja de leerse, y en este módulo el rojo significa «no lo podés llevar».
        $r = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 100, 'estiba' => 'costado'],
        ])->assertOk();

        $this->assertFalse($r->viewData('mixta')['peso']['se_pasa']);
        $this->assertFalse($r->viewData('mixta')['peso']['recorto']);
        $this->assertStringNotContainsString('Se pasa de la carga máxima', $r->getContent());
        $this->assertStringNotContainsString('Al filo de la carga máxima', $r->getContent());
    }

    public function test_el_cupo_maximo_dice_cuantos_entrarian_si_el_peso_no_cortara(): void
    {
        // En «¿Cuánto entra?» el número grande es lo único que se lee. «Entran 5» sin
        // decir que por espacio entrarían 55 se interpreta como que el camión es chico, y
        // la decisión que sale de ahí (mandar otro camión más grande) es la equivocada:
        // el problema son los kilos, y el camión grande también los tiene.
        $plomo = TipoBulto::create([
            'nombre' => 'Caja de plomo', 'categoria' => 'cajas',
            'largo_cm' => 40, 'ancho_cm' => 40, 'alto_cm' => 40, 'peso_kg' => 300,
            'unidades' => 1, 'apilable_max' => 4, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);

        $r = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $plomo->id,
        ]))->assertOk();

        // Desde la fusión (21-08) el número vive en la FILA de la carga (`por_espacio`) y
        // el aviso viaja PEGADO al veredicto, arriba del dibujo, en vez de en una tarjeta
        // propia debajo. La regla no cambió —el cupo cortado por kilos tiene que decir
        // cuántos entrarían si el peso no cortara— pero el lugar sí, y por eso el candado
        // se reescribe en vez de darse por perdido.
        $mixta = $r->viewData('mixta');
        $fila = $mixta['lineas'][array_key_first($mixta['lineas'])];

        $this->assertSame('peso', $fila['limita']);
        $this->assertSame(4, $fila['bultos_colocados'], '1.400 kg del fixture / 300 kg por caja.');
        $this->assertGreaterThan(
            $fila['bultos_colocados'],
            $fila['por_espacio'],
            'Por espacio entrarían muchas más.',
        );

        $html = $r->getContent();
        $this->assertStringContainsString('se llena de kilos antes', $html);
        $this->assertStringContainsString(
            'por espacio entrarían '.number_format($fila['por_espacio'], 0, ',', '.'),
            $html,
            'El número tiene que estar A LA VISTA: el aviso sin cifra no cambia ninguna decisión.',
        );
    }

    public function test_no_se_tumba_conserva_el_giro_de_90_que_de_pie_pierde(): void
    {
        // Pedido del dueño (11-08) mirando cómo EasyCargo deja declarar el giro de cada
        // bulto: para cubicar cajas de distintos tamaños hace falta poder decir «esta gira
        // en el piso pero no se acuesta».
        //
        // Es la diferencia con `pie`, y no es cosmética: una caja marcada «este lado
        // arriba» puesta a lo ancho puede entrar donde a lo largo no. Sin esta opción
        // había que elegir entre dos mentiras — libre (el motor la tumba y promete un
        // acomodo que nadie hace) o de pie (pierde el giro válido y el cupo sale bajo).
        //
        // Caja de 90 × 60 × 120 en el HD35 (430 × 200 × 220). Las tres respuestas son
        // distintas y se verifican a mano:
        //   de pie          4 × 3 de piso × 1 de alto (120 de 220)      = 12
        //   no se tumba     7 × 2 girada  × 1                           = 14
        //   automático      la acuesta: 3 × 2 × 3 capas de 60  = 18, + 2 en el sobrante = 20
        //
        // ESE «+ 2» ES NUEVO Y ES REAL. Desde la fusión (21-08) la pregunta se contesta
        // por `carga()` y no por `cupo()`: la rejilla de 18 deja 70 cm de largo libres al
        // fondo (3 × 120 = 360 de 430) y ahí el motor para dos cajas de canto (60 × 90 ×
        // 120, x=360 → 420 ≤ 430). `cupo()` nunca rellenaba el sobrante, así que este
        // camino no promete más de lo que puede: promete lo que DIBUJA, y los dos bloques
        // están en la escena. Los cupos de referencia del HD35 (420 de pie, 480 acostado)
        // no se mueven — los fija `CargaUnificadaTest`.
        $caja = TipoBulto::create([
            'nombre' => 'Caja alta de prueba', 'categoria' => 'cajas',
            'largo_cm' => 90, 'ancho_cm' => 60, 'alto_cm' => 120, 'peso_kg' => 1,
            'unidades' => 1, 'apilable_max' => 5, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);

        // La ORIENTACIÓN con que quedó puesta se lee del BLOQUE que dibuja el visor, no
        // de `cupo()`: desde la fusión (21-08) la pantalla contesta por el camino de
        // líneas, y el bloque es además el lugar más honesto para preguntarlo — es la
        // caja tal como se está dibujando. Los bultos salen de la fila de la carga.
        //
        // OJO CON LA UNIDAD: el visor trabaja en METROS (`escena()` divide por 100), así
        // que se convierte acá a centímetros enteros. Las tres alturas de abajo son las
        // del catálogo y se verificaron a mano en centímetros; compararlas contra 1.2
        // dejaría el candado ilegible al lado del comentario que lo justifica.
        $cupo = function (string $estiba) use ($caja) {
            $r = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $caja->id, 'estiba' => $estiba,
            ]))->assertOk();
            $mixta = $r->viewData('mixta');
            $fila = $mixta['lineas'][array_key_first($mixta['lineas'])];
            $o = $r->viewData('escena')['bloques'][0]['orientacion'];

            return [
                'orientacion' => array_map(fn (float $m) => (int) round($m * 100), $o),
                'bultos' => $fila['bultos_colocados'],
                'bloques' => $r->viewData('escena')['bloques'],
            ];
        };

        $dePie = $cupo('pie');
        $noSeTumba = $cupo('horizontal');
        $libre = $cupo('auto');

        // LA CAJA SIGUE PARADA en las dos primeras: 120 cm de alto en las dos. Es lo que
        // separa «no se tumba» de «automático», que sí la acuesta a 60.
        $this->assertSame(120, $dePie['orientacion']['alto']);
        $this->assertSame(120, $noSeTumba['orientacion']['alto'], 'No se tumba: sigue parada.');
        $this->assertSame(60, $libre['orientacion']['alto'], 'La libre sí la acuesta.');

        // Y las tres dan números distintos, en ese orden. Mutado por los dos lados: si
        // «no se tumba» se tratara como estiba forzada daría 12, y si se tratara como
        // automático daría 18.
        $this->assertSame(12, $dePie['bultos'], '4 × 3 de piso × 1 de alto.');
        $this->assertSame(14, $noSeTumba['bultos'], 'Girada 90°: 7 × 2 de piso × 1 de alto.');
        $this->assertSame(20, $libre['bultos'], 'Acostada 3 × 2 × 3 capas, más 2 de canto en el sobrante.');
        // Y el bloque del sobrante EXISTE: sin este assert, un 20 salido de contar mal
        // sería indistinguible de un 20 salido de acomodar bien.
        $this->assertCount(2, $libre['bloques'], 'La rejilla acostada más el relleno de canto.');

        // Sigue siendo CONSERVADORA: nunca promete más que la libre. Es lo que la hace
        // segura de ofrecer.
        $this->assertLessThanOrEqual($libre['bultos'], $noSeTumba['bultos']);

        // Y el CONTRATO con el motor, aparte del número: «no se tumba» NO es una estiba
        // forzada. Se fija acá porque en el resultado no se nota —`rotacion` le gana a
        // `orientacion_fija` en el motor, así que marcarla como forzada sería inerte
        // hoy—, pero dejaría la bandera mintiendo para el próximo que la lea.
        $paraMotor = $caja->paraCalculo('horizontal');
        $this->assertSame('horizontal', $paraMotor['rotacion']);
        $this->assertFalse($paraMotor['orientacion_fija'], 'No es una estiba forzada.');
        $this->assertSame([90, 60, 120], [$paraMotor['largo'], $paraMotor['ancho'], $paraMotor['alto']]);
    }

    public function test_un_pallet_es_una_linea_mas_de_la_carga_mixta(): void
    {
        // Pedido del dueño (10-08): «si cargo botellones y tapas, también tengo que tener
        // la opción de poder cargar pallets, porque en la vida real cargamos a veces
        // pallets y de paso bidones o dispensadores… dame la chance de cargar cosas mixtas
        // sin sacarme de la interfaz». Antes «Sobre pallet» era un MODO que se comía el
        // camión entero y había que elegir uno de los dos.
        $r = $this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 2, 'pallet' => 'industrial', 'pallet_alto' => 180],
            ['tipo' => $this->bolsa->id, 'cantidad' => 100, 'estiba' => 'costado'],
        ])->assertOk();

        $lineas = $r->viewData('mixta')['lineas'];
        $this->assertCount(2, $lineas, 'Las dos conviven en la misma carga.');

        // La línea EN PALLET cuenta PALLETS, no cajas sueltas: «2 de 2», y las cajas de
        // arriba se informan aparte. Si el bulto viajara con sus 18 unidades, «cargadas 36
        // de 2» sería un número sin sentido.
        $this->assertSame(2, $lineas[0]['pedidas_unidades']);
        $this->assertSame(2, $lineas[0]['cargadas_unidades']);
        $this->assertNotNull($lineas[0]['pallet']);
        $this->assertSame('Industrial 120 × 100', $lineas[0]['pallet']['nombre']);
        $this->assertSame($this->caja->nombre, $lineas[0]['pallet']['producto']->nombre);

        // 18 cajas por pallet, a mano: la caja mide 46 × 37 × 42 y el pallet deja
        // 120 × 100 × 165 de útil (180 menos los 15 de tarima). La mejor de las seis
        // rotaciones es 37 × 46 × 42 → 3 × 2 × 3.
        $this->assertSame(18, $lineas[0]['pallet']['por_pallet']);

        // Y la línea suelta de al lado sigue siendo lo de siempre.
        $this->assertNull($lineas[1]['pallet']);
        $this->assertSame(100, $lineas[1]['cargadas_unidades']);

        // El peso del pallet ARMADO es la madera más su carga, y entra al cálculo: un
        // camión se puede llenar por kilos antes que por espacio. 25 kg de madera más
        // 18 cajas de 10 kg.
        $this->assertSame(205.0, $lineas[0]['pallet']['peso_armado_kg']);
    }

    public function test_el_pallet_de_la_carga_mixta_se_dibuja_como_pallet_y_no_como_un_cajon(): void
    {
        // El visor ya sabía dibujar tarima + carga (`forma: 'pallet'` con su `interior`).
        // Que la línea mixta emita el MISMO contrato es lo que evitó tocar el JS — y lo
        // que hace que el dibujo no pueda contradecir al cálculo, porque el interior sale
        // del cupo que se calculó y no de una rejilla aparte.
        $escena = $this->verMixta([
            ['tipo' => $this->caja->id, 'cantidad' => 2, 'pallet' => 'industrial'],
            ['tipo' => $this->bolsa->id, 'cantidad' => 50, 'estiba' => 'costado'],
        ])->assertOk()->viewData('escena');

        $porForma = collect($escena['bloques'])->keyBy('forma');
        $this->assertTrue($porForma->has('pallet'), 'El bloque del pallet se dibuja como pallet.');
        $this->assertTrue($porForma->has('botellones'), 'Y la bolsa suelta sigue siendo bidones.');

        $pallet = $porForma['pallet'];
        $this->assertSame(0.15, $pallet['base'], 'La tarima, en metros.');
        $this->assertGreaterThan(0, $pallet['interior']['cantidad'], 'Lleva carga encima.');
        $this->assertSame('caja', $pallet['interior']['forma']);
        // El interior se pinta del color del bloque: es el mismo producto.
        $this->assertSame($pallet['color'], $pallet['interior']['color']);
    }

    public function test_un_pallet_donde_no_entra_ni_una_caja_no_se_sube_vacio(): void
    {
        // §3.3.5: pasa de verdad —la bolsa de botellones mide 130 cm y el pallet 120— y un
        // «2 pallets» de tarimas vacías se leería como que la carga entra. El motor no
        // puede verlo solo: para él la línea pidió cero pallets y los colocó todos, así
        // que el veredicto se arma con las filas.
        //
        // El selector de la pantalla solo ofrece cajas, pero un link viejo o retocado
        // puede traer cualquier producto y la respuesta tiene que ser honesta igual.
        $r = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 2, 'pallet' => 'industrial'],
        ])->assertOk();

        $fila = $r->viewData('mixta')['lineas'][0];
        $this->assertSame(0, $fila['cargadas_unidades']);
        $this->assertSame('pallet_vacio', $fila['motivo']);
        $this->assertFalse($r->viewData('mixta')['cabeTodo'], 'No puede decir «cabe todo».');

        // Y NO hay tarimas vacías en el camión.
        $this->assertSame([], $r->viewData('escena')['bloques']);
        // El POR QUÉ se dice en la fila del panel, con el texto corto de `MOTIVOS_CORTOS`:
        // ahí la columna mide 224px y la versión larga («no entra ni una encima del
        // pallet», la de la lista que se fue al cerrar el «todo en una pantalla») no cabe
        // en una línea. Lo que el candado cuida es que el motivo se DIGA, no su redacción.
        $this->assertStringContainsString('no entra en el pallet', $r->getContent());
        // Y sin inventar un pedido: la línea pidió 2 pallets, así que dice cuántos quedaron
        // afuera. Un «Quedan 0 afuera» al lado del motivo se contradice solo.
        $this->assertStringContainsString('Quedan 2 afuera', $r->getContent());
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
        $calculo = new CalculoDeCarga;
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

    /**
     * Rebana el menú lateral, que es donde vive la lista de la carga. Mismo idioma que
     * el resto de los candados de este archivo: se ancla en el `aria-label` del
     * `<aside>` y corta en su cierre.
     */
    /**
     * CUÁNTAS UNIDADES ENTRARON, por el camino unificado.
     *
     * Desde el 21-08 la pantalla tiene UNA sola pregunta: un `tipo_bulto_id` sin líneas
     * —los links viejos y lo que mandaba la pestaña «¿Cuánto entra?»— se traduce a una
     * línea, así que el número ya no vive en `viewData('resultado')` (el `cupo()`) sino
     * en la fila de la carga.
     *
     * Los candados que preguntaban por el camino viejo se migraron a este helper en vez
     * de borrarse: cada uno fija una regla que la pantalla nueva tiene que seguir
     * cumpliendo, y apagarlos habría dejado la regla sin quien la vigile. `cupo()` sigue
     * existiendo y sigue teniendo sus propios candados —lo usa la comparativa entre
     * camiones—; lo que cambió es de dónde saca la pantalla su respuesta.
     */
    private function unidadesQueEntran($respuesta): int
    {
        $mixta = $respuesta->viewData('mixta');
        $this->assertNotNull($mixta, 'La pantalla no calculó ninguna carga.');

        return $mixta['lineas'][array_key_first($mixta['lineas'])]['cargadas_unidades'];
    }

    private function menuDelVisor(string $html): string
    {
        $desde = strpos($html, 'Herramientas');
        $this->assertNotFalse($desde, 'Ya no hay menú lateral en el visor.');

        return substr($html, $desde, strpos($html, '</aside>', $desde) - $desde);
    }

    public function test_el_panel_de_cubicaje_acompana_al_camion(): void
    {
        // El formato del panel izquierdo de EasyCargo, que el dueño pidió (06-08): por
        // producto, su letra, cuántas van y cuánto espacio ocupan, AL LADO del camión.
        // Repite el detalle de abajo a propósito — el valor es no levantar la vista del
        // dibujo para saber qué es cada bloque.
        //
        // El formato «200/200» de la primera versión ya no existe: desde el 21-08 la
        // fila dice «200 unidades» y agrega «de N» SOLO cuando difieren, porque repetir
        // el mismo número dos veces era ruido en una columna de 330px. La intención del
        // candado no cambió, y el número sigue teniendo que estar.
        $html = $this->verMixta([
            ['tipo' => $this->bolsa->id, 'cantidad' => 200],
            ['tipo' => $this->caja->id, 'cantidad' => 20],
        ])->assertOk()->getContent();

        $panel = $this->menuDelVisor($html);

        // Cada producto, con su nombre y su cantidad cargada.
        $this->assertStringContainsString($this->bolsa->nombre, $panel);
        $this->assertStringContainsString($this->caja->nombre, $panel);
        $this->assertStringContainsString('>200</span>', $panel, 'La cantidad cargada no está en el panel.');
        $this->assertStringContainsString('>20</span>', $panel);

        // Y el espacio que ocupa cada uno, que es lo que el dueño pidió el 21-08.
        $this->assertMatchesRegularExpression('/\d+,\d m³/', $panel,
            'La fila no dice cuánto espacio ocupa.');

        // Entra todo, así que el panel NO puede decir que algo quedó afuera. Se assertea
        // el TEXTO y no la clase `text-red-600`: esa cadena también vive dentro del
        // `hover:text-red-600` del botón de quitar, así que el assert negativo pasaría
        // por la razón equivocada (la trampa del substring de la bitácora [2026-07-30]).
        $this->assertStringNotContainsString('afuera', $panel,
            'Entra toda la carga y el panel dice que quedó algo afuera.');
    }

    public function test_el_panel_de_cubicaje_marca_en_rojo_lo_que_no_entra(): void
    {
        // 600 botellones son 120 bolsas y el HD35 admite 84: tiene que avisar sin que
        // haya que leer el detalle de abajo. Y desde el 21-08 dice además POR QUÉ —
        // antes era un punto rojo y el motivo había que ir a buscarlo.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]])
            ->assertOk()->getContent();

        $panel = $this->menuDelVisor($html);

        $this->assertStringContainsString('>420</span>', $panel, 'No dice cuántas entraron.');
        $this->assertStringContainsString('de 600', $panel, 'No dice de cuántas eran.');
        $this->assertStringContainsString('Quedan 180 afuera', $panel, 'No dice cuántas quedaron afuera.');
        $this->assertStringContainsString('text-red-600', $panel, 'Se perdió la señal roja.');
        // El motivo, en palabras y desde la única fuente.
        $this->assertStringContainsString(
            SimuladorCargaController::MOTIVOS_CORTOS['espacio'], $panel,
            'El panel no dice por qué quedó carga afuera.',
        );
    }

    public function test_toda_silueta_declarada_tiene_su_rama_en_el_visor_y_sus_ejes(): void
    {
        // Una silueta se declara en TRES lugares que tienen que coincidir: la constante
        // del modelo, el mapa de ejes del controlador y la rama del visor. Si falta la
        // del visor no pasa nada visible —el bloque cae en la cabina genérica— y el
        // camión se dibuja como cualquier otro, que es justo lo que el dueño pidió
        // arreglar el 05-08 («que se vean más reales y no cuadrados»).
        //
        // El candado del seeder ya exige que la silueta sembrada esté en la constante;
        // este exige que la constante esté CABLEADA. Se agregó al estrenar `camion_nqr`
        // (11-08), que es cuando se vio que los tres lugares podían separarse en silencio.
        $js = file_get_contents(resource_path('js/carga3d.js'));
        $ejes = (new \ReflectionClass(SimuladorCargaController::class))
            ->getReflectionConstant('EJES_POR_SILUETA')->getValue();

        foreach (array_keys(CamionSimulacion::SILUETAS) as $silueta) {
            // `camion` es la genérica y NO tiene rama propia a propósito: es el `else`
            // del despacho, el respaldo para un camión sin silueta o sin fotos todavía.
            // Exigirle una la volvería una silueta más y dejaría al visor sin default.
            if ($silueta !== 'camion') {
                $this->assertStringContainsString(
                    "veh.silueta === '{$silueta}'", $js,
                    "El visor no tiene rama para la silueta «{$silueta}»: se dibujaría como la genérica.",
                );
            }
            $this->assertArrayHasKey(
                $silueta, $ejes,
                "La silueta «{$silueta}» no declara cuántos ejes dibuja.",
            );
        }
    }

    public function test_las_cabinas_propias_llevan_los_detalles_del_costado(): void
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

        foreach (['cabinaHino', 'cabinaLiviana', 'cabinaTracto', 'cabinaNqr'] as $cabina) {
            $cuerpo = $this->cuerpoDeFuncion($js, $cabina);
            $this->assertStringContainsString(
                'costadoDeCabina(', $cuerpo,
                "La cabina [{$cabina}] no dibuja los detalles del costado.",
            );
        }

        // La VISERA no la llevan todas, y es a propósito: el tracto ya tiene el deflector
        // rompiendo el plano, y el NQR de las fotos tiene el techo liso. Inventársela
        // sería agregar algo que las fotos no muestran — el mismo criterio que sacó la
        // puerta lateral del furgón (§4.1septies-bis).
        foreach (['cabinaTracto', 'cabinaNqr'] as $sinVisera) {
            $this->assertStringNotContainsString(
                'visera(', $this->cuerpoDeFuncion($js, $sinVisera),
                "La cabina [{$sinVisera}] no debería llevar visera: sus fotos no la muestran.",
            );
        }
    }

    /**
     * EL `x-data` DE LA PANTALLA TIENE QUE SEGUIR SIENDO EVALUABLE.
     *
     * Este componente es el estado de toda la pantalla —`modo`, `lineas`, `sucio`— y su
     * `x-data` es un objeto de 150 líneas escrito dentro de un ATRIBUTO HTML, comentarios
     * incluidos. Cuando algo lo corta, no falla una parte: **no evalúa nada**, la pantalla
     * queda con todos los controles muertos y la consola se llena de
     * `ReferenceError: <prop> is not defined` — un binding por cada descendiente.
     *
     * Y LA SUITE NO LO VE. Ningún test de PHP evalúa Alpine, así que este defecto convive
     * con 280 candados en verde. La bitácora lo tiene documentado dos veces —las comillas
     * dobles [2026-08-10] y ahora el cierre de comentario de bloque, que se colocó al
     * intentar EXPLICAR el gotcha dentro del propio comentario— y en las dos no había
     * candado: solo una advertencia que hay que acordarse de leer.
     *
     * Esto no reemplaza evaluar Alpine, que sería lo correcto y no está en el presupuesto
     * de esta pantalla. Cubre las dos formas concretas que ya ocurrieron, que es mucho más
     * que cero.
     */
    public function test_el_x_data_de_la_pantalla_no_se_corta_a_si_mismo(): void
    {
        $html = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk()->getContent();

        // Se recogen TODOS los `x-data` del documento por su delimitador real —`"` a `"`,
        // que es exactamente como los parte el navegador— y se elige el de esta pantalla
        // por su contenido. Elegirlo por posición no sirve: la página trae varios (el shell,
        // la campanita, el visor) y el primero es `{ menuAbierto: false }`.
        //
        // El recorte por comillas ES el mecanismo del candado: si dentro del atributo hay
        // una comilla doble, el navegador corta ahí y el pedazo que queda es corto — el
        // mismo corte que deja Alpine sin componente.
        preg_match_all('/x-data="([^"]*)"/', $html, $todos);
        $xdata = '';
        foreach ($todos[1] as $candidato) {
            if (str_contains($candidato, 'modo:')) {
                $xdata = $candidato;
                break;
            }
        }
        $this->assertNotSame('', $xdata,
            'No se encontró el x-data de la pantalla, o se cortó ANTES de `modo:` — que ya es el defecto.');

        // Los dos assert que siguen se reparten los dos defectos, pero NO uno cada uno:
        // dónde cae una comilla doble decide cuál de los dos salta (temprano corta el
        // atributo y se nota en el largo; tarde deja un comentario sin cerrar). Por eso los
        // dos mensajes nombran las dos causas — un mensaje que apunte a la equivocada manda
        // a buscar el defecto al otro lado del archivo.
        $sospechas = 'Causas conocidas, en orden: (1) una COMILLA DOBLE dentro del x-data '
            .'—ni en los comentarios— bitácora [2026-08-10]; (2) un cierre de comentario de '
            .'bloque suelto, que aparece al intentar EXPLICAR ese gotcha dentro del comentario. '
            .'En los dos casos Alpine no evalúa el componente y TODA la pantalla queda muerta.';

        $this->assertGreaterThan(3000, strlen($xdata), 'El x-data se cortó. '.$sospechas);

        $this->assertSame(
            substr_count($xdata, '/*'),
            substr_count($xdata, '*/'),
            'Los comentarios del x-data no cierran parejo. '.$sospechas,
        );

        // Y la prueba de que el recorte de arriba está leyendo lo que dice leer: las tres
        // propiedades que sostienen la pantalla tienen que estar en el pedazo.
        foreach (['modo:', 'lineas:', 'sucio:'] as $prop) {
            $this->assertStringContainsString($prop, $xdata,
                "El recorte del x-data no contiene [{$prop}]: el candado está mirando otra cosa.");
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

        // Se ancla en el texto «Herramientas» y NO en el primer `<aside>`: el layout de la
        // app tiene su propio aside (el menú de navegación) y la rebanada caía ahí. Desde
        // la Cabina (21-08) el texto lo aporta el `aria-label` del propio <aside>, ANTES
        // del primer control — si alguien lo quita, toda esta familia de rebanadas muere.
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

        // El menú es la CABINA (dueño 21-08, opción D del canvas de propuestas —
        // §4.1nonies-ter): CERO desplegables. Lo visual vive como iconos en la
        // cabecera, el cuerpo es Cargar, y lo demás espera en dos HOJAS que abre el
        // pie («Compartir» y «Herramientas»). Esto supersede el «cada sección es un
        // desplegable» del 06-08: si un <details> reaparece en el menú, alguien está
        // deshaciendo la decisión sin enterarse.
        $this->assertSame(0, substr_count($menu, '<details'));
        // Se busca la forma `x-show="…"` COMPLETA y no la expresión suelta: el @click
        // del lanzador del pie también dice «hoja === 'compartir'», así que la
        // expresión suelta pasaba en verde con la hoja borrada (cazado por mutación).
        foreach (['compartir', 'herramientas'] as $hoja) {
            $this->assertStringContainsString('x-show="hoja === \''.$hoja.'\'"', $menu,
                "Falta la hoja [{$hoja}] del pie del menú.");
        }

        // Y el PALLET también se ofrece desde el menú (hoja «Herramientas»), con los
        // dos tipos estándar.
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

        // Desde la fusión (21-08) esto no es una capa encima del cupo: la cantidad ES la
        // de la línea, así que el motor coloca exactamente lo pedido y el dibujo son los
        // bloques que colocó. Se cuentan los BULTOS DIBUJADOS y no un `tope` que capaba el
        // dibujo después — el dibujo dejó de necesitar capa, que era el punto.
        $dibujados = fn ($r) => collect($r->viewData('escena')['bloques'])->sum('cantidad');
        $fila = fn ($r) => $r->viewData('mixta')['lineas'][array_key_first($r->viewData('mixta')['lineas'])];

        // 50 botellones = 10 bolsas: entran (el máximo es 420) y el dibujo muestra 10.
        $conPrueba = $ver(50);
        $this->assertTrue($conPrueba->viewData('mixta')['cabeTodo']);
        $this->assertSame(50, $fila($conPrueba)['cargadas_unidades']);
        $this->assertSame(10, $dibujados($conPrueba));
        $conPrueba->assertSee('Tus 50 entran');

        // 600 no entran: el veredicto dice cuántas sí, y el dibujo muestra el máximo.
        $sinCupo = $ver(600);
        $this->assertFalse($sinCupo->viewData('mixta')['cabeTodo']);
        $this->assertSame(420, $fila($sinCupo)['cargadas_unidades']);
        $this->assertSame(84, $dibujados($sinCupo));
        $sinCupo->assertSee('De tus 600 entran 420');

        // Sin cantidad la pregunta es la OTRA —«¿cuánto entra?»— y por eso no hay número
        // pedido contra el que comparar: la línea es abierta y el dibujo va al máximo.
        $sinPrueba = $ver(null);
        $this->assertNull($fila($sinPrueba)['pedidas_unidades']);
        $this->assertTrue($fila($sinPrueba)['abierta']);
        $this->assertSame(84, $dibujados($sinPrueba));
        $sinPrueba->assertSee('Entran 420');
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

        $this->assertSame(270, $this->unidadesQueEntran($conTope), '6 capas de 26 cm.');
        $this->assertSame(360, $this->unidadesQueEntran($sinTope), '8 capas llenan los 220 cm.');

        // Sin pedir nada manda el catálogo: es el dato que él dictó y con el que se
        // verificaron las referencias.
        $this->assertNull($ver(null)->viewData('apilado'));
        $this->assertSame(420, $this->unidadesQueEntran($ver(null)));
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
        $calculo = new CalculoDeCarga;
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
        $calculo = new CalculoDeCarga;
        $pallet = PalletSimulado::desdeFormulario('industrial', null, null, 180);
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
        $p = PalletSimulado::desdeFormulario('industrial', 9999, 0, 9999, 999);

        $this->assertSame(300, $p->largo_cm);
        $this->assertSame(100, $p->ancho_cm, 'Un 0 cae al estándar del tipo elegido.');
        $this->assertSame(PalletSimulado::ALTO_MAX, $p->alto_cm);
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

    /**
     * LOS TRES BOTONES DEL MOUSE (pedido del dueño 12-08-2026, con los controles de
     * EasyCargo en la mano: izquierdo gira, derecho recorre, rueda acerca).
     *
     * Girar y acercar ya estaban; faltaba el del medio. Lo que se vigila acá es lo que
     * se rompe en silencio si alguien «simplifica» el manejo del puntero:
     *
     *  · que el derecho DESPLACE y no gire — sin la distinción, el arrastre con el
     *    derecho giraría el camión y el usuario no sabría por qué;
     *  · que el desplazamiento viva APARTE del encuadre: girar con zoom 1 vuelve a
     *    medir CX/CY en cada frame, así que un pan guardado ahí se borra al primer
     *    grado de giro y parece que el visor «se resetea solo»;
     *  · que el menú del navegador no se abra encima del camión.
     */
    public function test_el_boton_derecho_desplaza_y_el_izquierdo_sigue_girando(): void
    {
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('const DESPLAZA = new Set([1, 2]);', $js,
            'Se perdió qué botones desplazan: el derecho (2) y el del medio (1).');
        $this->assertStringContainsString('mueve: DESPLAZA.has(e.button)', $js,
            'El arrastre ya no distingue el botón, así que el derecho volvería a girar.');
        $this->assertStringContainsString("canvas.addEventListener('contextmenu'", $js,
            'Sin frenar el menú contextual, el botón derecho abre el menú del navegador sobre el camión.');

        // El pan se SUMA al encuadre, no vive adentro. Es la línea que evita que girar
        // un grado borre el desplazamiento que el usuario acaba de hacer.
        $this->assertStringContainsString('CX = AW / 2 - centro[0] * ESC + pan[0];', $js);
        $this->assertStringContainsString('CY = AH / 2 - centro[1] * ESC + pan[1];', $js);

        // Y una vista fija lo limpia: «Planta» tiene que mostrar el camión entero, no
        // el rincón al que lo habían corrido.
        $this->assertMatchesRegularExpression(
            '/function vista\(clave\) \{[\s\S]{0,900}?pan = \[0, 0\];/',
            $js,
            'Las vistas fijas dejaron de limpiar el desplazamiento.',
        );
    }

    public function test_el_desplazamiento_tiene_tope_para_no_perder_el_camion(): void
    {
        // Sin tope, un arrastre largo deja el lienzo en blanco y la única salida es
        // «Reiniciar». El tope crece con el zoom: a zoom 1 no se puede perder el
        // camión, y acercado hay cancha para recorrer la carga de punta a punta.
        $js = file_get_contents(resource_path('js/carga3d.js'));

        $this->assertStringContainsString('const tope = [AW * 0.6 * zoom, AH * 0.6 * zoom];', $js,
            'El desplazamiento perdió su tope (o dejó de crecer con el zoom).');
    }

    public function test_la_pantalla_dice_que_el_boton_derecho_mueve(): void
    {
        // Girar se descubre arrastrando y la rueda es un reflejo; apretar el botón
        // derecho sobre un dibujo no se le ocurre a nadie si la pantalla no lo dice.
        $this->verMixta([['tipo' => $this->caja->id, 'cantidad' => 10]])
            ->assertOk()
            ->assertSee('botón derecho para mover');
    }

    /**
     * EL VEREDICTO Y LA FICHA DEL CAMIÓN VAN ARRIBA DEL DIBUJO (pedido del dueño
     * 12-08-2026, dibujado sobre la pantalla).
     *
     * El «No cabe todo» vivía en una tarjeta DEBAJO del visor: había que mirar el
     * camión, bajar, y recién ahí enterarse de que no entraba. La ficha del camión
     * estaba al pie del recuadro, o sea después del tablero de acomodo — a dos
     * pantallazos del dibujo que describe.
     *
     * Se mide por POSICIÓN en el HTML contra el `<canvas>`, que es lo que de verdad se
     * pidió: un `assertSee` seguiría verde con el cartel de vuelta abajo.
     */
    private function posiciones(string $html): array
    {
        $lienzo = strpos($html, '<canvas id="carga3d"');
        $this->assertNotFalse($lienzo, 'No está el lienzo del visor.');

        return [
            'lienzo' => $lienzo,
            'veredicto' => strpos($html, 'No cabe todo'),
            'ficha' => strpos($html, 'Medidas útiles'),
        ];
    }

    public function test_el_cartel_de_no_cabe_todo_va_arriba_del_lienzo(): void
    {
        // 600 botellones son 120 bolsas y en el HD35 entran 84.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]])
            ->assertOk()->assertSee('No cabe todo')->getContent();

        $p = $this->posiciones($html);
        $this->assertNotFalse($p['veredicto']);
        $this->assertLessThan($p['lienzo'], $p['veredicto'],
            'El cartel de «No cabe todo» volvió a quedar debajo del dibujo.');
    }

    public function test_la_ficha_del_camion_va_arriba_del_lienzo(): void
    {
        $html = $this->verMixta([['tipo' => $this->caja->id, 'cantidad' => 10]])
            ->assertOk()->getContent();

        $p = $this->posiciones($html);
        $this->assertNotFalse($p['ficha'], 'Se perdió la ficha del camión del visor.');
        $this->assertLessThan($p['lienzo'], $p['ficha'],
            'La ficha del camión volvió al pie del recuadro.');
        // Y sigue trayendo los cuatro datos que se pidieron.
        foreach (['Medidas útiles', 'Volumen', 'Carga máxima', 'Piso libre en la puerta'] as $dato) {
            $this->assertStringContainsString($dato, $html);
        }
    }

    public function test_el_veredicto_se_dice_una_sola_vez(): void
    {
        // Antes estaba arriba y abajo a la vez mientras se movía. Decir dos veces lo
        // mismo en la misma pantalla es justo el exceso de texto que el dueño pidió
        // recortar el 10-08.
        $html = $this->verMixta([['tipo' => $this->bolsa->id, 'cantidad' => 600]])->getContent();

        $this->assertSame(1, substr_count($html, 'No cabe todo'));
    }

    public function test_en_cuanto_entra_el_cartel_lleva_los_numeros_y_no_la_frase_pelada(): void
    {
        // La pregunta fue «¿me entran 500?»: la respuesta útil es cuántos entran y
        // cuántos quedan, no un «no cabe todo» que obliga a bajar a buscar el número.
        $res = $this->actingAs($this->vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->hd35->id,
            'tipo_bulto_id' => $this->bolsa->id,
            'cantidad' => 500,
        ]));

        $res->assertOk()
            ->assertSee('No cabe todo')
            ->assertSee('De tus 500 entran 420. Quedan 80 afuera.');

        $p = $this->posiciones($res->getContent());
        $this->assertLessThan($p['lienzo'], $p['veredicto']);
        $this->assertSame(1, substr_count($res->getContent(), 'No cabe todo'));
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
