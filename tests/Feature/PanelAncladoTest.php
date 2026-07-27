<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Panel flotante: la posición se MIDE, no se declara (doctrina del dueño, 2026-07-26).
 *
 * La campanita abría un panel de 320px anclado al borde derecho de un botón que
 * vive en una sidebar de 264px: ~72px quedaban fuera de la pantalla. Es
 * reincidencia del hallazgo [2026-07-01] sobre los globos ⓘ — "el align es
 * estático y no se puede acertar", porque el lado bueno depende de dónde caiga
 * el disparador y del ancho de pantalla de quien mira. Aquel arreglo tocó sólo
 * al info-tip; x-dropdown quedó con la misma enfermedad.
 *
 * Ahora los dos comparten `x-dg-anclar` (resources/js/app.js), que al abrir mide
 * y coloca. Estos candados protegen el MECANISMO, que es lo que no se puede
 * verificar leyendo una vista.
 *
 * Ancla: el atributo `data-dg-panel` que emiten los dos componentes. No se
 * asserta por clases de posición sueltas (`absolute`, `end-0`) — las comparten
 * badges, timelines y autocompletados de media app, y un verde así pasaría por
 * la razón equivocada (doctrina verde-engañoso, bitácora 2026-07-20).
 */
class PanelAncladoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Paneles anclados que NO usan el mecanismo y está bien que no lo usen,
     * porque no pueden desbordarse por construcción. Se declaran acá para que
     * el barrido siga siendo estricto con todo lo demás.
     */
    private const SEGUROS_POR_CONSTRUCCION = [
        // Heredan el ancho del input que tienen encima (w-full): nunca son más
        // anchos que su disparador, así que no hay lado que elegir.
        'components/buscador-remoto.blade.php',
        'components/cliente-ingreso.blade.php',
        'admin/agenda-terreno/_form.blade.php',
        'admin/instalaciones/_form.blade.php',
        'admin/servicio-tecnico/cotizacion.blade.php',
        'admin/servicio-tecnico/reparacion.blade.php',
        'admin/servicio-tecnico/lote/create.blade.php',
        // Swatches del Inicio: inset-x-0, o sea pegado a los dos bordes de su
        // tarjeta. Tampoco elige lado.
        'components/dashboard/acceso.blade.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->givePermissionTo(Permission::all());
    }

    private function html(string $ruta): string
    {
        return $this->actingAs($this->admin())->get($ruta)->assertOk()->getContent();
    }

    /**
     * @return iterable<string, \RecursiveDirectoryIterator|\SplFileInfo>
     */
    private function vistas(): iterable
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \RecursiveDirectoryIterator::SKIP_DOTS)
        );
    }

    /**
     * El candado central: todo panel flotante que se pinta lleva el mecanismo.
     * Se comparan los DOS marcadores porque cada uno protege un descuido
     * distinto: si alguien agrega un panel nuevo sin `x-dg-anclar`, los conteos
     * se separan; si alguien borra la directiva del componente compartido, el
     * total cae a cero.
     */
    public function test_todo_panel_flotante_lleva_el_ancla(): void
    {
        foreach (['dashboard' => route('dashboard'), 'catálogo' => route('admin.productos.index')] as $nombre => $ruta) {
            $html = $this->html($ruta);

            $paneles = substr_count($html, 'data-dg-panel');
            $anclas = substr_count($html, 'x-dg-anclar');

            $this->assertGreaterThan(0, $paneles, "En {$nombre} no se pintó ningún panel flotante: el candado quedaría vacío.");
            $this->assertSame($paneles, $anclas,
                "En {$nombre} hay {$paneles} paneles flotantes pero {$anclas} con `x-dg-anclar`. "
                .'Un panel sin el mecanismo se sale de la pantalla donde no haya espacio.');
        }
    }

    /**
     * El caso que lo destapó, anclado a su pantalla concreta: el panel de la
     * campanita vive dentro del bloque de la sidebar y lleva el mecanismo. Sin
     * esto, el candado de arriba seguiría verde aunque justo la campanita
     * perdiera la directiva.
     */
    public function test_el_panel_de_la_campanita_lleva_el_ancla(): void
    {
        $html = $this->html(route('dashboard'));

        $desdeCampanita = substr($html, (int) strpos($html, 'data-menu-campanita'));
        $bloque = substr($desdeCampanita, 0, (int) strpos($desdeCampanita, 'data-menu-usuario'));

        $this->assertStringContainsString('data-dg-panel', $bloque, 'La campanita ya no pinta un panel flotante.');
        $this->assertStringContainsString('x-dg-anclar', $bloque,
            'El panel de la campanita perdió `x-dg-anclar`: con 320px dentro de una sidebar de 264px, vuelve a salirse ~72px por la izquierda.');
    }

    /**
     * Red de seguridad estática: aunque el JS no haya corrido todavía (o falle),
     * el panel no puede ser más ancho que la pantalla. Se asserta la forma
     * CONTIGUA que produce el componente —ancho seguido del tope— y no la clase
     * suelta, que por sí sola no dice a quién se le aplicó.
     */
    public function test_el_panel_declara_un_tope_de_viewport(): void
    {
        $html = $this->html(route('admin.productos.index'));

        foreach (['w-80 max-w-[calc(100vw-1rem)]', 'w-56 max-w-[calc(100vw-1rem)]'] as $forma) {
            $this->assertStringContainsString($forma, $html,
                "Falta el tope de viewport contiguo al ancho ({$forma}): sin él, un panel más ancho que la pantalla desborda antes de que corra el JS.");
        }
    }

    /**
     * Un ancho mal escrito no se ve: el panel simplemente se queda sin ancho.
     * Antes `width="48"` y `width="w-80"` funcionaban por un `match` con un caso
     * especial, y cualquier otro número suelto emitía una clase inválida.
     */
    public function test_un_ancho_de_menu_desconocido_revienta(): void
    {
        // Blade envuelve lo que se lanza al renderizar, así que se asserta el
        // MENSAJE (más específico que la clase) en vez de expectException.
        try {
            Blade::render(
                '<x-dropdown width="56"><x-slot name="trigger">t</x-slot><x-slot name="content">c</x-slot></x-dropdown>'
            );
            $this->fail('Un ancho inválido tiene que reventar: si no, el panel queda sin ancho y nadie se entera.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Ancho de menú desconocido [56]', $e->getMessage());
        }
    }

    public function test_un_ancho_de_menu_valido_si_renderiza(): void
    {
        $html = Blade::render(
            '<x-dropdown width="w-56"><x-slot name="trigger">t</x-slot><x-slot name="content">c</x-slot></x-dropdown>'
        );

        // Sin esto, el test de arriba pasaría igual con un componente que
        // rechaza TODO (un verde por la razón equivocada).
        $this->assertStringContainsString('w-56 max-w-[calc(100vw-1rem)]', $html);
        $this->assertStringContainsString('data-dg-panel', $html);
    }

    /**
     * El prop que la bitácora declaró imposible de acertar ya no existe, y
     * ninguna vista lo pasa. Se verifica en los dos sentidos para que no sea un
     * assertDontSee trivial: primero que los usos existan (si el componente
     * desapareciera, esto no protegería nada), después que ninguno traiga align.
     */
    public function test_el_info_tip_ya_no_adivina_su_lado(): void
    {
        $usos = 0;

        foreach ($this->vistas() as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }

            $relativo = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            $fuente = file_get_contents($archivo->getPathname());

            preg_match_all('/<x-info-tip\b[^>]*/', $fuente, $m);

            foreach ($m[0] as $etiqueta) {
                $usos++;
                $this->assertStringNotContainsString('align', $etiqueta,
                    "[{$relativo}] le pasa `align` al ⓘ. El lado lo elige `x-dg-anclar` midiendo; "
                    .'escrito a mano vuelve a fallar en los anchos donde no se acertó.');
            }
        }

        $this->assertGreaterThan(20, $usos, 'Se encontraron muy pocos usos de <x-info-tip>: el barrido no probaría nada.');

        $this->assertStringNotContainsString("'align'",
            file_get_contents(resource_path('views/components/info-tip.blade.php')),
            'El componente ⓘ volvió a declarar el prop `align`.');
    }

    /**
     * Ninguna vista se arma su propio panel anclado a mano. Es el mecanismo que
     * impide que el error vuelva de a uno: `x-dropdown` y `x-info-tip` son los
     * dos únicos lugares donde se decide una posición flotante.
     */
    public function test_ninguna_vista_ancla_un_panel_a_mano(): void
    {
        $componentes = [
            realpath(resource_path('views/components/dropdown.blade.php')),
            realpath(resource_path('views/components/info-tip.blade.php')),
        ];

        // Un panel es "anclado a mano" cuando junta las tres cosas que causaron
        // el defecto: posición absoluta, ancho FIJO (no w-full, que hereda) y un
        // borde horizontal elegido a dedo.
        $patron = '/class="[^"]*\babsolute\b[^"]*"/';

        $revisados = 0;

        foreach ($this->vistas() as $archivo) {
            if ($archivo->getExtension() !== 'php' || in_array($archivo->getRealPath(), $componentes, true)) {
                continue;
            }

            $relativo = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            $relativo = str_replace('\\', '/', $relativo);
            $revisados++;

            if (in_array($relativo, self::SEGUROS_POR_CONSTRUCCION, true)) {
                continue;
            }

            preg_match_all($patron, file_get_contents($archivo->getPathname()), $m);

            foreach ($m[0] as $clases) {
                $anclaHorizontal = preg_match('/\b(start|end|left|right)-0\b/', $clases);
                // (?<![\w-]) deja fuera `min-w-`/`max-w-`: un badge con
                // `min-w-[1.25rem]` no es un panel, es un contador de 20px.
                // `w-full` tampoco cuenta — hereda el ancho y no elige lado.
                $anchoFijo = preg_match('/(?<![\w-])w-(\d+|\[)/', $clases);

                $this->assertFalse((bool) ($anclaHorizontal && $anchoFijo),
                    "[{$relativo}] arma un panel anclado a mano ({$clases}). "
                    .'Usa <x-dropdown> o <x-info-tip>: son los que traen `x-dg-anclar`.');
            }
        }

        $this->assertGreaterThan(100, $revisados, 'Se revisaron muy pocas vistas.');
    }

    /**
     * La sidebar tiene que pintar POR ENCIMA del contenido: al correrse para
     * caber, el panel de la campanita cruza los 264px y entra sobre <main>, que
     * tiene elementos en z-10/z-20/z-30. `sticky` crea contexto de apilamiento
     * aunque el z-index sea auto, así que resetearlo en lg dejaba la sidebar
     * debajo. Se asserta la forma contigua además del negativo: un
     * assertDontSee solo pasaría trivial el día que cambie el orden de clases.
     */
    public function test_la_sidebar_no_queda_bajo_el_contenido(): void
    {
        $html = $this->html(route('dashboard'));

        $this->assertStringContainsString('z-40 flex w-[300px]', $html,
            'Cambió la forma del <aside>: revisá que siga trayendo su z-index base.');
        $this->assertStringContainsString('lg:sticky lg:top-0 lg:h-screen', $html,
            'Cambió la forma del <aside> en lg: este candado dejó de mirar lo que dice mirar.');
        $this->assertStringNotContainsString('lg:z-auto', $html,
            'El <aside> volvió a resetear su z-index en lg: el panel de la campanita queda por debajo del contenido.');
    }
}
