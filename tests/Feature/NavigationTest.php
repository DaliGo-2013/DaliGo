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
        // «Códigos QR» ídem desde el Lote 4 (PLAN-MENU-DENSIDAD): dejó de ser
        // ítem del menú — su entrada es el botón de la cabecera del Listado.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Listado')
            ->assertDontSee('Registrar ingreso')
            ->assertSee('Ingreso por lote')
            ->assertDontSee('Códigos QR')
            ->assertSee('Informe');

        // El acceso NO se perdió: el admin lo tiene en la cabecera del Listado.
        $this->actingAs($this->usuarioCon('admin'))
            ->get(route('admin.servicio-tecnico.index'))
            ->assertOk()
            ->assertSee('Códigos QR')
            ->assertSee(route('admin.servicio-tecnico.qr'), false);
    }

    /**
     * El vendedor TIENE el ingreso por lote y NO tiene la gestión del taller.
     *
     * Este test decía lo contrario hasta el 28-08-2026 —su comentario afirmaba
     * «vendedor = view servicio tecnico (sin manage, sin crear lote)»— y el dueño
     * invirtió esa mitad: *«el vendedor tiene que tener la opción para ver el
     * ingreso por lote si en algún momento pasa»*. Se invierte la aserción, no se
     * afloja: la otra mitad —que el lote NO le abre la gestión— es la que importa
     * y queda igual de firme.
     *
     * `crear lote servicio` es el permiso ACOTADO del conductor: registra máquinas
     * que llegan en ruta y nada más. Que sea aparte de `manage servicio tecnico` es
     * lo que permite darle una puerta sin darle el taller entero.
     */
    public function test_el_vendedor_tiene_el_lote_pero_no_la_gestion_del_taller(): void
    {
        $this->actingAs($this->usuarioCon('vendedor'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Listado')
            ->assertSee('Informe')
            // La puerta que el dueño le dio (28-08): el ingreso por lote.
            ->assertSee('Ingreso por lote')
            // Y las dos que siguen siendo de `manage servicio tecnico`.
            ->assertDontSee('Registrar ingreso')
            ->assertDontSee('Códigos QR');

        // Tampoco en la cabecera del Listado: el botón QR es de `manage`, y al
        // vendedor (solo `view`) la pantalla QR le daría 403 (Lote 4).
        $this->actingAs($this->usuarioCon('vendedor'))
            ->get(route('admin.servicio-tecnico.index'))
            ->assertOk()
            ->assertDontSee('Códigos QR');

        // El lote se le abre de verdad, no es solo un ítem en el menú.
        $this->actingAs($this->usuarioCon('vendedor'))
            ->get(route('admin.servicio-tecnico.lote.create'))
            ->assertOk();
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
