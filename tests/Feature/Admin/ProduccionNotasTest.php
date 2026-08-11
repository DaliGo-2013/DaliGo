<?php

namespace Tests\Feature\Admin;

use App\Models\ProduccionAsignacion;
use App\Models\ProduccionNota;
use App\Models\ProduccionReporte;
use App\Models\User;
use App\Support\AvisosError;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P-M11-22 · Notas del jefe. Candados del dictado v21: (3) nota vencida o de
 * otro soplador NO se pinta, la global si; (4) 403 del CRUD sin permiso.
 */
class ProduccionNotasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('soplador');
    }

    private function nota(array $attrs = []): ProduccionNota
    {
        return ProduccionNota::create(array_merge([
            'autor_id' => $this->jefe()->id,
            'soplador_id' => null,
            'texto' => 'Hoy llegan preformas nuevas a las 15:00',
        ], $attrs));
    }

    private function reporteDe(User $soplador): ProduccionReporte
    {
        $fecha = FechaNegocio::hoy();

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => $fecha, 'turno' => 'dia', 'asignadas' => 100,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id,
            'fecha' => $fecha, 'turno' => 'dia', 'asignadas' => 100,
            'estado' => ProduccionReporte::BORRADOR,
        ]);
    }

    // --- Gating (candado 4) ---

    public function test_sin_permiso_no_se_gestionan_notas(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        // GET navegable = redirect + aviso; POST = 403 crudo (contrato de la casa).
        $this->actingAs($member)->get(route('admin.produccion.notas.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', AvisosError::SIN_PERMISO);

        $this->actingAs($member)->post(route('admin.produccion.notas.store'), ['texto' => 'X'])
            ->assertForbidden();

        $this->actingAs($this->soplador())->get(route('admin.produccion.notas.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_el_jefe_ve_el_listado_y_resalta_un_solo_item_del_menu(): void
    {
        $this->nota();

        $html = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.notas.index'))
            ->assertOk()
            ->assertSee('Hoy llegan preformas nuevas a las 15:00')
            ->getContent();

        // La ruta nueva cae en el patron del item Produccion y en NINGUN otro
        // (el barrido de SidebarTest no cubre rutas que no son items del menu).
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    // --- CRUD ---

    public function test_el_jefe_crea_una_nota(): void
    {
        $jefe = $this->jefe();
        $soplador = $this->soplador();

        $this->actingAs($jefe)->post(route('admin.produccion.notas.store'), [
            'texto' => 'Cuidado con el molde 3',
            'soplador_id' => $soplador->id,
            'vigente_desde' => '',
            'vigente_hasta' => '',
        ])->assertRedirect(route('admin.produccion.notas.index'));

        $this->assertDatabaseHas('produccion_notas', [
            'texto' => 'Cuidado con el molde 3',
            'soplador_id' => $soplador->id,
            'autor_id' => $jefe->id,
        ]);
    }

    public function test_crear_exige_texto_y_cronologia(): void
    {
        $jefe = $this->jefe();

        $this->actingAs($jefe)->post(route('admin.produccion.notas.store'), ['texto' => ''])
            ->assertSessionHasErrors('texto');

        $this->actingAs($jefe)->post(route('admin.produccion.notas.store'), [
            'texto' => 'X',
            'vigente_desde' => '2026-08-20',
            'vigente_hasta' => '2026-08-10',
        ])->assertSessionHasErrors('vigente_hasta');
    }

    public function test_el_jefe_edita_y_elimina(): void
    {
        $jefe = $this->jefe();
        $nota = $this->nota();

        $this->actingAs($jefe)->put(route('admin.produccion.notas.update', $nota), [
            'texto' => 'Texto corregido',
        ])->assertRedirect(route('admin.produccion.notas.index'));

        $this->assertSame('Texto corregido', $nota->fresh()->texto);

        $this->actingAs($jefe)->delete(route('admin.produccion.notas.destroy', $nota))
            ->assertRedirect();

        $this->assertDatabaseMissing('produccion_notas', ['id' => $nota->id]);
    }

    // --- Vigencia (frontera de fechas, candado 3) ---

    public function test_la_vigencia_respeta_los_bordes_del_dia(): void
    {
        $hoy = FechaNegocio::hoy();
        $manana = Carbon::parse($hoy)->addDay()->toDateString();
        $ayer = Carbon::parse($hoy)->subDay()->toDateString();

        $desdeHoy = $this->nota(['vigente_desde' => $hoy]);
        $desdeManana = $this->nota(['vigente_desde' => $manana]);
        $vencidaAyer = $this->nota(['vigente_hasta' => $ayer]);
        $hastaHoy = $this->nota(['vigente_hasta' => $hoy]);
        $sinFechas = $this->nota();

        $vigentes = ProduccionNota::vigentes()->pluck('id')->all();

        $this->assertContains($desdeHoy->id, $vigentes);
        $this->assertContains($hastaHoy->id, $vigentes);
        $this->assertContains($sinFechas->id, $vigentes);
        $this->assertNotContains($desdeManana->id, $vigentes);
        $this->assertNotContains($vencidaAyer->id, $vigentes);

        // El helper en PHP dice lo mismo que el scope (badge del listado).
        $this->assertTrue($desdeHoy->esVigente());
        $this->assertFalse($desdeManana->esVigente());
        $this->assertFalse($vencidaAyer->esVigente());
    }

    // --- El banner en mi-reporte (candado 3) ---

    public function test_el_soplador_ve_la_global_y_la_suya_pero_no_la_ajena_ni_la_vencida(): void
    {
        $soplador = $this->soplador();
        $otro = $this->soplador();
        $reporte = $this->reporteDe($soplador);

        $this->nota(['texto' => 'Nota global para toda la planta']);
        $this->nota(['texto' => 'Nota personal tuya', 'soplador_id' => $soplador->id]);
        $this->nota(['texto' => 'Nota de otro soplador', 'soplador_id' => $otro->id]);
        $this->nota(['texto' => 'Nota vencida vieja', 'vigente_hasta' => Carbon::parse(FechaNegocio::hoy())->subDay()->toDateString()]);

        $this->actingAs($soplador)
            ->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('Nota global para toda la planta')
            ->assertSee('Nota personal tuya')
            ->assertDontSee('Nota de otro soplador')
            ->assertDontSee('Nota vencida vieja');
    }

    public function test_el_banner_tambien_se_ve_en_un_reporte_enviado(): void
    {
        // El banner vive ANTES del split de ramas de mi-reporte: un reporte ya
        // enviado (solo lectura) sigue mostrando la nota vigente.
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);
        $reporte->update(['estado' => ProduccionReporte::ENVIADO]);

        $this->nota(['texto' => 'Aviso general de planta']);

        $this->actingAs($soplador)
            ->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('Aviso general de planta');
    }
}
