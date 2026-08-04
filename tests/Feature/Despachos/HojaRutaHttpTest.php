<?php

namespace Tests\Feature\Despachos;

use App\Models\DocumentoVenta;
use App\Models\HojaDeRuta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Services\Despachos\HojaRutaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La capa HTTP de la hoja de ruta: cada llave gatea SU ruta con SU permiso
 * (matriz cruzada — que ventas no pueda dar la llave de bodega es el punto
 * de que sean tres), el contrato de errores amables (GET navega al Inicio
 * con aviso, POST conserva su 403) y el form que arma la hoja.
 */
class HojaRutaHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Http::fake(function (Request $request) {
            if (preg_match('#documents/\d+\.json#', $request->url())) {
                return Http::response(['id' => 900, 'state' => 0, 'commercialState' => 0, 'cancellationStatus' => 0]);
            }

            return Http::response([], 404);
        });
    }

    private function usuario(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    private function hojaEnBorrador(): HojaDeRuta
    {
        return app(HojaRutaService::class)->crear([
            'sucursal_id' => Sucursal::factory()->create()->id,
            'zona_id' => Zona::factory()->create()->id,
            'vehiculo_id' => Vehiculo::factory()->create()->id,
            'conductor_id' => $this->usuario('conductor')->id,
        ]);
    }

    // ─── Acceso al listado (contrato de errores amables) ────────────

    public function test_el_listado_lo_ven_el_gestor_y_los_tres_autorizadores(): void
    {
        foreach (['jefe_logistica', 'jefe_despacho', 'jefe_ventas', 'jefe_bodega'] as $rol) {
            $this->actingAs($this->usuario($rol))
                ->get(route('admin.hojas-ruta.index'))
                ->assertOk();
        }
    }

    public function test_un_get_sin_permiso_navega_al_inicio_con_aviso(): void
    {
        $this->actingAs($this->usuario('conductor'))
            ->get(route('admin.hojas-ruta.index'))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull(session('aviso'));
    }

    public function test_solo_el_gestor_entra_al_form_de_creacion(): void
    {
        $this->actingAs($this->usuario('jefe_logistica'))
            ->get(route('admin.hojas-ruta.create'))
            ->assertOk();

        // jefe_ventas porta una llave pero NO arma hojas.
        $this->actingAs($this->usuario('jefe_ventas'))
            ->get(route('admin.hojas-ruta.create'))
            ->assertRedirect(route('dashboard'));
    }

    // ─── El form crea la hoja completa ──────────────────────────────

    public function test_el_form_crea_la_hoja_con_sus_paradas_y_cobros(): void
    {
        $docs = DocumentoVenta::factory()->count(2)->create();
        $vehiculo = Vehiculo::factory()->create(['ppu' => 'HR1234']);

        $res = $this->actingAs($this->usuario('jefe_logistica'))
            ->post(route('admin.hojas-ruta.store'), [
                'sucursal_id' => Sucursal::factory()->create()->id,
                'zona_id' => Zona::factory()->create()->id,
                'vehiculo_id' => $vehiculo->id,
                'conductor_id' => $this->usuario('conductor')->id,
                'documentos' => $docs->pluck('id')->all(),
                'cobros' => [$docs[0]->id => 'pagado'],
            ]);

        $hoja = HojaDeRuta::firstOrFail();
        $res->assertRedirect(route('admin.hojas-ruta.show', $hoja));

        $this->assertSame(1000, $hoja->folio);
        $this->assertSame('HR1234', $hoja->patente);
        $this->assertCount(2, $hoja->paradas);
        $this->assertSame('pagado', $hoja->paradas[0]->estado_cobro);
        $this->assertSame('cobrar_en_entrega', $hoja->paradas[1]->estado_cobro);
    }

    public function test_el_form_rechaza_un_vehiculo_dado_de_baja(): void
    {
        // Mismo scope que el selector: solo la flota ACTIVA (M-3, 2026-06-30).
        $baja = Vehiculo::factory()->create(['estado' => Vehiculo::ESTADO_BAJA, 'baja_at' => now(), 'baja_motivo' => 'vendido']);

        $this->actingAs($this->usuario('jefe_logistica'))
            ->post(route('admin.hojas-ruta.store'), [
                'sucursal_id' => Sucursal::factory()->create()->id,
                'zona_id' => Zona::factory()->create()->id,
                'vehiculo_id' => $baja->id,
                'conductor_id' => $this->usuario('conductor')->id,
                'documentos' => [DocumentoVenta::factory()->create()->id],
            ])
            ->assertSessionHasErrors('vehiculo_id');
    }

    public function test_el_form_exige_un_conductor_de_verdad(): void
    {
        $this->actingAs($this->usuario('jefe_logistica'))
            ->post(route('admin.hojas-ruta.store'), [
                'sucursal_id' => Sucursal::factory()->create()->id,
                'zona_id' => Zona::factory()->create()->id,
                'vehiculo_id' => Vehiculo::factory()->create()->id,
                'conductor_id' => $this->usuario('vendedor')->id,   // no es conductor
                'documentos' => [DocumentoVenta::factory()->create()->id],
            ])
            ->assertSessionHasErrors('conductor_id');
    }

    // ─── La matriz cruzada de llaves (el corazón de R11) ────────────

    public function test_cada_llave_solo_la_da_quien_porta_su_permiso(): void
    {
        $hoja = $this->hojaEnBorrador();

        // Un POST sin el permiso conserva su 403 (contrato de errores).
        $this->actingAs($this->usuario('jefe_despacho'))
            ->post(route('admin.hojas-ruta.autorizar-pagos', $hoja))->assertForbidden();
        $this->actingAs($this->usuario('jefe_bodega'))
            ->post(route('admin.hojas-ruta.autorizar-pagos', $hoja))->assertForbidden();

        $this->actingAs($this->usuario('jefe_ventas'))
            ->post(route('admin.hojas-ruta.autorizar-ruta', $hoja))->assertForbidden();
        $this->actingAs($this->usuario('jefe_bodega'))
            ->post(route('admin.hojas-ruta.autorizar-ruta', $hoja))->assertForbidden();

        $this->actingAs($this->usuario('jefe_ventas'))
            ->post(route('admin.hojas-ruta.autorizar-carga', $hoja))->assertForbidden();
        $this->actingAs($this->usuario('jefe_despacho'))
            ->post(route('admin.hojas-ruta.autorizar-carga', $hoja))->assertForbidden();

        // Y el gestor sin llaves tampoco: armar la hoja no da derecho a autorizarla.
        $this->actingAs($this->usuario('jefe_logistica'))
            ->post(route('admin.hojas-ruta.autorizar-pagos', $hoja))->assertForbidden();

        $this->assertSame(HojaDeRuta::BORRADOR, $hoja->fresh()->estado,
            'Ningún 403 debe haber movido el estado.');
    }

    public function test_la_cadena_completa_por_http(): void
    {
        $hoja = $this->hojaEnBorrador();

        $this->actingAs($this->usuario('jefe_ventas'))
            ->post(route('admin.hojas-ruta.autorizar-pagos', $hoja))
            ->assertRedirect(route('admin.hojas-ruta.show', $hoja));
        $this->assertSame(HojaDeRuta::PAGOS_OK, $hoja->fresh()->estado);

        $this->actingAs($this->usuario('jefe_despacho'))
            ->post(route('admin.hojas-ruta.autorizar-ruta', $hoja));
        $this->assertSame(HojaDeRuta::RUTA_AUTORIZADA, $hoja->fresh()->estado);

        $bodega = $this->usuario('jefe_bodega');
        $this->actingAs($bodega)->post(route('admin.hojas-ruta.autorizar-carga', $hoja));
        $this->assertSame(HojaDeRuta::CARGADA, $hoja->fresh()->estado);

        $this->actingAs($bodega)->post(route('admin.hojas-ruta.salir', $hoja));

        $fresca = $hoja->fresh();
        $this->assertSame(HojaDeRuta::EN_RUTA, $fresca->estado);
        $this->assertSame($bodega->id, $fresca->en_ruta_por);
    }

    public function test_un_salto_por_http_vuelve_con_error_sin_mover_el_estado(): void
    {
        $hoja = $this->hojaEnBorrador();

        // La ruta correcta con el permiso correcto, pero fuera de secuencia.
        $this->actingAs($this->usuario('jefe_despacho'))
            ->post(route('admin.hojas-ruta.autorizar-ruta', $hoja))
            ->assertSessionHasErrors('estado');

        $this->assertSame(HojaDeRuta::BORRADOR, $hoja->fresh()->estado);
    }

    // ─── El show y el reorden ───────────────────────────────────────

    public function test_el_show_muestra_la_cadena_y_el_boton_de_la_llave_pendiente(): void
    {
        $hoja = $this->hojaEnBorrador();

        $this->actingAs($this->usuario('jefe_ventas'))
            ->get(route('admin.hojas-ruta.show', $hoja))
            ->assertOk()
            ->assertSee('Hoja de ruta · folio '.$hoja->folio)
            ->assertSee('Autorizar pagos')
            ->assertSee(route('admin.hojas-ruta.autorizar-pagos', $hoja), false);

        // Quien no porta la llave 1 no ve su botón (el gate real es la ruta).
        $this->actingAs($this->usuario('jefe_bodega'))
            ->get(route('admin.hojas-ruta.show', $hoja))
            ->assertOk()
            ->assertDontSee(route('admin.hojas-ruta.autorizar-pagos', $hoja), false);
    }

    public function test_reordenar_por_http_regenera_la_secuencia(): void
    {
        $hoja = $this->hojaEnBorrador();
        $docs = DocumentoVenta::factory()->count(2)->create();
        app(HojaRutaService::class)->generarParadas($hoja, $docs->pluck('id')->all());
        [$primera, $segunda] = $hoja->paradas;

        $this->actingAs($this->usuario('jefe_logistica'))
            ->put(route('admin.hojas-ruta.orden', $hoja), [
                'paradas' => [$segunda->id, $primera->id],
            ])
            ->assertRedirect(route('admin.hojas-ruta.show', $hoja));

        $this->assertSame([$segunda->id, $primera->id], $hoja->fresh()->paradas->pluck('id')->all());

        // Reordenar es del gestor: una llave no basta.
        $this->actingAs($this->usuario('jefe_ventas'))
            ->put(route('admin.hojas-ruta.orden', $hoja), ['paradas' => [$primera->id, $segunda->id]])
            ->assertForbidden();
    }
}
