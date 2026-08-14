<?php

namespace Tests\Feature\Admin;

use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SucursalManagementTest extends TestCase
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

    public function test_admin_can_view_sucursales_index(): void
    {
        $this->actingAs($this->admin())->get('/admin/sucursales')->assertOk();
    }

    /**
     * La fila de la sucursal ES el enlace a su edicion (pedido del dueño 03-08:
     * fuera el lapiz, se entra tocando la sucursal). Sin condicion de permiso:
     * el resource completo esta detras de 'manage sucursales'.
     */
    public function test_la_fila_de_la_sucursal_enlaza_directo_a_editar(): void
    {
        $sucursal = Sucursal::factory()->create(['nombre' => 'Coquimbo Enlace']);

        $html = $this->actingAs($this->admin())->get('/admin/sucursales')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/admin\/sucursales\/'.$sucursal->id.'\/edit"[^>]*>(?:(?!<\/a>).)*Coquimbo Enlace/s',
            $html,
            'El nombre de la sucursal no está dentro del enlace a su edición.'
        );
        $this->assertStringNotContainsString('title="Editar"', $html);
        // El tacho de eliminar sigue: solo se fue el lapiz.
        $this->assertStringContainsString('title="Eliminar"', $html);
    }

    public function test_admin_can_view_create_form(): void
    {
        $this->actingAs($this->admin())->get('/admin/sucursales/create')
            ->assertOk()
            ->assertSee('Crear sucursal');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)->get('/admin/sucursales')->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
        $this->actingAs($member)->get('/admin/sucursales/create')->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
        $this->actingAs($member)->post('/admin/sucursales', ['nombre' => 'X', 'codigo' => 'X'])->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/sucursales')->assertRedirect('/login');
    }

    public function test_admin_can_create_sucursal(): void
    {
        $this->actingAs($this->admin())->post('/admin/sucursales', [
            'nombre' => 'Mirador',
            'codigo' => 'MIRADOR',
            'ciudad' => 'La Serena',
            'es_central' => '1',
            'activa' => '1',
        ])->assertRedirect(route('admin.sucursales.index'));

        $this->assertDatabaseHas('sucursales', [
            'codigo' => 'MIRADOR',
            'nombre' => 'Mirador',
            'es_central' => true,
            'activa' => true,
        ]);
    }

    public function test_create_requires_nombre_and_codigo(): void
    {
        $this->actingAs($this->admin())->post('/admin/sucursales', [])
            ->assertSessionHasErrors(['nombre', 'codigo']);
    }

    public function test_create_rejects_duplicate_codigo(): void
    {
        Sucursal::create(['nombre' => 'Coquimbo', 'codigo' => 'COQUIMBO']);

        $this->actingAs($this->admin())->post('/admin/sucursales', [
            'nombre' => 'Otra',
            'codigo' => 'COQUIMBO',
        ])->assertSessionHasErrors('codigo');
    }

    public function test_admin_can_update_sucursal(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Buzeta', 'codigo' => 'BUZETA']);

        $this->actingAs($this->admin())
            ->put("/admin/sucursales/{$sucursal->id}", [
                'nombre' => 'Buzeta Norte',
                'codigo' => 'BUZETA',
                'activa' => '1',
            ])
            ->assertRedirect(route('admin.sucursales.index'));

        $this->assertSame('Buzeta Norte', $sucursal->fresh()->nombre);
    }

    public function test_admin_can_delete_unused_sucursal(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Temporal', 'codigo' => 'TEMP']);

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}");

        $this->assertDatabaseMissing('sucursales', ['codigo' => 'TEMP']);
    }

    public function test_cannot_delete_sucursal_with_users(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Abate Molina', 'codigo' => 'ABATE']);
        $user = User::factory()->create(['sucursal_id' => $sucursal->id]);
        $user->assignRole('member');

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}");

        $this->assertDatabaseHas('sucursales', ['codigo' => 'ABATE']);
    }

    public function test_admin_can_assign_sucursal_when_creating_user(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);

        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Con Sucursal',
            'email' => 'con.sucursal@impdali.cl',
            'role' => 'member',
            'sucursal_id' => $sucursal->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'con.sucursal@impdali.cl',
            'sucursal_id' => $sucursal->id,
        ]);
    }

    public function test_admin_can_update_user_sucursal(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Coquimbo', 'codigo' => 'COQUIMBO']);
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}", [
                'role' => 'member',
                'name' => $user->name,
                'email' => $user->email,
                'sucursal_id' => $sucursal->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($sucursal->id, $user->fresh()->sucursal_id);
    }

    public function test_seeder_creates_base_sucursales_idempotently(): void
    {
        $this->seed(\Database\Seeders\SucursalSeeder::class);
        $this->seed(\Database\Seeders\SucursalSeeder::class); // re-ejecutar no duplica

        $this->assertSame(4, Sucursal::count());
        $this->assertDatabaseHas('sucursales', ['codigo' => 'MIRADOR', 'es_central' => true]);
        // El nombre oficial de la casa matriz es «Mirador», sin artículo (dueño, 14-08-2026).
        // Se ve en el correo de ingreso, en el de retiro y en la pantalla del QR.
        $this->assertSame('Mirador', Sucursal::where('codigo', 'MIRADOR')->value('nombre'));
    }

    /**
     * «El Mirador» era un error de tipeo al crear la ficha (dueño, 14-08-2026) y el seeder no
     * lo corrige solo: es `firstOrCreate`, no pisa lo editado desde la UI. Por eso hay una
     * migración one-shot, y este candado prueba que hace lo que dice.
     */
    public function test_la_migracion_devuelve_a_mirador_su_nombre_oficial(): void
    {
        $mirador = Sucursal::create(['nombre' => 'El Mirador', 'codigo' => 'MIRADOR', 'es_central' => true, 'activa' => true]);
        // Una ficha con OTRO código no se toca, aunque se llame parecido: si hay una duplicada,
        // renombrarla dejaría dos «Mirador» y taparía el problema en vez de mostrarlo.
        $otra = Sucursal::create(['nombre' => 'EL MIRADOR', 'codigo' => 'EL-MIRADOR', 'activa' => true]);

        $migracion = require database_path('migrations/2026_08_14_200000_normaliza_el_nombre_de_la_sucursal_mirador.php');
        $migracion->up();

        $this->assertSame('Mirador', $mirador->fresh()->nombre);
        $this->assertSame('EL MIRADOR', $otra->fresh()->nombre);
    }

    /**
     * EL CODIGO ES UNA LLAVE Y SE GUARDA NORMALIZADO (14-08-2026). En producción estaban
     * «Mirador» y «Coquimbo» retipeados desde este mismo formulario, y con eso el plazo de
     * reparación del correo caía al default de 15 días hábiles (ver
     * Sucursal::getDiasReparacionAttribute y PlazoSinFechaPrometidaTest).
     */
    public function test_el_codigo_se_guarda_en_mayusculas(): void
    {
        $this->actingAs($this->admin())->post('/admin/sucursales', [
            'nombre' => 'Mirador', 'codigo' => '  mirador-2  ', 'activa' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sucursales', ['codigo' => 'MIRADOR-2']);
    }

    /** Y al editar tampoco se puede volver a minúsculas «sin querer». */
    public function test_editar_tampoco_deja_el_codigo_en_minusculas(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Coquimbo', 'codigo' => 'COQUIMBO', 'activa' => true]);

        $this->actingAs($this->admin())->put("/admin/sucursales/{$sucursal->id}", [
            'nombre' => 'Coquimbo', 'codigo' => 'Coquimbo', 'ciudad' => 'Coquimbo', 'activa' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('COQUIMBO', $sucursal->fresh()->codigo);
    }

    /** Y la migración one-shot arregla los que ya están guardados así. */
    public function test_la_migracion_normaliza_los_codigos_ya_guardados(): void
    {
        $mirador = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'Mirador', 'es_central' => true, 'activa' => true]);
        $coquimbo = Sucursal::create(['nombre' => 'Coquimbo', 'codigo' => 'Coquimbo', 'activa' => true]);

        $migracion = require database_path('migrations/2026_08_14_210000_normaliza_los_codigos_de_sucursal.php');
        $migracion->up();

        $this->assertSame('MIRADOR', $mirador->fresh()->codigo);
        $this->assertSame('COQUIMBO', $coquimbo->fresh()->codigo);
        // Y con eso el plazo vuelve a ser el que dictó el dueño.
        $this->assertSame(10, $mirador->fresh()->dias_reparacion);
        $this->assertSame(15, $coquimbo->fresh()->dias_reparacion);
    }

    /**
     * La migración NO fuerza una colisión: dos sucursales que difieren solo en mayúsculas son un
     * duplicado, y eso se resuelve moviendo órdenes y usuarios, no en una migración a ciegas.
     */
    public function test_la_migracion_no_pisa_un_codigo_ya_ocupado(): void
    {
        $buena = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR', 'es_central' => true, 'activa' => true]);
        $duplicada = Sucursal::create(['nombre' => 'El Mirador', 'codigo' => 'Mirador', 'activa' => true]);

        $migracion = require database_path('migrations/2026_08_14_210000_normaliza_los_codigos_de_sucursal.php');
        $migracion->up();

        $this->assertSame('MIRADOR', $buena->fresh()->codigo);
        $this->assertSame('Mirador', $duplicada->fresh()->codigo, 'La migración pisó un código ya ocupado en vez de dejar el duplicado a la vista.');
    }

    // ── P-M04-11 · guardas de eliminación COMPLETAS ────────────────────────
    // Cada FK con datos bloquea con un flash que dice QUÉ reasignar; el
    // RESTRICT de la BD es el cinturón, no la primera línea (un 500 no
    // explica nada). Se asserta el estado Y el mensaje.

    /** La guarda de máquinas existía desde M11 pero sin test (hallazgo del sweep). */
    public function test_cannot_delete_sucursal_with_maquinas(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);
        \App\Models\Maquina::create(['nombre' => 'Sopladora 1', 'sucursal_id' => $sucursal->id, 'activa' => true]);

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}")
            ->assertSessionHas('status', 'No puedes eliminar Mirador: tiene máquinas asociadas.');

        $this->assertDatabaseHas('sucursales', ['codigo' => 'MIRADOR']);
    }

    public function test_cannot_delete_sucursal_with_bodegas(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);
        \App\Models\Bodega::factory()->create(['sucursal_id' => $sucursal->id]);

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}")
            ->assertSessionHas('status', 'No puedes eliminar Mirador: tiene bodegas asignadas. Reasígnalas primero desde Inventario.');

        $this->assertDatabaseHas('sucursales', ['codigo' => 'MIRADOR']);
    }

    public function test_cannot_delete_sucursal_with_hojas_de_ruta(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);
        \App\Models\HojaDeRuta::factory()->create(['sucursal_id' => $sucursal->id]);

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}")
            ->assertSessionHas('status', 'No puedes eliminar Mirador: tiene hojas de ruta registradas.');

        $this->assertDatabaseHas('sucursales', ['codigo' => 'MIRADOR']);
    }

    public function test_cannot_delete_sucursal_with_devoluciones(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);
        \App\Models\Devolucion::factory()->create(['sucursal_id' => $sucursal->id]);

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}")
            ->assertSessionHas('status', 'No puedes eliminar Mirador: tiene devoluciones registradas.');

        $this->assertDatabaseHas('sucursales', ['codigo' => 'MIRADOR']);
    }

    public function test_cannot_delete_sucursal_with_traslados_de_servicio(): void
    {
        $origen = Sucursal::create(['nombre' => 'Coquimbo', 'codigo' => 'COQUIMBO']);
        $destino = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);
        \App\Models\TrasladoServicio::create([
            'codigo' => 'TR-TEST-1',
            'sucursal_origen_id' => $origen->id,
            'sucursal_destino_id' => $destino->id,
            'emisor_nombre' => 'Prueba',
            'despachado_at' => now(),
            'total_enviado' => 1,
        ]);

        // Bloquea tanto como ORIGEN…
        $this->actingAs($this->admin())->delete("/admin/sucursales/{$origen->id}")
            ->assertSessionHas('status', 'No puedes eliminar Coquimbo: tiene traslados de servicio técnico registrados.');
        // …como DESTINO.
        $this->actingAs($this->admin())->delete("/admin/sucursales/{$destino->id}")
            ->assertSessionHas('status', 'No puedes eliminar Mirador: tiene traslados de servicio técnico registrados.');

        $this->assertSame(2, Sucursal::count());
    }

    /**
     * El confirm() de eliminar interpola el nombre vía Js::from: un apóstrofo
     * ("O'Higgins") con {{ }} crudo era un SyntaxError y el form se enviaba
     * SIN preguntar (bitácora 2026-07-28). Rojo con el código viejo.
     */
    public function test_confirmacion_de_eliminar_sobrevive_un_apostrofo(): void
    {
        Sucursal::create(['nombre' => "O'Higgins", 'codigo' => 'OHIGGINS']);

        $html = $this->actingAs($this->admin())->get('/admin/sucursales')->assertOk()->getContent();

        // Js::from escapa el apóstrofo a la secuencia unicode u0027 con
        // contrabarra dentro del string JS; la contrabarra viaja literal en el
        // HTML y se arma con chr(92) para no pelear con capas de escapado.
        $this->assertStringContainsString('O'.chr(92).'u0027Higgins', $html);
        // …y la forma cruda que rompía el handler ya no puede aparecer.
        $this->assertStringNotContainsString("confirm('¿Eliminar la sucursal O'Higgins?')", $html);
    }
}
