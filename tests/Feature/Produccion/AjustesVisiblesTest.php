<?php

namespace Tests\Feature\Produccion;

use App\Models\Aprobacion;
use App\Models\ProduccionAjuste;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El ajuste del jefe se VE (dueño 09-09): cuando el jefe corrige cantidades
 * de un reporte —enviado o ya aprobado—, el soplador lo encuentra en su
 * historial de 45 días con quién, qué ítem y cuánto; y toda producción
 * corregida lleva la etiqueta «Modificado» con antes → después en el detalle
 * (del soplador y del jefe). Fuente: produccion_ajustes, una fila por campo
 * que cambió, escrita al aplicar el ajuste.
 */
class AjustesVisiblesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(string $nombre = 'Jefe Bodega'): User
    {
        return tap(User::factory()->create(['name' => $nombre]))->assignRole('jefe_bodega');
    }

    private function soplador(): User
    {
        return tap(User::factory()->create(['name' => 'Soplador Uno']))->assignRole('soplador');
    }

    /** Reporte con 700 de 1ª, 10 de 2ª, 5 malos, 0 dañadas sobre 800 asignadas. */
    private function reporteDe(User $soplador, string $estado = ProduccionReporte::ENVIADO, ?string $fecha = null): ProduccionReporte
    {
        $fecha ??= now()->toDateString();
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => $fecha, 'turno' => 'dia', 'asignadas' => 800,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id, 'fecha' => $fecha,
            'turno' => 'dia', 'asignadas' => 800, 'primera' => 700, 'segunda' => 10, 'malo' => 5, 'danada' => 0,
            'estado' => $estado,
        ]);
    }

    /** El jefe ajusta: 1ª 700→680, malos 5→25, resto igual. Sin regla sembrada → auto-aprobado. */
    private function ajustar(User $jefe, ProduccionReporte $reporte, array $cambios = [], string $motivo = 'Recuento del jefe')
    {
        return $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), array_merge([
            'asignadas' => 800, 'primera' => 680, 'segunda' => 10, 'malo' => 25, 'danada' => 0,
            'motivo_ajuste' => $motivo,
        ], $cambios));
    }

    // --- El registro ---------------------------------------------------------

    public function test_ajustar_registra_una_fila_por_campo_cambiado(): void
    {
        $jefe = $this->jefe();
        $reporte = $this->reporteDe($this->soplador());

        $this->ajustar($jefe, $reporte)->assertSessionHas('status', 'Reporte actualizado.');

        // Cambiaron 1ª y malos; asignadas, 2ª y dañadas quedaron iguales → 2 filas.
        $this->assertSame(2, ProduccionAjuste::count());
        $this->assertDatabaseHas('produccion_ajustes', [
            'reporte_id' => $reporte->id, 'campo' => 'primera', 'antes' => 700, 'despues' => 680,
            'responsable_id' => $jefe->id, 'autorizado_por' => null, 'motivo' => 'Recuento del jefe',
        ]);
        $this->assertDatabaseHas('produccion_ajustes', [
            'reporte_id' => $reporte->id, 'campo' => 'malo', 'antes' => 5, 'despues' => 25,
        ]);
        $this->assertTrue($reporte->fresh()->modificado);
    }

    public function test_ajuste_sobre_el_umbral_registra_al_solicitante_y_a_quien_autorizo(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        Queue::fake();
        $jefe = $this->jefe();
        $admin = tap(User::factory()->create(['name' => 'Admin Uno']))->assignRole('admin');
        $reporte = $this->reporteDe($this->soplador());

        // Σ|Δ| = 200 ≥ 50 → pendiente; nada se registra hasta que se aplica.
        $this->ajustar($jefe, $reporte, ['primera' => 500, 'malo' => 5]);
        $this->assertSame(0, ProduccionAjuste::count());
        $this->assertFalse($reporte->fresh()->modificado);

        $pendiente = Aprobacion::where('estado', Aprobacion::ESTADO_PENDIENTE)->sole();
        $this->actingAs($admin)->post(route('aprobaciones.aprobar', $pendiente));

        $this->assertDatabaseHas('produccion_ajustes', [
            'reporte_id' => $reporte->id, 'campo' => 'primera', 'antes' => 700, 'despues' => 500,
            'responsable_id' => $jefe->id, 'autorizado_por' => $admin->id, 'aprobacion_id' => $pendiente->id,
        ]);

        // Y el detalle del jefe nombra a los dos.
        $this->actingAs($jefe)->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()->assertSee('Jefe Bodega')->assertSee('autorizó Admin Uno');
    }

    public function test_ajuste_sin_cambios_no_marca_modificado(): void
    {
        $jefe = $this->jefe();
        $reporte = $this->reporteDe($this->soplador());

        // Mismos números, solo motivo: no hay nada que mostrarle al soplador.
        $this->ajustar($jefe, $reporte, ['primera' => 700, 'malo' => 5], 'Revisado sin cambios')
            ->assertSessionHas('status', 'Reporte actualizado.');

        $this->assertSame(0, ProduccionAjuste::count());
        $this->assertFalse($reporte->fresh()->modificado);
        $this->actingAs($jefe)->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()->assertDontSee('Modificado');
    }

    // --- El soplador lo ve -----------------------------------------------------

    public function test_el_soplador_ve_la_etiqueta_y_el_detalle_en_su_historial(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, fecha: now()->subDays(3)->toDateString());
        $this->ajustar($this->jefe('Rosa Jefa'), $reporte);

        $html = $this->actingAs($soplador)->get(route('produccion.mi.historial'))->assertOk()->getContent();

        $this->assertStringContainsString('>Modificado<', $html);
        $this->assertStringContainsString('Rosa Jefa', $html);
        // Forma contigua del cambio: ítem, antes → después (el 700 es el antes; el 680, lo que quedó).
        $this->assertStringContainsString('1ª <span class="tabular-nums">700 → <span class="font-semibold text-neutral-900">680</span>', $html);
        $this->assertStringContainsString('Malos <span class="tabular-nums">5 → <span class="font-semibold text-neutral-900">25</span>', $html);
        // El motivo NO va en la fila del historial (vive en el detalle).
        $this->assertStringNotContainsString('Recuento del jefe', $html);
    }

    public function test_el_soplador_ve_antes_y_despues_en_el_detalle_cerrado_incluso_aprobado(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, ProduccionReporte::APROBADO);
        $this->ajustar($this->jefe('Rosa Jefa'), $reporte);

        $html = $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))->assertOk()->getContent();

        $this->assertStringContainsString('>Modificado<', $html);
        $this->assertStringContainsString('Cambios del jefe', $html);
        $this->assertStringContainsString('Rosa Jefa', $html);
        $this->assertStringContainsString('1ª: 700 → <span class="font-semibold text-neutral-900">680</span>', $html);
        $this->assertStringContainsString('Motivo: Recuento del jefe', $html);
        // Y sigue aprobado: el ajuste no cambia el estado.
        $this->assertSame(ProduccionReporte::APROBADO, $reporte->fresh()->estado);
    }

    // --- El jefe lo ve --------------------------------------------------------

    public function test_el_jefe_ve_la_etiqueta_en_listas_y_detalle(): void
    {
        $jefe = $this->jefe();
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $this->ajustar($jefe, $reporte);

        $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk()->assertSee('>Modificado<', false);
        $this->actingAs($jefe)->get(route('admin.produccion.dia', ['fecha' => now()->toDateString()]))->assertOk()->assertSee('>Modificado<', false);
        $this->actingAs($jefe)->get(route('admin.produccion.soplador', $soplador))->assertOk()->assertSee('>Modificado<', false);
        $this->actingAs($jefe)->get(route('admin.produccion.reporte.show', $reporte))->assertOk()
            ->assertSee('>Modificado<', false)
            ->assertSee('Cambios del jefe')
            ->assertSee('1ª: 700 → <span class="font-semibold text-neutral-900">680</span>', false);
    }

    public function test_un_reporte_sin_ajustes_no_lleva_etiqueta(): void
    {
        $jefe = $this->jefe();
        $soplador = $this->soplador();
        $ajustado = $this->reporteDe($soplador, fecha: now()->subDay()->toDateString());
        $this->reporteDe($soplador); // intacto
        $this->ajustar($jefe, $ajustado);

        // Control positivo y negativo en la misma pantalla: dos reportes, UNA etiqueta.
        $html = $this->actingAs($jefe)->get(route('admin.produccion.soplador', $soplador))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '>Modificado<'));

        $html = $this->actingAs($soplador)->get(route('produccion.mi.historial'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '>Modificado<'));
    }

    public function test_las_etiquetas_de_campo_son_una_sola_fuente(): void
    {
        // Antes había dos copias del mapa (bandeja de aprobaciones y {cambio}
        // de las notificaciones); ahora las tres superficies leen la constante.
        $bandeja = file_get_contents(resource_path('views/aprobaciones/index.blade.php'));
        $motor = file_get_contents(app_path('Services/Aprobaciones/Aprobaciones.php'));

        $this->assertStringContainsString('ProduccionAjuste::ETIQUETAS', $bandeja);
        $this->assertStringNotContainsString("'primera' => '1ª'", $bandeja);
        $this->assertStringContainsString('ProduccionAjuste::ETIQUETAS', $motor);
        $this->assertSame(['asignadas', 'primera', 'segunda', 'malo', 'danada'], array_keys(ProduccionAjuste::ETIQUETAS));
    }
}
