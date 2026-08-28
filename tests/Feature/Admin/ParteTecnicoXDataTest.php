<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\TiempoReparacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EL `x-data` DEL PARTE DEL TÉCNICO NO SE CORTA A SÍ MISMO.
 *
 * Este candado existe por la bitácora [2026-08-25] y [2026-08-10]: una comilla doble dentro de un
 * `x-data` inline —o un cierre de comentario de bloque— corta el atributo HTML ahí mismo, el resto
 * del objeto queda fuera, y Alpine recibe un x-data truncado. El componente entero deja de
 * evaluar y CADA binding descendiente revienta con `ReferenceError: <prop> is not defined`. La
 * pantalla no responde a nada.
 *
 * LO GRAVE, Y EL MOTIVO DE ESTE ARCHIVO: la suite de PHP no evalúa Alpine, así que todo esto
 * convive con los candados en verde. La bitácora dejó la advertencia dos veces y el defecto
 * volvió las dos; la única red que existe es un chequeo estructural sobre el HTML renderizado.
 *
 * El x-data de esta pantalla es el candidato natural: es largo, se arma con seis `@js(...)` y uno
 * de ellos lleva el catálogo completo de trabajos con texto escrito por jefatura — o sea, texto
 * que puede traer comillas y que nadie revisa.
 */
class ParteTecnicoXDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    /**
     * Recorta el valor del atributo POR SU DELIMITADOR REAL (`"` a `"`), que es exactamente como
     * lo parte el navegador. Si adentro hay una comilla doble, el recorte queda corto — y eso es
     * lo que se mide.
     */
    private function atributoXData(string $html): string
    {
        $inicio = strpos($html, 'x-data="reparacionForm(');
        $this->assertNotFalse($inicio, 'No se encontró el x-data del parte del técnico: el candado dejó de mirar algo.');

        $desde = $inicio + strlen('x-data="');
        $fin = strpos($html, '"', $desde);

        return substr($html, $desde, $fin - $desde);
    }

    private function html(OrdenServicio $orden): string
    {
        return $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->getContent();
    }

    public function test_el_x_data_del_parte_no_se_corta_a_si_mismo(): void
    {
        // Un trabajo del catálogo CON COMILLAS DOBLES en su nombre: es texto que escribe
        // jefatura, así que puede traerlas, y viaja al x-data dentro del catálogo.
        TiempoReparacion::create([
            'trabajo' => 'Cambio de manguera de 1" y ajuste — funciona normal',
            'horas' => 1.0,
            'grupo' => 'Reparada',
            'activo' => true,
        ]);

        $atributo = $this->atributoXData($this->html(OrdenServicio::factory()->create()));

        // 1. El recorte tiene que llegar hasta el CIERRE del objeto. Si una comilla doble se
        //    colara, el atributo terminaría antes y esto quedaría corto.
        $this->assertStringContainsString(
            'textoTrabajo:',
            $atributo,
            'El x-data se cortó antes de su última clave: hay una comilla doble adentro (probablemente en un @js que no escapó).',
        );

        // 2. Y los comentarios de bloque, balanceados: un `*/` de más cierra el comentario en
        //    medio de la prosa y el resto pasa a ser código roto.
        $this->assertSame(
            substr_count($atributo, '/*'),
            substr_count($atributo, '*/'),
            'Los comentarios de bloque del x-data no están balanceados: el objeto queda con un error de sintaxis y Alpine no evalúa la pantalla.',
        );
    }

    /**
     * Lo mismo para el partial de los trabajos, que tiene su propio x-data chico (el colapsable)
     * y se alimenta de `$errors` y de la cuenta de marcados.
     */
    public function test_el_x_data_del_colapsable_de_trabajos_tampoco_se_corta(): void
    {
        $html = $this->html(OrdenServicio::factory()->create());

        $inicio = strpos($html, 'x-data="{ abierto:');
        $this->assertNotFalse($inicio, 'No se encontró el x-data del colapsable de trabajos.');

        $desde = $inicio + strlen('x-data="');
        $atributo = substr($html, $desde, strpos($html, '"', $desde) - $desde);

        $this->assertStringEndsWith('}', trim($atributo), 'El x-data del colapsable quedó cortado.');
    }

    /**
     * Y el catálogo llega COMPLETO al x-data: con un trabajo cuyo nombre trae comillas, los
     * demás tienen que seguir estando. Sin este assert, el test de arriba pasaría igual con el
     * catálogo truncado en el primer trabajo problemático.
     */
    public function test_el_catalogo_llega_completo_aunque_un_trabajo_traiga_comillas(): void
    {
        TiempoReparacion::create(['trabajo' => 'Cambio de manguera de 1" — funciona normal', 'horas' => 1.0, 'grupo' => 'Reparada', 'activo' => true]);
        TiempoReparacion::create(['trabajo' => 'Cambio de caldera — funciona normal', 'horas' => 1.5, 'grupo' => 'Reparada', 'activo' => true]);
        TiempoReparacion::create(['trabajo' => 'Cambio de filtro — funciona normal', 'horas' => 0.5, 'grupo' => 'Reparada', 'activo' => true]);

        $atributo = $this->atributoXData($this->html(OrdenServicio::factory()->create()));

        // Los TRES trabajos tienen que estar, y se buscan por su TEXTO y no por las comillas del
        // JSON: `@js()` las emite como secuencia unicode escapada (y el JSON entero va dentro de
        // comillas simples, que es lo que hace imposible que el atributo se corte). Asertar
        // contra esa secuencia es frágil de escribir —dos intentos dieron 0 por buscar la forma
        // equivocada, o sea un candado que no vigilaba nada— y además ataría el test al formato
        // interno del helper. El texto de cada trabajo es estable y prueba lo mismo: si el
        // catálogo se truncara en el primero (el que trae la comilla), los otros dos faltarían.
        foreach (['Cambio de manguera de 1', 'Cambio de caldera', 'Cambio de filtro'] as $esperado) {
            $this->assertStringContainsString(
                $esperado,
                $atributo,
                "El trabajo «{$esperado}» no llegó al catálogo del x-data: se truncó en el que trae la comilla doble.",
            );
        }
    }
}
