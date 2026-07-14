<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Notificacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotificacionPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function conPermiso(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view notificaciones');

        return $user;
    }

    public function test_permiso_existe_en_el_seeder(): void
    {
        $this->assertTrue(Permission::where('name', 'view notificaciones')->exists());
    }

    public function test_panel_visible_con_permiso(): void
    {
        $this->actingAs($this->conPermiso())
            ->get(route('admin.notificaciones.index'))
            ->assertOk()
            ->assertViewIs('admin.notificaciones.index');
    }

    public function test_panel_403_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.notificaciones.index'))
            ->assertForbidden();
    }

    public function test_filtro_por_estado_acota(): void
    {
        $base = ['evento' => 'sistema.prueba', 'canal' => Notificacion::CANAL_MAIL, 'destinatario' => 'x@example.com', 'titulo' => 'T', 'cuerpo' => 'C'];
        Notificacion::create($base + ['estado' => Notificacion::ENVIADA]);
        Notificacion::create($base + ['estado' => Notificacion::FALLIDA]);

        $this->actingAs($this->conPermiso())
            ->get(route('admin.notificaciones.index', ['estado' => Notificacion::FALLIDA]))
            ->assertOk()
            ->assertViewHas('notificaciones', fn ($p) => $p->total() === 1 && $p->first()->estado === Notificacion::FALLIDA);
    }

    public function test_panel_muestra_el_correo_de_destino_y_el_error_completo(): void
    {
        // Micro-backlog M15 (obs. del QA de P-M15-09): (a) el correo de destino
        // debe verse aunque la notificación tenga usuario interno (antes solo
        // salía el nombre); (b) el ultimo_error se muestra ÍNTEGRO (expandible),
        // no solo el resumen truncado de 80 chars.
        $admin = $this->conPermiso();
        $errorLargo = 'Failed to authenticate on SMTP server with username "servicio@example.com" using 3 possible authenticators: (1) LOGIN rechazado; (2) PLAIN rechazado; (3) XOAUTH2 no soportado por el servidor remoto.';

        Notificacion::create([
            'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_MAIL,
            'user_id' => $admin->id,
            'destinatario' => 'destino-real@example.com',
            'titulo' => 'T',
            'cuerpo' => 'C',
            'estado' => Notificacion::FALLIDA,
            'ultimo_error' => $errorLargo,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notificaciones.index'))
            ->assertOk()
            ->assertSee('destino-real@example.com')   // (a) correo visible junto al nombre
            ->assertSee($admin->name)
            ->assertSee('XOAUTH2 no soportado');       // (b) la cola del error (>80 chars) está en la página

        $this->assertTrue(mb_strlen($errorLargo) > 80, 'El fixture debe superar el truncado viejo de 80 chars.');
    }

    public function test_boton_prueba_despacha_al_usuario_actual(): void
    {
        Queue::fake();
        $user = $this->conPermiso();

        $this->actingAs($user)
            ->post(route('admin.notificaciones.prueba'))
            ->assertRedirect(route('admin.notificaciones.index'));

        // database + mail para el usuario actual (whatsapp es opt-in).
        $this->assertSame(2, Notificacion::where('user_id', $user->id)->count());
    }
}
