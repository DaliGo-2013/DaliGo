<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MenuPrincipal;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Candado de las CONSOLIDACIONES del menú (PLAN-MENU-DENSIDAD, F1 en adelante).
 *
 * El hueco que cierra (detectado en la auditoría F0): mover una pantalla a una
 * pestaña de otro ítem SIN sumar sus rutas al patrón `activo` del anfitrión
 * deja la página sin resaltado en la sidebar EN SILENCIO — SidebarTest solo
 * visita las rutas de los ítems que SIGUEN en el menú, así que ningún test lo
 * cazaba. Cada lote de consolidación futuro agrega UNA línea al mapa
 * CONSOLIDADAS y hereda los tres asserts gratis.
 */
class MenuConsolidacionesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prefijo de nombre de ruta consolidado => key del ítem anfitrión
     * (formato de MenuPrincipal::items(): "modulo.item"). La puerta que se
     * visita es la ruta llamada EXACTAMENTE `{prefijo}` si existe (una
     * consolidación de ruta hoja, como Estado) o `{prefijo}index` (una familia
     * con prefijo, como listas-precios) — en ambos casos sin parámetros.
     */
    private const CONSOLIDADAS = [
        'admin.listas-precios.' => 'comercial.catalogo', // F1: Precios → pestaña de Catálogo
        'admin.dte.estado' => 'facturacion.documentos', // Lote 3: Estado → pestaña de Documentos
        'admin.servicio-tecnico.qr' => 'servicio-tecnico.listado', // Lote 4: Códigos QR → botón del Listado
        'admin.servicios-terreno.' => 'servicio-tecnico.agenda-terreno', // Lote 5: Servicios de terreno → pestaña de la Agenda
        'admin.tiempos-reparacion.' => 'servicio-tecnico.listado', // A1: Costos generales → desplegable «Configuración» del Listado
        'admin.traslados.' => 'servicio-tecnico.listado', // A2: Traslados al taller → pestaña del Listado
    ];

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return tap(User::factory()->create())
            ->givePermissionTo(Permission::all());
    }

    public function test_toda_ruta_consolidada_esta_cubierta_por_el_activo_del_anfitrion(): void
    {
        foreach (self::CONSOLIDADAS as $prefijo => $anfitrionKey) {
            $anfitrion = MenuPrincipal::items()[$anfitrionKey] ?? null;
            $this->assertNotNull($anfitrion, "El anfitrión [{$anfitrionKey}] ya no existe en MenuPrincipal.");

            $rutas = collect(Route::getRoutes()->getRoutesByName())
                ->keys()
                ->filter(fn (string $nombre) => str_starts_with($nombre, $prefijo));
            $this->assertNotEmpty($rutas, "No hay rutas registradas bajo [{$prefijo}]: el mapa CONSOLIDADAS quedó stale.");

            foreach ($rutas as $nombre) {
                $this->assertTrue(
                    collect($anfitrion['activo'])->contains(fn (string $patron) => Str::is($patron, $nombre)),
                    "[{$nombre}] no está cubierta por el `activo` de [{$anfitrionKey}]: esa página "
                    .'quedaría sin resaltado en la sidebar, en silencio (el hueco de F0).'
                );
            }
        }
    }

    public function test_la_pantalla_consolidada_resalta_exactamente_al_anfitrion(): void
    {
        $admin = $this->admin();

        foreach (self::CONSOLIDADAS as $prefijo => $anfitrionKey) {
            $anfitrion = MenuPrincipal::items()[$anfitrionKey];

            $puerta = Route::getRoutes()->getByName($prefijo) !== null ? $prefijo : $prefijo.'index';
            $ruta = Route::getRoutes()->getByName($puerta);
            $this->assertNotNull($ruta, "No existe [{$puerta}] para visitar; el mapa exige la ruta exacta o un index, sin parámetros.");
            $this->assertSame([], $ruta->parameterNames(), "[{$puerta}] necesita parámetros; el candado no la puede visitar.");

            $html = $this->actingAs($admin)->get(route($puerta))->assertOk()->getContent();

            // Exactamente UN resaltado del menú, y es el del anfitrión (forma
            // contigua que produce x-sidebar-item, doctrina anti verde-engañoso).
            // Las pestañas de la pantalla usan aria-current="true" a propósito
            // para no entrar en esta cuenta (comentario en admin/catalogo/_tabs).
            $this->assertSame(
                1,
                substr_count($html, 'aria-current="page"'),
                "En [{$prefijo}index] debe resaltar EXACTAMENTE un ítem del menú."
            );
            $this->assertStringContainsString(
                'href="'.route($anfitrion['route']).'" aria-current="page"',
                $html,
                "El ítem resaltado en [{$prefijo}index] no es el anfitrión [{$anfitrionKey}]."
            );
        }
    }

    public function test_ningun_item_del_menu_apunta_a_un_prefijo_consolidado(): void
    {
        // La otra mitad de la consolidación: si el ítem retirado vuelve al menú
        // (o un lote futuro olvida quitarlo), esto lo hace visible.
        foreach (MenuPrincipal::items() as $key => $item) {
            foreach (array_keys(self::CONSOLIDADAS) as $prefijo) {
                $this->assertFalse(
                    str_starts_with($item['route'], $prefijo),
                    "El ítem [{$key}] apunta a [{$item['route']}], que está consolidado como pestaña: "
                    .'o se quita el ítem del menú o se saca el prefijo del mapa CONSOLIDADAS.'
                );
            }
        }
    }
}
