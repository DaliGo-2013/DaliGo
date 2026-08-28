<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MenuPrincipal;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Volver" único (doctrina del dueño, 2026-07-24). Una sola forma de volver a la
 * pantalla anterior en toda la app: texto fijo "Volver", arriba a la izquierda
 * pegado al título, y presente SOLO en pantallas que cuelgan de un listado.
 *
 * Ancla: el atributo `data-dg-volver` que emite <x-volver> (doctrina anti
 * verde-engañoso). No se asserta por clases CSS —las comparte cualquier botón
 * secundario de la página— ni por la palabra "Volver" suelta, que también
 * aparece en tooltips, en prosa de ayuda y en los avisos de error.
 */
class VolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function cuantosVolver(string $html): int
    {
        return substr_count($html, 'data-dg-volver');
    }

    /**
     * El candado central: ni cero (usuario atrapado) ni dos (el defecto que
     * motivó todo esto — produccion/reporte tenía dos con destinos distintos).
     */
    public function test_pantalla_hija_tiene_exactamente_un_volver(): void
    {
        // El Kardex salió de esta lista en P-NAV-06 (pasó a ítem del menú y
        // perdió su Volver) y VOLVIÓ con la consolidación D1 (17-ago): de
        // nuevo es hija del panel de Producción, con el Volver de vuelta.
        // Tipos de botellón repite el molde con E1 (18-ago): dejó de ser ítem
        // (es pestaña de Configuración de producción) y su Volver volvió,
        // ahora apuntando a la anfitriona.
        foreach (['admin.produccion.dia', 'admin.produccion.movimientos', 'admin.tipos-botellon.index'] as $ruta) {
            $html = $this->actingAs($this->admin())->get(route($ruta))->assertOk()->getContent();

            $this->assertSame(1, $this->cuantosVolver($html),
                "[$ruta] debe tener EXACTAMENTE un botón Volver.");
        }
    }

    /**
     * Posición: el "Volver" se renderiza ANTES del título, no en el bloque de
     * acciones de la derecha (donde 26 vistas lo habían escrito a mano, pegado
     * al botón de confirmar).
     */
    public function test_el_volver_va_antes_del_titulo(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.produccion.dia'))->assertOk()->getContent();

        $volver = strpos($html, 'data-dg-volver');
        $titulo = strpos($html, '<h2 class="text-xl font-semibold');

        $this->assertNotFalse($volver, 'No se encontró el botón Volver.');
        $this->assertNotFalse($titulo, 'No se encontró el título del page-header.');
        $this->assertLessThan($titulo, $volver,
            'El "Volver" debe ir arriba a la IZQUIERDA (antes del título), no en las acciones de la derecha.');
    }

    /**
     * Forma: el texto VISIBLE es el literal "Volver" y nada más. El nombre del
     * destino va en el tooltip — si vuelve a colarse en el texto ("Volver a
     * listas", "Mis producciones"), el botón deja de verse igual en cada
     * pantalla y esto se pone rojo.
     */
    public function test_el_texto_visible_es_solo_volver(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.produccion.dia'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<a [^>]*data-dg-volver.*?<\/a>/s', $html, $enlace),
            'No se encontró el enlace del botón Volver.');
        $this->assertSame('Volver', trim(strip_tags($enlace[0])),
            'El texto visible debe ser exactamente "Volver"; el destino va en el title.');
    }

    /** El href apunta a la pantalla padre (destino garantizado sin JS). */
    public function test_el_volver_apunta_al_padre(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.produccion.dia'))
            ->assertOk()
            ->assertSee('href="'.route('admin.produccion.index').'" data-dg-volver', false);
    }

    /**
     * Un listado que ES ítem del menú no tiene página padre: se llega por la
     * sidebar. Cuatro listados llevaban un "Volver al inicio" que hacía que el
     * mismo botón significara dos cosas distintas según la pantalla.
     */
    public function test_un_listado_del_menu_no_lleva_volver(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.produccion.index'))->assertOk()->getContent();

        $this->assertSame(0, $this->cuantosVolver($html),
            'Un listado del menú no lleva Volver: se llega por la sidebar.');
    }

    /**
     * Los FORMULARIOS que además son ítem del menú son la única excepción: por
     * decisión del dueño un formulario tiene exactamente una salida, y es el
     * Volver (reemplazó a la X y al "Cancelar"). En lote/create el destino se
     * calcula por permiso: el conductor no puede ver el listado de ST (403), así
     * que para él el padre es el Inicio.
     */
    private const FORMULARIOS_DEL_MENU = ['admin.servicio-tecnico.lote.create'];

    /**
     * Candado DERIVADO de MenuPrincipal: cubre solo los ítems del menú de hoy y
     * cualquiera que se agregue mañana, sin mantener una lista a mano. Atrapó
     * drift real al construir esto (qr, seguimiento y informes son ítems del
     * menú y se les había puesto Volver por error).
     */
    public function test_ningun_item_del_menu_lleva_volver(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(Permission::all()); // todo el menú alcanzable

        $revisados = 0;

        foreach (MenuPrincipal::items() as $key => $item) {
            if (in_array($item['route'], self::FORMULARIOS_DEL_MENU, true)) {
                continue;
            }

            // Los ítems con parámetros en la URL no se pueden pedir sin fixtures.
            $ruta = Route::getRoutes()->getByName($item['route']);
            if ($ruta === null || $ruta->parameterNames() !== []) {
                continue;
            }

            $html = $this->actingAs($usuario)->get(route($item['route']))->assertOk()->getContent();
            $revisados++;

            $this->assertSame(0, $this->cuantosVolver($html),
                "El ítem de menú [{$key}] ({$item['route']}) NO debe llevar Volver: "
                .'se llega por la sidebar, no cuelga de un listado.');
        }

        // Si un refactor deja items() vacío o todo con parámetros, el foreach no
        // asserta nada y el test pasaría en falso.
        $this->assertGreaterThan(10, $revisados, 'Se revisaron muy pocos ítems del menú.');
    }

    /**
     * P-NAV-06 CERRADO (2026-07-27): las ex-huérfanas (Máquinas, Tipos de
     * botellón, Kardex, Conductores) ya SON ítems del menú — su antiguo Volver
     * (que era la única salida) se les quitó, y ahora las cubre
     * test_ningun_item_del_menu_lleva_volver como a cualquier ítem. Este
     * candado conserva la historia y avisa al revés: si alguien las SACA del
     * menú, vuelven a quedar huérfanas y necesitan su Volver de vuelta.
     */
    public function test_las_ex_huerfanas_estan_en_el_menu(): void
    {
        $exHuerfanas = [
            // Máquinas SIGUE aquí a propósito: con la consolidación E1 es la
            // ANFITRIONA de «Configuración de producción» — su ruta sigue
            // siendo la del ítem, así que nunca quedó huérfana.
            'admin.maquinas.index',
            // Tipos de botellón salió de esta lista con la consolidación E1
            // (18-ago), molde D1: al salir del menú recuperó su <x-volver>
            // (tercera vida: huérfana con Volver al panel → ítem P-NAV-06
            // 27-jul → pestaña de Configuración de producción con Volver a la
            // anfitriona). Su resaltado lo vigila MenuConsolidacionesTest y su
            // Volver volvió a test_pantalla_hija_tiene_exactamente_un_volver.
            // El Kardex (admin.produccion.movimientos) salió de esta lista con
            // la consolidación D1 (17-ago) — exactamente el caso que este
            // candado anuncia: al sacarlo del menú recuperó su <x-volver>
            // (tercera vida: huérfana con Volver → ítem P-NAV-06 27-jul → hija
            // del panel de Producción). Ya no queda huérfana: su entrada es el
            // botón «Kardex» de la cabecera del panel, su resaltado lo vigila
            // la 11ª entrada de MenuConsolidacionesTest y su Volver volvió a
            // test_pantalla_hija_tiene_exactamente_un_volver.
            'admin.conductores.index',
        ];

        $enElMenu = array_column(MenuPrincipal::items(), 'route');

        foreach ($exHuerfanas as $ruta) {
            $this->assertContains($ruta, $enElMenu,
                "[{$ruta}] salió del menú: sin ítem propio queda huérfana y necesita su Volver de vuelta (ver P-NAV-06/P-NAV-08).");
        }
    }

    /**
     * Candado anti-deriva: barre los fuentes Blade buscando los idiomas viejos.
     * Sin esto, la próxima pantalla que alguien escriba copiando una vecina
     * reintroduce una familia y volvemos de a poco a las 13 de antes.
     *
     * NO prohíbe la flecha en general: el carrusel y el mes anterior de la
     * agenda, el reset "← Todos los años" del listado de ST y el conmutador del
     * boceto de seguimiento usan la misma flecha y NO son navegación.
     */
    public function test_no_quedan_formas_viejas_de_volver(): void
    {
        $prohibidos = [
            'onclick="if (window.history.length'
                => 'el onclick de history.back copiado a mano (ahora lo hace el handler de data-dg-volver en app.js)',
            'label="Volver'
                => 'el icon-button con flecha escrito a mano (usa :back del page-header, o <x-volver> si la vista no tiene cabecera)',
            ':cancel='
                => 'la prop cancel de form-actions/form-footer (la única salida de un formulario es el Volver del encabezado)',
        ];

        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $revisados = 0;

        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }

            $contenido = file_get_contents($archivo->getPathname());
            $relativo = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            $revisados++;

            foreach ($prohibidos as $aguja => $porque) {
                $this->assertStringNotContainsString($aguja, $contenido,
                    "[{$relativo}] reintroduce {$porque}.");
            }

            // Enlace tenue con flecha como salida (la familia que vivía en tres
            // posiciones distintas, incluida el final de la página).
            $this->assertDoesNotMatchRegularExpression(
                '/<x-secondary-link[^>]*>\s*(?:←|&larr;|<span[^>]*>\s*(?:←|&larr;))/u',
                $contenido,
                "[{$relativo}] usa un x-secondary-link con flecha como salida; usa <x-volver>."
            );
        }

        $this->assertGreaterThan(100, $revisados, 'Se revisaron muy pocas vistas.');
    }
}
