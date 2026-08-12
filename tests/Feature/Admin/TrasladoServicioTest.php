<?php

namespace Tests\Feature\Admin;

use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\TrasladoServicio;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Traslado de maquinas a reparar: sucursal -> casa matriz (pedido del dueño
 * 03-08-2026, «eliminar las excusas, todo transparente»).
 *
 * Lo que estos candados fijan es la CADENA DE CUSTODIA: que exista un emisor con
 * nombre, un receptor con nombre, y que una diferencia entre lo que salio y lo
 * que llego quede registrada como hecho y avisada — no como discusion.
 */
class TrasladoServicioTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $matriz;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->matriz = Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['nombre' => 'El Mirador', 'activa' => true, 'es_central' => true]);
        $this->sucursal = Sucursal::firstOrCreate(['codigo' => 'COQUIMBO'], ['nombre' => 'Coquimbo', 'activa' => true, 'es_central' => false]);
    }

    private function jefeSucursal(): User
    {
        return tap(User::factory()->create(['name' => 'Luis Figueroa']))->assignRole('jefe_sucursal');
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create(['name' => 'Fernando Rojas']))->assignRole('tecnico');
    }

    /** Orden viva en la sucursal que NO repara: la que tiene que viajar. */
    private function ordenEnSucursal(array $extra = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'estado' => 'recibido',
            'traslado_id' => null,
            'traslado_recibida_at' => null,
        ], $extra));
    }

    private function despachar(User $quien, array $ordenes, array $extra = [])
    {
        return $this->actingAs($quien)->post(route('admin.traslados.store'), array_merge([
            'ordenes' => collect($ordenes)->pluck('id')->all(),
            'emisor_nombre' => 'Luis Figueroa',
            'conductor' => 'Pedro Soto',
        ], $extra));
    }

    // --- Despacho ---

    public function test_el_jefe_de_sucursal_despacha_y_queda_como_emisor(): void
    {
        $orden = $this->ordenEnSucursal();

        $this->despachar($this->jefeSucursal(), [$orden])->assertRedirect(route('admin.traslados.index'));

        $traslado = TrasladoServicio::sole();
        $this->assertSame(TrasladoServicio::EN_TRANSITO, $traslado->estado);
        $this->assertSame('Luis Figueroa', $traslado->emisor_nombre);
        $this->assertSame('Pedro Soto', $traslado->conductor);
        $this->assertSame($this->sucursal->id, $traslado->sucursal_origen_id);
        $this->assertSame($this->matriz->id, $traslado->sucursal_destino_id);
        // El conteo se CONGELA al despachar: es la mitad que hace verificable una
        // diferencia posterior.
        $this->assertSame(1, $traslado->total_enviado);
        $this->assertStringStartsWith('TR-', $traslado->codigo);
        // Y la cuenta con la que se cargó queda aparte del nombre escrito.
        $this->assertNotNull($traslado->emisor_id);

        $this->assertSame($traslado->id, $orden->fresh()->traslado_id);
        $this->assertNull($orden->fresh()->traslado_recibida_at);
    }

    public function test_despachar_avisa_al_taller(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $tecnico = $this->tecnico();

        $this->despachar($this->jefeSucursal(), [$this->ordenEnSucursal()]);

        $aviso = Notificacion::where('evento', 'traslado.despachado')
            ->where('user_id', $tecnico->id)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->first();

        $this->assertNotNull($aviso, 'El técnico no recibió el aviso de que vienen máquinas.');
        $this->assertStringContainsString('Coquimbo', $aviso->cuerpo);
        $this->assertStringContainsString('Luis Figueroa', $aviso->cuerpo);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $aviso->titulo.' '.$aviso->cuerpo);
    }

    /**
     * Maquinas de DOS sucursales en un mismo envio -> DOS traslados. El
     * responsable de una entrega es de UNA sucursal; mezclarlos dejaria un
     * emisor respondiendo por algo que no entrego.
     */
    public function test_maquinas_de_dos_sucursales_generan_dos_traslados(): void
    {
        $otra = Sucursal::firstOrCreate(['codigo' => 'ABATE'], ['nombre' => 'Abate Molina', 'activa' => true, 'es_central' => false]);
        $a = $this->ordenEnSucursal();
        $b = $this->ordenEnSucursal(['sucursal_id' => $otra->id]);

        $this->despachar($this->jefeSucursal(), [$a, $b]);

        $this->assertSame(2, TrasladoServicio::count());
        $this->assertNotSame($a->fresh()->traslado_id, $b->fresh()->traslado_id);
    }

    /** No se despacha lo que ya viajó (aunque llegue el id en el formulario). */
    public function test_no_se_puede_despachar_dos_veces_la_misma_maquina(): void
    {
        $orden = $this->ordenEnSucursal();
        $jefe = $this->jefeSucursal();

        $this->despachar($jefe, [$orden]);
        $this->despachar($jefe, [$orden]);

        $this->assertSame(1, TrasladoServicio::count(), 'Se creó un segundo traslado para una máquina ya despachada.');
    }

    /** La máquina recibida en la casa matriz no viaja: no aparece para despachar. */
    public function test_las_maquinas_de_la_casa_matriz_no_se_despachan(): void
    {
        $enMatriz = OrdenServicio::factory()->create(['sucursal_id' => $this->matriz->id, 'estado' => 'recibido']);

        $this->actingAs($this->jefeSucursal())->get(route('admin.traslados.create'))
            ->assertOk()
            ->assertDontSee($enMatriz->folio);
    }

    // --- Recepción ---

    public function test_recibir_completo_habilita_la_reparacion_y_avisa_a_la_sucursal(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $orden = $this->ordenEnSucursal();
        $jefe = $this->jefeSucursal();
        $this->despachar($jefe, [$orden]);
        $traslado = TrasladoServicio::sole();

        $this->actingAs($this->tecnico())
            ->put(route('admin.traslados.recibir', $traslado), [
                'recibidas' => [$orden->id],
                'receptor_nombre' => 'Fernando Rojas',
            ])->assertRedirect(route('admin.traslados.show', $traslado));

        $traslado->refresh();
        $this->assertSame(TrasladoServicio::RECIBIDO, $traslado->estado);
        $this->assertSame('Fernando Rojas', $traslado->receptor_nombre);
        $this->assertSame(1, $traslado->total_recibido);
        $this->assertFalse($traslado->tiene_diferencia);
        $this->assertNotNull($orden->fresh()->traslado_recibida_at);
        // Ya está en el taller: se puede trabajar.
        $this->assertFalse($orden->fresh()->en_transito);

        // El aviso vuelve a quien despachó: cierra el círculo (salió, llegó).
        $this->assertDatabaseHas('notificaciones', [
            'evento' => 'traslado.recibido',
            'user_id' => $jefe->id,
            'canal' => Notificacion::CANAL_DATABASE,
        ]);
    }

    /**
     * El caso que da sentido al modulo: salieron 2, llego 1. Queda registrado con
     * los DOS nombres y el detalle de cual falta.
     */
    public function test_recibir_con_diferencias_registra_el_faltante_y_avisa_con_los_dos_nombres(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $llega = $this->ordenEnSucursal(['cliente_nombre' => 'Aguas Llega']);
        $falta = $this->ordenEnSucursal(['cliente_nombre' => 'Aguas Falta']);
        $this->despachar($this->jefeSucursal(), [$llega, $falta]);
        $traslado = TrasladoServicio::sole();

        $this->actingAs($this->tecnico())
            ->put(route('admin.traslados.recibir', $traslado), [
                'recibidas' => [$llega->id],          // el otro NO llegó
                'receptor_nombre' => 'Fernando Rojas',
                'observaciones_recepcion' => 'Llegó una sola en el camión.',
            ])->assertRedirect();

        $traslado->refresh()->load('ordenes');
        $this->assertTrue($traslado->tiene_diferencia);
        $this->assertSame(2, $traslado->total_enviado);
        $this->assertSame(1, $traslado->total_recibido);
        $this->assertSame(1, $traslado->faltantes);
        $this->assertSame('Recibido con diferencias', $traslado->estado_label);
        $this->assertSame('danger', $traslado->estado_variante);

        // La que falta se puede NOMBRAR: sin eso, «falta una» no sirve.
        $this->assertSame([$falta->id], $traslado->ordenesFaltantes()->pluck('id')->all());

        // Y la que no llegó sigue bloqueada para reparar.
        $this->assertTrue($falta->fresh()->en_transito);
        $this->assertFalse($llega->fresh()->en_transito);

        $aviso = Notificacion::where('evento', 'traslado.diferencias')
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();
        $this->assertStringContainsString('Luis Figueroa', $aviso->cuerpo);   // emisor
        $this->assertStringContainsString('Fernando Rojas', $aviso->cuerpo);  // receptor
        $this->assertStringContainsString($falta->folio, $aviso->cuerpo);     // qué falta
        $this->assertStringContainsString('Llegó una sola', $aviso->cuerpo);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $aviso->titulo.' '.$aviso->cuerpo);
    }

    /** Recibir CERO máquinas es un resultado posible (y el más grave). */
    public function test_se_puede_registrar_que_no_llego_ninguna(): void
    {
        $orden = $this->ordenEnSucursal();
        $this->despachar($this->jefeSucursal(), [$orden]);
        $traslado = TrasladoServicio::sole();

        $this->actingAs($this->tecnico())
            ->put(route('admin.traslados.recibir', $traslado), ['receptor_nombre' => 'Fernando Rojas'])
            ->assertRedirect();

        $traslado->refresh();
        $this->assertSame(0, $traslado->total_recibido);
        $this->assertTrue($traslado->tiene_diferencia);
        $this->assertSame(1, $traslado->faltantes);
    }

    public function test_no_se_puede_recibir_dos_veces(): void
    {
        $orden = $this->ordenEnSucursal();
        $this->despachar($this->jefeSucursal(), [$orden]);
        $traslado = TrasladoServicio::sole();
        $tecnico = $this->tecnico();

        $this->actingAs($tecnico)->put(route('admin.traslados.recibir', $traslado), [
            'recibidas' => [$orden->id], 'receptor_nombre' => 'Fernando Rojas',
        ]);
        $primera = $traslado->fresh()->recibido_at;

        $this->actingAs($tecnico)->put(route('admin.traslados.recibir', $traslado), [
            'recibidas' => [], 'receptor_nombre' => 'Otro Nombre',
        ]);

        $this->assertSame('Fernando Rojas', $traslado->fresh()->receptor_nombre, 'Una segunda recepción sobrescribió al receptor original.');
        $this->assertEquals($primera, $traslado->fresh()->recibido_at);
    }

    // --- El candado del dueño: no se repara lo que no llegó ---

    public function test_no_se_puede_reparar_una_maquina_que_sigue_en_la_sucursal(): void
    {
        $orden = $this->ordenEnSucursal();

        $this->assertTrue($orden->en_transito);
        $this->assertStringContainsString('todavía no se despachó', (string) $orden->motivo_no_llego);

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado', 'causa_falla' => 'uso_normal', 'repuestos' => [],
            ])->assertSessionHas('status', fn ($s) => str_contains($s, 'No se puede trabajar esta máquina'));

        $this->assertSame('recibido', $orden->fresh()->estado, 'Se reparó una máquina que no había llegado al taller.');
    }

    public function test_no_se_puede_reparar_una_maquina_en_transito(): void
    {
        $orden = $this->ordenEnSucursal();
        $this->despachar($this->jefeSucursal(), [$orden]);

        $this->assertTrue($orden->fresh()->en_transito);
        $this->assertStringContainsString('Va en camino', (string) $orden->fresh()->motivo_no_llego);

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado', 'causa_falla' => 'uso_normal', 'repuestos' => [],
            ]);

        $this->assertSame('recibido', $orden->fresh()->estado);
    }

    /** El candado NO puede bloquear lo que se recibió en la casa matriz. */
    public function test_la_maquina_recibida_en_la_matriz_se_repara_sin_traslado(): void
    {
        $orden = OrdenServicio::factory()->create(['sucursal_id' => $this->matriz->id, 'estado' => 'en_revision']);

        $this->assertFalse($orden->en_transito);

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado', 'causa_falla' => 'uso_normal', 'repuestos' => [],
            ])->assertRedirect();

        $this->assertSame('reparado', $orden->fresh()->estado);
    }

    /**
     * Tampoco puede bloquear las ordenes ANTERIORES al registro: la migracion
     * one-shot las sella como llegadas, si no el candado dejaria parada la
     * operacion que hoy esta viva en Abate y Coquimbo.
     */
    public function test_las_ordenes_previas_al_registro_no_quedan_bloqueadas(): void
    {
        $previa = $this->ordenEnSucursal(['traslado_recibida_at' => now()->subMonth()]);

        $this->assertFalse($previa->en_transito, 'Una orden anterior al registro de traslados quedó bloqueada.');
        $this->assertNull($previa->traslado_id, 'No debe inventarse un traslado que nunca existió.');
    }

    // --- Permisos: las dos puntas son de personas distintas ---

    public function test_sin_permiso_de_despachar_no_se_puede_despachar(): void
    {
        // El técnico RECIBE, no despacha.
        $this->actingAs($this->tecnico())->get(route('admin.traslados.create'))
            ->assertRedirect(route('dashboard'));

        $this->despachar($this->tecnico(), [$this->ordenEnSucursal()]);
        $this->assertSame(0, TrasladoServicio::count());
    }

    public function test_sin_permiso_de_recibir_no_se_puede_confirmar(): void
    {
        $orden = $this->ordenEnSucursal();
        $jefe = $this->jefeSucursal();
        $this->despachar($jefe, [$orden]);
        $traslado = TrasladoServicio::sole();

        // El jefe de sucursal despachó; NO puede confirmar su propia entrega.
        // 403 y no redirect al Inicio: el desvío amable es para la NAVEGACIÓN (GET);
        // en un PUT el handler deja el 403 crudo (decisión D-014, bitácora 24-07).
        $this->actingAs($jefe)->put(route('admin.traslados.recibir', $traslado), [
            'recibidas' => [$orden->id], 'receptor_nombre' => 'Luis Figueroa',
        ])->assertForbidden();

        $this->assertSame(TrasladoServicio::EN_TRANSITO, $traslado->fresh()->estado);
    }

    /** Las dos puntas ven el listado; nadie más. */
    public function test_el_listado_lo_ven_las_dos_puntas_y_no_un_tercero(): void
    {
        $this->actingAs($this->jefeSucursal())->get(route('admin.traslados.index'))->assertOk();
        $this->actingAs($this->tecnico())->get(route('admin.traslados.index'))->assertOk();

        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $this->actingAs($vendedor)->get(route('admin.traslados.index'))->assertRedirect(route('dashboard'));
    }

    // --- Lo que se ve en pantalla ---

    public function test_el_listado_muestra_las_maquinas_paradas_sin_despachar(): void
    {
        $orden = $this->ordenEnSucursal(['cliente_nombre' => 'Aguas Parada']);

        $this->actingAs($this->jefeSucursal())->get(route('admin.traslados.index'))
            ->assertOk()
            ->assertSee('sin despachar al taller')
            ->assertSee('Aguas Parada')
            ->assertSee($orden->folio);
    }

    public function test_la_ficha_de_la_orden_dice_donde_esta_la_maquina(): void
    {
        $orden = $this->ordenEnSucursal();
        $this->despachar($this->jefeSucursal(), [$orden]);

        $this->actingAs($this->tecnico())->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('Va en camino desde Coquimbo');
    }

}
