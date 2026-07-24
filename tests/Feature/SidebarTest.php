<?php

namespace Tests\Feature;

use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shell V4 (sidebar acordeón + topbar). Complementa a NavigationTest (gateo
 * de ítems por rol) con los contratos propios del shell nuevo. Anclas por
 * FORMA CONTIGUA que los componentes producen a propósito (doctrina anti
 * verde-engañoso): `href="…" aria-current="page"` (sidebar-item),
 * `<details open data-modulo="…"` (sidebar-group) y
 * `text-neutral-900">Título` (h1 de la topbar).
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

    public function test_item_activo_lleva_aria_current_y_solo_su_acordeon_abre(): void
    {
        $respuesta = $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.productos.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.productos.index').'" aria-current="page"', false)
            ->assertSee('<details open data-modulo="comercial"', false);

        // Exactamente UN acordeón abierto: si el cálculo del módulo activo se
        // pierde y todos llegan `open` (o ninguno), esto se pone rojo.
        $this->assertSame(
            1,
            substr_count($respuesta->getContent(), '<details open'),
            'Debe abrir exactamente el acordeón del módulo activo.'
        );
    }

    public function test_link_directo_activo_lleva_aria_current(): void
    {
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'" aria-current="page"', false);
    }

    public function test_pagina_de_detalle_abre_el_acordeon_via_activo_extra(): void
    {
        // El contrato activo_extra (MenuPrincipal): las pantallas de detalle
        // de ST sin ítem propio abren el acordeón del módulo y titulan la
        // topbar igual.
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('<details open data-modulo="servicio-tecnico"', false)
            ->assertSee('text-neutral-900">Servicio Técnico', false);
    }

    public function test_hamburguesa_y_campana_movil_presentes_en_toda_pagina_autenticada(): void
    {
        // Campana móvil SIEMPRE visible en la barra (hallazgo QA 14-07) y
        // hamburguesa del drawer — también fuera del dashboard. El aria-label
        // sin conteo solo lo produce la campana móvil (el partial desktop usa
        // sr-only con paréntesis; CampanitaTest cubre los conteos).
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Abrir menú')
            ->assertSee('aria-label="Notificaciones"', false);
    }

    public function test_drawer_movil_nace_oculto_sin_flash(): void
    {
        // Candado del anti-flash pre-Alpine: la clase estática
        // max-lg:-translate-x-full debe venir del SERVIDOR (Alpine solo la
        // retira al abrir). Si alguien la mueve al binding dinámico, el drawer
        // parpadea abierto en cada carga móvil.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('w-[300px] max-lg:-translate-x-full', false);
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
        // Forma contigua del h1 de la topbar (`text-neutral-900">Label`):
        // el label de la sidebar y el page-header de la página NO la
        // producen, así que esto falla si la topbar deja de titular.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.servicio-tecnico.index'))
            ->assertOk()
            ->assertSee('text-neutral-900">Servicio Técnico', false);
    }

    public function test_perfil_cae_al_nombre_de_la_app_como_titulo(): void
    {
        // Ruta fuera del menú (perfil): el h1 de la topbar cae al nombre de
        // la app (forma contigua — el <title> y el brand de la sidebar no
        // producen `text-neutral-900">DaliGo</h1>`) y ningún acordeón abre.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('text-neutral-900">'.config('app.name', 'DaliGo').'</h1>', false)
            ->assertDontSee('<details open', false);
    }
}
