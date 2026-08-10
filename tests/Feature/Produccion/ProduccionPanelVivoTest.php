<?php

namespace Tests\Feature\Produccion;

use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionParada;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\AvisosError;
use App\Support\FechaNegocio;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P-M11-21 · Panel «Hoy en vivo». Candado 5 del dictado: permisos + duración
 * de paradas abiertas calculada server-side (medianoche incluida). Fechas de
 * viaje FIJAS en invierno chileno (el DST corre las horas de pared).
 */
class ProduccionPanelVivoTest extends TestCase
{
    use RefreshDatabase;

    private const DIA_PRUEBA = '2026-08-12';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

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
            'soplador_id' => $soplador->id, 'fecha' => $fecha, 'turno' => $turno, 'asignadas' => $asignadas,
        ]);

        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id,
            'fecha' => $fecha, 'turno' => $turno, 'asignadas' => $asignadas, 'estado' => $estado,
        ]);

        if ($primera > 0) {
            ProduccionRegistro::create([
                'reporte_id' => $reporte->id, 'maquina_id' => $maquina?->id,
                'primera' => $primera, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            ]);
            $reporte->recalcularDesdeRegistros();
        }

        return $reporte;
    }

    // --- Permisos (candado 5) ---

    public function test_el_jefe_ve_el_panel_y_el_soplador_no(): void
    {
        $this->viajarAMediodiaChileno();

        $this->actingAs($this->jefe())->get(route('admin.produccion.vivo'))->assertOk();

        $this->actingAs($this->soplador())
            ->get(route('admin.produccion.vivo'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', AvisosError::SIN_PERMISO);

        $this->actingAs($this->soplador())
            ->get(route('admin.produccion.vivo.conteo'))
            ->assertRedirect(route('dashboard'));
    }

    // --- Contenido y semáforo ---

    public function test_muestra_avance_proyeccion_y_semaforo_en_riesgo(): void
    {
        $this->viajarAMediodiaChileno();
        $soplador = $this->soplador();
        $maquina = $this->maquina('Sopladora 7');
        $reporte = $this->reporteConProduccion($soplador, 100, 20, maquina: $maquina);

        $respuesta = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.vivo'))
            ->assertOk()
            // Por RUTA (marcador estable) y por los valores que el panel deriva.
            ->assertSee(route('admin.produccion.reporte.show', $reporte), false)
            ->assertSee('proyección 60%')
            ->assertSee('En riesgo')
            ->assertSee('Sopladora 7')
            ->assertSee('20 de 100 vendibles (20%)');

        $respuesta->assertViewHas('filas', function ($filas) {
            return count($filas) === 1
                && $filas[0]['proyeccion'] === 60
                && $filas[0]['semaforo'] === \App\Services\Produccion\CorteSic::SEMAFORO_EN_RIESGO
                && $filas[0]['variante'] === 'brand';
        });
    }

    public function test_reporte_enviado_va_atenuado_sin_proyeccion(): void
    {
        $this->viajarAMediodiaChileno();
        $reporte = $this->reporteConProduccion($this->soplador(), 100, 90, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.vivo'))
            ->assertOk()
            ->assertViewHas('filas', function ($filas) {
                return count($filas) === 1
                    && $filas[0]['abierto'] === false
                    && $filas[0]['proyeccion'] === null;
            })
            ->assertSee('Enviado');
    }

    public function test_estado_vacio_sin_reportes_en_el_turno(): void
    {
        $this->viajarAMediodiaChileno();

        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.vivo'))
            ->assertOk()
            ->assertSee('Sin producciones asignadas en el turno activo.');
    }

    // --- Firma del poll (misma función para la vista y el JSON) ---

    public function test_la_firma_de_la_vista_coincide_con_la_del_conteo(): void
    {
        $this->viajarAMediodiaChileno();
        $this->reporteConProduccion($this->soplador(), 100, 20);
        $jefe = $this->jefe();

        $firmaVista = $this->actingAs($jefe)->get(route('admin.produccion.vivo'))->viewData('firma');

        $this->actingAs($jefe)
            ->getJson(route('admin.produccion.vivo.conteo'))
            ->assertOk()
            ->assertJsonStructure(['total', 'firma'])
            ->assertJson(['total' => 1, 'firma' => $firmaVista]);
    }

    // --- Medianoche (candado 5): turno noche + parada abierta corriendo ---

    public function test_de_madrugada_muestra_ayer_noche_con_la_parada_corriendo(): void
    {
        // 06:00 UTC del día siguiente = 02:00 Chile: turno noche de AYER activo.
        $this->travelTo(Carbon::parse(self::DIA_PRUEBA, 'UTC')->addDay()->setTime(6, 0));
        $soplador = $this->soplador();
        $maquina = $this->maquina();

        $ayer = FechaNegocio::ahora()->subDay()->toDateString();
        $reporte = $this->reporteConProduccion($soplador, 100, 30, fecha: $ayer, turno: 'noche', maquina: $maquina);
        $reporte->paradas()->create([
            'maquina_id' => $maquina->id,
            'motivo' => 'Falla de máquina',
            'clase' => ProduccionParada::claseDe('Falla de máquina'),
            'origen' => 'maquina',
            'inicio' => '23:40',
            'fin' => null,
        ]);

        // De 23:40 a 02:00 hay 2 h 20 min — el módulo 1440 envuelve la medianoche.
        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.vivo'))
            ->assertOk()
            ->assertSee($soplador->name)
            ->assertSee('Falla de máquina')
            ->assertSee('lleva 2 h 20 min');
    }

    // --- Unitario del helper de duración corriendo ---

    public function test_duracion_minutos_hasta_envuelve_medianoche(): void
    {
        $parada = new ProduccionParada(['inicio' => '23:40', 'fin' => null]);

        $this->assertSame(140, $parada->duracionMinutosHasta('02:00'));
        $this->assertSame(20, $parada->duracionMinutosHasta('00:00'));
        $this->assertNull($parada->duracionMinutosHasta(null));
        $this->assertSame('2 h 20 min', ProduccionParada::labelDe(140));
    }
}
