<?php

namespace Tests\Feature\Admin;

use App\Mail\EquipoListoParaRetiro;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * EL CORREO DEL RETIRO: LA GARANTÍA DE LA REPARACIÓN Y EL ORDEN DE LOS MOSTRADORES.
 *
 * Pedido del dueño (14-08-2026): «agregar abajo que a partir de la fecha de reparación del
 * dispensador entra en vigencia la garantía por tres meses; se retira en bodega el dispensador,
 * primero pasa por bodega para corroborar sus datos y luego a pagar por sala de ventas».
 *
 * EL ORDEN ESTABA AL REVÉS: el correo decía «pasa primero por sala de ventas para el pago y ahí
 * mismo te entregamos el equipo». Con eso el cliente entraba por la puerta equivocada y lo
 * mandaban a caminar — y es el tipo de detalle que nadie reporta como error, solo molesta.
 *
 * Y SON DOS GARANTÍAS DISTINTAS: la del PRODUCTO (6 meses desde la compra, la que decide si el
 * ingreso se cobra) y la de la REPARACIÓN (3 meses desde que se repara, la que se promete acá).
 * Reusar la de 6 habría prometido el doble de cobertura en un correo que el cliente guarda.
 */
class GarantiaYRetiroCorreoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    /** El HTML que le llega al cliente, ya renderizado. */
    private function correo(OrdenServicio $orden, ?OrdenServicioCotizacion $cotizacion = null): string
    {
        return (new EquipoListoParaRetiro($orden, $cotizacion))->render();
    }

    private function orden(array $overrides = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'estado' => 'reparado',
            'facturacion' => 'reparacion',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_email' => 'cliente@example.com',
            'trabajo_realizado' => 'Cambio de placa eléctrica — funciona normal',
            'sucursal_id' => Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'El Mirador', 'es_central' => true])->id,
        ], $overrides));
    }

    // ─────────────────────────────────────────────────── la garantía

    public function test_el_correo_dice_los_tres_meses_de_garantia_de_la_reparacion(): void
    {
        $html = $this->correo($this->orden());

        $this->assertStringContainsString('Garantía de la reparación', $html);
        $this->assertStringContainsString('3 meses', $html);
    }

    /**
     * SIN FECHAS DE DESDE/HASTA (dueño, 14-08, después de verlo renderizado): «no le pongas la
     * fecha desde hasta cuándo, solo 3 meses y listo». Se había calculado el vencimiento
     * pensando que le ahorraba una discusión; le pareció de más y manda él.
     */
    public function test_el_correo_no_pone_fechas_de_la_garantia(): void
    {
        Carbon::setTestNow('2026-08-14 10:00:00');

        $html = $this->correo($this->orden());

        $this->assertStringNotContainsString('vence el', $html);
        $this->assertStringNotContainsString('14-11-2026', $html, 'Volvió la fecha de vencimiento que el dueño pidió sacar.');
        $this->assertStringContainsString('Corre a partir de la fecha de reparación', $html);

        Carbon::setTestNow();
    }

    /** Si ya se avisó, la garantía corre desde ESE día y no desde el reenvío. */
    public function test_la_garantia_corre_desde_el_dia_en_que_se_reparo(): void
    {
        $orden = $this->orden(['listo_avisado_at' => '2026-07-01 09:00:00']);

        $this->assertSame('2026-07-01', $orden->garantiaReparacionDesde()->toDateString());
        $this->assertSame('2026-10-01', $orden->garantiaReparacionVence()->toDateString());
    }

    /** Las dos garantías no se pisan: 3 meses la del trabajo, 6 la del producto. */
    public function test_la_garantia_del_trabajo_no_es_la_del_producto(): void
    {
        $this->assertSame(3, OrdenServicio::GARANTIA_REPARACION_MESES);
        $this->assertSame(6, OrdenServicio::GARANTIA_MESES);
    }

    // ─────────────────────────────────────────────────── el orden del retiro

    public function test_primero_bodega_y_despues_sala_de_ventas(): void
    {
        $html = $this->correo($this->orden());

        $this->assertStringContainsString('Pasa primero por <strong>bodega</strong>', $html);
        $this->assertStringContainsString('retiras el equipo en bodega', $html);

        // Y el orden importa: bodega tiene que aparecer ANTES que sala de ventas en el texto.
        $this->assertLessThan(
            strpos($html, 'sala de ventas</strong> para el pago'),
            strpos($html, 'Pasa primero por <strong>bodega</strong>'),
            'El correo manda al cliente a sala de ventas antes que a bodega: es el orden que el dueño mandó cambiar.',
        );
    }

    /** Y el texto viejo no quedó dando vueltas en ninguna parte. */
    public function test_ya_no_dice_que_pase_primero_por_sala_de_ventas(): void
    {
        $html = $this->correo($this->orden());

        $this->assertStringNotContainsString('Pasa primero por <strong>sala de ventas</strong>', $html);
        $this->assertStringNotContainsString('ahí mismo te entregamos el equipo', $html);
    }

    /** El lugar de retiro dice «en bodega», no solo la sucursal. */
    public function test_los_datos_del_retiro_dicen_que_es_en_bodega(): void
    {
        $html = $this->correo($this->orden());

        $this->assertStringContainsString('El Mirador', $html);
        $this->assertStringContainsString('en bodega', $html);
    }

    /**
     * EN GARANTÍA NO HAY PAGO: no se le nombra sala de ventas, para no hacerlo buscar un
     * mostrador al que no tiene que ir. Pero la garantía del trabajo se le dice igual.
     */
    public function test_en_garantia_no_lo_manda_a_sala_de_ventas(): void
    {
        $orden = $this->orden([
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'boleta',
            'garantia_doc_fecha' => Carbon::now()->subMonth()->toDateString(),
        ]);

        $html = $this->correo($orden);

        $this->assertStringContainsString('No tienes que pasar por sala de ventas', $html);
        $this->assertStringContainsString('Pasa por <strong>bodega</strong>', $html);
        // La garantía del trabajo se promete igual: es información de la reparación, no del cobro.
        $this->assertStringContainsString('Garantía de la reparación', $html);
    }

    /** Sin sucursal (entró por ruta) el correo sigue sin inventar un lugar. */
    public function test_sin_sucursal_no_inventa_donde_retirar(): void
    {
        $html = $this->correo($this->orden(['sucursal_id' => null]));

        $this->assertStringContainsString('Coordinaremos contigo el punto de entrega', $html);
        // Pero la garantía se dice igual.
        $this->assertStringContainsString('Garantía de la reparación', $html);
    }
}
