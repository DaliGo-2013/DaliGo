<?php

namespace Tests\Feature\Devoluciones;

use App\Models\Bodega;
use App\Models\Devolucion;
use App\Models\DevolucionItem;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lado interno de M13: gates por permiso, recibir con fotos de BODEGA (el
 * segundo momento de evidencia), la regla dura del transporte (P-M13-02) y
 * el reingreso al kardex LOCAL — con el candado central del módulo: los
 * `stocks` (espejo de Bsale) NO se tocan jamás.
 */
class DevolucionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Storage::fake('local');
    }

    private function jefeBodega(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    public function test_el_listado_y_la_ficha_gatean_por_permiso(): void
    {
        $devolucion = Devolucion::factory()->create();
        $sinPermiso = tap(User::factory()->create())->assignRole('soplador');

        $this->actingAs($sinPermiso)->get(route('admin.devoluciones.index'))->assertRedirect();
        $this->actingAs($this->jefeBodega())->get(route('admin.devoluciones.index'))
            ->assertOk()
            ->assertSee($devolucion->folio);
        $this->actingAs($this->jefeBodega())->get(route('admin.devoluciones.show', $devolucion->id))
            ->assertOk()
            ->assertSee($devolucion->cliente_nombre);
    }

    public function test_recibir_exige_fotos_de_bodega_y_avisa_al_cliente(): void
    {
        $devolucion = Devolucion::factory()->create();
        $devolucion->items()->create(['descripcion' => 'Dispensador', 'cantidad' => 1]);
        $jefe = $this->jefeBodega();

        // Sin fotos → validación.
        $this->actingAs($jefe)
            ->post(route('admin.devoluciones.recibir', $devolucion->id))
            ->assertSessionHasErrors('fotos');

        // Con fotos → recibida, con SU origen, y el cliente avisado por mail.
        $this->actingAs($jefe)->post(route('admin.devoluciones.recibir', $devolucion->id), [
            'fotos' => [UploadedFile::fake()->image('bodega1.jpg', 800, 600)],
        ])->assertRedirect(route('admin.devoluciones.show', $devolucion->id));

        $devolucion->refresh();
        $this->assertSame(Devolucion::RECIBIDA, $devolucion->estado);
        $this->assertSame($jefe->id, $devolucion->recibida_por);
        $this->assertSame(1, $devolucion->fotos()->where('origen', 'bodega')->count());
        $this->assertDatabaseHas('notificaciones', [
            'evento' => 'devolucion.recibida',
            'destinatario' => $devolucion->cliente_email,
            'canal' => 'mail',
        ]);

        // Recibirla DOS veces no duplica (guard de estado bajo lock).
        $this->actingAs($jefe)->post(route('admin.devoluciones.recibir', $devolucion->id), [
            'fotos' => [UploadedFile::fake()->image('bodega2.jpg')],
        ])->assertRedirect();
        $this->assertSame(Devolucion::RECIBIDA, $devolucion->fresh()->estado);
    }

    public function test_causa_transporte_exige_transportista_y_seguimiento(): void
    {
        $devolucion = Devolucion::factory()->recibida()->create();
        $item = $devolucion->items()->create(['descripcion' => 'Dispensador', 'cantidad' => 1]);

        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.evaluar', $devolucion->id), [
            'causa' => 'transporte',
            'items' => [$item->id => DevolucionItem::DANADO],
        ])->assertSessionHasErrors(['transportista', 'seguimiento']);

        $this->assertSame(Devolucion::RECIBIDA, $devolucion->fresh()->estado);

        // Con el dato del reclamo, pasa.
        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.evaluar', $devolucion->id), [
            'causa' => 'transporte',
            'transportista' => 'Starken',
            'seguimiento' => 'STK-112233',
            'items' => [$item->id => DevolucionItem::DANADO],
        ])->assertSessionHasNoErrors();

        $devolucion->refresh();
        $this->assertSame(Devolucion::EVALUADA, $devolucion->estado);
        $this->assertSame('Starken', $devolucion->transportista);
        $this->assertSame(DevolucionItem::DANADO, $item->fresh()->estado_producto);
    }

    public function test_reingreso_escribe_el_kardex_local_y_los_stocks_no_se_tocan(): void
    {
        // El espejo de Bsale, ANTES: una fila de stock que nada debe mover.
        $bodega = Bodega::factory()->create();
        $producto = Producto::factory()->create();
        Stock::factory()->create([
            'bodega_id' => $bodega->id,
            'producto_id' => $producto->id,
            'stock_real' => 50,
            'stock_reservado' => 0,
            'stock_disponible' => 50,
        ]);
        $espejoAntes = Stock::orderBy('id')->get()->toArray();

        $devolucion = Devolucion::factory()->evaluada()->create();
        $apto = $devolucion->items()->create([
            'descripcion' => 'Dispensador OK', 'cantidad' => 2,
            'estado_producto' => DevolucionItem::APTO, 'producto_id' => $producto->id,
        ]);
        $danado = $devolucion->items()->create([
            'descripcion' => 'Dispensador roto', 'cantidad' => 1,
            'estado_producto' => DevolucionItem::DANADO,
        ]);

        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'reingreso',
        ])->assertSessionHasNoErrors();

        $devolucion->refresh();
        $this->assertSame(Devolucion::REINGRESADA, $devolucion->estado);

        // Kardex local: apto → reingreso a la bodega parametrizada; dañado → merma.
        $this->assertDatabaseHas('devolucion_movimientos', [
            'devolucion_item_id' => $apto->id, 'tipo' => 'reingreso', 'bodega_destino' => 'CONTENEDORES',
        ]);
        $this->assertDatabaseHas('devolucion_movimientos', [
            'devolucion_item_id' => $danado->id, 'tipo' => 'merma', 'bodega_destino' => null,
        ]);

        // EL CANDADO CENTRAL: el espejo de stocks quedó BYTE A BYTE igual.
        $this->assertSame($espejoAntes, Stock::orderBy('id')->get()->toArray(),
            'El kardex local de devoluciones JAMÁS escribe stocks (espejo read-only de Bsale).');

        // Y el cliente fue avisado del resultado.
        $this->assertDatabaseHas('notificaciones', [
            'evento' => 'devolucion.resuelta',
            'destinatario' => $devolucion->cliente_email,
        ]);
    }

    public function test_reingreso_sin_items_aptos_es_rechazado(): void
    {
        $devolucion = Devolucion::factory()->evaluada()->create();
        $devolucion->items()->create([
            'descripcion' => 'Roto', 'cantidad' => 1, 'estado_producto' => DevolucionItem::DANADO,
        ]);

        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'reingreso',
        ])->assertRedirect();

        // Nada cambió: ni estado ni movimientos.
        $this->assertSame(Devolucion::EVALUADA, $devolucion->fresh()->estado);
        $this->assertDatabaseCount('devolucion_movimientos', 0);
    }

    public function test_rechazo_exige_motivo_y_avisa_al_cliente(): void
    {
        $devolucion = Devolucion::factory()->evaluada()->create();

        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'rechazo',
        ])->assertSessionHasErrors('resolucion_motivo');

        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'rechazo',
            'resolucion_motivo' => 'El daño es por mal uso, no de fábrica.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(Devolucion::RECHAZADA, $devolucion->fresh()->estado);
        $this->assertDatabaseHas('notificaciones', ['evento' => 'devolucion.resuelta']);
    }

    public function test_una_devolucion_no_evaluada_no_se_puede_resolver(): void
    {
        $devolucion = Devolucion::factory()->create(); // solicitada

        $this->actingAs($this->jefeBodega())->post(route('admin.devoluciones.resolver', $devolucion->id), [
            'salida' => 'rechazo', 'resolucion_motivo' => 'x',
        ])->assertRedirect();

        $this->assertSame(Devolucion::SOLICITADA, $devolucion->fresh()->estado);
    }

    public function test_la_foto_privada_exige_sesion_y_permiso(): void
    {
        $devolucion = Devolucion::factory()->create();
        Storage::disk('local')->put('devoluciones/fotos/1/x.jpg', 'bytes');
        $foto = $devolucion->fotos()->create(['ruta' => 'devoluciones/fotos/1/x.jpg', 'origen' => 'cliente']);

        // Invitado → al login; rol sin permiso → rebote (403 amable).
        $this->get(route('admin.devoluciones.foto', $foto))->assertRedirect();
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $this->actingAs($soplador)->get(route('admin.devoluciones.foto', $foto))->assertRedirect();

        // Con permiso → la sirve.
        $this->actingAs($this->jefeBodega())->get(route('admin.devoluciones.foto', $foto))->assertOk();
    }
}
