<?php

namespace Tests\Feature;

use App\Support\MenuPrincipal;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Candado del gate P-NAV-05: el permiso con que el MENÚ decide mostrar un ítem
 * tiene que alcanzar para ENTRAR a su ruta.
 *
 * El hueco que cierra: MenuPrincipalTest ya verifica que la ruta existe y que
 * el permiso existe en el seeder, pero NUNCA compara las dos cosas entre sí.
 * Un ítem que declare 'manage production' sobre una ruta gateada con
 * 'view audit' pasa todos los candados actuales y le muestra al usuario un
 * ítem que termina en 403 → rebote al Inicio con session('aviso'). Y los dos
 * tests que sí recorren todas las rutas del menú (SidebarTest, VolverTest) lo
 * hacen con Permission::all(), o sea que su assertOk() es un smoke test de
 * SUPERUSUARIO: no puede detectar esto por construcción.
 *
 * Se puede automatizar porque el middleware real usa la misma sintaxis que el
 * campo 'permiso' del menú: 'permission:a|b' con '|' = "cualquiera de".
 *
 * Álgebra de la comparación (importa, porque una ruta puede apilar gates):
 * - menú  = OR sobre su lista       → conjunto M
 * - ruta  = AND de sus middleware, y cada uno OR sobre su lista → clausulas C1..Cn
 * El menú IMPLICA a la ruta si, para CADA clausula Ci, M ⊆ Ci: así cualquier
 * permiso que alcance para ver el ítem alcanza también para pasar el gate.
 * Ejemplo real de gate apilado: admin.servicio-tecnico.documento vive dentro
 * del grupo 'permission:manage servicio tecnico' y ADEMÁS declara
 * 'permission:emitir documentos tributarios' — exige los dos.
 */
class MenuPermisoRutaTest extends TestCase
{
    public function test_el_permiso_del_menu_alcanza_para_entrar_a_su_ruta(): void
    {
        $revisados = 0;

        foreach (MenuPrincipal::items() as $key => $item) {
            $ruta = Route::getRoutes()->getByName($item['route']);
            $this->assertNotNull($ruta, "El ítem [{$key}] apunta a [{$item['route']}] que no existe.");

            $clausulas = self::clausulasDePermiso($ruta);
            if ($clausulas === []) {
                continue; // Ruta sin gate de permiso: nada que implicar.
            }

            $revisados++;
            $delMenu = $item['permiso'] === null ? [] : explode('|', $item['permiso']);

            foreach ($clausulas as $middleware => $clausula) {
                // permiso null = "todo autenticado": más permisivo que CUALQUIER
                // gate, así que no puede cubrir una ruta que exige permiso.
                $sobra = $delMenu === []
                    ? ['(ninguno: el ítem se muestra a todo autenticado)']
                    : array_diff($delMenu, $clausula);

                $this->assertSame(
                    [],
                    array_values($sobra),
                    "El ítem de menú [{$key}] se muestra con un permiso que NO alcanza para entrar a "
                    ."[{$item['route']}]: el menú declara [{$item['permiso']}] pero la ruta exige "
                    ."[{$middleware}]. Quien tenga ".implode(', ', $sobra)." va a VER el ítem y "
                    .'recibir un 403 (rebote al Inicio con aviso). Alinea el permiso del menú con el '
                    .'middleware de la ruta, o gatea la ruta más laxo — pero no dejes el ítem visible.'
                );
            }
        }

        // Que el candado no pase en verde por no haber revisado nada (si un
        // refactor cambia la forma de items() o del middleware, esto avisa).
        $this->assertGreaterThanOrEqual(
            25,
            $revisados,
            "Solo se revisaron {$revisados} ítems con gate de permiso: el candado deja de cubrir el menú."
        );
    }

    /**
     * Clausulas de permiso del stack REAL de la ruta, indexadas por el
     * middleware textual que las produjo (para nombrarlo en el fallo).
     *
     * gatherMiddleware() incluye el middleware heredado de los Route::group,
     * que es como está declarado casi todo el menú — leer solo la línea de la
     * ruta se perdería el gate del grupo, que es justo el caso peligroso.
     *
     * @return array<string, array<int, string>>
     */
    private static function clausulasDePermiso(\Illuminate\Routing\Route $ruta): array
    {
        $clausulas = [];

        foreach ($ruta->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }
            foreach (['permission:', 'can:'] as $prefijo) {
                if (str_starts_with($middleware, $prefijo)) {
                    $clausulas[$middleware] = explode('|', substr($middleware, strlen($prefijo)));
                }
            }
        }

        return $clausulas;
    }
}
