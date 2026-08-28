<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Vehiculo;
use App\Support\MenuPrincipal;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo LOGÍSTICA · flota de vehículos (pedido del dueño 04-08-2026).
 *
 * Lo que fijan estos candados es lo que la planilla «Control vehiculos» NO podía
 * garantizar:
 *  - el ESTADO del vehículo está separado del conductor (en el Excel "PERDIDA
 *    TOTAL" y "VENTA FEBRERO 2023" vivían en la columna del chofer),
 *  - sacar un vehículo de la flota EXIGE decir por qué,
 *  - el semáforo de vencimientos se calcula (nadie pinta celdas),
 *  - ver y editar son permisos distintos.
 */
class VehiculoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefeLogistica(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_logistica');
    }

    /** Alguien que solo CONSULTA la flota: el caso de cobranzas a futuro. */
    private function soloLectura(): User
    {
        return tap(User::factory()->create())->givePermissionTo('ver vehiculos');
    }

    private function datosValidos(array $extra = []): array
    {
        return array_merge([
            'ppu' => 'PFBS22',
            'alias' => 'Hino 500',
            'tipo' => 'camion',
            'estado' => Vehiculo::ESTADO_ACTIVO,
        ], $extra);
    }

    // --- Acceso -----------------------------------------------------------

    public function test_sin_permiso_no_se_ve_la_flota(): void
    {
        $ajeno = User::factory()->create();

        // GET a una pantalla: el handler de 403 devuelve al Inicio con aviso
        // (decisión D-014), no una pantalla de error.
        $this->actingAs($ajeno)->get(route('admin.vehiculos.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_quien_solo_ve_no_puede_crear_ni_editar(): void
    {
        $lector = $this->soloLectura();
        $vehiculo = Vehiculo::factory()->create();

        $this->actingAs($lector)->get(route('admin.vehiculos.index'))->assertOk();
        $this->actingAs($lector)->get(route('admin.vehiculos.show', $vehiculo))->assertOk();

        // El formulario de creación es navegación (GET) → redirige al Inicio.
        $this->actingAs($lector)->get(route('admin.vehiculos.create'))
            ->assertRedirect(route('dashboard'));

        // Un POST/PUT sin permiso es 403 pelado: el redirect amable es solo para
        // navegación (misma regla que el traslado, decisión D-014).
        $this->actingAs($lector)->post(route('admin.vehiculos.store'), $this->datosValidos())
            ->assertForbidden();
        $this->actingAs($lector)->put(route('admin.vehiculos.update', $vehiculo), $this->datosValidos())
            ->assertForbidden();
        $this->actingAs($lector)->delete(route('admin.vehiculos.destroy', $vehiculo))
            ->assertForbidden();
    }

    public function test_el_jefe_de_logistica_administra_la_flota(): void
    {
        $this->actingAs($this->jefeLogistica())
            ->post(route('admin.vehiculos.store'), $this->datosValidos())
            ->assertRedirect();

        $this->assertDatabaseHas('vehiculos', ['ppu' => 'PFBS22', 'tipo' => 'camion']);
    }

    // --- Creación y normalización ------------------------------------------

    public function test_la_patente_se_guarda_en_mayusculas_y_sin_espacios(): void
    {
        // En la planilla convivían "TJGW-15" y "TJGW15": si no se normaliza, el
        // unique no sirve y entran dos filas del mismo camión.
        $this->actingAs($this->jefeLogistica())
            ->post(route('admin.vehiculos.store'), $this->datosValidos(['ppu' => ' pfbs 22 ']));

        $this->assertDatabaseHas('vehiculos', ['ppu' => 'PFBS22']);
    }

    public function test_no_se_puede_repetir_la_patente(): void
    {
        Vehiculo::factory()->create(['ppu' => 'PFBS22']);

        $this->actingAs($this->jefeLogistica())
            ->post(route('admin.vehiculos.store'), $this->datosValidos())
            ->assertSessionHasErrors('ppu');

        $this->assertSame(1, Vehiculo::where('ppu', 'PFBS22')->count());
    }

    // --- Estado separado del conductor -------------------------------------

    public function test_sacar_un_vehiculo_de_la_flota_exige_decir_por_que(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $this->actingAs($this->jefeLogistica())
            ->put(route('admin.vehiculos.update', $vehiculo), $this->datosValidos([
                'ppu' => $vehiculo->ppu,
                'estado' => Vehiculo::ESTADO_VENDIDO,
                'baja_motivo' => '',
            ]))
            ->assertSessionHasErrors('baja_motivo');

        $this->assertSame(Vehiculo::ESTADO_ACTIVO, $vehiculo->fresh()->estado);
    }

    public function test_reincorporar_un_vehiculo_limpia_los_datos_de_la_baja(): void
    {
        // Sin esto, un vehículo que vuelve arrastraría para siempre un
        // "Venta febrero 2023" en su ficha.
        $vehiculo = Vehiculo::factory()->create([
            'estado' => Vehiculo::ESTADO_VENDIDO,
            'baja_motivo' => 'Venta febrero 2023',
            'baja_at' => '2023-02-15',
        ]);

        $this->actingAs($this->jefeLogistica())
            ->put(route('admin.vehiculos.update', $vehiculo), $this->datosValidos([
                'ppu' => $vehiculo->ppu,
                'estado' => Vehiculo::ESTADO_ACTIVO,
            ]));

        $vehiculo->refresh();
        $this->assertSame(Vehiculo::ESTADO_ACTIVO, $vehiculo->estado);
        $this->assertNull($vehiculo->baja_motivo);
        $this->assertNull($vehiculo->baja_at);
    }

    // --- Semáforo de documentos --------------------------------------------

    public function test_el_semaforo_toma_el_peor_documento(): void
    {
        $vehiculo = Vehiculo::factory()->alDia()->create([
            'soap_vence' => now()->subDay()->toDateString(),
        ]);

        // Cuatro al día y uno vencido: el vehículo está vencido. El semáforo no
        // promedia — con el SOAP vencido no puede circular.
        $this->assertSame(Vehiculo::DOC_VENCIDO, $vehiculo->estado_documental);
    }

    public function test_por_vencer_es_treinta_dias_y_no_uno_mas(): void
    {
        $justo = Vehiculo::factory()->alDia()->create([
            'soap_vence' => now()->addDays(Vehiculo::DIAS_AVISO)->toDateString(),
        ]);
        $afuera = Vehiculo::factory()->alDia()->create([
            'soap_vence' => now()->addDays(Vehiculo::DIAS_AVISO + 1)->toDateString(),
        ]);

        $this->assertSame(Vehiculo::DOC_POR_VENCER, $justo->estado_documental);
        $this->assertSame(Vehiculo::DOC_AL_DIA, $afuera->estado_documental);
    }

    public function test_un_vehiculo_fuera_de_la_flota_no_contamina_el_semaforo(): void
    {
        // Un permiso de circulación vencido en un camión vendido en 2022 no es
        // un problema de nadie: no puede aparecer como pendiente.
        $vendido = Vehiculo::factory()->create([
            'estado' => Vehiculo::ESTADO_VENDIDO,
            'baja_motivo' => 'Venta febrero 2022',
            'permiso_circulacion_vence' => '2022-03-31',
        ]);

        $this->assertSame(Vehiculo::DOC_NO_APLICA, $vendido->estado_documental);
    }

    public function test_el_semirremolque_no_rinde_emisiones(): void
    {
        // En la planilla esto está escrito a mano como "NO APLICA" en la celda.
        // Modelado como regla, nadie lo persigue como dato faltante.
        $semi = Vehiculo::factory()->alDia()->create(['tipo' => 'semirremolque', 'emisiones_vence' => null]);
        $camion = Vehiculo::factory()->alDia()->create(['tipo' => 'camion', 'emisiones_vence' => null]);

        $this->assertFalse($semi->documentoAplica('emisiones_vence'));
        $this->assertSame(Vehiculo::DOC_AL_DIA, $semi->estado_documental);

        // Al camión SÍ le falta el dato: no se lo puede tapar.
        $this->assertTrue($camion->documentoAplica('emisiones_vence'));
        $this->assertSame(Vehiculo::DOC_SIN_REGISTRO, $camion->estado_documental);
    }

    public function test_el_plazo_se_dice_en_palabras(): void
    {
        // Un número pelado ("-3") obliga a interpretar; el aviso y la lista
        // tienen que decir qué significa.
        $this->assertSame('Vence hoy', Vehiculo::plazoLabel(0));
        $this->assertSame('Vence mañana', Vehiculo::plazoLabel(1));
        $this->assertSame('Vence en 12 días', Vehiculo::plazoLabel(12));
        $this->assertSame('Venció ayer', Vehiculo::plazoLabel(-1));
        $this->assertSame('Venció hace 3 días', Vehiculo::plazoLabel(-3));
        $this->assertSame('Sin registrar', Vehiculo::plazoLabel(null));
    }

    public function test_los_colores_respetan_la_paleta_de_la_app(): void
    {
        // Los tres colores del Excel (rojo/amarillo/verde) se traducen a la
        // paleta ESTRICTA de 4: rojo solo para lo negativo, naranjo de marca
        // para lo que requiere acción, neutro para lo que está en reposo. Si
        // alguien devuelve 'success'/'warning', <x-badge> los ignora en
        // silencio y el estado pierde su significado.
        $this->assertSame('danger', Vehiculo::variante(Vehiculo::DOC_VENCIDO));
        $this->assertSame('brand', Vehiculo::variante(Vehiculo::DOC_POR_VENCER));
        $this->assertSame('neutral', Vehiculo::variante(Vehiculo::DOC_AL_DIA));
        $this->assertSame('neutral', Vehiculo::variante(Vehiculo::DOC_SIN_REGISTRO));
    }

    // --- Listado ------------------------------------------------------------

    public function test_el_listado_dice_que_documento_esta_mal_sin_abrir_la_ficha(): void
    {
        Vehiculo::factory()->alDia()->create([
            'ppu' => 'RVBD32',
            'alias' => 'HD35 Concepción',
            'soap_vence' => now()->subDays(2)->toDateString(),
        ]);

        $this->actingAs($this->jefeLogistica())
            ->get(route('admin.vehiculos.index'))
            ->assertOk()
            ->assertSee('RVBD32')
            ->assertSee('SOAP')
            ->assertSee('venció hace 2 días');
    }

    public function test_el_resumen_cuenta_solo_la_flota_activa(): void
    {
        Vehiculo::factory()->alDia()->count(2)->create();
        Vehiculo::factory()->alDia()->create(['soap_vence' => now()->subDay()->toDateString()]);
        Vehiculo::factory()->create([
            'estado' => Vehiculo::ESTADO_VENDIDO,
            'baja_motivo' => 'Venta',
            'soap_vence' => '2022-01-01',
        ]);

        $respuesta = $this->actingAs($this->jefeLogistica())->get(route('admin.vehiculos.index'));

        $resumen = $respuesta->viewData('resumen');
        $this->assertSame(3, $resumen['total']);                        // el vendido no cuenta
        $this->assertSame(1, $resumen[Vehiculo::DOC_VENCIDO]);
        $this->assertSame(2, $resumen[Vehiculo::DOC_AL_DIA]);
    }

    public function test_el_filtro_de_vencidos_deja_solo_los_vencidos(): void
    {
        Vehiculo::factory()->alDia()->create(['ppu' => 'ALDIA11']);
        Vehiculo::factory()->alDia()->create(['ppu' => 'VENCE22', 'rt_vence' => now()->subDay()->toDateString()]);

        $this->actingAs($this->jefeLogistica())
            ->get(route('admin.vehiculos.index', ['doc' => Vehiculo::DOC_VENCIDO]))
            ->assertOk()
            ->assertSee('VENCE22')
            ->assertDontSee('ALDIA11');
    }

    public function test_la_busqueda_encuentra_la_patente_con_y_sin_guion(): void
    {
        Vehiculo::factory()->create(['ppu' => 'TJGW15', 'alias' => 'RAM Pedro']);

        foreach (['TJGW15', 'TJGW-15', 'tjgw15'] as $termino) {
            $this->assertSame(
                1,
                Vehiculo::buscar($termino)->count(),
                "La búsqueda '{$termino}' debería encontrar la patente TJGW15.",
            );
        }
    }

    public function test_la_busqueda_no_mira_el_alias_y_el_rotulo_no_lo_promete(): void
    {
        // Decisión del dueño (11-08-2026): se busca por patente, marca y conductor.
        // El alias quedó fuera A SABIENDAS de que es el nombre grande de cada fila,
        // así que el rótulo del buscador tampoco puede ofrecerlo: prometer de más
        // manda a escribir algo que no encuentra nada, y se lee como que el vehículo
        // no está cargado.
        Vehiculo::factory()->create([
            'ppu' => 'TJGW15', 'alias' => 'Ratoncito', 'marca' => 'RAM', 'conductor_nombre' => 'Pedro Soto',
        ]);

        $this->assertSame(0, Vehiculo::buscar('Ratoncito')->count());
        $this->assertSame(1, Vehiculo::buscar('RAM')->count());
        $this->assertSame(1, Vehiculo::buscar('Pedro')->count());

        $this->actingAs($this->jefeLogistica())
            ->get(route('admin.vehiculos.index'))
            ->assertOk()
            ->assertDontSee('Buscar (patente, alias');
    }

    // --- Navegación ---------------------------------------------------------

    public function test_logistica_aparece_en_el_menu_de_quien_ve_la_flota(): void
    {
        $arbol = MenuPrincipal::para($this->soloLectura());

        $this->assertArrayHasKey('logistica', $arbol);
        $this->assertArrayHasKey('vehiculos', $arbol['logistica']['items']);
    }

    public function test_logistica_no_aparece_para_quien_no_tiene_el_permiso(): void
    {
        $this->assertArrayNotHasKey('logistica', MenuPrincipal::para(User::factory()->create()));
    }

    // --- Conductores dentro de Logística (pedido del dueño 04-08) -----------

    public function test_conductores_vive_en_logistica_y_no_en_servicio_tecnico(): void
    {
        $arbol = MenuPrincipal::para($this->jefeLogistica());

        $this->assertArrayHasKey('conductores', $arbol['logistica']['items']);
        $this->assertArrayNotHasKey(
            'conductores',
            $arbol['servicio-tecnico']['items'] ?? [],
            'Conductores no puede quedar duplicado: dos ítems con la misma ruta rompen el resaltado del menú.',
        );
    }

    public function test_el_jefe_de_logistica_puede_abrir_conductores_de_verdad(): void
    {
        // El par que importa: que el menú lo OFREZCA y que la ruta lo DEJE
        // entrar. Si el gate del ítem y el de la ruta se separan, el menú
        // muestra una pantalla que devuelve 403 (D-014).
        $this->actingAs($this->jefeLogistica())
            ->get(route('admin.conductores.index'))
            ->assertOk();
    }

    /**
     * EL TÉCNICO NO VE CONDUCTORES — candado INVERTIDO el 26-08-2026, y su versión anterior es
     * el ejemplo de por qué conviene invertirlos en vez de borrarlos.
     *
     * Decía, con este comentario: *«El catálogo alimenta el ingreso por lote y el traslado al
     * taller: si el técnico lo perdiera, el conductor que retira máquinas en ruta dejaría de
     * existir para él»*, y fijaba que el técnico SÍ tuviera el ítem y SÍ entrara a la pantalla.
     *
     * **La premisa era falsa.** Los dos selectores leen `Conductor::activos()` desde sus
     * propios controladores (`LoteServicioController`, `TrasladoServicioController`), cada uno
     * con su permiso, así que nunca pasaron por el gate de esta pantalla. Lo único que el
     * canAny `manage servicio tecnico|manage vehiculos` conseguía era que el permiso central
     * del TALLER abriera una pantalla de LOGÍSTICA — y con ella el ítem del menú, que es lo
     * que el dueño vio en el teléfono de un técnico el 26-08: *«no entiendo por qué ve
     * conductores, no recuerdo habilitar ese permiso»*.
     *
     * Lo que el candado viejo temía sigue cubierto, y por eso este cambio es seguro:
     * `ConductorTest::test_el_tecnico_sigue_pudiendo_elegir_un_chofer_en_el_ingreso_por_lote`.
     */
    public function test_el_tecnico_no_ve_conductores_porque_no_es_de_logistica(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');

        $arbol = MenuPrincipal::para($tecnico);

        // El módulo LOGÍSTICA no le aparece por este ítem. Se comprueba sin asumir que el
        // módulo exista para él: su visibilidad se deriva de los ítems que puede ver.
        $this->assertArrayNotHasKey('conductores', $arbol['logistica']['items'] ?? [],
            'El técnico volvió a ver Conductores: alguien reabrió el gate a un permiso del taller.');

        // Y el gate de la RUTA acompaña al del menú (D-014): si se separaran, el menú
        // ofrecería una pantalla que devuelve 403, o peor, la pantalla quedaría abierta sin
        // que el menú la muestre.
        $this->actingAs($tecnico)->get(route('admin.conductores.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_la_ficha_es_el_destino_de_la_fila(): void
    {
        $vehiculo = Vehiculo::factory()->alDia()->create(['ppu' => 'PLKC95', 'alias' => 'Actros']);

        $this->actingAs($this->jefeLogistica())
            ->get(route('admin.vehiculos.index'))
            ->assertOk()
            ->assertSee(route('admin.vehiculos.show', $vehiculo), false);
    }
}
