<?php

namespace Tests\Feature\Admin;

use App\Models\Bodega;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M04-F1 (P-M04-10): la clasificación local de bodegas se edita desde la app.
 * Gate: `manage sucursales` (administrar la estructura), DISTINTO de
 * `manage productos` (ver el inventario) — el candado 3 del dictado v36
 * prueba la separación con un usuario que SÍ ve pero NO administra.
 */
class BodegaClasificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** Ve el inventario (manage productos) pero NO administra la estructura. */
    private function gestorCatalogo(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage productos');

        return $user;
    }

    public function test_guest_es_redirigido_al_login(): void
    {
        $bodega = Bodega::factory()->create();

        $this->get(route('admin.bodegas.edit', $bodega))->assertRedirect('/login');
    }

    public function test_ver_inventario_no_habilita_a_clasificar(): void
    {
        $bodega = Bodega::factory()->create();
        $gestor = $this->gestorCatalogo();

        // Puede VER el inventario…
        $this->actingAs($gestor)->get(route('admin.bodegas.index'))->assertOk();
        $this->actingAs($gestor)->get(route('admin.bodegas.show', $bodega))->assertOk();

        // …pero la clasificación exige manage sucursales (GET navega → Inicio
        // con aviso; mutación → 403 crudo; mismo contrato que sucursales).
        $this->actingAs($gestor)->get(route('admin.bodegas.edit', $bodega))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
        $this->actingAs($gestor)->put(route('admin.bodegas.update', $bodega), [
            'proposito' => 'fisica',
        ])->assertForbidden();

        $this->assertFalse($bodega->fresh()->clasificacion_confirmada);
    }

    public function test_quien_solo_ve_no_encuentra_los_botones_de_administrar(): void
    {
        $bodega = Bodega::factory()->create();
        $gestor = $this->gestorCatalogo();

        $index = $this->actingAs($gestor)->get(route('admin.bodegas.index'))->getContent();
        $show = $this->actingAs($gestor)->get(route('admin.bodegas.show', $bodega))->getContent();

        $this->assertStringNotContainsString('Agregar bodega', $index);
        $this->assertStringNotContainsString('Editar bodega', $show);

        // Y el admin sí los ve (control positivo del @can).
        $index = $this->actingAs($this->admin())->get(route('admin.bodegas.index'))->getContent();
        $show = $this->actingAs($this->admin())->get(route('admin.bodegas.show', $bodega))->getContent();

        $this->assertStringContainsString('Agregar bodega', $index);
        $this->assertStringContainsString('Editar bodega', $show);
    }

    public function test_admin_clasifica_y_guardar_confirma(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);
        $bodega = Bodega::factory()->create(['nombre' => 'BODEGA SANTA ROSA', 'proposito' => 'insumos']);

        // Antes de confirmar, la ficha muestra el badge de hipótesis.
        $antes = $this->actingAs($this->admin())->get(route('admin.bodegas.show', $bodega))->getContent();
        $this->assertStringContainsString('por confirmar', $antes);

        $respuesta = $this->actingAs($this->admin())->put(route('admin.bodegas.update', $bodega), [
            'sucursal_id' => $sucursal->id,
            'proposito' => 'insumos',
            'alias' => 'Santa Rosa',
            'en_operacion' => '1',
        ]);

        $respuesta->assertRedirect(route('admin.bodegas.show', $bodega));
        $respuesta->assertSessionHas('status', "Bodega {$bodega->nombre} clasificada.");

        $bodega->refresh();
        $this->assertSame($sucursal->id, $bodega->sucursal_id);
        $this->assertSame('insumos', $bodega->proposito);
        $this->assertSame('Santa Rosa', $bodega->alias);
        $this->assertTrue($bodega->en_operacion);
        $this->assertTrue($bodega->clasificacion_confirmada, 'Guardar la ficha ES confirmar (candado 6 del dictado).');

        // …y el badge desaparece (la otra mitad del candado 6).
        $despues = $this->actingAs($this->admin())->get(route('admin.bodegas.show', $bodega))->getContent();
        $this->assertStringNotContainsString('por confirmar', $despues);
    }

    public function test_actualizar_no_toca_lo_que_viene_de_bsale(): void
    {
        $bodega = Bodega::factory()->create(['nombre' => 'MIRADOR', 'bsale_office_id' => 4]);

        $this->actingAs($this->admin())->put(route('admin.bodegas.update', $bodega), [
            'proposito' => 'fisica',
            'nombre' => 'HACKEADA',          // no está en las reglas: se ignora
            'bsale_office_id' => 999,        // ídem
            'estado_baja' => 'dada_de_baja', // la baja es de F2, no de este form
        ]);

        $bodega->refresh();
        $this->assertSame('MIRADOR', $bodega->nombre);
        $this->assertSame(4, $bodega->bsale_office_id);
        $this->assertNull($bodega->estado_baja);
    }

    public function test_validacion_del_formulario(): void
    {
        $bodega = Bodega::factory()->create();
        $admin = $this->admin();

        // Sin propósito no hay clasificación.
        $this->actingAs($admin)->put(route('admin.bodegas.update', $bodega), [])
            ->assertSessionHasErrors('proposito');

        // Propósito fuera del catálogo.
        $this->actingAs($admin)->put(route('admin.bodegas.update', $bodega), ['proposito' => 'galpon'])
            ->assertSessionHasErrors('proposito');

        // Sucursal inexistente.
        $this->actingAs($admin)->put(route('admin.bodegas.update', $bodega), [
            'proposito' => 'fisica', 'sucursal_id' => 99999,
        ])->assertSessionHasErrors('sucursal_id');

        // Alias más largo que la columna (191, MySQL 5.7).
        $this->actingAs($admin)->put(route('admin.bodegas.update', $bodega), [
            'proposito' => 'fisica', 'alias' => str_repeat('a', 192),
        ])->assertSessionHasErrors('alias');

        $this->assertFalse($bodega->fresh()->clasificacion_confirmada, 'Un update rechazado no confirma nada.');
    }

    public function test_desmarcar_en_operacion_la_saca_del_scope_operativo(): void
    {
        $bodega = Bodega::factory()->create();

        // El checkbox desmarcado no viaja en el POST (idioma del navegador).
        $this->actingAs($this->admin())->put(route('admin.bodegas.update', $bodega), [
            'proposito' => 'cerrada',
        ]);

        $bodega->refresh();
        $this->assertFalse($bodega->en_operacion);
        $this->assertSame(0, Bodega::enOperacion()->count());
    }

    public function test_la_clasificacion_queda_auditada(): void
    {
        $bodega = Bodega::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.bodegas.update', $bodega), [
            'proposito' => 'taller',
        ]);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Bodega::class,
            'auditable_id' => $bodega->id,
            'event' => 'updated',
        ]);
    }

    public function test_badges_nueva_y_en_baja(): void
    {
        // Recién llegada del sync: sin propósito, sin confirmar.
        $nueva = Bodega::factory()->create(['nombre' => 'BODEGA RECIEN LLEGADA']);
        // En régimen: clasificada y confirmada.
        $clasificada = Bodega::factory()->clasificada()->create(['nombre' => 'BODEGA EN REGIMEN']);
        // En proceso de baja (columna lista para F2).
        $enBaja = Bodega::factory()->clasificada()->create([
            'nombre' => 'BODEGA SALIENTE',
            'estado_baja' => Bodega::BAJA_PENDIENTE_TRASLADO,
        ]);

        $html = $this->actingAs($this->admin())->get(route('admin.bodegas.index'))->getContent();

        $this->assertStringContainsString('nueva — por clasificar', $html);
        $this->assertStringContainsString('en baja', $html);

        // La clasificada no arrastra ninguno de los dos badges: se verifica por
        // ficha (el index mezcla las tres y una cadena suelta engaña — doctrina
        // verde-engañoso 2026-07-20).
        $ficha = $this->actingAs($this->admin())->get(route('admin.bodegas.show', $clasificada))->getContent();
        $this->assertStringNotContainsString('nueva — por clasificar', $ficha);
        $this->assertStringNotContainsString('por confirmar', $ficha);
        $this->assertStringNotContainsString('en baja', $ficha);
    }
}
