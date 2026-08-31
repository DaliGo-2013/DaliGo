<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados del selector de sopladores (pedido del dueño 28-08-2026: en
 * «Asignar producción» aparecía CUALQUIER usuario). La causa: las tres
 * pantallas poblaban por el PERMISO 'report production' — que admin y
 * jefaturas también tienen — y el guardado validaba solo exists:users,id.
 * Ahora todo deriva de User::sopladores() (rol, paramétrico vía la clave
 * `produccion_roles_soplador`). Molde PARAMETRICOS.
 */
class SopladorSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function payloadAsignar(int $sopladorId): array
    {
        return ['soplador_id' => $sopladorId, 'turno' => 'dia', 'fecha' => now()->toDateString(), 'asignadas' => 100];
    }

    public function test_el_selector_solo_ofrece_usuarios_con_rol_de_soplador(): void
    {
        // El caso EXACTO del reporte del dueño: el admin tiene el permiso
        // 'report production' y con el código viejo aparecía en el selector.
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $admin = $this->jefe();
        $member = tap(User::factory()->create())->assignRole('member');

        // viewData y no assertSee: el nombre del admin actuante aparece
        // igual en la topbar — un assertDontSee pasaría/fallaría por la
        // superficie equivocada (doctrina verde-engañoso).
        $ids = $this->actingAs($admin)
            ->get(route('admin.produccion.asignar'))
            ->assertOk()
            ->viewData('sopladores')
            ->pluck('id');

        $this->assertTrue($ids->contains($soplador->id));
        $this->assertFalse($ids->contains($admin->id), 'El admin (permiso sin rol) volvió a colarse en el selector.');
        $this->assertFalse($ids->contains($member->id));
    }

    public function test_asignar_rechaza_un_usuario_sin_rol_de_soplador(): void
    {
        // El POST armado a mano (el hueco de fondo: exists:users,id dejaba
        // pasar a cualquiera aunque el selector se filtrara).
        $admin = $this->jefe();

        $this->actingAs($admin)
            ->post(route('admin.produccion.asignar.store'), $this->payloadAsignar($admin->id))
            ->assertSessionHasErrors('soplador_id');

        $this->assertDatabaseMissing('produccion_asignaciones', ['soplador_id' => $admin->id]);

        // Y el camino feliz sigue feliz.
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $this->actingAs($admin)
            ->post(route('admin.produccion.asignar.store'), $this->payloadAsignar($soplador->id))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('produccion_asignaciones', ['soplador_id' => $soplador->id]);
    }

    public function test_mover_la_clave_mueve_el_selector_y_la_validacion(): void
    {
        // La perilla existe: con 'conductor' sumado a la clave, un conductor
        // entra al selector Y pasa el guardado.
        $this->seed(ConfiguracionSeeder::class);
        $conductor = tap(User::factory()->create())->assignRole('conductor');
        $admin = $this->jefe();

        // Antes de mover la clave: el conductor NO es asignable.
        $this->actingAs($admin)
            ->post(route('admin.produccion.asignar.store'), $this->payloadAsignar($conductor->id))
            ->assertSessionHasErrors('soplador_id');

        Configuracion::set('produccion_roles_soplador', ['soplador', 'conductor']);

        $ids = $this->actingAs($admin)
            ->get(route('admin.produccion.asignar'))
            ->viewData('sopladores')
            ->pluck('id');
        $this->assertTrue($ids->contains($conductor->id));

        $this->actingAs($admin)
            ->post(route('admin.produccion.asignar.store'), $this->payloadAsignar($conductor->id))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('produccion_asignaciones', ['soplador_id' => $conductor->id]);
    }

    public function test_lista_rota_o_vacia_cae_al_default(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        // Rol inexistente: se descarta y queda el default (no un selector muerto).
        Configuracion::set('produccion_roles_soplador', ['rol_fantasma']);
        $this->assertSame(['soplador'], User::rolesSoplador());

        // Vacía (por fuera de la UI, que la rechaza): default igual — acá el
        // vacío deliberado NO existe: sin sopladores no se puede operar.
        Configuracion::set('produccion_roles_soplador', []);
        $this->assertSame(['soplador'], User::rolesSoplador());

        // Sin clave (BD virgen): default.
        Configuracion::query()->where('clave', 'produccion_roles_soplador')->delete();
        \Illuminate\Support\Facades\Cache::forget('config.produccion_roles_soplador');
        $this->assertSame(['soplador'], User::rolesSoplador());
    }

    public function test_la_ui_rechaza_un_rol_inexistente_nombrandolo(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $clave = Configuracion::query()->where('clave', 'produccion_roles_soplador')->firstOrFail();

        // El typo clásico: «sopladores» no es un rol. Sin este rechazo, el
        // clamp lo descartaría al leer y la edición no movería nada (no-op
        // silencioso).
        $this->actingAs($this->jefe())
            ->put(route('admin.configuracion.update', $clave), ['valor' => "soplador\nsopladores"])
            ->assertSessionHasErrors('valor');
        $this->assertSame(['soplador'], User::rolesSoplador());

        // Un rol real entra.
        $this->actingAs($this->jefe())
            ->put(route('admin.configuracion.update', $clave), ['valor' => "soplador\nconductor"])
            ->assertRedirect(route('admin.configuracion.index'));
        $this->assertSame(['soplador', 'conductor'], User::rolesSoplador());
    }

    public function test_la_nota_dirigida_solo_acepta_sopladores(): void
    {
        $admin = $this->jefe();

        $this->actingAs($admin)
            ->post(route('admin.produccion.notas.store'), ['texto' => 'Nota', 'soplador_id' => $admin->id])
            ->assertSessionHasErrors('soplador_id');

        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $this->actingAs($admin)
            ->post(route('admin.produccion.notas.store'), ['texto' => 'Nota', 'soplador_id' => $soplador->id])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_el_historial_de_sopladores_usa_el_mismo_universo(): void
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $admin = $this->jefe();

        $ids = $this->actingAs($admin)
            ->get(route('admin.produccion.sopladores'))
            ->assertOk()
            ->viewData('sopladores')
            ->pluck('id');

        $this->assertTrue($ids->contains($soplador->id));
        $this->assertFalse($ids->contains($admin->id));
    }

    public function test_estructural_las_tres_pantallas_derivan_y_no_queda_el_permiso_a_mano(): void
    {
        // La mitad que DISCRIMINA (molde LOG-1): reponer el permiso en un
        // controlador deja la pantalla igual HOY para un soplador real, pero
        // el selector volvería a ofrecer a cualquiera con el permiso.
        foreach ([
            'app/Http/Controllers/Admin/ProduccionController.php',
            'app/Http/Controllers/Admin/ProduccionNotaController.php',
        ] as $ruta) {
            $fuente = file_get_contents(base_path($ruta));

            $this->assertStringContainsString('sopladores()', $fuente,
                "{$ruta} no deriva de User::sopladores().");
            $this->assertStringNotContainsString("User::permission('report production')", $fuente,
                "{$ruta} volvió a poblar el selector por permiso.");
        }
    }
}
