<?php

namespace Tests\Feature\Despachos;

use App\Models\Cliente;
use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * DESPACHOS-v1 · P-DSP-03: entidad Despacho + panel admin. La re-verificación
 * contra Bsale (requisito del review de P-DSP-01: un DTE anulado NO se
 * despacha aunque el espejo local diga vigente) se fakea por documento.
 */
class DespachoTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,mixed> respuesta del GET puntual documents/{id}.json */
    private array $bsaleDocPuntual = ['id' => 900, 'state' => 0, 'commercialState' => 0, 'cancellationStatus' => 0];

    private bool $bsaleCaido = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Http::fake(function (Request $request) {
            if ($this->bsaleCaido) {
                return Http::response('boom', 500);
            }
            if (preg_match('#documents/\d+\.json#', $request->url())) {
                return Http::response($this->bsaleDocPuntual);
            }

            return Http::response([], 404);
        });
    }

    private function jefe(): User
    {
        return User::factory()->create()->assignRole('jefe_bodega');
    }

    private function documento(array $overrides = []): DocumentoVenta
    {
        return DocumentoVenta::create(array_merge([
            'bsale_document_id' => 900,
            'folio' => 4321,
            'emitido_at' => now()->subDay(),
            'total' => 119000,
            'cancellation_status' => 0,
        ], $overrides));
    }

    public function test_panel_403_sin_permiso_y_200_para_jefe_bodega(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.despachos.index'))
            ->assertForbidden();

        $this->actingAs($this->jefe())
            ->get(route('admin.despachos.index'))
            ->assertOk()
            ->assertViewIs('admin.despachos.index');
    }

    public function test_crea_despacho_con_codigo_dsp_y_zona_del_cliente(): void
    {
        $zona = Zona::create(['nombre' => 'Santiago Norte', 'activa' => true]);
        $cliente = Cliente::create(['razon_social' => 'Cliente SpA', 'zona_id' => $zona->id]);
        $doc = $this->documento(['cliente_id' => $cliente->id]);

        $this->actingAs($this->jefe())
            ->post(route('admin.despachos.store'), ['documento_venta_id' => $doc->id])
            ->assertRedirect(route('admin.despachos.index'))
            ->assertSessionHas('status');

        $despacho = Despacho::firstOrFail();
        $this->assertMatchesRegularExpression('/^DSP-[A-Z0-9]{8}$/', $despacho->codigo);
        $this->assertSame(Despacho::PREPARADO, $despacho->estado);
        $this->assertSame($doc->id, $despacho->documento_venta_id);
        // Sin zona en el form → hereda la zona EFECTIVA del cliente (P-DSP-02).
        $this->assertSame($zona->id, $despacho->zona_id);
    }

    public function test_dte_anulado_en_bsale_se_rechaza_y_refresca_el_espejo(): void
    {
        // El espejo local dice vigente (stale), pero Bsale dice ANULADO: el
        // re-check puntual manda (requisito del review de P-DSP-01).
        $doc = $this->documento(['cancellation_status' => 0]);
        $this->bsaleDocPuntual = ['id' => 900, 'state' => 0, 'commercialState' => 2, 'cancellationStatus' => 1];

        $this->actingAs($this->jefe())
            ->from(route('admin.despachos.create'))
            ->post(route('admin.despachos.store'), ['documento_venta_id' => $doc->id])
            ->assertRedirect(route('admin.despachos.create'))
            ->assertSessionHasErrors('documento_venta_id');

        $this->assertSame(0, Despacho::count());
        // De paso el espejo quedó fresco: la anulación ya no está stale.
        $this->assertSame(1, $doc->fresh()->cancellation_status);
    }

    public function test_sin_verificacion_no_hay_despacho_si_bsale_esta_caido(): void
    {
        $doc = $this->documento();
        $this->bsaleCaido = true;

        $this->actingAs($this->jefe())
            ->from(route('admin.despachos.create'))
            ->post(route('admin.despachos.store'), ['documento_venta_id' => $doc->id])
            ->assertRedirect(route('admin.despachos.create'))
            ->assertSessionHasErrors('documento_venta_id');

        $this->assertSame(0, Despacho::count());
    }

    public function test_un_documento_solo_admite_un_despacho(): void
    {
        $doc = $this->documento();
        $jefe = $this->jefe();

        $this->actingAs($jefe)->post(route('admin.despachos.store'), ['documento_venta_id' => $doc->id]);
        $this->assertSame(1, Despacho::count());

        $this->actingAs($jefe)
            ->from(route('admin.despachos.create'))
            ->post(route('admin.despachos.store'), ['documento_venta_id' => $doc->id])
            ->assertSessionHasErrors('documento_venta_id');

        $this->assertSame(1, Despacho::count());
    }

    public function test_create_lista_solo_documentos_sin_despacho_y_no_anulados(): void
    {
        $libre = $this->documento(['bsale_document_id' => 901, 'folio' => 1001]);
        $anulado = $this->documento(['bsale_document_id' => 902, 'folio' => 1002, 'cancellation_status' => 1]);
        $conDespacho = $this->documento(['bsale_document_id' => 903, 'folio' => 1003]);
        Despacho::create(['documento_venta_id' => $conDespacho->id]);

        $this->actingAs($this->jefe())
            ->get(route('admin.despachos.create'))
            ->assertOk()
            ->assertViewHas('documentos', function ($docs) use ($libre, $anulado, $conDespacho) {
                return $docs->contains('id', $libre->id)
                    && ! $docs->contains('id', $anulado->id)
                    && ! $docs->contains('id', $conDespacho->id);
            });
    }

    public function test_permisos_nuevos_existen_y_estan_asignados(): void
    {
        $jefe = $this->jefe();
        $conductor = User::factory()->create()->assignRole('conductor');

        $this->assertTrue($jefe->can('manage despachos'));
        $this->assertTrue($conductor->can('confirmar entrega'));
        $this->assertFalse($conductor->can('manage despachos'));
    }

    public function test_verifica_el_documento_correcto_y_persiste_transportista_conductor_y_zona_explicita(): void
    {
        $zonaCliente = Zona::create(['nombre' => 'Santiago Norte', 'activa' => true]);
        $zonaForm = Zona::create(['nombre' => '6ª Región', 'activa' => true]);
        $cliente = Cliente::create(['razon_social' => 'Cliente SpA', 'zona_id' => $zonaCliente->id]);
        $doc = $this->documento(['cliente_id' => $cliente->id]);
        $conductor = User::factory()->create()->assignRole('conductor');

        $this->actingAs($this->jefe())->post(route('admin.despachos.store'), [
            'documento_venta_id' => $doc->id,
            'zona_id' => $zonaForm->id,
            'conductor_id' => $conductor->id,
            'transportista' => 'Camión externo',
        ])->assertRedirect(route('admin.despachos.index'));

        // La re-verificación fue contra el bsale_document_id (900), no el id local.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'documents/900.json'));

        $despacho = Despacho::firstOrFail();
        $this->assertSame($zonaForm->id, $despacho->zona_id); // la explícita GANA a la del cliente
        $this->assertSame($conductor->id, $despacho->conductor_id);
        $this->assertSame('Camión externo', $despacho->transportista);
    }

    public function test_respuesta_200_sin_cancellation_status_se_rechaza_fail_closed(): void
    {
        // Review P-DSP-03: un 200 con body vacío/shape inesperado NO es
        // "vigente" — indeterminado se rechaza igual que una caída.
        $doc = $this->documento();
        $this->bsaleDocPuntual = [];

        $this->actingAs($this->jefe())
            ->from(route('admin.despachos.create'))
            ->post(route('admin.despachos.store'), ['documento_venta_id' => $doc->id])
            ->assertRedirect(route('admin.despachos.create'))
            ->assertSessionHasErrors('documento_venta_id');

        $this->assertSame(0, Despacho::count());
    }

    public function test_unique_estructural_impide_dos_despachos_del_mismo_documento(): void
    {
        // La regla vive en la BD (unique documento_venta_id), no solo en el
        // check del service: una carrera que se cuele igual choca aquí.
        $doc = $this->documento();
        Despacho::create(['documento_venta_id' => $doc->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Despacho::create(['documento_venta_id' => $doc->id]);
    }

    public function test_conductor_debe_tener_rol_conductor(): void
    {
        $doc = $this->documento();
        $noConductor = User::factory()->create(); // sin rol

        $this->actingAs($this->jefe())
            ->from(route('admin.despachos.create'))
            ->post(route('admin.despachos.store'), [
                'documento_venta_id' => $doc->id,
                'conductor_id' => $noConductor->id,
            ])
            ->assertSessionHasErrors('conductor_id');

        $this->assertSame(0, Despacho::count());
    }

    public function test_zona_inactiva_se_rechaza(): void
    {
        $doc = $this->documento();
        $inactiva = Zona::create(['nombre' => 'Zona vieja', 'activa' => false]);

        $this->actingAs($this->jefe())
            ->from(route('admin.despachos.create'))
            ->post(route('admin.despachos.store'), [
                'documento_venta_id' => $doc->id,
                'zona_id' => $inactiva->id,
            ])
            ->assertSessionHasErrors('zona_id');

        $this->assertSame(0, Despacho::count());
    }

    public function test_index_filtra_por_estado_e_ignora_un_estado_invalido(): void
    {
        $d1 = $this->documento(['bsale_document_id' => 911, 'folio' => 2001]);
        $d2 = $this->documento(['bsale_document_id' => 912, 'folio' => 2002]);
        Despacho::create(['documento_venta_id' => $d1->id, 'estado' => Despacho::PREPARADO]);
        Despacho::create(['documento_venta_id' => $d2->id, 'estado' => Despacho::ENTREGADO]);

        $jefe = $this->jefe();

        $this->actingAs($jefe)
            ->get(route('admin.despachos.index', ['estado' => Despacho::PREPARADO]))
            ->assertOk()
            ->assertViewHas('despachos', fn ($p) => $p->total() === 1 && $p->first()->estado === Despacho::PREPARADO);

        $this->actingAs($jefe)
            ->get(route('admin.despachos.index', ['estado' => 'no-existe']))
            ->assertOk()
            ->assertViewHas('despachos', fn ($p) => $p->total() === 2); // inválido = sin filtro
    }

    public function test_create_y_store_tambien_exigen_el_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)->get(route('admin.despachos.create'))->assertForbidden();
        $this->actingAs($sinPermiso)
            ->post(route('admin.despachos.store'), ['documento_venta_id' => 1])
            ->assertForbidden();
    }

    public function test_store_con_documento_inexistente_falla_la_validacion(): void
    {
        $this->actingAs($this->jefe())
            ->from(route('admin.despachos.create'))
            ->post(route('admin.despachos.store'), ['documento_venta_id' => 999999])
            ->assertRedirect(route('admin.despachos.create'))
            ->assertSessionHasErrors('documento_venta_id');

        $this->assertSame(0, Despacho::count());
    }
}
