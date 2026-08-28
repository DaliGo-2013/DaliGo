<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MenuPrincipal;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SERVICIO TÉCNICO SON DOS DOMINIOS Y NO SE MEZCLAN (dueño, 28-08-2026):
 *
 *   «el ingreso por unidad y por lote se refiere a los dispensadores, y lo de
 *    instalaciones, reparaciones, mantenciones y visita técnica es referido a lo
 *    industrial — sopladora, lavadora, osmosis. Que estén las dos opciones
 *    separadas para los vendedores y el jefe de ventas. Es importante que no esté
 *    mezclado aunque los dos temas son de servicio técnico en general.»
 *
 * El menú los tenía en una lista plana de cinco ítems donde nada decía cuál era
 * cuál. El vocabulario de la separación NO es nuevo: es el que ya usaba el
 * Informe («Dispensadores» / «Industrial»).
 */
class MenuDominiosServicioTecnicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function conRol(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    private function itemsST(User $user): array
    {
        return MenuPrincipal::para($user)['servicio-tecnico']['items'] ?? [];
    }

    /**
     * Cada ítem del taller y del terreno declara SU dominio. Derivado de los
     * datos, así que un ítem nuevo sin grupo lo pone rojo — que es justo lo que
     * hay que decidir al agregarlo: ¿es dispensadores, industrial, o sirve a los
     * dos (y entonces va sin grupo, arriba)?
     */
    public function test_cada_item_de_servicio_tecnico_declara_su_dominio(): void
    {
        $items = MenuPrincipal::MODULOS['servicio-tecnico']['items'];

        $esperado = [
            'listado' => 'dispensadores',
            'lote' => 'dispensadores',
            'agenda-terreno' => 'industrial',
            'instalaciones' => 'industrial',
            // El Informe sirve a los DOS y los separa por dentro: va sin grupo.
            'informe' => null,
        ];

        foreach ($esperado as $key => $grupo) {
            $this->assertArrayHasKey($key, $items, "Desapareció el ítem [{$key}] del menú de Servicio Técnico.");
            $this->assertSame($grupo, $items[$key]['grupo'] ?? null,
                "El ítem [{$key}] debe declarar grupo ".($grupo ?? 'NINGUNO')
                .': los dos dominios de Servicio Técnico no se mezclan.');
        }

        // Y que no quede ningún ítem del módulo fuera de esta decisión.
        $this->assertSame([], array_diff(array_keys($items), array_keys($esperado)),
            'Hay un ítem nuevo en Servicio Técnico sin decidir a qué dominio pertenece.');
    }

    /**
     * El orden que pidió el dueño: el Informe arriba (sin encabezado, porque
     * sirve a los dos), después DISPENSADORES y después INDUSTRIAL.
     */
    public function test_el_jefe_de_ventas_ve_los_dos_dominios_separados_y_en_orden(): void
    {
        $bloques = MenuPrincipal::agrupar($this->itemsST($this->conRol('jefe_ventas')));

        $this->assertCount(3, $bloques, 'Deben ser tres bloques: sin rótulo, Dispensadores e Industrial.');

        $this->assertNull($bloques[0]['titulo'], 'El Informe va arriba y SIN encabezado: sirve a los dos dominios.');
        $this->assertSame(['informe'], array_keys($bloques[0]['items']));

        $this->assertSame('Dispensadores', $bloques[1]['titulo']);
        $this->assertSame(['listado', 'lote'], array_keys($bloques[1]['items']),
            'Los dos ingresos del taller —unidad (botón del Listado) y lote— van juntos.');

        $this->assertSame('Industrial', $bloques[2]['titulo']);
        $this->assertSame(['agenda-terreno', 'instalaciones'], array_keys($bloques[2]['items']));
    }

    /** El vendedor ve la misma separación: es el que no los tiene que confundir. */
    public function test_el_vendedor_ve_los_dos_dominios_separados(): void
    {
        $bloques = MenuPrincipal::agrupar($this->itemsST($this->conRol('vendedor')));

        $titulos = array_column($bloques, 'titulo');
        $this->assertSame([null, 'Dispensadores', 'Industrial'], $titulos);
    }

    /**
     * Un encabezado NUNCA queda huérfano: el bloque nace de los ítems que
     * sobrevivieron a la poda por permiso. El técnico de taller no tiene nada
     * industrial, así que no debe ver ese rótulo.
     */
    public function test_un_dominio_sin_items_visibles_no_deja_encabezado_huerfano(): void
    {
        $bloques = MenuPrincipal::agrupar($this->itemsST($this->conRol('tecnico')));

        $this->assertNotContains('Industrial', array_column($bloques, 'titulo'),
            'El técnico de taller no tiene permisos industriales: ese encabezado no debe existir para él.');
        $this->assertContains('Dispensadores', array_column($bloques, 'titulo'));
    }

    /**
     * Y el caso simétrico, que además prueba que el orden de los bloques NO es
     * una lista fija: el técnico industrial tiene prioridad por rol (agenda e
     * instalaciones primero), así que SU bloque Industrial sube — sin que
     * `agrupar()` sepa nada de roles.
     */
    public function test_el_orden_de_los_bloques_respeta_la_prioridad_por_rol(): void
    {
        // Carlos cubriendo el taller además de lo suyo (mismo escenario que
        // MenuPrincipalTest::test_tecnico_industrial_lidera_con_agenda_e_instalaciones).
        $carlos = $this->conRol('tecnico_industrial');
        $carlos->givePermissionTo(['view servicio tecnico', 'manage servicio tecnico', 'crear lote servicio']);

        $titulos = array_column(MenuPrincipal::agrupar($this->itemsST($carlos)), 'titulo');

        $this->assertSame([null, 'Industrial', 'Dispensadores'], $titulos,
            'Para el técnico industrial el bloque Industrial va PRIMERO: el orden de los bloques '
            .'sale de la primera aparición de cada grupo, no de una lista fija.');
    }

    /**
     * Un `grupo` sin rótulo en GRUPOS revienta en vez de dibujar un encabezado
     * vacío — que es un defecto que en una revisión de código no se ve.
     */
    public function test_un_grupo_desconocido_revienta(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Grupo de menú desconocido: [taller]');

        MenuPrincipal::agrupar(['x' => ['label' => 'X', 'grupo' => 'taller', 'permiso' => null]]);
    }

    /** Un módulo sin grupos sigue funcionando como antes: UN bloque sin título. */
    public function test_un_modulo_sin_grupos_devuelve_un_solo_bloque_sin_titulo(): void
    {
        $bloques = MenuPrincipal::agrupar(MenuPrincipal::MODULOS['comercial']['items']);

        $this->assertCount(1, $bloques);
        $this->assertNull($bloques[0]['titulo']);
    }

    /** Y la separación llega a la pantalla, no solo a los datos. */
    public function test_la_sidebar_dibuja_los_dos_encabezados(): void
    {
        $html = $this->actingAs($this->conRol('jefe_ventas'))->get(route('dashboard'))
            ->assertOk()->getContent();

        // Contiguo a la clase del encabezado de sección: el rótulo suelto podría
        // venir de cualquier otra parte de la página (doctrina verde-engañoso).
        foreach (['Dispensadores', 'Industrial'] as $titulo) {
            $this->assertStringContainsString(
                'uppercase tracking-wide text-neutral-500">'.$titulo.'<', $html,
                "Falta el encabezado [{$titulo}] en la sidebar."
            );
        }
    }
}
