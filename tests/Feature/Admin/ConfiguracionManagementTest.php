<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfiguracionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function ajuste(string $tipo, string $valor, string $clave = 'ajuste_test'): Configuracion
    {
        return Configuracion::create([
            'clave' => $clave,
            'valor' => $valor,
            'tipo' => $tipo,
            'grupo' => 'pruebas',
            'descripcion' => 'Ajuste de prueba.',
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/configuracion')->assertRedirect('/login');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $ajuste = $this->ajuste(Configuracion::TIPO_INTEGER, '10');

        $this->actingAs($member)->get('/admin/configuracion')->assertForbidden();
        $this->actingAs($member)->get("/admin/configuracion/{$ajuste->id}/edit")->assertForbidden();
        $this->actingAs($member)->put("/admin/configuracion/{$ajuste->id}", ['valor' => '20'])->assertForbidden();
    }

    public function test_admin_can_view_index_grouped(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $this->actingAs($this->admin())
            ->get('/admin/configuracion')
            ->assertOk()
            ->assertSee('Cotizaciones')                 // Str::headline('cotizaciones')
            ->assertSee('Umbral Aprobacion Clp');        // Str::headline de la clave
    }

    public function test_admin_can_view_edit_form(): void
    {
        $ajuste = $this->ajuste(Configuracion::TIPO_INTEGER, '10');

        $this->actingAs($this->admin())
            ->get("/admin/configuracion/{$ajuste->id}/edit")
            ->assertOk()
            ->assertSee('Valor');
    }

    public function test_update_persists_and_recasts_integer(): void
    {
        $ajuste = $this->ajuste(Configuracion::TIPO_INTEGER, '10');

        $this->actingAs($this->admin())
            ->put("/admin/configuracion/{$ajuste->id}", ['valor' => '250'])
            ->assertRedirect(route('admin.configuracion.index'));

        $this->assertDatabaseHas('configuraciones', ['clave' => 'ajuste_test', 'valor' => '250']);
        $this->assertSame(250, Configuracion::get('ajuste_test'));
    }

    public function test_update_persists_and_recasts_decimal(): void
    {
        $ajuste = $this->ajuste(Configuracion::TIPO_DECIMAL, '1.0');

        $this->actingAs($this->admin())
            ->put("/admin/configuracion/{$ajuste->id}", ['valor' => '1.5'])
            ->assertRedirect(route('admin.configuracion.index'));

        $this->assertSame(1.5, Configuracion::get('ajuste_test'));
    }

    public function test_update_boolean_false_when_unchecked(): void
    {
        $ajuste = $this->ajuste(Configuracion::TIPO_BOOLEAN, '1');

        // El checkbox no enviado => debe quedar en false ('0').
        $this->actingAs($this->admin())
            ->put("/admin/configuracion/{$ajuste->id}", [])
            ->assertRedirect(route('admin.configuracion.index'));

        $this->assertDatabaseHas('configuraciones', ['clave' => 'ajuste_test', 'valor' => '0']);
        $this->assertFalse(Configuracion::get('ajuste_test'));
    }

    public function test_update_boolean_true_when_checked(): void
    {
        $ajuste = $this->ajuste(Configuracion::TIPO_BOOLEAN, '0');

        $this->actingAs($this->admin())
            ->put("/admin/configuracion/{$ajuste->id}", ['valor' => '1'])
            ->assertRedirect(route('admin.configuracion.index'));

        $this->assertDatabaseHas('configuraciones', ['clave' => 'ajuste_test', 'valor' => '1']);
        $this->assertTrue(Configuracion::get('ajuste_test'));
    }

    public function test_update_json_roundtrip(): void
    {
        $ajuste = $this->ajuste(Configuracion::TIPO_JSON, '{"a":1}');

        $this->actingAs($this->admin())
            ->put("/admin/configuracion/{$ajuste->id}", ['valor' => '{"a": 1, "b": 2}'])
            ->assertRedirect(route('admin.configuracion.index'));

        $this->assertSame(['a' => 1, 'b' => 2], Configuracion::get('ajuste_test'));
    }

    public function test_update_rejects_non_integer(): void
    {
        $ajuste = $this->ajuste(Configuracion::TIPO_INTEGER, '10');

        $this->actingAs($this->admin())
            ->put("/admin/configuracion/{$ajuste->id}", ['valor' => 'abc'])
            ->assertSessionHasErrors('valor');

        $this->assertDatabaseHas('configuraciones', ['clave' => 'ajuste_test', 'valor' => '10']);
    }

    public function test_update_rejects_invalid_json(): void
    {
        $ajuste = $this->ajuste(Configuracion::TIPO_JSON, '{"a":1}');

        $this->actingAs($this->admin())
            ->put("/admin/configuracion/{$ajuste->id}", ['valor' => '{not json'])
            ->assertSessionHasErrors('valor');
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertSame('fallback', Configuracion::get('no_existe', 'fallback'));
    }

    public function test_get_set_roundtrip_and_cache_invalidation(): void
    {
        $this->ajuste(Configuracion::TIPO_INTEGER, '10');

        $this->assertSame(10, Configuracion::get('ajuste_test')); // calienta cache

        Configuracion::set('ajuste_test', 20);

        $this->assertSame(20, Configuracion::get('ajuste_test')); // set() invalido la cache
    }

    public function test_get_caches_and_does_not_requery(): void
    {
        $this->ajuste(Configuracion::TIPO_INTEGER, '10');

        Configuracion::get('ajuste_test'); // calienta cache

        DB::enableQueryLog();
        $value = Configuracion::get('ajuste_test');
        $this->assertSame(10, $value);
        $this->assertCount(0, DB::getQueryLog()); // segunda lectura sin tocar BD
    }

    public function test_seeder_is_idempotent_and_preserves_ui_edits(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        // Edicion via UI.
        Configuracion::set('umbral_aprobacion_clp', 2000000);

        // Un nuevo deploy re-siembra.
        $this->seed(ConfiguracionSeeder::class);

        $this->assertSame(2000000, Configuracion::get('umbral_aprobacion_clp')); // el edit sobrevive
        $this->assertSame(1, Configuracion::where('clave', 'umbral_aprobacion_clp')->count()); // sin duplicar
    }
}
