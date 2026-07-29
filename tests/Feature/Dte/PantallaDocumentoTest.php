<?php

namespace Tests\Feature\Dte;

use App\Models\OrdenServicio;
use App\Models\User;
use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\FormaPago;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Candados de la pantalla del documento (M05 · B8), que hoy es un ENSAYO EN SECO.
 *
 * Lo importante que se verifica acá: que la pantalla NO emite y que dice por qué,
 * en vez de ofrecer un botón que falle o —peor— uno que funcione sin autorización.
 */
class PantallaDocumentoTest extends TestCase
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

    private function ordenCobrable(int $manoObra = 30000): OrdenServicio
    {
        $orden = OrdenServicio::factory()->create([
            'facturacion' => 'reparacion',
            'mano_obra' => $manoObra,
        ]);

        $orden->repuestos()->create([
            'nombre' => 'Termostato', 'sku' => '1070154', 'cantidad' => 1, 'precio_unitario' => 12000,
        ]);

        return $orden->fresh();
    }

    public function test_la_pantalla_muestra_las_lineas_con_su_codigo_y_los_totales(): void
    {
        $orden = $this->ordenCobrable();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.documento', $orden))
            ->assertOk()
            ->assertSee('Termostato')
            ->assertSee('1070154')
            ->assertSee('Hora servicio técnico')
            // Totales: 12.000 + 30.000 = 42.000
            ->assertSee('$42.000')
            // El orden de la pantalla de Bsale.
            ->assertSee('Cantidad')
            ->assertSee('$/unidad')
            ->assertSee('Subtotal');
    }

    public function test_no_ofrece_emitir_y_explica_por_que(): void
    {
        $orden = $this->ordenCobrable();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.documento', $orden))
            ->assertOk()
            ->assertSee('Ensayo en seco')
            ->assertSee('no se va a emitir nada', false)
            // El aviso dice el motivo exacto, no un "no disponible" genérico.
            ->assertSee('DESHABILITADA');

        // Y el candado de verdad: todavía NO EXISTE una ruta que emita. El botón de
        // la pantalla es inerte porque no hay a dónde enviar, no porque esté oculto.
        $this->assertFalse(
            Route::has('admin.servicio-tecnico.documento.emitir'),
            'Cuando se agregue la ruta de emisión, este test debe cambiar A PROPÓSITO: '
            .'es el momento en que la pantalla pasa a poder crear documentos reales.',
        );
    }

    public function test_muestra_el_mensaje_exacto_que_se_enviaria(): void
    {
        // Con el medio de pago ya mapeado se puede armar el mensaje completo.
        config(['dte.bsale.medios_pago' => [FormaPago::EFECTIVO => 3]]);
        $orden = $this->ordenCobrable();

        $payload = $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.documento', $orden))
            ->assertOk()
            ->viewData('payload');

        $this->assertSame($orden->codigo, $payload['salesId']);
        $this->assertSame(DocumentoTributario::BOLETA, $payload['codeSii']);
        $this->assertCount(2, $payload['details']);
        $this->assertSame(3, $payload['payments'][0]['paymentTypeId']);
    }

    public function test_si_falta_un_id_de_bsale_el_documento_igual_se_ve(): void
    {
        // Es el estado normal hoy: config/dte.php arranca vacío. La pantalla tiene
        // que mostrar el documento igual —es su propósito— y decir qué falta, en vez
        // de quedar inservible hasta que alguien complete la configuración.
        config(['dte.bsale.medios_pago' => []]);
        $orden = $this->ordenCobrable();

        $vista = $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.documento', $orden))
            ->assertOk()
            ->assertSee('Termostato')
            ->assertSee('$42.000')
            ->assertSee('Falta un dato de Bsale');

        $this->assertNotNull($vista->viewData('documento'), 'El documento existe...');
        $this->assertNull($vista->viewData('payload'), '...pero el mensaje al emisor no se puede armar.');
        $this->assertStringContainsString('medios_pago', (string) $vista->viewData('faltaConfigurar'));
        $this->assertNull($vista->viewData('problema'), 'No es un problema del documento.');
    }

    public function test_el_tipo_de_documento_y_la_forma_de_pago_se_eligen(): void
    {
        $orden = $this->ordenCobrable();

        $vista = $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.documento', [
                'orden' => $orden,
                'tipo_dte' => DocumentoTributario::FACTURA_AFECTA,
                'forma_pago' => FormaPago::TRANSFERENCIA,
            ]))
            ->assertOk();

        $this->assertSame(DocumentoTributario::FACTURA_AFECTA, $vista->viewData('tipoDte'));
        $this->assertSame(FormaPago::TRANSFERENCIA, $vista->viewData('documento')->formaPago);
    }

    public function test_una_factura_sin_giro_avisa_en_la_pantalla(): void
    {
        // El cliente de la orden no tiene ficha, así que no hay giro.
        $orden = $this->ordenCobrable();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.documento', ['orden' => $orden, 'tipo_dte' => DocumentoTributario::FACTURA_AFECTA]))
            ->assertOk()
            ->assertSee('Falta el giro del cliente');
    }

    public function test_una_orden_en_garantia_muestra_el_problema_sin_reventar(): void
    {
        $orden = OrdenServicio::factory()->create([
            'facturacion' => 'garantia',
            'garantia_doc_fecha' => now()->subMonth()->toDateString(),
            'mano_obra' => 10000,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.documento', $orden))
            ->assertOk()
            ->assertSee('No se puede armar el documento')
            ->assertSee('GARANTÍA');
    }

    public function test_un_tipo_de_documento_invalido_se_rechaza(): void
    {
        $orden = $this->ordenCobrable();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.documento', ['orden' => $orden, 'tipo_dte' => DocumentoTributario::GUIA_DESPACHO]))
            ->assertSessionHasErrors('tipo_dte');
    }

    public function test_sin_el_permiso_de_emision_no_se_entra(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');

        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.documento', $this->ordenCobrable()))
            ->assertRedirect(route('dashboard'));
    }

    public function test_el_invitado_va_al_login(): void
    {
        $this->get(route('admin.servicio-tecnico.documento', $this->ordenCobrable()))
            ->assertRedirect('/login');
    }
}
