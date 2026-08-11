<?php

namespace Tests\Feature\Produccion;

use App\Models\Configuracion;
use App\Models\Maquina;
use App\Models\Notificacion;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionCorte;
use App\Models\ProduccionParada;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P-M11-21 · Corte SIC. Candados del dictado v20: (1) 2 cortes seguidos bajo
 * umbral => 2º urgente y el 3º NO duplica; (2) sin asignación hoy => silencio;
 * (3) el primer corte del turno no divide por cero; (4) MUTADO: sin el guard
 * anti-spam (wasRecentlyCreated / racha) estos tests se ponen rojos; (6) los
 * cortes usan FechaNegocio (frontera nocturna con travelTo).
 *
 * El reloj congelado de TestCase (12:00 UTC = 08:00 Chile en invierno) cae al
 * INICIO del turno día, así que cada test viaja con travelTo explícito.
 */
class ProduccionCorteSicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Queue::fake();
    }

    /**
     * Fecha FIJA de invierno chileno (UTC-4): con la fecha real del calendario
     * el offset cambia con el DST (UTC-3 desde septiembre) y las horas de
     * pared se corren — flaky-por-calendario, la lección del 2026-07-31.
     */
    private const DIA_PRUEBA = '2026-08-12';

    /** 16:00 UTC del día de prueba = 12:00 en Chile: turno día, 4 h transcurridas. */
    private function viajarAMediodiaChileno(): void
    {
        $this->travelTo(Carbon::parse(self::DIA_PRUEBA.' 16:00:00', 'UTC'));
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('soplador');
    }

    private function maquina(string $nombre = 'Sopladora 1'): Maquina
    {
        $sucursal = Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['nombre' => 'Mirador']);

        return Maquina::create(['nombre' => $nombre, 'sucursal_id' => $sucursal->id, 'activa' => true]);
    }

    /** Reporte del turno con su producción; fecha = día de negocio ACTUAL salvo que se pase otra. */
    private function reporteConProduccion(
        User $soplador,
        int $asignadas,
        int $primera,
        string $estado = ProduccionReporte::BORRADOR,
        ?string $fecha = null,
        string $turno = 'dia',
        ?Maquina $maquina = null,
    ): ProduccionReporte {
        $fecha ??= FechaNegocio::hoy();

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => $turno,
            'asignadas' => $asignadas,
        ]);

        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => $turno,
            'asignadas' => $asignadas,
            'estado' => $estado,
        ]);

        if ($primera > 0) {
            ProduccionRegistro::create([
                'reporte_id' => $reporte->id,
                'maquina_id' => $maquina?->id,
                'primera' => $primera,
                'segunda' => 0,
                'malo' => 0,
                'danada' => 0,
            ]);
            $reporte->recalcularDesdeRegistros();
        }

        return $reporte;
    }

    /** Avisos de campanita del corte (una fila por destinatario). */
    private function avisos()
    {
        return Notificacion::where('evento', 'produccion.meta_en_riesgo')
            ->where('canal', Notificacion::CANAL_DATABASE);
    }

    // --- Scheduler (grilla I-01) ---

    public function test_corte_agendado_en_la_grilla_de_15(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'produccion:corte-sic'));

        $this->assertNotNull($evento, 'Falta produccion:corte-sic en el scheduler.');
        $this->assertSame('0 */2 * * *', $evento->expression, 'Fuera de la grilla */15 de I-01.');
        $this->assertTrue($evento->withoutOverlapping, 'Debe tener withoutOverlapping.');
    }

    // --- Silencios correctos (candados 2 y 3) ---

    public function test_fuera_de_horario_de_turno_es_silencio(): void
    {
        // Solo turno día: a las 02:00 de Chile no hay turno activo.
        Configuracion::set('produccion_turnos', ['dia' => ['inicio' => '08:00', 'fin' => '20:00']]);
        $this->travelTo(Carbon::parse(self::DIA_PRUEBA.' 06:00:00', 'UTC'));

        $this->reporteConProduccion($this->soplador(), 100, 5);
        $this->jefe();

        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(0, ProduccionCorte::count());
        $this->assertSame(0, $this->avisos()->count());
    }

    public function test_sin_asignacion_hoy_es_silencio(): void
    {
        $this->viajarAMediodiaChileno();
        $this->jefe();

        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(0, ProduccionCorte::count());
        $this->assertSame(0, $this->avisos()->count());
    }

    public function test_el_primer_corte_del_turno_no_divide_por_cero(): void
    {
        // 12:30 UTC = 08:30 Chile: 30 min de turno, bajo MINUTOS_MINIMOS.
        $this->travelTo(Carbon::parse(self::DIA_PRUEBA.' 12:30:00', 'UTC'));

        $this->reporteConProduccion($this->soplador(), 100, 0);
        $this->jefe();

        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(0, ProduccionCorte::count());
        $this->assertSame(0, $this->avisos()->count());
    }

    public function test_enviados_y_aprobados_quedan_fuera_del_corte(): void
    {
        $this->viajarAMediodiaChileno();
        $this->jefe();

        $this->reporteConProduccion($this->soplador(), 100, 5, ProduccionReporte::ENVIADO);
        $this->reporteConProduccion($this->soplador(), 100, 5, ProduccionReporte::APROBADO);

        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(0, ProduccionCorte::count());
        $this->assertSame(0, $this->avisos()->count());
    }

    // --- El aviso (candado 1: normal -> urgente -> silencio) ---

    public function test_bajo_umbral_avisa_al_jefe_con_placeholders_resueltos(): void
    {
        $this->viajarAMediodiaChileno();
        $jefe = $this->jefe();
        $soplador = $this->soplador();
        $maquina = $this->maquina('Sopladora 7');

        // 20 de 100 con 4 h de 12: proyección 60% < 85.
        $reporte = $this->reporteConProduccion($soplador, 100, 20, maquina: $maquina);
        $reporte->paradas()->create([
            'maquina_id' => $maquina->id,
            'motivo' => 'Falla de máquina',
            'clase' => ProduccionParada::claseDe('Falla de máquina'),
            'origen' => 'maquina',
            'inicio' => '10:00',
            'fin' => null,
        ]);

        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $aviso = $this->avisos()->where('user_id', $jefe->id)->firstOrFail();

        // Placeholders TODOS resueltos (doctrina M15) y sin la marca urgente.
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $aviso->titulo.$aviso->cuerpo);
        $this->assertStringNotContainsString('URGENTE', $aviso->titulo);
        $this->assertStringContainsString('60%', $aviso->titulo);
        $this->assertStringContainsString('Sopladora 7', $aviso->cuerpo);
        $this->assertStringContainsString('Falla de máquina', $aviso->cuerpo);

        // El aviso aterriza en el reporte, gateado por permiso.
        $this->assertSame(route('admin.produccion.reporte.show', $reporte), $aviso->urlDestinoPara($jefe));
        $this->assertNull($aviso->urlDestinoPara($soplador));

        $this->assertDatabaseHas('produccion_cortes', [
            'reporte_id' => $reporte->id,
            'bajo_umbral' => true,
            'proyeccion' => 60,
            'avisado' => true,
            'urgente' => false,
        ]);
    }

    public function test_segundo_corte_consecutivo_es_urgente_y_el_tercero_calla(): void
    {
        $this->viajarAMediodiaChileno();
        $jefe = $this->jefe();
        $this->reporteConProduccion($this->soplador(), 100, 20);

        $this->artisan('produccion:corte-sic')->assertSuccessful();
        $this->assertSame(1, $this->avisos()->count());

        // 2º corte (2 h después): sigue bajo -> URGENTE.
        $this->travelTo(now()->addHours(2));
        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(2, $this->avisos()->count());
        $urgente = $this->avisos()->orderByDesc('id')->first();
        $this->assertStringStartsWith('⚠ URGENTE: ', $urgente->titulo);

        // 3º corte igual: nada cambió -> silencio (pero el corte SE registra).
        $this->travelTo(now()->addHours(2));
        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(2, $this->avisos()->count());
        $this->assertSame(3, ProduccionCorte::count());
    }

    public function test_recuperarse_resetea_la_racha_y_una_nueva_caida_reavisa(): void
    {
        $this->viajarAMediodiaChileno();
        $this->jefe();
        $soplador = $this->soplador();
        $reporte = $this->reporteConProduccion($soplador, 100, 20);

        $this->artisan('produccion:corte-sic')->assertSuccessful();
        $this->assertSame(1, $this->avisos()->count());

        // Se recupera: produce 40 más -> proyección sobre el umbral.
        ProduccionRegistro::create(['reporte_id' => $reporte->id, 'primera' => 40, 'segunda' => 0, 'malo' => 0, 'danada' => 0]);
        $reporte->recalcularDesdeRegistros();

        $this->travelTo(now()->addHours(2));
        $this->artisan('produccion:corte-sic')->assertSuccessful();
        $this->assertSame(1, $this->avisos()->count()); // al día: sin aviso

        // Vuelve a caer (el turno avanza y la proyección baja): racha NUEVA
        // -> aviso normal de nuevo, no urgente.
        $this->travelTo(now()->addHours(4));
        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(2, $this->avisos()->count());
        $this->assertStringNotContainsString('URGENTE', $this->avisos()->orderByDesc('id')->first()->titulo);
    }

    // --- Anti-duplicado del slot (candado 4, objetivo del MUTADO) ---

    public function test_correr_dos_veces_el_mismo_slot_no_duplica(): void
    {
        $this->viajarAMediodiaChileno();
        $this->jefe();
        $this->reporteConProduccion($this->soplador(), 100, 20);

        $this->artisan('produccion:corte-sic')->assertSuccessful();
        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(1, ProduccionCorte::count());
        $this->assertSame(1, $this->avisos()->count());
    }

    // --- Destinos y dry-run ---

    public function test_avisa_solo_a_quienes_gestionan_produccion(): void
    {
        $this->viajarAMediodiaChileno();
        $jefe = $this->jefe();
        $soplador = $this->soplador();
        $this->reporteConProduccion($soplador, 100, 20);

        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(1, $this->avisos()->where('user_id', $jefe->id)->count());
        $this->assertSame(0, $this->avisos()->where('user_id', $soplador->id)->count());
    }

    public function test_dry_run_no_registra_ni_avisa(): void
    {
        $this->viajarAMediodiaChileno();
        $this->jefe();
        $this->reporteConProduccion($this->soplador(), 100, 20);

        $this->artisan('produccion:corte-sic --dry-run')->assertSuccessful();

        $this->assertSame(0, ProduccionCorte::count());
        $this->assertSame(0, $this->avisos()->count());
    }

    // --- Frontera nocturna (candado 6): el corte usa FechaNegocio ---

    public function test_de_madrugada_corta_el_reporte_de_ayer_turno_noche(): void
    {
        // 06:00 UTC del día siguiente = 02:00 Chile (invierno): turno noche
        // activo, arrancó AYER de negocio.
        $this->travelTo(Carbon::parse(self::DIA_PRUEBA, 'UTC')->addDay()->setTime(6, 0));
        $jefe = $this->jefe();
        $soplador = $this->soplador();

        $ayer = FechaNegocio::ahora()->subDay()->toDateString();
        $deAnoche = $this->reporteConProduccion($soplador, 100, 10, fecha: $ayer, turno: 'noche');
        // Un reporte de noche con fecha de HOY no corresponde al turno activo.
        $deHoy = $this->reporteConProduccion($soplador, 100, 10, turno: 'noche');

        $this->artisan('produccion:corte-sic')->assertSuccessful();

        $this->assertSame(1, ProduccionCorte::where('reporte_id', $deAnoche->id)->count());
        $this->assertSame(0, ProduccionCorte::where('reporte_id', $deHoy->id)->count());
        $this->assertSame(1, $this->avisos()->where('user_id', $jefe->id)->count());
    }

    // --- Proyección y semáforo (unitarios sobre el service) ---

    public function test_proyeccion_lineal_en_enteros_y_con_clamp(): void
    {
        $service = app(\App\Services\Produccion\CorteSic::class);

        // 20 producidas en 240 de 720 min con meta 100 -> 60%.
        $this->assertSame(60, $service->proyeccionPct(20, 100, 240, 720));
        // Guards: sin minutos o sin meta -> 0 (jamás división por cero).
        $this->assertSame(0, $service->proyeccionPct(20, 100, 0, 720));
        $this->assertSame(0, $service->proyeccionPct(20, 0, 240, 720));
        // Clamp: 500 producidas en 60 de 720 min con meta 100 -> tope 999.
        $this->assertSame(999, $service->proyeccionPct(500, 100, 60, 720));
    }

    public function test_el_semaforo_respeta_la_paleta_de_la_app(): void
    {
        $service = app(\App\Services\Produccion\CorteSic::class);

        // 0 producido con horas corridas ES riesgo, no "al día".
        $this->assertSame(\App\Services\Produccion\CorteSic::SEMAFORO_EN_RIESGO, $service->semaforo(0, 85, 0));
        $this->assertSame(\App\Services\Produccion\CorteSic::SEMAFORO_CRITICO, $service->semaforo(50, 85, 2));
        $this->assertSame(\App\Services\Produccion\CorteSic::SEMAFORO_AL_DIA, $service->semaforo(90, 85, 0));
        $this->assertSame(\App\Services\Produccion\CorteSic::SEMAFORO_AL_DIA, $service->semaforo(null, 85, 0));

        // Paleta ESTRICTA de 4 (mismo candado que Vehiculo::variante): si
        // alguien devuelve 'success'/'warning', <x-badge> lo ignora en silencio.
        $this->assertSame('danger', \App\Services\Produccion\CorteSic::variante(\App\Services\Produccion\CorteSic::SEMAFORO_CRITICO));
        $this->assertSame('brand', \App\Services\Produccion\CorteSic::variante(\App\Services\Produccion\CorteSic::SEMAFORO_EN_RIESGO));
        $this->assertSame('neutral', \App\Services\Produccion\CorteSic::variante(\App\Services\Produccion\CorteSic::SEMAFORO_AL_DIA));
    }
}
