<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\User;
use App\Support\LimiteSesiones;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla «Sesiones por usuario» (Configuración): edita el default, los
 * overrides por rol y por usuario puntual del límite de sesiones. Molde de
 * AvisosNotificacionScreenTest: mismo permiso que Configuración, claves
 * ocultas del índice técnico, puertas traseras cerradas.
 */
class LimiteSesionesScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->sembrarClaves();
    }

    private function sembrarClaves(): void
    {
        foreach ([
            [LimiteSesiones::CLAVE_DEFAULT, '3', Configuracion::TIPO_INTEGER],
            [LimiteSesiones::CLAVE_ROLES, '[]', Configuracion::TIPO_JSON],
            [LimiteSesiones::CLAVE_USUARIOS, '[]', Configuracion::TIPO_JSON],
        ] as [$clave, $valor, $tipo]) {
            Configuracion::create([
                'clave' => $clave, 'valor' => $valor, 'tipo' => $tipo,
                'grupo' => LimiteSesiones::GRUPO, 'descripcion' => 'test',
            ]);
        }
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    public function test_carga_con_permiso_y_muestra_lo_vigente(): void
    {
        Configuracion::set(LimiteSesiones::CLAVE_ROLES, json_encode(['soplador' => 2]));

        $html = $this->actingAs($this->admin())
            ->get(route('admin.configuracion.sesiones.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sesiones por usuario', $html);
        $this->assertStringContainsString('Límite por defecto', $html);
        $this->assertStringContainsString('Soplador', $html); // etiqueta del rol
        // Forma contigua del input del rol con su valor (verde-engañoso).
        $this->assertStringContainsString('name="roles[soplador]"', $html);
    }

    public function test_sin_permiso_no_entra(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.configuracion.sesiones.edit'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_put_persiste_default_rol_y_usuario_nuevo(): void
    {
        $u = User::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.sesiones.update'), [
                'limite_default' => 4,
                'roles' => ['soplador' => 2],
                'nuevo_usuario_id' => $u->id,
                'nuevo_limite' => 5,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.configuracion.sesiones.edit'));

        $this->assertSame(4, LimiteSesiones::defaultVigente());
        $this->assertSame(['soplador' => 2], LimiteSesiones::overridesRoles());
        $this->assertSame([$u->id => 5], LimiteSesiones::overridesUsuarios());
        // Y la resolución completa lo refleja.
        $this->assertSame(5, LimiteSesiones::de($u->fresh()));
    }

    public function test_un_put_identico_no_reescribe_nada(): void
    {
        // Auditoría limpia (molde avisos): guardar sin cambios no toca filas.
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.sesiones.update'), ['limite_default' => 3])
            ->assertRedirect(route('admin.configuracion.sesiones.edit'));

        $this->assertStringContainsString('Sin cambios', session('status'));
    }

    public function test_un_rol_desconocido_se_rechaza_nombrandolo(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.sesiones.update'), [
                'limite_default' => 3,
                'roles' => ['fantasma' => 2],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertStringContainsString('fantasma', session('errors')->first('roles'));
        $this->assertSame([], LimiteSesiones::overridesRoles());
    }

    public function test_usuario_inexistente_y_valores_fuera_de_rango_se_rechazan(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.sesiones.update'), [
                'limite_default' => 3,
                'usuarios' => [99999 => 2],
            ])
            ->assertSessionHasErrors('usuarios');

        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.sesiones.update'), ['limite_default' => LimiteSesiones::MAX + 1])
            ->assertSessionHasErrors('limite_default');

        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.sesiones.update'), ['limite_default' => -1])
            ->assertSessionHasErrors('limite_default');
    }

    public function test_vaciar_el_numero_quita_el_override_del_usuario(): void
    {
        $u = User::factory()->create();
        Configuracion::set(LimiteSesiones::CLAVE_USUARIOS, json_encode([$u->id => 5]));

        // El navegador manda el campo vacío ('' → null): eso ES quitar.
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.sesiones.update'), [
                'limite_default' => 3,
                'usuarios' => [$u->id => ''],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([], LimiteSesiones::overridesUsuarios());
    }

    public function test_el_indice_de_configuracion_oculta_el_grupo_y_ofrece_la_tarjeta(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.configuracion.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sesiones por usuario', $html);
        $this->assertStringContainsString(route('admin.configuracion.sesiones.edit'), $html);
        // Las claves crudas no se listan: su único editor es la pantalla.
        $this->assertStringNotContainsString('sesiones_limite_default', $html);
        $this->assertStringNotContainsString('sesiones_limite_roles', $html);
    }

    public function test_las_puertas_traseras_del_resource_redirigen(): void
    {
        $config = Configuracion::where('clave', LimiteSesiones::CLAVE_DEFAULT)->firstOrFail();

        // Lectura…
        $this->actingAs($this->admin())
            ->get(route('admin.configuracion.edit', $config))
            ->assertRedirect(route('admin.configuracion.sesiones.edit'));

        // …y ESCRITURA (mejora sobre el molde de avisos): el PUT crudo no
        // pasa por acá y el valor queda intacto.
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.update', $config), ['valor' => '9'])
            ->assertRedirect(route('admin.configuracion.sesiones.edit'));

        $this->assertSame(3, LimiteSesiones::defaultVigente());
    }
}
