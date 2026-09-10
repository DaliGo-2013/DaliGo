<?php

namespace Tests\Feature;

use App\Models\PlanHito;
use App\Models\User;
use App\Support\FechaNegocio;
use App\Support\PlanProyecto;
use Database\Seeders\PlanHitosSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/**
 * Hitos del plan editables desde /plan (P-PLAN-06, pedido del dueño
 * 10-09-2026): dejaron de ser una constante del repo y viven en `plan_hitos`.
 * Lo que se vigila: que el seeder los siembre UNA vez y no pise ediciones, que
 * el CRUD exija el permiso de gestión, la validación, y que la página y el
 * Excel lean de la BD (si no, editar no cambiaría nada visible).
 */
class PlanHitosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuarioQueVe(): User
    {
        return tap(User::factory()->create())->givePermissionTo('ver plan proyecto');
    }

    private function usuarioQueGestiona(): User
    {
        return tap(User::factory()->create())
            ->givePermissionTo(['ver plan proyecto', 'gestionar plan proyecto']);
    }

    // --- Semilla ---

    public function test_el_seeder_siembra_los_hitos_iniciales_y_no_pisa_lo_editado(): void
    {
        $this->seed(PlanHitosSeeder::class);
        $this->assertDatabaseCount('plan_hitos', count(PlanProyecto::HITOS));
        $this->assertDatabaseHas('plan_hitos', ['clave' => 'H2', 'cumplido' => true]);

        // El gerente corre un hito y marca otro: el próximo deploy (db:seed)
        // NO los devuelve a la semilla.
        $h3 = PlanHito::where('clave', "H3'")->firstOrFail();
        $h3->update(['etiqueta' => 'Transversales listos (corrido)', 'fecha' => '2026-11-20', 'cumplido' => true]);

        $this->seed(PlanHitosSeeder::class);

        $this->assertDatabaseCount('plan_hitos', count(PlanProyecto::HITOS));
        $h3->refresh();
        $this->assertSame('Transversales listos (corrido)', $h3->etiqueta);
        $this->assertSame('2026-11-20', $h3->fecha->toDateString());
        $this->assertTrue($h3->cumplido);
    }

    // --- Permisos ---

    public function test_crear_editar_y_borrar_exigen_el_permiso_de_gestion(): void
    {
        $hito = PlanHito::create(['clave' => 'H9', 'etiqueta' => 'X', 'fecha' => '2026-12-01']);
        $lector = $this->usuarioQueVe();
        $payload = ['clave' => 'H10', 'etiqueta' => 'Colado', 'fecha' => '2026-12-02'];

        // Las ACCIONES (no-GET) conservan su 403 (D-014: solo la navegación
        // GET se redirige al Inicio con aviso).
        $this->actingAs($lector)->post(route('plan.hitos.store'), $payload)->assertForbidden();
        $this->actingAs($lector)->patch(route('plan.hitos.update', $hito), $payload)->assertForbidden();
        $this->actingAs($lector)->delete(route('plan.hitos.destroy', $hito))->assertForbidden();

        $this->assertDatabaseCount('plan_hitos', 1);
        $this->assertSame('H9', $hito->fresh()->clave);
    }

    public function test_el_lector_ve_los_hitos_pero_no_los_controles_de_edicion(): void
    {
        PlanHito::create(['clave' => 'H9', 'etiqueta' => 'Hito solo lectura', 'fecha' => '2026-12-01']);

        $this->actingAs($this->usuarioQueVe())->get(route('plan.index'))
            ->assertOk()
            ->assertSee('Hito solo lectura')
            ->assertDontSee('Agregar hito')
            ->assertDontSee(route('plan.hitos.store'));
    }

    // --- CRUD ---

    public function test_crear_editar_y_eliminar_un_hito(): void
    {
        $gestor = $this->usuarioQueGestiona();

        $this->actingAs($gestor)->post(route('plan.hitos.store'), [
            'clave' => 'H8',
            'etiqueta' => 'Facturación electrónica en Mirador',
            'fecha' => '2026-11-15',
        ])->assertRedirect(route('plan.index'))->assertSessionHas('status', 'Hito agregado.');

        $hito = PlanHito::where('clave', 'H8')->firstOrFail();
        $this->assertSame('2026-11-15', $hito->fecha->toDateString());
        $this->assertFalse($hito->cumplido);

        // Editar: corre la fecha y lo marca cumplido (checkbox presente).
        $this->actingAs($gestor)->patch(route('plan.hitos.update', $hito), [
            'clave' => 'H8',
            'etiqueta' => 'Facturación electrónica en Mirador',
            'fecha' => '2026-11-30',
            'cumplido' => '1',
        ])->assertRedirect(route('plan.index'));
        $hito->refresh();
        $this->assertSame('2026-11-30', $hito->fecha->toDateString());
        $this->assertTrue($hito->cumplido);

        // Desmarcar: el checkbox llega AUSENTE, no como 0 — debe leerse como false.
        $this->actingAs($gestor)->patch(route('plan.hitos.update', $hito), [
            'clave' => 'H8',
            'etiqueta' => 'Facturación electrónica en Mirador',
            'fecha' => '2026-11-30',
        ])->assertRedirect(route('plan.index'));
        $this->assertFalse($hito->fresh()->cumplido);

        $this->actingAs($gestor)->delete(route('plan.hitos.destroy', $hito))
            ->assertRedirect(route('plan.index'));
        $this->assertDatabaseCount('plan_hitos', 0);
    }

    public function test_valida_clave_unica_fecha_y_obligatorios(): void
    {
        $gestor = $this->usuarioQueGestiona();
        $existente = PlanHito::create(['clave' => 'H8', 'etiqueta' => 'Ya existe', 'fecha' => '2026-12-01']);

        // Clave repetida en el alta.
        $this->actingAs($gestor)->post(route('plan.hitos.store'), [
            'clave' => 'H8', 'etiqueta' => 'Otro', 'fecha' => '2026-12-02',
        ])->assertSessionHasErrors('clave');

        // Fecha que no es una fecha.
        $this->actingAs($gestor)->post(route('plan.hitos.store'), [
            'clave' => 'H9', 'etiqueta' => 'Otro', 'fecha' => '15-11-2026',
        ])->assertSessionHasErrors('fecha');

        // Sin etiqueta.
        $this->actingAs($gestor)->post(route('plan.hitos.store'), [
            'clave' => 'H9', 'fecha' => '2026-11-15',
        ])->assertSessionHasErrors('etiqueta');

        $this->assertDatabaseCount('plan_hitos', 1);

        // Editar conservando su PROPIA clave no choca con el unique.
        $this->actingAs($gestor)->patch(route('plan.hitos.update', $existente), [
            'clave' => 'H8', 'etiqueta' => 'Renombrado', 'fecha' => '2026-12-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Renombrado', $existente->fresh()->etiqueta);
    }

    // --- Lectura: la página y el Excel leen de la BD ---

    public function test_la_pagina_muestra_el_countdown_de_cada_hito_desde_la_bd(): void
    {
        $hoy = Carbon::parse(FechaNegocio::hoy());
        // Creados en orden DISTINTO al de fecha, para que el orden por id no
        // pase el assert de orden por casualidad.
        PlanHito::create(['clave' => 'HP', 'etiqueta' => 'Hito que viene', 'fecha' => $hoy->copy()->addDays(10)->toDateString()]);
        PlanHito::create(['clave' => 'HC', 'etiqueta' => 'Hito ya cumplido', 'fecha' => $hoy->copy()->subDays(30)->toDateString(), 'cumplido' => true]);
        PlanHito::create(['clave' => 'HA', 'etiqueta' => 'Hito que se pasó', 'fecha' => $hoy->copy()->subDays(3)->toDateString()]);

        $this->actingAs($this->usuarioQueVe())->get(route('plan.index'))
            ->assertOk()
            ->assertSee('Hito ya cumplido')->assertSee('Cumplido')
            ->assertSee('Hito que se pasó')->assertSee('Atrasado 3 d')
            ->assertSee('Hito que viene')->assertSee('Faltan 10 d')
            // Orden por fecha: el cumplido (más antiguo) va primero.
            ->assertSeeInOrder(['Hito ya cumplido', 'Hito que se pasó', 'Hito que viene']);
    }

    public function test_un_hito_agregado_en_la_ui_viaja_al_excel(): void
    {
        PlanHito::create(['clave' => 'H8', 'etiqueta' => 'Hito nacido en la pantalla', 'fecha' => '2026-11-15']);
        $admin = tap(User::factory()->create())->assignRole('admin');

        $res = $this->actingAs($admin)->get(route('plan.excel'));
        $res->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsxhitos');
        file_put_contents($tmp, $res->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertStringContainsString('>H8<', $hoja);
        $this->assertStringContainsString('Hito nacido en la pantalla', $hoja);
        $this->assertStringContainsString('15-11-2026', $hoja);
    }
}
