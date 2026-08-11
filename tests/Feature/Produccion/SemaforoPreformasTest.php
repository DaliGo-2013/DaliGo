<?php

namespace Tests\Feature\Produccion;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Produccion\SemaforoPreformas;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-M11-22 · Semaforo de preformas. Candados del dictado v21: (1) verde/
 * amarillo/rojo exactos con stock 500/80/0 contra meta 100; (2) sin espejo o
 * sin preforma asignada => SIN semaforo (silencio, nada de rojo falso) — el
 * MUTADO del gate apunta a test_sin_enlace_bsale_es_silencio; (5) el soplador
 * sigue sin ver costos.
 */
class SemaforoPreformasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function sucursal(string $codigo = 'MIRADOR'): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => $codigo], ['nombre' => ucfirst(strtolower($codigo))]);
    }

    private function soplador(?Sucursal $sucursal = null): User
    {
        return tap(User::factory()->create(['sucursal_id' => $sucursal?->id]))->assignRole('soplador');
    }

    private function preforma(?int $variantId = 4242): Producto
    {
        return Producto::factory()->create([
            'nombre' => 'Preforma 20L test',
            'categoria' => 'Preformas',
            'activo' => true,
            'bsale_variant_id' => $variantId,
        ]);
    }

    private function bodega(Sucursal $sucursal, bool $enOperacion = true): Bodega
    {
        return Bodega::factory()->create([
            'sucursal_id' => $sucursal->id,
            'en_operacion' => $enOperacion,
            'estado_baja' => null,
        ]);
    }

    private function stock(Bodega $bodega, Producto $producto, int $disponible): Stock
    {
        return Stock::factory()->create([
            'bodega_id' => $bodega->id,
            'producto_id' => $producto->id,
            'stock_real' => $disponible,
            'stock_reservado' => 0,
            'stock_disponible' => $disponible,
        ]);
    }

    private function reporteCon(?Producto $preforma, User $soplador, int $asignadas = 100): ProduccionReporte
    {
        $fecha = FechaNegocio::hoy();

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $asignadas,
            'preforma_id' => $preforma?->id,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $asignadas,
            'estado' => ProduccionReporte::BORRADOR,
        ]);
    }

    private function estado(?ProduccionReporte $reporte, User $soplador): ?array
    {
        return app(SemaforoPreformas::class)->estadoPara($reporte, $soplador);
    }

    // --- Candado 1: los tres estados exactos contra meta 100 ---

    public function test_stock_que_alcanza_es_neutral(): void
    {
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $preforma = $this->preforma();
        $this->stock($this->bodega($sucursal), $preforma, 500);

        $estado = $this->estado($this->reporteCon($preforma, $soplador, 100), $soplador);

        $this->assertSame(SemaforoPreformas::ALCANZA, $estado['estado']);
        $this->assertSame('neutral', $estado['variante']);
        $this->assertStringContainsString('500', $estado['label']);
    }

    public function test_stock_parcial_es_brand(): void
    {
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $preforma = $this->preforma();
        $this->stock($this->bodega($sucursal), $preforma, 80);

        $estado = $this->estado($this->reporteCon($preforma, $soplador, 100), $soplador);

        $this->assertSame(SemaforoPreformas::PARCIAL, $estado['estado']);
        $this->assertSame('brand', $estado['variante']);
    }

    public function test_sin_stock_es_danger(): void
    {
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $preforma = $this->preforma();
        $this->stock($this->bodega($sucursal), $preforma, 0);

        $estado = $this->estado($this->reporteCon($preforma, $soplador, 100), $soplador);

        $this->assertSame(SemaforoPreformas::SIN_STOCK, $estado['estado']);
        $this->assertSame('danger', $estado['variante']);
    }

    // --- Candado 2: silencios (nada de rojo falso) ---

    public function test_sin_preforma_asignada_es_silencio(): void
    {
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $this->bodega($sucursal);

        $this->assertNull($this->estado($this->reporteCon(null, $soplador), $soplador));
    }

    public function test_sin_enlace_bsale_es_silencio(): void
    {
        // El MUTADO del gate: hay bodega EN OPERACION y cero filas de Stock —
        // sin el gate de variant_id, la suma vacia daria 0 y el semaforo
        // gritaria "sin stock" (danger) en vez de callar.
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $this->bodega($sucursal);
        $preforma = $this->preforma(variantId: null);

        $this->assertNull($this->estado($this->reporteCon($preforma, $soplador), $soplador));
    }

    public function test_soplador_sin_sucursal_es_silencio(): void
    {
        $soplador = $this->soplador(null);
        $preforma = $this->preforma();
        $this->stock($this->bodega($this->sucursal()), $preforma, 500);

        $this->assertNull($this->estado($this->reporteCon($preforma, $soplador), $soplador));
    }

    public function test_sucursal_sin_bodegas_operativas_es_silencio(): void
    {
        // La bodega existe pero esta fuera de operacion: hueco de
        // CONFIGURACION, no un quiebre de stock => silencio, no danger.
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $preforma = $this->preforma();
        $this->stock($this->bodega($sucursal, enOperacion: false), $preforma, 500);

        $this->assertNull($this->estado($this->reporteCon($preforma, $soplador), $soplador));
    }

    // --- Qué bodegas cuentan ---

    public function test_bodega_fuera_de_operacion_no_suma(): void
    {
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $preforma = $this->preforma();
        $this->stock($this->bodega($sucursal), $preforma, 80);
        $this->stock($this->bodega($sucursal, enOperacion: false), $preforma, 500);

        $estado = $this->estado($this->reporteCon($preforma, $soplador, 100), $soplador);

        // Si la cerrada sumara, seria ALCANZA (580 >= 100).
        $this->assertSame(SemaforoPreformas::PARCIAL, $estado['estado']);
    }

    public function test_bodega_de_otra_sucursal_no_suma(): void
    {
        $mirador = $this->sucursal('MIRADOR');
        $coquimbo = $this->sucursal('COQUIMBO');
        $soplador = $this->soplador($mirador);
        $preforma = $this->preforma();
        $this->stock($this->bodega($mirador), $preforma, 80);
        $this->stock($this->bodega($coquimbo), $preforma, 500);

        $estado = $this->estado($this->reporteCon($preforma, $soplador, 100), $soplador);

        $this->assertSame(SemaforoPreformas::PARCIAL, $estado['estado']);
    }

    // --- Superficie (candados 1 y 5) ---

    public function test_el_badge_se_ve_en_mi_reporte_y_sin_costos(): void
    {
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $preforma = $this->preforma();
        $this->stock($this->bodega($sucursal), $preforma, 80);
        $reporte = $this->reporteCon($preforma, $soplador, 100);

        $html = $this->actingAs($soplador)
            ->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('Preformas parciales: 80 visibles')
            ->getContent();

        // Regla Katana (candado 5): jamas un costo en la pantalla del operario.
        // Texto percibido (sin scripts/tags: los magics de Alpine llevan '$'
        // legitimos) + regex de monto — misma forma que RecetaCrudTest.
        $texto = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/si', ' ', $html);
        $texto = preg_replace('/<[^>]+>/', ' ', $texto);

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\p{L}])(precio|precios|costo|costos|CLP|UF)(?![\p{L}])/iu',
            $texto,
            'La pantalla del soplador menciona costos/precios con el semaforo activo.',
        );
        $this->assertDoesNotMatchRegularExpression('/\$\s?\d/', $texto, 'Hay un monto en pesos a la vista del soplador.');
    }

    public function test_sin_semaforo_la_cabecera_no_muestra_badge(): void
    {
        $sucursal = $this->sucursal();
        $soplador = $this->soplador($sucursal);
        $this->bodega($sucursal);
        $reporte = $this->reporteCon(null, $soplador);

        $this->actingAs($soplador)
            ->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertDontSee('Preformas OK')
            ->assertDontSee('Sin preformas visibles');
    }
}
