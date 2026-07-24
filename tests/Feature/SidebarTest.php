<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shell V4 (sidebar acordeón + topbar). Complementa a NavigationTest (gateo
 * de ítems por rol) con los contratos propios del shell nuevo: ítem activo
 * marcado, acordeón del módulo activo abierto, hamburguesa y campana móvil
 * presentes en cualquier página autenticada, y poda fuera del dashboard.
 * Doctrina: asertar por ruta/aria/forma contigua, no HTML pegado.
 */
class SidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuarioCon(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    public function test_item_activo_lleva_aria_current_y_su_acordeon_abre(): void
    {
        // El componente imprime href y aria-current contiguos a propósito:
        // la forma es estable por diseño, no un accidente del markup.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.productos.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.productos.index').'" aria-current="page"', false)
            ->assertSee('<details open', false);
    }

    public function test_link_directo_activo_lleva_aria_current(): void
    {
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'" aria-current="page"', false);
    }

    public function test_hamburguesa_y_campana_movil_presentes_en_toda_pagina_autenticada(): void
    {
        // Campana móvil SIEMPRE visible en la barra (hallazgo QA 14-07) y
        // hamburguesa del drawer — también fuera del dashboard.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Abrir menú')
            ->assertSee(route('notificaciones.index'), false)
            ->assertSee('aria-label="Notificaciones"', false);
    }

    public function test_sidebar_se_poda_por_permisos_fuera_del_dashboard(): void
    {
        // Generaliza AprobacionAccionableTest: la poda del menú aplica en
        // TODAS las páginas, no solo en el dashboard.
        $this->actingAs($this->usuarioCon('soplador'))
            ->get(route('produccion.mi.index'))
            ->assertOk()
            ->assertSee(route('produccion.mi.index'), false)
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee('Administración');
    }

    public function test_topbar_muestra_el_titulo_del_modulo_activo(): void
    {
        // En una pantalla de ST el título del módulo aparece en la topbar
        // (además del label del acordeón): 2 apariciones mínimo.
        $respuesta = $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.servicio-tecnico.index'))
            ->assertOk();

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($respuesta->getContent(), 'Servicio Técnico'),
            'El título del módulo activo debe verse en topbar y sidebar.'
        );
    }

    public function test_perfil_cae_al_nombre_de_la_app_como_titulo(): void
    {
        // Ruta fuera del menú (perfil): el título de topbar no inventa módulo.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(config('app.name'));
    }
}
