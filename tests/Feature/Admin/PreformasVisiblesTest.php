<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de la whitelist de preformas del selector de Asignar producción
 * (pedido del dueño 31-08-2026 con captura: «mi jefe quiere poder escoger
 * cuáles serán vistas»). Clave `produccion_preformas_visibles`, editada con
 * CHECKBOXES en Configuración; semántica whitelist elegida por el dueño (una
 * preforma nueva NO aparece hasta marcarla). Fuente única:
 * Producto::preformasVisibles() — selector y validación comparten universo.
 */
class PreformasVisiblesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function preforma(string $sku, string $nombre): Producto
    {
        return Producto::factory()->create([
            'sku' => $sku, 'nombre' => $nombre, 'activo' => true,
            'categoria' => 'Preformas', // calza el patrón %preforma% de config
        ]);
    }

    private function payloadAsignar(int $preformaId): array
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        return [
            'soplador_id' => $soplador->id, 'turno' => 'dia',
            'fecha' => now()->toDateString(), 'asignadas' => 100,
            'preforma_id' => $preformaId,
        ];
    }

    public function test_sin_seleccion_el_selector_ofrece_todas(): void
    {
        // BD virgen (clave sin sembrar) = el histórico: todas las del patrón.
        $a = $this->preforma('PRE-A', 'Preforma A');
        $b = $this->preforma('PRE-B', 'Preforma B');

        $ids = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))
            ->assertOk()
            ->viewData('preformas')
            ->pluck('id');

        $this->assertTrue($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));

        // Y el fallback histórico sigue vivo: sin NINGUNA con categoría de
        // preforma, se ofrecen todos los activos (mismo criterio de siempre).
        Producto::query()->update(['categoria' => 'Botellones']);
        $ids = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))
            ->viewData('preformas')
            ->pluck('id');
        $this->assertTrue($ids->contains($a->id));
    }

    public function test_marcar_una_seleccion_filtra_selector_y_validacion(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $visible = $this->preforma('PRE-VIS', 'Preforma visible');
        $oculta = $this->preforma('PRE-OCU', 'Preforma oculta');

        Configuracion::set(Producto::CLAVE_PREFORMAS_VISIBLES, ['PRE-VIS']);

        // El selector ofrece SOLO la marcada…
        $ids = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))
            ->viewData('preformas')
            ->pluck('id');
        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($oculta->id));

        // …y la validación usa el MISMO universo: la oculta no entra ni con
        // el POST armado a mano (doctrina M-3), la visible sí.
        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.asignar.store'), $this->payloadAsignar($oculta->id))
            ->assertSessionHasErrors('preforma_id');
        $this->assertDatabaseMissing('produccion_asignaciones', ['preforma_id' => $oculta->id]);

        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.asignar.store'), $this->payloadAsignar($visible->id))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('produccion_asignaciones', ['preforma_id' => $visible->id]);
    }

    public function test_una_preforma_nueva_no_aparece_con_seleccion_guardada(): void
    {
        // La semántica whitelist que eligió el dueño: con selección guardada,
        // lo nuevo del catálogo queda fuera hasta marcarlo…
        $this->seed(ConfiguracionSeeder::class);
        $this->preforma('PRE-VIEJA', 'Preforma vieja');
        Configuracion::set(Producto::CLAVE_PREFORMAS_VISIBLES, ['PRE-VIEJA']);

        $nueva = $this->preforma('PRE-NUEVA', 'Preforma nueva de Bsale');

        $ids = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))
            ->viewData('preformas')
            ->pluck('id');
        $this->assertFalse($ids->contains($nueva->id));

        // …y sin selección, aparece sola.
        Configuracion::set(Producto::CLAVE_PREFORMAS_VISIBLES, []);
        $ids = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))
            ->viewData('preformas')
            ->pluck('id');
        $this->assertTrue($ids->contains($nueva->id));
    }

    public function test_whitelist_podrida_cae_a_todas(): void
    {
        // Todos los SKU de la selección fuera del universo (renombrados o
        // desactivados): el selector NO puede quedar muerto por datos podridos.
        $this->seed(ConfiguracionSeeder::class);
        $a = $this->preforma('PRE-A', 'Preforma A');
        Configuracion::set(Producto::CLAVE_PREFORMAS_VISIBLES, ['SKU-QUE-YA-NO-EXISTE']);

        $ids = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))
            ->viewData('preformas')
            ->pluck('id');

        $this->assertTrue($ids->contains($a->id));
    }

    public function test_la_pantalla_edita_con_checkboxes_y_persiste(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->preforma('PRE-A', 'Preforma A');
        $this->preforma('PRE-B', 'Preforma B');
        Configuracion::set(Producto::CLAVE_PREFORMAS_VISIBLES, ['PRE-A']);
        $clave = Configuracion::query()
            ->where('clave', Producto::CLAVE_PREFORMAS_VISIBLES)->firstOrFail();

        // GET: checkboxes con la marcada reflejando la BD. Forma CONTIGUA del
        // input (x-checkbox emite el @checked ANTES de name/value y [^>]* no
        // cruza de tag) — un value suelto lo satisface cualquier casilla.
        $html = $this->actingAs($this->jefe())
            ->get(route('admin.configuracion.edit', $clave))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/checked[^>]*name="valor\[\]"[^>]*value="PRE-A"/', $html);
        $this->assertDoesNotMatchRegularExpression('/checked[^>]*name="valor\[\]"[^>]*value="PRE-B"/', $html);

        // PUT con checkboxes: persiste la selección nueva.
        $this->actingAs($this->jefe())
            ->put(route('admin.configuracion.update', $clave), ['valor' => ['PRE-B']])
            ->assertRedirect(route('admin.configuracion.index'));
        $this->assertSame(['PRE-B'], Configuracion::get(Producto::CLAVE_PREFORMAS_VISIBLES));

        // PUT sin `valor` (todas desmarcadas): guarda [] = modo automático,
        // y el selector vuelve a ofrecer todas.
        $this->actingAs($this->jefe())
            ->put(route('admin.configuracion.update', $clave), [])
            ->assertRedirect(route('admin.configuracion.index'));
        $this->assertSame([], Configuracion::get(Producto::CLAVE_PREFORMAS_VISIBLES));
        $this->assertCount(2, $this->actingAs($this->jefe())
            ->get(route('admin.produccion.asignar'))->viewData('preformas'));
    }

    public function test_un_sku_desconocido_se_rechaza_nombrandolo(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->preforma('PRE-A', 'Preforma A');
        $clave = Configuracion::query()
            ->where('clave', Producto::CLAVE_PREFORMAS_VISIBLES)->firstOrFail();

        $this->actingAs($this->jefe())
            ->put(route('admin.configuracion.update', $clave), ['valor' => ['PRE-A', 'PRE-TYPO']])
            ->assertSessionHasErrors('valor');

        // Y la BD no cambió.
        $this->assertSame([], Configuracion::get(Producto::CLAVE_PREFORMAS_VISIBLES));
    }
}
