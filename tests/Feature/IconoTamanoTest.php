<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Candado del tamaño de los componentes <x-icon.*>.
 *
 * El bug histórico: el <svg> hacía $attributes->merge(['class' => 'h-5 w-5']),
 * que CONCATENA las clases. Un llamador con h-4 w-4 producía "h-5 w-5 h-4 w-4"
 * y en el CSS compilado .h-5 va después de .h-4 → ganaba h-5 y el override hacia
 * abajo quedaba INERTE en todo el repo. Estos asserts fallan con el código viejo.
 * Ver bitácora 2026-07-24.
 */
class IconoTamanoTest extends TestCase
{
    private function claseDelSvg(string $template): string
    {
        $html = Blade::render($template);
        $this->assertMatchesRegularExpression(
            '/<svg[^>]*\bclass="/',
            $html,
            "El ícono no renderizó un <svg> con atributo class: {$template}"
        );
        preg_match('/<svg[^>]*\bclass="([^"]*)"/', $html, $m);

        return $m[1];
    }

    public function test_tamano_por_defecto_es_h5(): void
    {
        $this->assertSame('h-5 w-5', $this->claseDelSvg('<x-icon.pencil />'));
    }

    public function test_override_por_class_hacia_abajo_reemplaza_el_default(): void
    {
        // Con el código viejo esto era "h-5 w-5 h-4 w-4" y h-4 quedaba inerte.
        $clase = $this->claseDelSvg('<x-icon.trash class="h-4 w-4" />');

        $this->assertSame('h-4 w-4', $clase);
        $this->assertStringNotContainsString('h-5', $clase);
    }

    public function test_class_mixta_conserva_las_utilidades_no_de_tamano(): void
    {
        $clase = $this->claseDelSvg('<x-icon.chevron-down class="mt-1 h-4 w-4 text-neutral-400 -rotate-90" />');

        $this->assertStringContainsString('h-4 w-4', $clase);
        $this->assertStringContainsString('text-neutral-400', $clase);
        $this->assertStringContainsString('-rotate-90', $clase);
        $this->assertStringNotContainsString('h-5', $clase);
    }

    public function test_prop_size_estilo_avatar_reemplaza_el_default(): void
    {
        $this->assertSame('h-8 w-8', $this->claseDelSvg('<x-icon.plus size="h-8 w-8" />'));
    }

    public function test_class_sin_alto_conserva_el_default(): void
    {
        // Solo color: no hay un alto en class → el default h-5 w-5 debe mantenerse.
        $clase = $this->claseDelSvg('<x-icon.bell class="text-neutral-400" />');

        $this->assertStringContainsString('h-5 w-5', $clase);
        $this->assertStringContainsString('text-neutral-400', $clase);
    }

    public function test_min_height_no_dispara_la_guarda(): void
    {
        // min-h-12 NO es una utilidad de tamaño (h-N) → no debe descartar el default.
        $clase = $this->claseDelSvg('<x-icon.cube class="min-h-12" />');

        $this->assertStringContainsString('h-5 w-5', $clase);
        $this->assertStringContainsString('min-h-12', $clase);
    }
}
