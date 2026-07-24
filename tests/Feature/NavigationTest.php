<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo "Servicio Técnico" del menú (sidebar V4: acordeón renderizado desde
 * App\Support\MenuPrincipal) cuyos ítems se muestran por permiso. Cada
 * aserción usa textos que solo pueden venir del menú en el dashboard (la
 * cabecera de la página de ST no se renderiza ahí), así que confirman el
 * gateo — vale igual para desktop y móvil porque ambos son el MISMO aside.
 */
class NavigationTest extends TestCase
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

    public function test_admin_ve_todos_los_accesos_de_servicio_tecnico(): void
    {
        // "Registrar ingreso" ya NO va en el nav: vive como botón dentro de Listado.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Listado')
            ->assertDontSee('Registrar ingreso')
            ->assertSee('Ingreso por lote')
            ->assertSee('Códigos QR')
            ->assertSee('Informe');
    }

    public function test_solo_lectura_ve_listado_e_informe_pero_no_la_gestion(): void
    {
        // vendedor = 'view servicio tecnico' (sin manage, sin crear lote).
        $this->actingAs($this->usuarioCon('vendedor'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Listado')
            ->assertSee('Informe')
            ->assertDontSee('Registrar ingreso')
            ->assertDontSee('Códigos QR')
            ->assertDontSee('Ingreso por lote');
    }

    public function test_conductor_ve_el_menu_solo_con_ingreso_por_lote(): void
    {
        // conductor = solo 'crear lote servicio': ve el menú, pero únicamente
        // con el ítem de lote (antes ni siquiera veía el menú de ST).
        $this->actingAs($this->usuarioCon('conductor'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Servicio Técnico')
            ->assertSee('Ingreso por lote')
            ->assertDontSee('Registrar ingreso')
            ->assertDontSee('Códigos QR')
            ->assertDontSee('Listado')
            ->assertDontSee('Informe');
    }

    public function test_sin_permisos_de_servicio_tecnico_no_ve_el_menu(): void
    {
        // soplador = solo 'report production': nada de ST en el nav.
        $this->actingAs($this->usuarioCon('soplador'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Ingreso por lote')
            ->assertDontSee('Registrar ingreso')
            ->assertDontSee('Códigos QR');
    }
}
