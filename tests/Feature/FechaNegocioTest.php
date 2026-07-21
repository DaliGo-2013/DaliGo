<?php

namespace Tests\Feature;

use App\Models\Aprobacion;
use App\Models\OrdenServicio;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P-TZ-01 (PLAN-TIMEZONE §4.1-2 y §4.6): batería de FRONTERA NOCTURNA.
 * Reloj congelado a las 23:00 de Chile (julio = invierno, UTC-4) = 03:00 UTC
 * del día SIGUIENTE — antes de este lote, el "hoy" del servidor ya era mañana
 * y la operación nocturna quedaba a oscuras. app.timezone sigue UTC a propósito
 * (el storage y el motor no cambian: solo el DÍA DE NEGOCIO).
 */
class FechaNegocioTest extends TestCase
{
    use RefreshDatabase;

    private const NOCHE_UTC = '2026-07-21 03:00:00'; // = 20-07 23:00 en Chile

    private const DIA_NEGOCIO = '2026-07-20';

    private const DIA_UTC = '2026-07-21';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(Carbon::parse(self::NOCHE_UTC, 'UTC'));
    }

    private function userWithRole(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
    }

    /** Asignación + reporte del DÍA DE NEGOCIO chileno (turno noche). */
    private function produccionDeHoyChileno(User $soplador, array $extra = []): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => self::DIA_NEGOCIO,
            'turno' => 'noche',
            'asignadas' => 500,
        ]);

        return ProduccionReporte::create($extra + [
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => self::DIA_NEGOCIO,
            'turno' => 'noche',
            'asignadas' => 500,
            'estado' => ProduccionReporte::BORRADOR,
        ]);
    }

    // --- El helper -----------------------------------------------------------

    public function test_el_dia_de_negocio_es_el_chileno_no_el_utc(): void
    {
        $this->assertSame(self::DIA_NEGOCIO, FechaNegocio::hoy());
        $this->assertSame(self::DIA_UTC, now()->toDateString()); // el UTC ya es mañana
        $this->assertTrue(FechaNegocio::esHoy(self::DIA_NEGOCIO));
        $this->assertFalse(FechaNegocio::esHoy(self::DIA_UTC));
        $this->assertSame('America/Santiago', FechaNegocio::ahora()->timezoneName);
    }

    // --- Producción nocturna (§4.1, la joya) ----------------------------------

    public function test_el_soplador_nocturno_ve_su_produccion_del_dia_chileno(): void
    {
        $soplador = $this->userWithRole('soplador');
        $reporte = $this->produccionDeHoyChileno($soplador);

        $this->actingAs($soplador)->get(route('produccion.mi.index'))
            ->assertOk()
            ->assertDontSee('No tienes producciones asignadas para hoy')
            ->assertSee(route('produccion.mi.show', $reporte), false);
    }

    public function test_la_cola_del_jefe_lista_el_dia_chileno_y_el_pulso_tiene_datos(): void
    {
        $soplador = $this->userWithRole('soplador');
        $this->produccionDeHoyChileno($soplador, ['primera' => 100, 'estado' => ProduccionReporte::APROBADO]);
        $jefe = $this->userWithRole('jefe_bodega');

        // Cola del panel: el reporte del día chileno sigue EN la cola de hoy.
        $res = $this->actingAs($jefe)->get(route('admin.produccion.index'));
        $res->assertOk();
        $this->assertCount(1, $res->viewData('reportes'));

        // Pulso del Inicio: datos del día chileno, no ceros.
        $res = $this->actingAs($jefe)->get(route('dashboard'));
        $this->assertSame(100, $res->viewData('pulsoProduccion')['producido']);
        $this->assertSame(500, $res->viewData('pulsoProduccion')['asignadas']);
    }

    public function test_el_prefill_de_asignar_y_la_pantalla_del_soplador_dicen_hoy(): void
    {
        // Prefill del form de asignar = día de negocio (no mañana-UTC).
        $jefe = $this->userWithRole('jefe_bodega');
        $this->actingAs($jefe)->get(route('admin.produccion.asignar'))
            ->assertOk()
            ->assertSee('value="'.self::DIA_NEGOCIO.'"', false);

        // La pantalla de llenado dice "hoy" sobre el reporte del día chileno
        // (esHoy con día de negocio; antes isToday-UTC decía que era pasado).
        $soplador = $this->userWithRole('soplador');
        $reporte = $this->produccionDeHoyChileno($soplador);
        $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('Preformas asignadas hoy');
    }

    // --- Agenda de terreno fusionada (dictado v22: tests de frontera, gated -----
    //     al fix de Marcos `daf948d` — esta rama NO toca Blade ni controller) ----

    /** La celda de la grilla del calendario para un día (scoped por su ?dia=). */
    private function celdaDeLaGrilla(string $html, string $iso): string
    {
        $this->assertSame(1, preg_match('/<a[^>]*dia='.$iso.'"[^>]*>.*?<\/a>/s', $html, $m), "No encontré la celda del {$iso} en la grilla.");

        return $m[0];
    }

    public function test_la_agenda_nocturna_abre_en_el_hoy_chileno_y_lo_resalta(): void
    {
        $admin = $this->userWithRole('admin');

        // 23:00 de Chile, sin params: mes chileno, «abre en HOY» = HOY chileno,
        // y la cabecera del día lo dice (con isToday-UTC no lo diría: el día
        // UTC ya es mañana).
        $res = $this->actingAs($admin)->get(route('admin.agenda-terreno.index'));
        $res->assertOk();
        $this->assertSame(7, $res->viewData('mes'));
        $this->assertSame(self::DIA_NEGOCIO, $res->viewData('diaSel')->toDateString());
        $res->assertSee('· HOY');

        // En la grilla, el día chileno va como SELECCIONADO (brand-700 — la
        // precedencia $sel de bc51e82); mañana-UTC sin resalte alguno.
        $html = $res->getContent();
        $this->assertStringContainsString('font-bold text-brand-700', $this->celdaDeLaGrilla($html, self::DIA_NEGOCIO));
        $this->assertStringNotContainsString('font-bold', $this->celdaDeLaGrilla($html, self::DIA_UTC));

        // Y con OTRO día seleccionado se ve la rama esHoy del fix de Marcos:
        // el día chileno queda en brand-600 (el "hoy" de la grilla); mañana-UTC no.
        $html = $this->actingAs($admin)
            ->get(route('admin.agenda-terreno.index', ['anio' => 2026, 'mes' => 7, 'dia' => '2026-07-15']))
            ->getContent();
        $this->assertStringContainsString('font-bold text-brand-600', $this->celdaDeLaGrilla($html, self::DIA_NEGOCIO));
        $this->assertStringNotContainsString('font-bold', $this->celdaDeLaGrilla($html, self::DIA_UTC));
    }

    public function test_la_agenda_en_frontera_de_mes_abre_julio_chileno_no_agosto_utc(): void
    {
        // 22:00 de Chile del 31-07 = 02:00 UTC del 01-08.
        $this->travelTo(Carbon::parse('2026-08-01 02:00:00', 'UTC'));

        $res = $this->actingAs($this->userWithRole('admin'))->get(route('admin.agenda-terreno.index'));
        $res->assertOk();
        $this->assertSame(7, $res->viewData('mes'));
        $this->assertSame('2026-07-31', $res->viewData('diaSel')->toDateString());
    }

    public function test_editar_una_solicitud_sin_fecha_de_noche_vuelve_al_mes_chileno(): void
    {
        // El hallazgo update():151 de la auditoría del 21-07: el redirect de una
        // solicitud QR aún sin fecha derivaba anio/mes de now() UTC y los query
        // params puentean el default (ya chileno) del index.
        $vendedor = $this->userWithRole('vendedor');
        $solicitud = \App\Models\AgendaTrabajo::factory()->create(['estado' => 'solicitado', 'fecha' => null]);

        // 21:30 de Chile del 31-07 = 01:30 UTC del 01-08.
        $this->travelTo(Carbon::parse('2026-08-01 01:30:00', 'UTC'));

        $this->actingAs($vendedor)->put(route('admin.agenda-terreno.update', $solicitud), [
            'tipo' => 'visita_tecnica',
            'estado' => 'solicitado',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'Coordinación pendiente: corregimos el teléfono.',
        ])->assertRedirect(route('admin.agenda-terreno.index', ['anio' => 2026, 'mes' => 7]));
    }

    // --- Flujos públicos nocturnos (§4.1-2) ------------------------------------

    public function test_la_visita_industrial_acepta_el_hoy_chileno(): void
    {
        $this->post(route('visita-industrial.store'), [
            'sucursal_id' => $this->sucursal()->id,
            'tipo' => 'visita_tecnica',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'Necesito visita HOY: la osmosis pierde presión.',
            // Antes: rechazada de noche ('today' resolvía en UTC = mañana).
            'fecha_preferida' => self::DIA_NEGOCIO,
        ])->assertSessionDoesntHaveErrors('fecha_preferida');
    }

    public function test_el_ingreso_qr_nocturno_queda_fechado_el_dia_chileno(): void
    {
        Storage::fake('public');

        $this->post(route('ingreso-taller.store'), [
            'sucursal_id' => $this->sucursal()->id,
            'cliente_nombre' => 'Ana Cliente',
            'cliente_email' => 'ana@correo.cl',
            'cliente_telefono' => '+56 9 8888 7777',
            'cliente_rut' => '12.345.678-5',
            'tipo_equipo' => 'dispensador',
            'numero_serie' => 'SN-9090',
            'facturacion' => 'reparacion',
            'falla_reportada' => 'Gotea por abajo',
            'fotos' => [
                UploadedFile::fake()->image('foto1.jpg', 800, 600),
                UploadedFile::fake()->image('foto2.jpg', 800, 600),
            ],
        ]);

        // Antes quedaba fechado MAÑANA (hoy-UTC); ahora, el día chileno real.
        $orden = OrdenServicio::firstOrFail();
        $this->assertSame(self::DIA_NEGOCIO, $orden->fecha_ingreso->toDateString());
    }

    // --- Limitación aceptada (§4.6) --------------------------------------------

    public function test_el_filtro_del_historial_sigue_en_dias_utc_limitacion_aceptada(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        Queue::fake();

        Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'motivo' => 'm', 'descripcion' => 'Nocturna QA',
            'rol_aprobador' => 'admin',
        ]); // created_at = 21-07 03:00 UTC (día chileno: 20-07)

        $admin = $this->userWithRole('admin');
        $contiene = fn ($res) => collect($res->viewData('aprobaciones')->items())
            ->contains(fn ($a) => $a->descripcion === 'Nocturna QA');

        // whereDate extrae el día del string GUARDADO (UTC): la fila se filtra
        // por su día UTC, no el chileno. Documentado y ACEPTADO en v1
        // (PLAN-TIMEZONE §3 — CONVERT_TZ no es viable en este hosting).
        $res = $this->actingAs($admin)->get(route('admin.aprobaciones.index', ['desde' => self::DIA_UTC, 'hasta' => self::DIA_UTC]));
        $this->assertTrue($contiene($res), 'La fila debe encontrarse por su día UTC.');

        $res = $this->actingAs($admin)->get(route('admin.aprobaciones.index', ['desde' => self::DIA_NEGOCIO, 'hasta' => self::DIA_NEGOCIO]));
        $this->assertFalse($contiene($res), 'La limitación aceptada: el día chileno NO la encuentra.');
    }
}
