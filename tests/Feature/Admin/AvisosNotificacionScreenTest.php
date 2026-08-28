<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\User;
use App\Support\AudienciasNotificacion;
use App\Support\RolesDelSistema;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de la pantalla «Avisos y destinatarios» (Configuración → Avisos):
 * la matriz evento × rol con checkboxes que pidió el dueño el 28-08-2026.
 * Los del mecanismo (registry, defaults, silencio) viven en
 * AudienciasNotificacionTest; acá va la PANTALLA y su endpoint.
 */
class AvisosNotificacionScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    public function test_guest_y_sin_permiso_no_entran(): void
    {
        $this->get('/admin/configuracion/avisos')->assertRedirect('/login');

        $member = tap(User::factory()->create())->assignRole('member');
        $this->actingAs($member)->get('/admin/configuracion/avisos')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
        $this->actingAs($member)->put('/admin/configuracion/avisos', [])->assertForbidden();
    }

    public function test_la_matriz_refleja_la_bd(): void
    {
        Configuracion::set('notif_roles_devolucion_solicitada', ['soplador']);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.configuracion.avisos.edit'))
            ->assertOk()
            ->getContent();

        // Forma CONTIGUA del checkbox (doctrina verde-engañoso: entre 300
        // casillas, un value suelto lo satisface cualquiera). x-checkbox emite
        // el @checked ANTES de name/value, y [^>]* no cruza de tag: la terna
        // completa vive en el MISMO input.
        $this->assertMatchesRegularExpression(
            '/checked[^>]*name="audiencias\[devolucion\.solicitada\]\[\]"[^>]*value="soplador"/',
            $html,
            'La casilla editada no aparece marcada.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/checked[^>]*name="audiencias\[devolucion\.solicitada\]\[\]"[^>]*value="jefe_ventas"/',
            $html,
            'Una casilla desmarcada en la BD aparece marcada en pantalla.',
        );

        // Cabeceras humanas, no headline: «Jefe de Ventas», con «de».
        $this->assertStringContainsString('Jefe de Ventas', $html);
        $this->assertStringContainsString('Técnico industrial', $html);
    }

    public function test_guardar_persiste_y_solo_audita_lo_que_cambio(): void
    {
        // La matriz completa con UN cambio: devolucion.solicitada pierde a
        // jefe_ventas. El resto viaja idéntico a su default.
        $audiencias = [];
        foreach (AudienciasNotificacion::DEFAULTS as $evento => $roles) {
            $audiencias[$evento] = $roles;
        }
        $audiencias['devolucion.solicitada'] = ['jefe_bodega', 'admin'];

        $auditsAntes = \OwenIt\Auditing\Models\Audit::where('auditable_type', Configuracion::class)->count();

        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.avisos.update'), ['audiencias' => $audiencias])
            ->assertRedirect(route('admin.configuracion.avisos.edit'));

        $this->assertSame(['jefe_bodega', 'admin'],
            AudienciasNotificacion::rolesPara('devolucion.solicitada'));
        // Un solo set() = un solo audit nuevo (cambios reales, no 25 filas).
        $this->assertSame($auditsAntes + 1,
            \OwenIt\Auditing\Models\Audit::where('auditable_type', Configuracion::class)->count());
    }

    public function test_desmarcar_todo_un_evento_guarda_el_silencio_y_la_pantalla_lo_dice(): void
    {
        // Decisión del dueño 28-08: sin mínimo. Un evento ausente del POST son
        // checkboxes desmarcados → lista vacía guardada.
        $audiencias = [];
        foreach (AudienciasNotificacion::DEFAULTS as $evento => $roles) {
            $audiencias[$evento] = $roles;
        }
        unset($audiencias['devolucion.solicitada']);

        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.avisos.update'), ['audiencias' => $audiencias])
            ->assertRedirect(route('admin.configuracion.avisos.edit'))
            ->assertSessionMissing('errors');

        $this->assertSame([], AudienciasNotificacion::rolesPara('devolucion.solicitada'));

        // Y la pantalla lo muestra: el badge existe (Alpine lo revela con
        // n === 0; el texto tiene que estar en el HTML).
        $this->actingAs($this->admin())
            ->get(route('admin.configuracion.avisos.edit'))
            ->assertSee('Nadie recibe este aviso');
    }

    public function test_rechaza_rol_inexistente(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.avisos.update'), [
                'audiencias' => ['devolucion.solicitada' => ['hackerman']],
            ])
            ->assertSessionHasErrors('audiencias.devolucion.solicitada.0');

        // Y la BD no cambió.
        $this->assertSame(AudienciasNotificacion::DEFAULTS['devolucion.solicitada'],
            AudienciasNotificacion::rolesPara('devolucion.solicitada'));
    }

    public function test_un_evento_desconocido_del_request_se_ignora(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.avisos.update'), [
                'audiencias' => ['evento.inventado' => ['admin']],
            ])
            ->assertRedirect(route('admin.configuracion.avisos.edit'));

        $this->assertNull(Configuracion::query()
            ->where('clave', 'notif_roles_evento_inventado')->first());
    }

    public function test_filas_informativas_sin_checkbox(): void
    {
        $res = $this->actingAs($this->admin())
            ->get(route('admin.configuracion.avisos.edit'))
            ->assertOk()
            // Cada evento fijo dice en español a quién va.
            ->assertSee('Al técnico asignado; si no hay, a todos los técnicos industriales.')
            ->assertSee('A quien pidió la aprobación (el solicitante).');

        // Y NO ofrece checkboxes para ellos.
        $this->assertStringNotContainsString('name="audiencias[terreno.agendado]', $res->getContent());
        $this->assertStringNotContainsString('name="audiencias[aprobacion.resuelta]', $res->getContent());
    }

    public function test_el_index_oculta_las_claves_y_enlaza_la_matriz(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/configuracion')
            ->assertOk()
            // Las 25 claves nuevas no aparecen como filas técnicas…
            ->assertDontSee('Notif Roles')
            // …la entrada a la matriz sí…
            ->assertSee('Avisos y destinatarios')
            ->assertSee(route('admin.configuracion.avisos.edit'))
            // …y el resto del listado sigue intacto.
            ->assertSee('Umbral Aprobacion Clp');
    }

    public function test_editar_una_clave_de_audiencias_por_url_redirige_a_la_matriz(): void
    {
        $clave = Configuracion::query()
            ->where('clave', 'notif_roles_devolucion_solicitada')->firstOrFail();

        $this->actingAs($this->admin())
            ->get("/admin/configuracion/{$clave->id}/edit")
            ->assertRedirect(route('admin.configuracion.avisos.edit'));
    }

    public function test_los_roles_del_sistema_tienen_etiqueta_curada(): void
    {
        // Los 12 del seeder no caen al fallback (headline diría «Jefe Ventas»).
        $opciones = RolesDelSistema::opciones();

        $this->assertSame('Jefe de Ventas', $opciones['jefe_ventas']);
        $this->assertSame('Técnico de taller', $opciones['tecnico']);
        $this->assertSame('Jefe de Logística', $opciones['jefe_logistica']);
        $this->assertCount(12, $opciones);

        // Un rol creado desde la UI aparece solo, con fallback headline, al final.
        \Spatie\Permission\Models\Role::create(['name' => 'cobranzas', 'guard_name' => 'web']);
        $opciones = RolesDelSistema::opciones();
        $this->assertSame('Cobranzas', $opciones['cobranzas']);
        $this->assertSame('cobranzas', array_key_last($opciones));
    }
}
