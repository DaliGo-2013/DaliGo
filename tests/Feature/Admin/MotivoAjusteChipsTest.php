<?php

namespace Tests\Feature\Admin;

use App\Models\Aprobacion;
use App\Models\Configuracion;
use App\Models\Notificacion;
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
 * #6 del QA 15-07 (idea del dueño, dictado v27): el motivo del ajuste deja de
 * ser texto libre y pasa a CHIPS parametrizables — lenguaje común entre quien
 * pide el ajuste y quien lo aprueba. La lista vive en Configuración
 * (`motivos_ajuste_produccion`, editable por UI); «Otro» conserva la salida
 * de escape con texto libre (centinela resuelto ANTES de validar, patrón de
 * la bitácora 2026-06-26/30).
 */
class MotivoAjusteChipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        Queue::fake();
    }

    public function test_el_form_de_ajuste_ofrece_los_motivos_sembrados_como_chips(): void
    {
        [$jefe, $reporte] = $this->escenario();

        $this->actingAs($jefe)->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            // Un chip por motivo sembrado (el value del radio es marcador estable).
            ->assertSee('value="Conteo corregido en bodega"', false)
            ->assertSee('value="Error de digitación del soplador"', false)
            // La salida de escape del «Otro» sigue disponible.
            ->assertSee('name="motivo_ajuste_otro"', false);
    }

    public function test_ajustar_con_un_chip_de_la_lista_registra_ese_motivo(): void
    {
        [$jefe, $reporte] = $this->escenario();

        // Δ chico (20 < umbral 50): aplica al tiro, como siempre.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 110, 'primera' => 10, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => 'Conteo corregido en bodega',
        ])->assertSessionHas('status', 'Reporte actualizado.');

        $this->assertSame('Conteo corregido en bodega', $reporte->fresh()->motivo_ajuste);
        // El motivo estandarizado viaja tal cual al registro de aprobación.
        $this->assertSame('Conteo corregido en bodega', Aprobacion::sole()->motivo);
    }

    public function test_ajustar_con_otro_usa_el_texto_libre(): void
    {
        [$jefe, $reporte] = $this->escenario();

        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 110, 'primera' => 10, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => ProduccionReporte::MOTIVO_OTRO,
            'motivo_ajuste_otro' => 'El cliente devolvió una tarima completa',
        ])->assertSessionHas('status', 'Reporte actualizado.');

        $this->assertSame('El cliente devolvió una tarima completa', $reporte->fresh()->motivo_ajuste);
    }

    public function test_otro_sin_texto_es_rechazado_y_el_reporte_queda_intacto(): void
    {
        [$jefe, $reporte] = $this->escenario();

        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 110, 'primera' => 10, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => ProduccionReporte::MOTIVO_OTRO,
            'motivo_ajuste_otro' => '   ',
        ])->assertSessionHasErrors('motivo_ajuste');

        $this->assertSame(100, $reporte->fresh()->asignadas);
        $this->assertSame(0, Aprobacion::count());
    }

    public function test_el_cambio_de_la_notificacion_no_duplica_el_motivo(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');

        // Δ grande (130 ≥ 50): pendiente → notifica al admin con {cambio}.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 150, 'primera' => 80, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => 'Merma mal clasificada',
        ]);

        $notif = Notificacion::where('evento', 'aprobacion.solicitada')
            ->where('user_id', $admin->id)->firstOrFail();

        // Positivo Y negativo (anti verde-engañoso): el cambio trae las
        // cantidades pero NO el motivo — ese ya viaja como {motivo}.
        $this->assertStringContainsString('Asignadas: 100 → 150', $notif->payload['cambio']);
        $this->assertStringNotContainsString('Motivo_ajuste', $notif->payload['cambio']);
        $this->assertStringContainsString('Merma mal clasificada', $notif->cuerpo); // via {motivo}
    }

    public function test_los_chips_se_editan_desde_la_ui_de_configuracion(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');
        $config = Configuracion::where('clave', 'motivos_ajuste_produccion')->sole();

        // El admin edita la lista por la UI (mismo endpoint del resto de claves).
        $this->actingAs($admin)->put(route('admin.configuracion.update', $config), [
            'valor' => json_encode(['Motivo nuevo de la casa'], JSON_UNESCAPED_UNICODE),
        ])->assertRedirect(route('admin.configuracion.index'));

        $this->actingAs($jefe)->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            ->assertSee('value="Motivo nuevo de la casa"', false)
            ->assertDontSee('value="Conteo corregido en bodega"', false);
    }

    public function test_una_lista_rota_degrada_a_solo_otro_sin_reventar(): void
    {
        [$jefe, $reporte] = $this->escenario();
        // Edición manual rota: un JSON válido pero que no es lista de strings.
        Configuracion::set('motivos_ajuste_produccion', ['a' => ['no', 'plana'], 'b' => 7]);

        $this->actingAs($jefe)->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            // Queda el chip «Otro» como salida de escape (el form no se cae).
            ->assertSee('name="motivo_ajuste_otro"', false);
    }

    /** @return array{0: User, 1: ProduccionReporte} jefe_bodega + reporte (100 asignadas, resto 0) */
    private function escenario(): array
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 100,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $asignacion->fecha,
            'turno' => 'dia',
            'asignadas' => 100,
            'estado' => ProduccionReporte::BORRADOR,
        ]);

        return [$jefe, $reporte];
    }
}
