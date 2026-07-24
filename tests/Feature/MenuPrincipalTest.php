<?php

namespace Tests\Feature;

use App\Support\AccesosDashboard;
use App\Support\MenuPrincipal;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Candados de la fuente única del menú (sidebar V4). Espejo del patrón de
 * DashboardColoresTest::test_toda_card_tiene_icono_existente: el árbol de
 * MenuPrincipal es datos, y los datos con typos fallan silencioso en runtime
 * (route 500, permiso que nunca matchea, ícono en blanco) — estos tests los
 * convierten en rojo de CI.
 */
class MenuPrincipalTest extends TestCase
{
    use RefreshDatabase;

    public function test_toda_route_del_menu_existe(): void
    {
        foreach (MenuPrincipal::items() as $key => $item) {
            $this->assertTrue(
                Route::has($item['route']),
                "El ítem de menú [{$key}] apunta a la ruta [{$item['route']}] que no existe."
            );
        }
    }

    public function test_todo_permiso_del_menu_existe(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (MenuPrincipal::items() as $key => $item) {
            if ($item['permiso'] === null) {
                continue;
            }
            foreach (explode('|', $item['permiso']) as $permiso) {
                $this->assertTrue(
                    Permission::where('name', $permiso)->exists(),
                    "El ítem de menú [{$key}] usa el permiso [{$permiso}] que no existe en el seeder."
                );
            }
        }
    }

    public function test_todo_icono_del_menu_existe(): void
    {
        foreach (MenuPrincipal::MODULOS as $key => $modulo) {
            $this->assertFileExists(
                resource_path("views/components/icon/{$modulo['icon']}.blade.php"),
                "El módulo [{$key}] usa el ícono [{$modulo['icon']}] que no existe en components/icon/."
            );
        }
    }

    public function test_todo_patron_activo_matchea_su_propia_route(): void
    {
        // Garantiza que hacer clic en un ítem lo deja marcado activo: al menos
        // un patrón de 'activo' debe cubrir la 'route' del propio ítem.
        foreach (MenuPrincipal::items() as $key => $item) {
            $matchea = collect($item['activo'])
                ->contains(fn (string $patron) => Str::is($patron, $item['route']));
            $this->assertTrue(
                $matchea,
                "Ningún patrón 'activo' del ítem [{$key}] matchea su propia ruta [{$item['route']}]."
            );
        }
    }

    public function test_todo_patron_activo_extra_matchea_rutas_registradas(): void
    {
        // activo_extra cubre pantallas de detalle sin ítem propio (show,
        // cotización…): un typo en el patrón falla silencioso (el acordeón
        // simplemente no abre). Candado: cada patrón matchea al menos una
        // ruta registrada real.
        $nombres = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($ruta) => $ruta->getName())
            ->filter();

