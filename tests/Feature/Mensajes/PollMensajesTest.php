<?php

namespace Tests\Feature\Mensajes;

use App\Models\Conversacion;
use App\Models\User;
use App\Services\Mensajes\Mensajeria;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * MSG-3 (PLAN-MENSAJES §5.4): el refresco automático del chat — endpoint
 * `mensajes.conteo` con la firma barata del estado + poll de 20s SOLO en las
 * pantallas del chat, sobre el componente <x-poll-recarga> (extraído en este
 * lote; vivo y cola de bodega migraron a él con cero cambio de conducta).
 */
class PollMensajesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(): User
    {
        return tap(User::factory()->create())->assignRole('member');
    }

    private function enviar(User $de, User $a, string $texto): void
    {
        Queue::fake();
        app(Mensajeria::class)->enviar($de, $a, $texto);
    }

    // ---------------------------------------------------------------
    // Gates del endpoint nuevo
    // ---------------------------------------------------------------

    public function test_conteo_exige_sesion_y_permiso(): void
    {
        // Guest: a login.
        $this->get(route('mensajes.conteo'))->assertRedirect(route('login'));

        // Sin permiso, pidiendo JSON (como el poll): 403 crudo.
        $this->actingAs(User::factory()->create())
            ->getJson(route('mensajes.conteo'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // La firma: misma función para vista y endpoint
    // ---------------------------------------------------------------

    public function test_la_firma_de_las_vistas_coincide_con_la_del_conteo(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');
        $conversacion = Conversacion::entre($ana, $beto);

        $firmaLista = $this->actingAs($ana)->get(route('mensajes.index'))->viewData('firmaChat');
        $firmaHilo = $this->actingAs($ana)->get(route('mensajes.show', $conversacion))->viewData('firmaChat');

        // OJO: abrir el hilo MARCA LEIDO (baja mi contador) → la firma de la
        // lista previa ya quedó vieja. El contrato misma-función se verifica
        // contra el estado VIGENTE tras cada render.
        $this->actingAs($ana)->getJson(route('mensajes.conteo'))
            ->assertOk()
            ->assertJson(['firma' => $firmaHilo]);

        $this->assertNotSame($firmaLista, $firmaHilo, 'Abrir el hilo bajó el contador: la firma debía moverse.');
    }

    public function test_la_firma_cambia_con_mensaje_nuevo_y_no_sin_actividad(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');

        $antes = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');
        $quieta = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');
        $this->assertSame($antes, $quieta, 'Sin actividad la firma no puede moverse (el monitor recargaría en loop).');

        $this->enviar($beto, $ana, 'Otro mensaje');

        $despues = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');
        $this->assertNotSame($antes, $despues, 'Un mensaje nuevo debe mover la firma.');
    }

    public function test_la_firma_cambia_al_marcar_leido(): void
    {
        // El contador propio es PARTE de la firma: leer en otra pestaña (o
        // dispositivo) también refresca la lista abierta.
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');

        $antes = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');

        Conversacion::entre($ana, $beto)->marcarLeida($ana);

        $despues = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');
        $this->assertNotSame($antes, $despues, 'Marcar leído baja el contador y debe mover la firma.');
    }

    // ---------------------------------------------------------------
    // El poll vive SOLO donde corresponde
    // ---------------------------------------------------------------

    public function test_lista_e_hilo_emiten_el_poll_del_chat(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');

        // La URL viaja por @js(), que escapa las barras (\/) — la forma cruda
        // JAMAS esta en el HTML (bitacora 2026-08-14): se des-escapa antes de
        // comparar, o el assert seria un candado inerte.
        $lista = $this->actingAs($ana)->get(route('mensajes.index'))->assertOk()->getContent();
        $hilo = $this->actingAs($ana)->get(route('mensajes.show', Conversacion::entre($ana, $beto)))
            ->assertOk()->getContent();

        $this->assertStringContainsString(route('mensajes.conteo'), str_replace('\\/', '/', $lista));
        $this->assertStringContainsString(route('mensajes.conteo'), str_replace('\\/', '/', $hilo));
    }

    public function test_el_componente_de_poll_vive_exactamente_en_sus_cuatro_consumidores(): void
    {
        // Estructural: el poll firma-reload no corre fuera de sus pantallas y
        // la migración de vivo/cola no dejó copias inline del script. El poll
        // de ST (banner con delta, sin recarga) es OTRA conducta a propósito
        // y queda FUERA del componente (coexistencia declarada, precedente
        // del _tabs de ST con x-tab-nav).
        $esperados = [
            'admin/despachos/cola.blade.php',
            'admin/produccion/vivo.blade.php',
            'mensajes/index.blade.php',
            'mensajes/show.blade.php',
        ];

        $conComponente = [];
        $conScriptInline = [];
        foreach (File::allFiles(resource_path('views')) as $archivo) {
            $ruta = str_replace('\\', '/', $archivo->getRelativePathname());
            $contenido = $archivo->getContents();
            if (str_contains($contenido, 'x-poll-recarga') && ! str_starts_with($ruta, 'components/')) {
                $conComponente[] = $ruta;
            }
            // 'document.visibilityState' y no 'visibilityState' a secas: el
            // comentario de cabecera de cola.blade nombra la palabra en PROSA.
            if (str_contains($contenido, 'document.visibilityState') && ! str_starts_with($ruta, 'components/')) {
                $conScriptInline[] = $ruta;
            }
        }
        sort($conComponente);

        $this->assertSame($esperados, $conComponente);
        // El único poll inline que queda es el de ST (conducta distinta).
        $this->assertSame(['admin/servicio-tecnico/index.blade.php'], $conScriptInline);
    }
}
