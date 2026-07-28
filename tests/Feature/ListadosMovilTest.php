<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navegar listados largos en el celular. Los listados paginan de 25 en 25 y en
 * móvil cada fila ocupa ~150px, así que la página del listado de Servicio
 * Técnico mide unas 6 pantallas: volver al buscador de arriba obligaba a
 * recorrer todo de vuelta.
 */
class ListadosMovilTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    public function test_el_volver_arriba_vive_en_el_layout_y_no_en_las_vistas(): void
    {
        // Va UNA sola vez en layouts/app.blade.php: así cubre las 69 pantallas
        // sin que cada vista tenga que acordarse. Si alguien lo copia a una
        // vista, aparecería dos veces en esa pantalla y este candado lo caza.
        $html = $this->actingAs($this->admin())
            ->get('/admin/servicio-tecnico')
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'aria-label="Volver arriba"') + substr_count($html, 'title="Volver arriba"'),
            'El botón "volver arriba" debe salir exactamente una vez (vive en el layout, no en la vista).',
        );
    }

    public function test_el_volver_arriba_es_solo_movil_y_aparece_recien_al_bajar(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/servicio-tecnico')
            ->assertOk()
            ->getContent();

        // sm:hidden — en escritorio la ruedita y la barra de scroll ya resuelven.
        $this->assertStringContainsString('sm:hidden', $html);
        // Umbral de 600px: antes de eso el buscador está a la vista y estorbaría.
        $this->assertStringContainsString('window.scrollY > 600', $html);
        // z-20: el drawer (z-40) y los modales (z-50) deben taparlo.
        $this->assertStringContainsString('z-20', $html);
    }

    public function test_el_dashboard_tambien_lo_trae_por_venir_del_layout(): void
    {
        // No es exclusivo del listado de ST: cualquier pantalla del layout lo hereda.
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Volver arriba');
    }
}