        foreach (MenuPrincipal::MODULOS as $key => $modulo) {
            foreach ($modulo['activo_extra'] ?? [] as $patron) {
                $this->assertTrue(
                    $nombres->contains(fn (string $nombre) => Str::is($patron, $nombre)),
                    "El patrón activo_extra [{$patron}] del módulo [{$key}] no matchea ninguna ruta registrada."
                );
            }
        }
    }

    public function test_toda_key_de_badge_tiene_resolver(): void
    {
        $resueltas = array_keys(MenuPrincipal::badges(null));

        // Módulos (badge propio, ej. ST) + ítems y links directos (items()
        // aplana ambos): todo badge declarado necesita resolver y tooltip.
        $declarantes = MenuPrincipal::items();
        foreach (MenuPrincipal::MODULOS as $key => $modulo) {
            $declarantes["modulo:{$key}"] = $modulo;
        }

        foreach ($declarantes as $key => $def) {
            if (isset($def['badge'])) {
                $this->assertContains(
                    $def['badge'],
                    $resueltas,
                    "[{$key}] declara el badge [{$def['badge']}] sin resolver en badges()."
                );
                $this->assertArrayHasKey(
                    'badge_title',
                    $def,
                    "[{$key}] tiene badge pero no badge_title (tooltip accesible)."
                );
            }
        }
    }

    public function test_badges_cuentan_pendientes_y_respetan_permisos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Señales reales: 1 aprobación pendiente, 1 orden por confirmar (QR),
        // 1 reporte enviado (por aprobar) y 1 devuelto del soplador.
        \App\Models\Aprobacion::create([
            'tipo_accion' => \App\Models\Aprobacion::ACCION_AJUSTE_REPORTE,
            'motivo' => 'm',
            'descripcion' => 'd',
            'rol_aprobador' => 'jefe_bodega',
        ]);
        \App\Models\OrdenServicio::factory()->create(['fuente' => 'qr', 'confirmada_at' => null]);
        \App\Models\AgendaTrabajo::factory()->create(['estado' => 'solicitado']);

        $soplador = tap(\App\Models\User::factory()->create())->assignRole('soplador');
        $asignacion = fn () => \App\Models\ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 100,
        ]);
        \App\Models\ProduccionReporte::create([
            'asignacion_id' => $asignacion()->id, 'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 100,
            'estado' => \App\Models\ProduccionReporte::ENVIADO,
        ]);
        \App\Models\ProduccionReporte::create([
            'asignacion_id' => $asignacion()->id, 'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 100,
            'estado' => \App\Models\ProduccionReporte::DEVUELTO,
        ]);

        // Admin: ve todas las señales globales + la bandeja completa.
        $admin = tap(\App\Models\User::factory()->create())->assignRole('admin');
        $badgesAdmin = MenuPrincipal::badges($admin);
        $this->assertSame(1, $badgesAdmin['aprobaciones_bandeja']);
        $this->assertSame(1, $badgesAdmin['st_por_confirmar']);
        $this->assertSame(1, $badgesAdmin['agenda_por_coordinar']);
        $this->assertSame(1, $badgesAdmin['produccion_por_aprobar']);

        // Soplador: solo su señal personal; las gateadas por permiso dan 0.
        $badgesSoplador = MenuPrincipal::badges($soplador);
        $this->assertSame(1, $badgesSoplador['mi_produccion_devueltos']);
        $this->assertSame(0, $badgesSoplador['aprobaciones_bandeja']);
        $this->assertSame(0, $badgesSoplador['st_por_confirmar']);
        $this->assertSame(0, $badgesSoplador['agenda_por_coordinar']);
        $this->assertSame(0, $badgesSoplador['produccion_por_aprobar']);
    }

    public function test_labels_de_menu_unicos(): void
    {
        // Codifica el hallazgo #1 del QA 15-07: dos ítems con el mismo nombre
        // confunden ("Aprobaciones" bandeja vs historial).
        $labels = collect(MenuPrincipal::items())->pluck('label');

        $this->assertSame(
            $labels->count(),
            $labels->unique()->count(),
            'Hay labels duplicados en el menú: '.$labels->duplicates()->implode(', ')
        );
    }

    public function test_cards_del_dashboard_son_subconjunto_del_menu(): void
    {
        // Anti-drift entre los dos catálogos (menú y accesos del Inicio) sin
        // unificarlos: cada card debe tener un ítem de menú con la misma ruta
        // y un permiso compatible (el del card es uno de los del ítem).
        $menuPorRoute = collect(MenuPrincipal::items())->keyBy('route');

        foreach (AccesosDashboard::cards() as $key => $card) {
            $item = $menuPorRoute->get($card['route']);

            $this->assertNotNull(
                $item,
                "La card [{$key}] apunta a [{$card['route']}] que no existe en MenuPrincipal."
            );
            $this->assertContains(
                $card['permiso'],
                explode('|', $item['permiso'] ?? ''),
                "La card [{$key}] usa el permiso [{$card['permiso']}] que no gatea su ítem de menú."
            );
        }
    }

    public function test_arbol_podado_deriva_visibilidad_de_los_items(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // conductor = solo 'crear lote servicio': el módulo ST aparece (derivado
        // de su único ítem visible) con SOLO ese ítem — en desktop Y móvil,
        // porque ambos renderizan este mismo árbol.
        $conductor = tap(\App\Models\User::factory()->create())->assignRole('conductor');
        $arbol = MenuPrincipal::para($conductor);

        $this->assertArrayHasKey('servicio-tecnico', $arbol);
        $this->assertSame(['lote'], array_keys($arbol['servicio-tecnico']['items']));
        $this->assertArrayNotHasKey('administracion', $arbol);
        $this->assertArrayHasKey('inicio', $arbol); // permiso null = todos

        // Invitado sin usuario: árbol vacío.
        $this->assertSame([], MenuPrincipal::para(null));
    }
}
