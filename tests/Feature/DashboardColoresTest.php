<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccesosDashboard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Color personalizable de las cards de accesos del Inicio (M16, D-013):
 * preferencia POR USUARIO (users.dashboard_colores), paleta cerrada de 8,
 * default sobrio (naranjo operativo / gris administración).
 */
class DashboardColoresTest extends TestCase
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

    // --- Endpoint de guardado ---------------------------------------------------

    public function test_guest_no_puede_guardar_colores(): void
    {
        $this->patchJson(route('dashboard.colores.update'), ['colores' => ['catalogo' => 'verde']])
            ->assertUnauthorized();
    }

    public function test_usuario_guarda_colores_validos_y_persisten(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('dashboard.colores.update'), ['colores' => ['catalogo' => 'verde', 'usuarios' => 'celeste']])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(['catalogo' => 'verde', 'usuarios' => 'celeste'], $user->fresh()->dashboard_colores);
    }

    public function test_rechaza_color_fuera_de_paleta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('dashboard.colores.update'), ['colores' => ['catalogo' => 'fucsia']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('colores.catalogo');

        $this->assertNull($user->fresh()->dashboard_colores);
    }

    public function test_rechaza_card_desconocida(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('dashboard.colores.update'), ['colores' => ['hackerman' => 'verde']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('colores');

        $this->assertNull($user->fresh()->dashboard_colores);
    }

    // --- Render en el dashboard --------------------------------------------------

    public function test_dashboard_pinta_color_personalizado(): void
    {
        $user = $this->admin();
        $user->dashboard_colores = ['catalogo' => 'verde'];
        $user->save();

        // OJO verde-engañoso: 'bg-emerald-100' a secas SIEMPRE está en la
        // página (viaja en el mapa paleta del x-data para el repintado). La
        // forma contigua 'duration-150 bg-emerald-100' solo la produce el
        // squircle renderizado server-side con ese color aplicado.
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('duration-150 bg-emerald-100', false);
    }

    public function test_colores_por_defecto_sobrios(): void
    {
        // Sin preferencia: naranjo (operativo) / gris (administración). Se
        // asserta por viewData — el markup comparte bg-brand-50 con el CTA
        // del operario y sería un verde-engañoso.
        $res = $this->actingAs($this->admin())->get('/dashboard');
        $res->assertOk();

        $cards = collect($res->viewData('accesos'))->flatten(1)->pluck('color', 'key');
        $this->assertSame('naranjo', $cards['catalogo']);
        $this->assertSame('naranjo', $cards['produccion']);
        $this->assertSame('naranjo', $cards['servicio-tecnico']);
        $this->assertSame('gris', $cards['usuarios']);
        $this->assertSame('gris', $cards['auditoria']);
    }

    public function test_color_invalido_persistido_cae_al_default(): void
    {
        $user = $this->admin();
        $user->dashboard_colores = ['catalogo' => 'fucsia']; // legacy/corrupto
        $user->save();

        $res = $this->actingAs($user)->get('/dashboard');

        $cards = collect($res->viewData('accesos'))->flatten(1)->pluck('color', 'key');
        $this->assertSame('naranjo', $cards['catalogo']);
    }

    public function test_la_preferencia_es_por_perfil(): void
    {
        $decorador = $this->admin();
        $decorador->dashboard_colores = ['catalogo' => 'violeta'];
        $decorador->save();

        // Otro usuario sigue viendo el default sobrio: ninguna card violeta
        // (el 'bg-violet-100' suelto sí existe siempre — mapa paleta del
        // x-data — así que el negativo va contra la forma del squircle).
        $res = $this->actingAs($this->admin())->get('/dashboard');
        $res->assertOk()->assertDontSee('duration-150 bg-violet-100', false);

        $cards = collect($res->viewData('accesos'))->flatten(1)->pluck('color', 'key');
        $this->assertNotContains('violeta', $cards->all());
    }

    public function test_paleta_del_blade_cubre_todas_las_keys(): void
    {
        // Candado anti-drift entre AccesosDashboard::COLORES (PHP) y el mapa
        // $paleta del Blade (clases literales): cada key debe pintar su clase.
        $esperadas = [
            'naranjo' => 'bg-brand-50',
            'gris' => 'bg-neutral-100',
            'celeste' => 'bg-sky-100',
            'verde' => 'bg-emerald-100',
            'ambar' => 'bg-amber-100',
            'violeta' => 'bg-violet-100',
            'turquesa' => 'bg-teal-100',
            'indigo' => 'bg-indigo-100',
        ];
        $this->assertSame(AccesosDashboard::COLORES, array_keys($esperadas));

        $user = $this->admin();
        foreach (AccesosDashboard::COLORES as $color) {
            $user->dashboard_colores = ['catalogo' => $color];
            $user->save();

            // Forma contigua del squircle (no el mapa paleta del x-data, que
            // trae todas las clases siempre — sería un verde-engañoso).
            $this->actingAs($user)->get('/dashboard')
                ->assertSee('duration-150 ' . $esperadas[$color], false);
        }
    }

    public function test_toda_card_tiene_icono_existente(): void
    {
        // Cada icon declarado en AccesosDashboard debe existir como
        // componente <x-icon.*> (x-dynamic-component fallaría en runtime).
        foreach (AccesosDashboard::cards() as $key => $def) {
            $this->assertFileExists(
                resource_path("views/components/icon/{$def['icon']}.blade.php"),
                "La card '{$key}' apunta al ícono inexistente '{$def['icon']}'.",
            );
        }
    }
}
