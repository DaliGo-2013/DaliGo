<?php

namespace Tests\Feature;

use App\View\Components\AppLayout;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ancho de página: DOS tokens y una sola fuente (decisión del dueño, 2026-07-25).
 *
 * Antes había 7 anchos distintos elegidos vista por vista y la banda del título
 * estaba fija en `max-w-7xl` dentro del layout, así que 47 de las 69 pantallas
 * con cabecera tenían el título desalineado del contenido — hasta 352px. Ahora
 * el título, el aviso y el cuerpo salen todos de la misma variable del layout.
 *
 * Estos son los PRIMEROS candados de layout del proyecto: antes de esto,
 * `grep "max-w\|overflow-x" tests/` no devolvía nada, y por eso el defecto vivió
 * sin que ningún test lo notara.
 */
class AnchoDePaginaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->givePermissionTo(Permission::all());
    }

    /**
     * Los anchos de los contenedores de PÁGINA que emite el layout (banda del
     * título, aviso y cuerpo). El ancla es `mx-auto max-w-… px-`: los tres los
     * llevan, y deja fuera los estrechamientos internos de una vista (ej. la
     * maqueta de teléfono del boceto de seguimiento, que es
     * `mx-auto max-w-sm rounded-2xl … p-5`, sin `px-`).
     *
     * @return list<string>
     */
    private function anchosDePagina(string $html): array
    {
        preg_match_all('/class="mx-auto (max-w-[0-9a-z]+) px-/', $html, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * El candado central: en una misma página, título y contenido comparten
     * EXACTAMENTE un ancho. Si alguien vuelve a fijar el ancho por su cuenta en
     * un lado, acá aparecen dos valores distintos y esto se pone rojo.
     */
    public function test_titulo_y_contenido_comparten_el_ancho(): void
    {
        $casos = [
            'admin.bodegas.index' => 'max-w-7xl',    // listado (default)
            'admin.clientes.create' => 'max-w-3xl',  // formulario
        ];

        foreach ($casos as $ruta => $esperado) {
            $html = $this->actingAs($this->admin())->get(route($ruta))->assertOk()->getContent();

            $this->assertSame([$esperado], $this->anchosDePagina($html),
                "[{$ruta}] debe tener UN solo ancho de página y ser {$esperado}.");
        }
    }

    /**
     * Ninguna vista vuelve a declarar su propio contenedor de página: ese es el
     * mecanismo que impide que los 7 anchos reaparezcan de a uno.
     */
    public function test_ninguna_vista_declara_su_propio_ancho_de_pagina(): void
    {
        $layout = realpath(resource_path('views/layouts/app.blade.php'));

        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $revisados = 0;

        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'php' || $archivo->getRealPath() === $layout) {
                continue;
            }

            $relativo = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            $revisados++;

            $this->assertDoesNotMatchRegularExpression(
                '/class="mx-auto max-w-[0-9a-z]+ px-/',
                file_get_contents($archivo->getPathname()),
                "[{$relativo}] declara su propio contenedor de página. El ancho lo pone el layout: "
                .'usa <x-app-layout ancho="listado|formulario">.'
            );
        }

        $this->assertGreaterThan(100, $revisados, 'Se revisaron muy pocas vistas.');
    }

    /**
     * Un token mal escrito se ve idéntico al correcto en pantalla, así que tiene
     * que reventar. Se verifica en los dos sentidos: que el componente rechace
     * lo inválido, y que ninguna vista declare algo fuera de la lista.
     */
    public function test_todo_ancho_declarado_es_un_token_valido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AppLayout('lista'); // typo verosímil de 'listado'
    }

    public function test_ninguna_vista_usa_un_token_inexistente(): void
    {
        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $declarados = 0;

        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }

            $relativo = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            preg_match_all('/<x-app-layout[^>]*\bancho="([^"]*)"/', file_get_contents($archivo->getPathname()), $m);

            foreach ($m[1] as $token) {
                $declarados++;
                $this->assertContains($token, AppLayout::ANCHOS,
                    "[{$relativo}] declara ancho=\"{$token}\", que no existe.");
            }
        }

        $this->assertGreaterThan(30, $declarados, 'Se encontraron muy pocos tokens declarados.');
    }

    /**
     * El aviso de bloqueo (403/404) usa el ancho de la página. Llegó hardcodeado
     * a `max-w-7xl`, así que sin esto queda corrido respecto del título y del
     * contenido en TODA vista `formulario` — tres bordes izquierdos distintos.
     */
    public function test_el_aviso_se_alinea_con_el_contenido(): void
    {
        $html = $this->actingAs($this->admin())
            ->withSession(['aviso' => 'No tienes permiso para entrar ahí.'])
            ->get(route('admin.clientes.create'))
            ->assertOk()
            ->assertSee('data-aviso', false)
            ->getContent();

        $this->assertSame(['max-w-3xl'], $this->anchosDePagina($html),
            'Con el aviso visible siguen siendo un solo ancho: título, aviso y contenido.');
    }
}
