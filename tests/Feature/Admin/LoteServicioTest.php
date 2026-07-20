<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\LoteServicio;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ingreso por lote de Servicio Técnico (conductor en ruta): crea N órdenes de
 * una empresa en una transacción, hereda los defaults del lote, es idempotente
 * por lote_uuid y las órdenes entran fuente='ruta' sin confirmar.
 */
class LoteServicioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function conductor(): User
    {
        return tap(User::factory()->create())->assignRole('conductor');
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::firstOrCreate(
            ['codigo' => 'MIRADOR'],
            ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]
        );
    }

    private function producto(string $sku = '1030034'): Producto
    {
        // Categoría de equipo para que pase el buscador; el store solo exige exists.
        return Producto::firstOrCreate(
            ['sku' => $sku],
            ['nombre' => 'Equipo '.$sku, 'categoria' => 'AGUA DISP. SOBREMESA COMPRESOR']
        );
    }

    private function payload(array $overrides = [], array $maquinas = null): array
    {
        $sucursal = $overrides['sucursal_id'] ?? $this->sucursal()->id;

        if ($maquinas === null) {
            $prod = $this->producto();
            $maquinas = [
                ['producto_id' => $prod->id, 'numero_serie' => 'SN-0001'],
                ['producto_id' => $prod->id, 'numero_serie' => 'SN-0002'],
                ['producto_id' => $prod->id, 'numero_serie' => 'SN-0003'],
            ];
        }

        return array_merge([
            'cliente_nombre' => 'Aguas JB SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_email' => 'jb@empresa.cl',
            'cliente_telefono' => '+56 9 1234 5678',
            'origen_ciudad' => 'Los Andes',
            'sucursal_id' => $sucursal,
            'fecha_ingreso' => '2026-07-13',
            'tipo_default' => 'dispensador',
            'facturacion_default' => 'reparacion',
            'falla_default' => 'No enfría, no calienta',
            'maquinas' => $maquinas,
        ], $overrides);
    }

    // --- Acceso / permisos ---

    public function test_guest_es_redirigido(): void
    {
        $this->get('/admin/servicio-tecnico/lote')->assertRedirect('/login');
    }

    public function test_sin_permiso_es_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/servicio-tecnico/lote')->assertForbidden();
    }

    public function test_conductor_puede_abrir_el_formulario(): void
    {
        $this->sucursal(); // el form lista sucursales de recepción
        $this->actingAs($this->conductor())
            ->get('/admin/servicio-tecnico/lote')->assertOk()->assertSee('Ingreso por lote');
    }

    public function test_conductor_no_gestiona_el_taller(): void
    {
        // El permiso del lote NO da acceso a la etapa de reparación (manage).
        $orden = OrdenServicio::factory()->create();
        $this->actingAs($this->conductor())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))->assertForbidden();
    }

    // --- Creación ---

    public function test_crea_el_lote_y_una_orden_por_maquina(): void
    {
        $conductor = $this->conductor();

        $this->actingAs($conductor)
            ->post(route('admin.servicio-tecnico.lote.store'), $this->payload())
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $lote = LoteServicio::first();
        $this->assertNotNull($lote);
        $this->assertSame(3, $lote->total_ordenes);
        $this->assertSame($conductor->id, $lote->conductor_id);
        $this->assertSame('Los Andes', $lote->origen_ciudad);
        $this->assertCount(3, $lote->ordenes);

        $orden = $lote->ordenes->first();
        $this->assertSame('ruta', $orden->fuente);
        $this->assertNull($orden->confirmada_at);
        $this->assertSame('dispensador', $orden->tipo_equipo);        // default heredado
        $this->assertSame('reparacion', $orden->facturacion);          // default heredado
        $this->assertSame('No enfría, no calienta', $orden->falla_reportada); // default heredado
        $this->assertNotNull($orden->fecha_entrega);                   // la fija el servidor
        $this->assertNull($orden->cliente_email);                      // el correo va a nivel lote
    }

    public function test_las_ordenes_del_lote_quedan_por_confirmar(): void
    {
        $this->actingAs($this->conductor())
            ->post(route('admin.servicio-tecnico.lote.store'), $this->payload());

        // fuente='ruta' + sin confirmar → entran en la cola "por confirmar".
        $this->assertSame(3, OrdenServicio::porConfirmar()->count());
    }

    public function test_codigo_dali_es_obligatorio_por_maquina(): void
    {
        $prod = $this->producto();
        $payload = $this->payload([], [
            ['producto_id' => $prod->id, 'numero_serie' => 'SN-1'],
            ['producto_id' => '', 'numero_serie' => 'SN-2'], // sin código
        ]);

        $this->actingAs($this->conductor())
            ->post(route('admin.servicio-tecnico.lote.store'), $payload)
            ->assertSessionHasErrors('maquinas.1.producto_id');

        $this->assertSame(0, LoteServicio::count());
        $this->assertSame(0, OrdenServicio::count());
    }

    public function test_serie_obligatoria_para_dispensador(): void
    {
        $prod = $this->producto();
        $payload = $this->payload(['tipo_default' => 'dispensador'], [
            ['producto_id' => $prod->id, 'numero_serie' => ''], // dispensador sin serie
        ]);

        $this->actingAs($this->conductor())
            ->post(route('admin.servicio-tecnico.lote.store'), $payload)
            ->assertSessionHasErrors('maquinas.0.numero_serie');
    }

    public function test_serie_opcional_para_herramienta(): void
    {
        $prod = $this->producto('HERR-1');
        $payload = $this->payload(['tipo_default' => 'herramienta'], [
            ['producto_id' => $prod->id, 'numero_serie' => ''],
        ]);

        $this->actingAs($this->conductor())
            ->post(route('admin.servicio-tecnico.lote.store'), $payload)
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertSame(1, OrdenServicio::where('tipo_equipo', 'herramienta')->count());
    }

    public function test_origen_invalido_es_rechazado(): void
    {
        $this->actingAs($this->conductor())
            ->post(route('admin.servicio-tecnico.lote.store'), $this->payload(['origen_ciudad' => 'Marte']))
            ->assertSessionHasErrors('origen_ciudad');
    }

    public function test_es_idempotente_por_lote_uuid(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';
        $payload = $this->payload(['lote_uuid' => $uuid]);

        $conductor = $this->conductor();
        $this->actingAs($conductor)->post(route('admin.servicio-tecnico.lote.store'), $payload);
        // Reenvío (cola offline): mismo uuid, no debe duplicar.
        $this->actingAs($conductor)->post(route('admin.servicio-tecnico.lote.store'), $this->payload(['lote_uuid' => $uuid]));

        $this->assertSame(1, LoteServicio::where('lote_uuid', $uuid)->count());
        $this->assertSame(3, OrdenServicio::count()); // 3, no 6
    }

    public function test_guarda_la_foto_de_respaldo(): void
    {
        $prod = $this->producto();
        $payload = $this->payload([], [
            ['producto_id' => $prod->id, 'numero_serie' => 'SN-FOTO', 'foto' => UploadedFile::fake()->image('equipo.jpg', 800, 600)],
        ]);

        $this->actingAs($this->conductor())
            ->post(route('admin.servicio-tecnico.lote.store'), $payload)
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $orden = OrdenServicio::first();
        $this->assertCount(1, $orden->fotos);
    }

    public function test_endpoint_json_para_la_cola_offline(): void
    {
        $conductor = $this->conductor();

        $this->actingAs($conductor)
            ->postJson(route('admin.servicio-tecnico.lote.store'), $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true, 'ordenes' => 3, 'duplicado' => false]);
    }
}
