<?php

namespace Tests\Feature\Admin;

use App\Support\PermisosAgrupados;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HIGIENE DE PERMISOS (pedido del dueño 05-08-2026: «crea permisos para que no
 * se pierda eso… pero no crear por crear para no acumularse lleno de permisos»).
 *
 * Cada permiso nuevo tiene que llegar COMPLETO o no llegar:
 *  1. con etiqueta legible en `config/permissions.php` → si no, la pantalla de
 *     Roles muestra el nombre técnico crudo («despachar traslado servicio»);
 *  2. con una categoría real → si no, cae en «Generales», que es el cajón donde
 *     los permisos se pierden de vista;
 *  3. USADO en alguna parte → un permiso que no gatea nada es exactamente la
 *     acumulación que el dueño no quiere. Si se crea antes que su
 *     funcionalidad (caso legítimo, ver PERMISOS_ANTES_DE_SU_FUNCIONALIDAD),
 *     hay que declararlo acá con su motivo — así la deuda es visible y no una
 *     línea olvidada en el seeder.
 *
 * Este test barre el seeder REAL, así que cubre los permisos que vengan.
 */
class HigienePermisosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Permisos que EXISTEN antes que la pantalla que van a gatear. Es una
     * práctica sancionada del proyecto («el permiso tiene que estar antes que
     * la funcionalidad», jefe_sucursal 28-07), pero cada caso se declara con su
     * motivo y su fecha: una lista que crece sin control es la señal de que se
     * están creando permisos por crear.
     *
     * @var array<string, string>  permiso => por qué todavía no gatea nada
     */
    private const PERMISOS_ANTES_DE_SU_FUNCIONALIDAD = [
        // Regla 9 de Contabilidad (28-07-2026): anular un documento tributario
        // es del gerente, el jefe de ventas y los jefes de sucursal. La emisión
        // de DTE todavía no existe (M05 no emite), así que no hay pantalla que
        // gatear — pero la matriz de quién puede anular ya está decidida.
        'emitir nota de credito' => 'M05 todavía no emite documentos: sin emisión no hay anulación que gatear (regla 9, 28-07).',
    ];

    /** @return list<string> los permisos que siembra el seeder */
    private function permisosSembrados(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return Permission::orderBy('name')->pluck('name')->all();
    }

    public function test_todo_permiso_tiene_etiqueta_legible(): void
    {
        $labels = (array) config('permissions.labels');

        $sinEtiqueta = array_values(array_filter(
            $this->permisosSembrados(),
            fn (string $p) => ! isset($labels[$p]),
        ));

        $this->assertSame([], $sinEtiqueta,
            "Estos permisos no tienen etiqueta en config/permissions.php, así que la pantalla de Roles muestra su nombre técnico crudo: \n  - ".implode("\n  - ", $sinEtiqueta));
    }

    public function test_ningun_permiso_cae_en_generales(): void
    {
        // «Generales» es el fallback de PermisosAgrupados: existe para que nada
        // quede sin mostrar, NO para que un dominio nuevo viva ahí. Si este test
        // se pone rojo, la respuesta es agregar la categoría en
        // config('permissions.grupos'), no ampliar el fallback.
        $huerfanos = array_values(array_filter(
            $this->permisosSembrados(),
            fn (string $p) => PermisosAgrupados::categoriaDe($p) === PermisosAgrupados::FALLBACK,
        ));

        $this->assertSame([], $huerfanos,
            "Estos permisos caen en «Generales» — les falta su categoría en config('permissions.grupos'): \n  - ".implode("\n  - ", $huerfanos));
    }

    public function test_ningun_permiso_queda_sin_usar(): void
    {
        $fuentes = $this->fuentesDelProyecto();

        $sinUso = [];
        foreach ($this->permisosSembrados() as $permiso) {
            if (isset(self::PERMISOS_ANTES_DE_SU_FUNCIONALIDAD[$permiso])) {
                continue;   // declarado a propósito, con su motivo
            }
            if (! str_contains($fuentes, $permiso)) {
                $sinUso[] = $permiso;
            }
        }

        $this->assertSame([], $sinUso,
            "Estos permisos no gatean NADA (ni una ruta, ni un @can, ni un ítem de menú).\n"
            ."O se cablean donde corresponde, o se sacan del seeder, o —si son un permiso\n"
            ."creado antes que su funcionalidad— se declaran en\n"
            ."HigienePermisosTest::PERMISOS_ANTES_DE_SU_FUNCIONALIDAD con su motivo:\n  - ".implode("\n  - ", $sinUso));
    }

    public function test_la_lista_de_permisos_sin_funcionalidad_no_junta_polvo(): void
    {
        // El guardián del guardián: si un permiso declarado como «pendiente» ya
        // se cableó, hay que sacarlo de la lista — o la excusa sobrevive al
        // motivo y la lista deja de significar algo.
        $fuentes = $this->fuentesDelProyecto();

        $yaUsados = array_values(array_filter(
            array_keys(self::PERMISOS_ANTES_DE_SU_FUNCIONALIDAD),
            fn (string $p) => str_contains($fuentes, $p),
        ));

        $this->assertSame([], $yaUsados,
            "Estos permisos YA se usan en el código: sacalos de PERMISOS_ANTES_DE_SU_FUNCIONALIDAD: \n  - ".implode("\n  - ", $yaUsados));
    }

    public function test_cada_item_del_menu_pide_un_permiso_que_existe(): void
    {
        // MenuPrincipalTest ya valida que el permiso exista; acá se cierra la
        // otra punta: que sea uno SEMBRADO, no uno inventado a mano que solo
        // existiría si alguien lo creó desde la UI de Roles.
        $sembrados = $this->permisosSembrados();

        $desconocidos = [];
        foreach (\App\Support\MenuPrincipal::items() as $clave => $item) {
            foreach (explode('|', (string) ($item['permiso'] ?? '')) as $permiso) {
                if ($permiso !== '' && ! in_array($permiso, $sembrados, true)) {
                    $desconocidos[] = "{$clave} → {$permiso}";
                }
            }
        }

        $this->assertSame([], $desconocidos,
            "Ítems del menú que piden un permiso que el seeder NO crea: \n  - ".implode("\n  - ", $desconocidos));
    }

    /** Todo el código donde un permiso podría estar cableado. */
    private function fuentesDelProyecto(): string
    {
        $texto = '';
        foreach (['app', 'routes', 'resources/views'] as $dir) {
            $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));
            foreach ($iterador as $archivo) {
                if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                    continue;
                }
                // El seeder nombra TODOS los permisos: incluirlo haría que
                // cualquier permiso parezca usado y el test no mordería.
                if (str_contains($archivo->getPathname(), 'RolesAndPermissionsSeeder')) {
                    continue;
                }
                $texto .= file_get_contents($archivo->getPathname());
            }
        }

        return $texto;
    }
}
