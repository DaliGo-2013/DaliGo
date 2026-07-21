<?php

namespace Tests\Feature;

use App\Models\AgendaTrabajo;
use App\Models\ServicioTerreno;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Solicitud pública de visita/revisión industrial (QR): el cliente pide que el
 * técnico vaya a su planta; entra a la Agenda de terreno como 'solicitado'
 * (sin fecha) y el staff la coordina (fecha + técnico → agendado).
 */
class VisitaIndustrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
    }

    private function vendedor(): User
    {
        return tap(User::factory()->create())->assignRole('vendedor');
    }

    private function payload(Sucursal $sucursal, array $overrides = []): array
    {
        return array_merge([
            'sucursal_id' => $sucursal->id,
            'tipo' => 'visita_tecnica',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La planta de osmosis 1T pierde presión.',
        ], $overrides);
    }

    // --- Acceso / chooser ---

    public function test_get_exige_firma_y_chooser_muestra_la_opcion(): void
    {
        $sucursal = $this->sucursal();

        $this->get(route('visita-industrial.create', ['sucursal' => $sucursal->id]))->assertForbidden();

        $this->get(URL::signedRoute('visita-industrial.create', ['sucursal' => $sucursal->id]))
            ->assertOk()
            ->assertSee('Visita / revisión industrial')
            ->assertSee('Visita técnica');

        // La opción aparece en el chooser del QR.
        $this->get(URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursal->id]))
            ->assertOk()->assertSee('Visita / revisión industrial');
    }

    public function test_visita_tecnica_es_la_primera_opcion_de_tipo(): void
    {
        $this->assertSame('visita_tecnica', AgendaTrabajo::TIPOS[0]);
    }

    public function test_el_cliente_no_ve_los_valores_uf_de_los_servicios(): void
    {
        $sucursal = $this->sucursal();
        ServicioTerreno::factory()->create(['nombre' => 'Full planta 1T', 'valor_uf' => 3]);

        $this->get(URL::signedRoute('visita-industrial.create', ['sucursal' => $sucursal->id]))
            ->assertOk()
            ->assertSee('Full planta 1T')   // el servicio se ofrece…
            ->assertDontSee('UF');          // …pero SIN su costo (es interno)
    }

    // --- Solicitud del cliente ---

    public function test_la_solicitud_avisa_a_ventas_por_coordinar(): void
    {
        // Ventas (jefe + vendedor) reciben campanita; el técnico industrial NO
        // (a él le llega el trabajo recién cuando lo fijan en su agenda).
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');

        $this->post(route('visita-industrial.store'), $this->payload($this->sucursal()));

        foreach ([$jefe, $vendedor] as $u) {
            $this->assertSame(1, \App\Models\Notificacion::where('user_id', $u->id)
                ->where('evento', 'terreno.solicitada')
                ->where('canal', \App\Models\Notificacion::CANAL_DATABASE)->count(),
                "Falta la campanita de {$u->name}");
        }
        $this->assertSame(0, \App\Models\Notificacion::where('user_id', $tecnico->id)
            ->where('evento', 'terreno.solicitada')->count());
    }

    public function test_crea_la_solicitud_sin_fecha_y_con_preferida(): void
    {
        $sucursal = $this->sucursal();
        $servicio = ServicioTerreno::factory()->create(['nombre' => 'Full planta 1T']);
        $preferida = now()->addDays(5)->toDateString();

        $this->post(route('visita-industrial.store'), $this->payload($sucursal, [
            'servicio_terreno_id' => $servicio->id,
            'fecha_preferida' => $preferida,
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $t = AgendaTrabajo::first();
        $this->assertSame('solicitado', $t->estado);
        $this->assertNull($t->fecha);                                 // la pone quien coordina
        $this->assertSame($preferida, $t->fecha_preferida->toDateString());
        $this->assertSame('visita_tecnica', $t->tipo);
        $this->assertSame('12345678-5', $t->cliente_rut);             // normalizado
        $this->assertSame($servicio->id, $t->servicio_terreno_id);
        $this->assertSame('Cliente (QR)', $t->creado_por);
    }

    public function test_fecha_preferida_pasada_es_rechazada(): void
    {
        $this->post(route('visita-industrial.store'), $this->payload($this->sucursal(), [
            'fecha_preferida' => now()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('fecha_preferida');
    }

    public function test_no_se_puede_pedir_visita_en_dias_ocupados(): void
    {
        // El técnico está de viaje (agendado) del 10 al 14 de un mes futuro.
        AgendaTrabajo::factory()->create([
            'estado' => 'agendado', 'fecha' => '2026-09-10', 'fecha_fin' => '2026-09-14',
            'ciudad' => 'Copiapó',
        ]);

        // El cliente no puede pedir una visita preferida dentro de ese rango.
        $this->post(route('visita-industrial.store'), $this->payload($this->sucursal(), [
            'fecha_preferida' => '2026-09-12',
        ]))->assertSessionHasErrors('fecha_preferida');

        $this->assertSame(1, AgendaTrabajo::count()); // no se creó la solicitud
    }

    public function test_honeypot_lleno_no_crea_nada(): void
    {
        $this->post(route('visita-industrial.store'), $this->payload($this->sucursal(), [
            'sitio_web' => 'http://spam.example',
        ]))->assertRedirect();

        $this->assertSame(0, AgendaTrabajo::count());
    }

    // --- Coordinación ---

    public function test_la_solicitud_aparece_en_por_coordinar_y_no_en_el_mes(): void
    {
        $this->post(route('visita-industrial.store'), $this->payload($this->sucursal()));

        $res = $this->actingAs($this->vendedor())->get('/admin/agenda-terreno');
        $res->assertOk()
            ->assertSee('Por coordinar (solicitudes del cliente)')
            ->assertSee('Aguas Claras SpA')
            ->assertSee('Coordinar');

        // Sin fecha, no está en ningún día del mes (solo en el bloque).
        $this->assertSame(0, AgendaTrabajo::delMes(now()->year, now()->month)->count());
    }

    public function test_el_tecnico_industrial_ahora_ve_el_bloque_por_coordinar(): void
    {
        // Con su nuevo permiso de agendar, el técnico también coordina solicitudes.
        $this->post(route('visita-industrial.store'), $this->payload($this->sucursal()));

        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');
        $this->actingAs($tecnico)->get('/admin/agenda-terreno')
            ->assertOk()
            ->assertSee('Por coordinar (solicitudes del cliente)')
            ->assertSee('Coordinar');
    }

    public function test_coordinar_pone_fecha_y_la_agenda(): void
    {
        $this->post(route('visita-industrial.store'), $this->payload($this->sucursal()));
        $t = AgendaTrabajo::first();

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), [
                'tipo' => $t->tipo,
                'fecha' => '2026-07-22',
                'estado' => 'agendado',
                'cliente_nombre' => $t->cliente_nombre,
                'cliente_rut' => $t->cliente_rut,
                'cliente_telefono' => $t->cliente_telefono,
                'cliente_email' => $t->cliente_email,
                'direccion' => $t->direccion,
                'ciudad' => $t->ciudad,
                'descripcion' => $t->descripcion,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $t->fresh();
        $this->assertSame('agendado', $fresh->estado);
        $this->assertSame('2026-07-22', $fresh->fecha->toDateString());
        // Ahora sí está en el mes.
        $this->assertSame(1, AgendaTrabajo::delMes(2026, 7)->count());
    }

    public function test_editar_una_solicitud_sin_fecha_no_exige_fecha(): void
    {
        $this->post(route('visita-industrial.store'), $this->payload($this->sucursal()));
        $t = AgendaTrabajo::first();

        // Corregir un dato manteniendo el estado 'solicitado' (sin fecha aún).
        // Los datos de contacto ya vienen de la solicitud pública (obligatorios).
        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), [
                'tipo' => $t->tipo,
                'estado' => 'solicitado',
                'cliente_nombre' => 'Aguas Claras SpA (corregido)',
                'cliente_rut' => $t->cliente_rut,
                'cliente_telefono' => $t->cliente_telefono,
                'cliente_email' => $t->cliente_email,
                'direccion' => $t->direccion,
                'ciudad' => $t->ciudad,
                'descripcion' => $t->descripcion,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Aguas Claras SpA (corregido)', $t->fresh()->cliente_nombre);
        $this->assertNull($t->fresh()->fecha);
    }

    public function test_agendar_interno_sigue_exigiendo_fecha(): void
    {
        // El flujo interno (staff) no puede crear sin fecha.
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', [
                'tipo' => 'mantencion',
                'cliente_nombre' => 'Cliente Interno',
                'descripcion' => 'Trabajo sin fecha',
            ])
            ->assertSessionHasErrors('fecha');
    }
}
