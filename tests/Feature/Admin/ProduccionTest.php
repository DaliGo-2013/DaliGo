<?php

namespace Tests\Feature\Admin;

use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduccionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('Jefatura');
    }

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('Soplador');
    }

    private function reporteDe(User $soplador, int $asignadas = 100, string $estado = ProduccionReporte::BORRADOR): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => $asignadas,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => $asignadas,
            'estado' => $estado,
        ]);
    }

    // --- Acceso / gating ---

    public function test_jefe_puede_ver_el_panel(): void
    {
        $this->actingAs($this->jefe())->get('/admin/produccion')->assertOk();
    }

    public function test_soplador_no_puede_ver_el_panel_del_jefe(): void
    {
        $this->actingAs($this->soplador())->get('/admin/produccion')->assertForbidden();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/admin/produccion')->assertRedirect('/login');
        $this->get('/produccion/mi-reporte')->assertRedirect('/login');
    }

    public function test_member_sin_permiso_no_entra_a_mi_produccion(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');
        $this->actingAs($member)->get('/produccion/mi-reporte')->assertForbidden();
    }

    // --- Asignacion (jefe) ---

    public function test_jefe_asigna_y_crea_reporte_en_borrador(): void
    {
        $soplador = $this->soplador();

        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), [
            'soplador_id' => $soplador->id,
            'turno' => 'dia',
            'fecha' => now()->toDateString(),
            'asignadas' => 1200,
        ])->assertRedirect(route('admin.produccion.index'));

        $this->assertDatabaseHas('produccion_asignaciones', [
            'soplador_id' => $soplador->id, 'asignadas' => 1200, 'turno' => 'dia',
        ]);
        $this->assertDatabaseHas('produccion_reportes', [
            'soplador_id' => $soplador->id, 'asignadas' => 1200, 'estado' => 'borrador',
        ]);
    }

    public function test_reasignar_actualiza_cantidad_sin_duplicar(): void
    {
        $soplador = $this->soplador();
        $payload = ['soplador_id' => $soplador->id, 'turno' => 'dia', 'fecha' => now()->toDateString()];

        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), $payload + ['asignadas' => 100]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.asignar.store'), $payload + ['asignadas' => 250]);

        $this->assertSame(1, ProduccionAsignacion::where('soplador_id', $soplador->id)->count());
        $this->assertDatabaseHas('produccion_asignaciones', ['soplador_id' => $soplador->id, 'asignadas' => 250]);
    }

    // --- Reporte del soplador ---

    public function test_soplador_ve_su_reporte_del_dia(): void
    {
        $soplador = $this->soplador();
        $this->reporteDe($soplador);

        $this->actingAs($soplador)->get('/produccion/mi-reporte')->assertOk();
    }

    public function test_soplador_guarda_borrador(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'primera' => 50, 'segunda' => 10, 'malo' => 5, 'enviar' => 0,
        ])->assertRedirect(route('produccion.mi.index'));

        $reporte->refresh();
        $this->assertSame('borrador', $reporte->estado);
        $this->assertSame(50, $reporte->primera);
    }

    public function test_soplador_envia_reporte_cuadrado(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'primera' => 90, 'segunda' => 5, 'malo' => 5, 'enviar' => 1,
        ])->assertRedirect(route('produccion.mi.index'));

        $reporte->refresh();
        $this->assertSame('enviado', $reporte->estado);
        $this->assertNotNull($reporte->enviado_at);
    }

    public function test_enviar_con_diferencia_exige_motivo(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'primera' => 50, 'segunda' => 0, 'malo' => 0, 'enviar' => 1,
        ])->assertSessionHasErrors('motivo');

        $this->assertSame('borrador', $reporte->fresh()->estado);
    }

    public function test_soplador_no_edita_reporte_ajeno(): void
    {
        $reporte = $this->reporteDe($this->soplador());
        $otro = $this->soplador();

        $this->actingAs($otro)->patch(route('produccion.mi.update', $reporte), [
            'primera' => 1, 'segunda' => 0, 'malo' => 0, 'enviar' => 0,
        ])->assertForbidden();
    }

    public function test_reporte_enviado_no_es_editable_por_el_soplador(): void
    {
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador, 100, ProduccionReporte::ENVIADO);

        $this->actingAs($soplador)->patch(route('produccion.mi.update', $reporte), [
            'primera' => 1, 'segunda' => 0, 'malo' => 0, 'enviar' => 0,
        ])->assertForbidden();
    }

    // --- Revision (jefe) ---

    public function test_jefe_aprueba_reporte_enviado(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte))
            ->assertRedirect(route('admin.produccion.index'));

        $this->assertSame('aprobado', $reporte->fresh()->estado);
    }

    public function test_jefe_no_aprueba_borrador(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::BORRADOR);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        $this->assertSame('borrador', $reporte->fresh()->estado);
    }

    public function test_jefe_devuelve_con_motivo(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.devolver', $reporte), [
            'devuelto_motivo' => 'Faltan los malos del segundo molde.',
        ])->assertRedirect(route('admin.produccion.index'));

        $reporte->refresh();
        $this->assertSame('devuelto', $reporte->estado);
        $this->assertSame('Faltan los malos del segundo molde.', $reporte->devuelto_motivo);
    }

    public function test_devolver_exige_motivo(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.devolver', $reporte), [])
            ->assertSessionHasErrors('devuelto_motivo');
    }

    public function test_jefe_ajusta_cantidades_con_motivo(): void
    {
        $reporte = $this->reporteDe($this->soplador(), 100, ProduccionReporte::ENVIADO);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'primera' => 80, 'segunda' => 15, 'malo' => 5, 'motivo_ajuste' => 'Recuento físico.',
        ])->assertRedirect(route('admin.produccion.reporte.show', $reporte));

        $reporte->refresh();
        $this->assertSame(80, $reporte->primera);
        $this->assertSame('Recuento físico.', $reporte->motivo_ajuste);
    }
}
