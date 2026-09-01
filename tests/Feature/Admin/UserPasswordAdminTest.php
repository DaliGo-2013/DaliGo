<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El admin restablece la contraseña de una cuenta desde Editar cuenta
 * (pedido del dueño 01-09: «no tiene opción para cambiarle la contraseña a
 * los usuarios y eso lo considero super básico»). Los operarios no tienen
 * casilla real para el enlace de recuperación, así que el camino es que el
 * admin la reponga y la entregue en persona.
 *
 * El payload de los tests SIMULA AL NAVEGADOR (bitácora 2026-07-06): los
 * campos de clave viajan SIEMPRE, vacíos cuando no se usan — omitir la
 * clave del array es un caso que un navegador real nunca produce.
 */
class UserPasswordAdminTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE_ORIGINAL = 'clave-original-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function cuenta(): User
    {
        return tap(User::factory()->create([
            'email' => 'operario@impdali.cl',
            'password' => Hash::make(self::CLAVE_ORIGINAL),
        ]))->assignRole('soplador');
    }

    /** El PUT completo del formulario de edición, como lo manda el navegador. */
    private function payload(User $cuenta, string $clave = '', string $confirmacion = ''): array
    {
        return [
            'name' => $cuenta->name,
            'email' => $cuenta->email,
            'role' => 'soplador',
            'sucursal_id' => '',
            'jefe_id' => '',
            'password' => $clave,
            'password_confirmation' => $confirmacion,
        ];
    }

    public function test_el_admin_restablece_la_contrasena(): void
    {
        $cuenta = $this->cuenta();

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta, 'nueva-clave-456', 'nueva-clave-456'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $cuenta->refresh();
        $this->assertTrue(Hash::check('nueva-clave-456', $cuenta->password), 'La clave nueva no quedó guardada.');
        $this->assertFalse(Hash::check(self::CLAVE_ORIGINAL, $cuenta->password), 'La clave vieja sigue sirviendo.');
        $this->assertStringContainsString('contraseña restablecida', session('status'));
    }

    public function test_en_blanco_la_contrasena_no_cambia(): void
    {
        $cuenta = $this->cuenta();
        $hashAntes = $cuenta->password;

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($hashAntes, $cuenta->refresh()->password, 'Guardar sin clave nueva tocó el hash.');
        $this->assertStringNotContainsString('restablecida', session('status'));
    }

    public function test_confirmacion_distinta_es_rechazada_sin_tocar_la_clave(): void
    {
        $cuenta = $this->cuenta();
        $hashAntes = $cuenta->password;

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta, 'nueva-clave-456', 'otra-cosa'))
            ->assertSessionHasErrors('password');

        $this->assertSame($hashAntes, $cuenta->refresh()->password);
    }

    public function test_clave_debil_es_rechazada(): void
    {
        $cuenta = $this->cuenta();
        $hashAntes = $cuenta->password;

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta, '123', '123'))
            ->assertSessionHasErrors('password');

        $this->assertSame($hashAntes, $cuenta->refresh()->password);
    }

    public function test_sin_permiso_no_se_puede_restablecer(): void
    {
        $cuenta = $this->cuenta();
        $hashAntes = $cuenta->password;

        // Mutación sin permiso = 403 crudo (el redirect amable al Inicio es
        // solo para GET navegable — D-014, mismo criterio que RecetaCrudTest).
        $this->actingAs(User::factory()->create())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta, 'nueva-clave-456', 'nueva-clave-456'))
            ->assertForbidden();

        $this->assertSame($hashAntes, $cuenta->refresh()->password);
    }

    public function test_la_auditoria_registra_el_cambio_sin_la_clave(): void
    {
        $cuenta = $this->cuenta();

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta, 'nueva-clave-456', 'nueva-clave-456'))
            ->assertSessionHasNoErrors();

        // Queda el EVENTO (quién restableció la clave de quién)…
        $audit = $cuenta->audits()->where('event', 'passwordChanged')->first();
        $this->assertNotNull($audit, 'El restablecimiento no dejó rastro en la auditoría.');

        // …pero NUNCA la clave: ni en texto plano ni como hash, en ningún
        // audit de la cuenta (el del update normal incluido — $auditExclude).
        foreach ($cuenta->audits as $a) {
            $fila = json_encode($a->old_values).json_encode($a->new_values);
            $this->assertStringNotContainsString('nueva-clave-456', $fila, 'La clave en texto plano llegó a la auditoría.');
            $this->assertStringNotContainsString('$2y$', $fila, 'El hash de la clave llegó a la auditoría.');
        }
    }

    public function test_guardar_sin_clave_no_emite_el_evento_de_auditoria(): void
    {
        $cuenta = $this->cuenta();

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $cuenta->audits()->where('event', 'passwordChanged')->count());
    }

    public function test_confirmacion_llena_sin_clave_es_rechazada(): void
    {
        // El silencio que cazó el panel pre-merge: la clave pegada SOLO en
        // «Confirmar» salía con éxito sin restablecer nada — el admin le
        // entregaba al operario una contraseña que nunca quedó puesta.
        $cuenta = $this->cuenta();
        $hashAntes = $cuenta->password;

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta, '', 'clave-pegada-456'))
            ->assertSessionHasErrors('password');

        $this->assertSame($hashAntes, $cuenta->refresh()->password);
    }

    public function test_restablecer_corta_las_sesiones_y_el_recordarme(): void
    {
        // El punto entero del gesto cuando el teléfono se perdió: sin esto,
        // la sesión viva y la cookie «recordarme» seguían entrando (no hay
        // AuthenticateSession en el stack y el recaller solo compara el
        // remember_token). Mismo gesto que el reset por correo.
        $cuenta = $this->cuenta();
        $cuenta->forceFill(['remember_token' => str_repeat('a', 60)])->save();
        DB::table('sessions')->insert([
            'id' => 'sesion-viva-del-telefono-perdido',
            'user_id' => $cuenta->id,
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta, 'nueva-clave-456', 'nueva-clave-456'))
            ->assertSessionHasNoErrors();

        $this->assertNotSame(str_repeat('a', 60), $cuenta->refresh()->remember_token, 'La cookie «recordarme» vieja sigue válida.');
        $this->assertNotNull($cuenta->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-viva-del-telefono-perdido']);
    }

    public function test_autorestablecerse_conserva_la_sesion_actual(): void
    {
        // El admin cambiándose SU clave no debe echarse a sí mismo; sus
        // OTRAS sesiones sí se cierran.
        $admin = $this->admin();

        // El harness HTTP regenera el id de sesión en cada request (no
        // viajan cookies), así que acá se invoca update() directo con una
        // Request armada: el id de la sesión «actual» queda determinista y
        // la exclusión del borrado se prueba de verdad. El gate de permisos
        // de la ruta ya lo cubre test_sin_permiso_no_se_puede_restablecer.
        $sesionActual = str_repeat('a', 40);
        DB::table('sessions')->insert([
            ['id' => $sesionActual, 'user_id' => $admin->id, 'payload' => 'x', 'last_activity' => now()->timestamp],
            ['id' => 'otra-sesion-del-admin', 'user_id' => $admin->id, 'payload' => 'x', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($admin);
        $store = $this->app['session.store'];
        $store->setId($sesionActual);
        $request = \Illuminate\Http\Request::create(route('admin.users.update', $admin), 'PUT', [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
            'password' => 'nueva-clave-456',
            'password_confirmation' => 'nueva-clave-456',
        ]);
        $request->setLaravelSession($store);
        $request->setUserResolver(fn () => $admin);

        app(\App\Http\Controllers\Admin\UserController::class)->update($request, $admin);

        $this->assertDatabaseHas('sessions', ['id' => $sesionActual]);
        $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion-del-admin']);
        $this->assertTrue(Hash::check('nueva-clave-456', $admin->refresh()->password));
    }

    public function test_el_registro_del_sistema_etiqueta_el_evento_en_espanol(): void
    {
        // Sin la entrada en AuditController::EVENTOS, el Registro mostraba el
        // crudo «passwordChanged» (tercer hallazgo del panel pre-merge).
        $cuenta = $this->cuenta();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $cuenta), $this->payload($cuenta, 'nueva-clave-456', 'nueva-clave-456'))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get('/admin/audits')
            ->assertOk()
            ->assertSee('restableció contraseña');
    }

    public function test_el_formulario_ofrece_los_campos_de_clave(): void
    {
        $cuenta = $this->cuenta();

        $this->actingAs($this->admin())
            ->get(route('admin.users.edit', $cuenta))
            ->assertOk()
            ->assertSee('Nueva contraseña (opcional)')
            ->assertSee('Confirmar nueva contraseña')
            ->assertSee('Déjala en blanco para no cambiarla.');
    }
}
