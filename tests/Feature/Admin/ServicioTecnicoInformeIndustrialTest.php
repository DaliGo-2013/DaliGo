<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\ServicioTerreno;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Informes de Servicio Técnico separados en dos "carpetas" (Dispensadores /
 * Industrial) + el informe INDUSTRIAL (agenda de terreno): uso de repuestos en
 * números, % por tipo de trabajo y servicios más usados. Los repuestos los
 * registra el técnico al cerrar el trabajo (PATCH estado=realizado).
 */
class ServicioTecnicoInformeIndustrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    // --- Landing (dos carpetas) ---

    public function test_guest_es_redirigido_del_landing(): void
    {
        $this->get('/admin/servicio-tecnico/informe')->assertRedirect('/login');
    }

    public function test_sin_permiso_no_ve_el_landing(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/servicio-tecnico/informe')->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
    }

    public function test_landing_muestra_las_dos_carpetas(): void
    {
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe')
            ->assertOk()
            ->assertSee('Dispensadores')
            ->assertSee('Industrial')
            ->assertSee(route('admin.servicio-tecnico.informe.dispensadores'), false)
            ->assertSee(route('admin.servicio-tecnico.informe.industrial'), false);
    }

    // --- Permisos por rol ---

    public function test_tecnico_de_taller_solo_ve_dispensadores(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');

        // El landing lo manda directo a SU informe (dispensadores).
        $this->actingAs($tecnico)->get('/admin/servicio-tecnico/informe')
            ->assertRedirect(route('admin.servicio-tecnico.informe.dispensadores'));

        $this->actingAs($tecnico)->get('/admin/servicio-tecnico/informe/dispensadores')->assertOk();

        // NO puede entrar al informe industrial.
        $this->actingAs($tecnico)->get('/admin/servicio-tecnico/informe/industrial')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
    }

    public function test_tecnico_industrial_solo_ve_industrial(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');

        // El landing lo manda directo a SU informe (industrial).
        $this->actingAs($tecnico)->get('/admin/servicio-tecnico/informe')
            ->assertRedirect(route('admin.servicio-tecnico.informe.industrial'));

        $this->actingAs($tecnico)->get('/admin/servicio-tecnico/informe/industrial')->assertOk();

        // NO puede entrar al informe de dispensadores.
        $this->actingAs($tecnico)->get('/admin/servicio-tecnico/informe/dispensadores')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
    }

    // --- Informe industrial ---

    public function test_industrial_cuenta_por_tipo_y_servicios_del_periodo(): void
    {
        $svc = ServicioTerreno::factory()->create(['nombre' => 'Full planta 1T']);
        AgendaTrabajo::factory()->count(2)->create(['fecha' => '2026-07-10', 'tipo' => 'mantencion', 'estado' => 'agendado', 'servicio_terreno_id' => $svc->id]);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-12', 'tipo' => 'instalacion', 'estado' => 'realizado', 'servicio_terreno_id' => $svc->id]);
        // Fuera del período y cancelado: NO cuentan.
        AgendaTrabajo::factory()->create(['fecha' => '2026-06-01', 'tipo' => 'reparacion', 'estado' => 'agendado']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-15', 'tipo' => 'reparacion', 'estado' => 'cancelado']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('total', 3)
            ->assertViewHas('porTipo', function (Collection $tipos) {
                return (int) $tipos->firstWhere('nombre', 'mantencion')?->cantidad === 2
                    && (int) $tipos->firstWhere('nombre', 'instalacion')?->cantidad === 1
                    && $tipos->firstWhere('nombre', 'reparacion') === null;   // cancelado/fuera de período
            })
            ->assertViewHas('topServicios', function (Collection $servicios) use ($svc) {
                return (int) $servicios->firstWhere('id', $svc->id)?->cantidad === 3;
            });
    }

    public function test_repuestos_se_registran_al_cerrar_y_se_cuentan_en_el_informe(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');
        // El `tipo` se FIJA (mismo criterio que el helper de InformeServicioTecnico
        // ExcelTest): AgendaTrabajoFactory lo sortea entre los 4, y desde el 14-08
        // el informe NO cuenta los repuestos de una visita técnica —ahí son lo que
        // se va a NECESITAR para cotizar, no lo gastado—. Sin fijarlo este test
        // falla 1 de cada 4 corridas: el flaky-por-factory-aleatoria de la bitácora
        // [2026-07-13], que acá tardó tres corridas verdes en asomar.
        $trabajo = AgendaTrabajo::factory()->create([
            'fecha' => '2026-07-10', 'estado' => 'agendado', 'tipo' => 'mantencion',
        ]);

        // El técnico cierra el trabajo y registra repuestos (una fila vacía se descarta).
        $this->actingAs($tecnico)
            ->patch(route('admin.agenda-terreno.estado', $trabajo), [
                'estado' => 'realizado',
                // Obligatorio desde el 14-08: cerrar exige contar qué pasó.
                'notas_tecnico' => 'Cambié la membrana y el filtro de papel.',
                'repuestos' => [
                    ['nombre' => 'Membrana', 'cantidad' => 2],
                    ['nombre' => 'Filtro de papel', 'cantidad' => 1],
                    ['nombre' => '', 'cantidad' => 3],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('realizado', $trabajo->fresh()->estado);
        $this->assertSame(2, $trabajo->repuestos()->count());
        $this->assertSame(3, (int) $trabajo->repuestos()->sum('cantidad'));

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('totalUnidadesRepuestos', 3)
            ->assertViewHas('totalNombresRepuestos', 2)
            ->assertViewHas('repuestos', function (Collection $r) {
                return (int) $r->firstWhere('nombre', 'Membrana')?->unidades === 2;
            });
    }

    public function test_cerrar_sin_repuestos_sigue_funcionando(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');
        $trabajo = AgendaTrabajo::factory()->create(['fecha' => '2026-07-10', 'estado' => 'agendado']);

        $this->actingAs($tecnico)
            ->patch(route('admin.agenda-terreno.estado', $trabajo), [
                'estado' => 'realizado',
                // Obligatorio desde el 14-08: cerrar exige contar qué pasó.
                'notas_tecnico' => 'Cerrado en terreno.',
            ])
            ->assertRedirect();

        $this->assertSame('realizado', $trabajo->fresh()->estado);
        $this->assertSame(0, $trabajo->repuestos()->count());
    }

    // --- Indicadores nuevos ---

    public function test_cumplimiento_realizados_vs_pendientes(): void
    {
        AgendaTrabajo::factory()->count(3)->create(['fecha' => '2026-07-10', 'estado' => 'realizado']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-11', 'estado' => 'agendado']);
        // Cancelado y solicitud sin fecha NO cuentan.
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-12', 'estado' => 'cancelado']);
        AgendaTrabajo::factory()->create(['fecha' => null, 'estado' => 'solicitado']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('total', 4)
            ->assertViewHas('realizados', 3)
            ->assertViewHas('pendientes', 1)
            ->assertViewHas('pctCumplimiento', 75);
    }

    public function test_clientes_que_mas_solicitan_agrupa_por_rut(): void
    {
        AgendaTrabajo::factory()->count(2)->create(['fecha' => '2026-07-10', 'estado' => 'realizado', 'cliente_rut' => '11111111-1', 'cliente_nombre' => 'Aguas Frecuentes']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-12', 'estado' => 'agendado', 'cliente_rut' => '22222222-2', 'cliente_nombre' => 'Cliente Ocasional']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('topClientes', function (Collection $clientes) {
                $top = $clientes->first();

                return $clientes->count() === 2
                    && $top->cliente_rut === '11111111-1'
                    && (int) $top->cantidad === 2;
            });
    }

    /**
     * UNA TARJETA POR TIPO DE TRABAJO (dueño 14-08-2026). Reemplaza al test que
     * asertaba `visitas`/`visitasRealizadas`/`pctVisitas`: esas tres variables
     * medían UN tipo y no se podían generalizar sin multiplicarlas por cuatro, así
     * que se unificaron en `tiposResumen`.
     */
    public function test_cada_tipo_de_trabajo_trae_su_conteo_y_su_porcentaje(): void
    {
        AgendaTrabajo::factory()->count(2)->create(['fecha' => '2026-07-10', 'tipo' => 'visita_tecnica', 'estado' => 'realizado']);
        AgendaTrabajo::factory()->count(2)->create(['fecha' => '2026-07-11', 'tipo' => 'mantencion', 'estado' => 'agendado']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-12', 'tipo' => 'reparacion', 'estado' => 'realizado']);

        $resumen = $this->actingAs($this->admin())
            ->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->viewData('tiposResumen')
            ->keyBy('tipo');

        $this->assertSame(2, $resumen['visita_tecnica']['total']);
        $this->assertSame(2, $resumen['visita_tecnica']['realizados']);
        $this->assertSame(40, $resumen['visita_tecnica']['pct']);   // 2 de 5

        $this->assertSame(2, $resumen['mantencion']['total']);
        $this->assertSame(0, $resumen['mantencion']['realizados'], 'Agendado no es realizado.');

        $this->assertSame(1, $resumen['reparacion']['total']);
        $this->assertSame(1, $resumen['reparacion']['realizados']);
    }

    /**
     * LOS CUATRO TIPOS SIEMPRE, incluso en 0. Que un mes no haya reparaciones es
     * información; una tarjeta ausente se lee como «esto no se mide» y manda a
     * alguien a buscar el dato a otra parte.
     */
    public function test_un_tipo_sin_trabajos_igual_muestra_su_tarjeta_en_cero(): void
    {
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-10', 'tipo' => 'mantencion', 'estado' => 'realizado']);

        $respuesta = $this->actingAs($this->admin())
            ->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk();

        $resumen = $respuesta->viewData('tiposResumen');

        // Las cuatro, y en el orden del catálogo (la visita técnica primero).
        $this->assertSame(AgendaTrabajo::TIPOS, $resumen->pluck('tipo')->all());

        $enCero = $resumen->keyBy('tipo')['instalacion'];
        $this->assertSame(0, $enCero['total']);
        $this->assertSame(0, $enCero['pct']);

        // Y se ven en la pantalla, con su rótulo legible (no el valor crudo).
        $html = $respuesta->getContent();
        foreach (['Visita técnica', 'Mantención', 'Reparación', 'Instalación'] as $etiqueta) {
            $this->assertStringContainsString($etiqueta, $html, "Falta la tarjeta de {$etiqueta}.");
        }
    }

    /**
     * El ranking «por tipo» y las tarjetas salen del MISMO conteo: si se separan,
     * la página muestra dos números distintos para lo mismo y ninguno de los dos
     * queda desmentido por el otro a la vista.
     */
    public function test_las_tarjetas_y_el_ranking_por_tipo_no_pueden_discrepar(): void
    {
        AgendaTrabajo::factory()->count(3)->create(['fecha' => '2026-07-10', 'tipo' => 'instalacion', 'estado' => 'realizado']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-11', 'tipo' => 'reparacion', 'estado' => 'agendado']);

        $respuesta = $this->actingAs($this->admin())
            ->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk();

        $tarjetas = $respuesta->viewData('tiposResumen')->keyBy('tipo');
        $ranking = collect($respuesta->viewData('porTipo'))->keyBy('nombre');

        foreach ($ranking as $tipo => $fila) {
            $this->assertSame(
                $tarjetas[$tipo]['total'],
                (int) $fila->cantidad,
                "La tarjeta y el ranking discrepan en {$tipo}."
            );
        }

        // El ranking va ordenado por cantidad (la instalación, con 3, primero).
        $this->assertSame('instalacion', collect($respuesta->viewData('porTipo'))->first()->nombre);
    }

    // --- Detalle cliqueable de Realizados / Pendientes ---

    public function test_pendientes_expone_su_porcentaje_del_periodo(): void
    {
        AgendaTrabajo::factory()->count(3)->create(['fecha' => '2026-07-10', 'estado' => 'realizado']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-11', 'estado' => 'agendado']);

        // 1 de 4 pendiente = 25% (complemento del 75% de cumplimiento).
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('pctPendientes', 25);
    }

    public function test_las_listas_de_detalle_traen_solo_su_estado_del_periodo(): void
    {
        $realizado = AgendaTrabajo::factory()->create(['fecha' => '2026-07-10', 'estado' => 'realizado']);
        $pendiente = AgendaTrabajo::factory()->create(['fecha' => '2026-07-20', 'estado' => 'agendado']);
        // Ruido: otro mes, cancelado y solicitud sin fecha → en ninguna lista.
        AgendaTrabajo::factory()->create(['fecha' => '2026-06-10', 'estado' => 'realizado']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-15', 'estado' => 'cancelado']);
        AgendaTrabajo::factory()->create(['fecha' => null, 'estado' => 'solicitado']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('realizadosLista', fn (Collection $l) => $l->pluck('id')->all() === [$realizado->id])
            ->assertViewHas('pendientesLista', fn (Collection $l) => $l->pluck('id')->all() === [$pendiente->id]);
    }

    public function test_la_vista_muestra_el_detalle_de_lo_hecho_y_lo_por_hacer(): void
    {
        AgendaTrabajo::factory()->create([
            'fecha' => '2026-07-10', 'estado' => 'realizado',
            'cliente_nombre' => 'Cliente Hecho', 'notas_tecnico' => 'Se cambio la membrana',
        ]);
        AgendaTrabajo::factory()->create([
            'fecha' => '2026-07-20', 'estado' => 'agendado',
            'cliente_nombre' => 'Cliente Por Hacer', 'descripcion' => 'Revisar bomba dosificadora',
        ]);

        // Los paneles se renderizan en el servidor (Alpine solo los muestra/oculta),
        // así que el detalle está en el HTML aunque el panel arranque cerrado.
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertSee('Cliente Hecho')
            ->assertSee('Se cambio la membrana')
            ->assertSee('Cliente Por Hacer')
            ->assertSee('Revisar bomba dosificadora');
    }
}
