<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\ServicioTerreno;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiciosTerrenoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agenda de terreno (técnico industrial): el jefe/vendedores agendan
 * mantenciones/reparaciones/instalaciones; el técnico industrial ve su mes
 * y marca lo realizado. Catálogo de servicios en UF editable.
 */
class AgendaTerrenoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function vendedor(): User
    {
        return tap(User::factory()->create())->assignRole('vendedor');
    }

    private function tecnicoIndustrial(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico_industrial');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tipo' => 'mantencion',
            'fecha' => '2026-07-20',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'direccion' => 'Av. Los Andes 123',
            'ciudad' => 'Curicó',
            'descripcion' => 'Mantención full planta 1T',
        ], $overrides);
    }

    // --- Acceso ---

    public function test_guest_es_redirigido(): void
    {
        $this->get('/admin/agenda-terreno')->assertRedirect('/login');
    }

    public function test_sin_permiso_es_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/agenda-terreno')->assertForbidden();
    }

    public function test_vendedor_puede_ver_y_agendar(): void
    {
        $this->actingAs($this->vendedor())->get('/admin/agenda-terreno')->assertOk();
        $this->actingAs($this->vendedor())->get('/admin/agenda-terreno/crear')->assertOk();
    }

    public function test_tecnico_industrial_ve_pero_no_agenda(): void
    {
        $tecnico = $this->tecnicoIndustrial();

        $this->actingAs($tecnico)->get('/admin/agenda-terreno')->assertOk();
        $this->actingAs($tecnico)->get('/admin/agenda-terreno/crear')->assertForbidden();
        $this->actingAs($tecnico)->post('/admin/agenda-terreno', $this->payload())->assertForbidden();
        // Tampoco toca el catálogo.
        $this->actingAs($tecnico)->get('/admin/servicios-terreno')->assertForbidden();
    }

    // --- Agendar ---

    public function test_vendedor_agenda_un_trabajo(): void
    {
        $vendedor = $this->vendedor();
        $servicio = ServicioTerreno::factory()->create(['nombre' => 'Full planta 1T']);
        $tecnico = $this->tecnicoIndustrial();

        $this->actingAs($vendedor)
            ->post('/admin/agenda-terreno', $this->payload([
                'servicio_terreno_id' => $servicio->id,
                'tecnico_id' => $tecnico->id,
            ]))
            ->assertRedirect(route('admin.agenda-terreno.index', ['anio' => 2026, 'mes' => 7]));

        $this->assertDatabaseHas('agenda_trabajos', [
            'tipo' => 'mantencion',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12345678-5',        // normalizado
            'servicio_terreno_id' => $servicio->id,
            'tecnico_id' => $tecnico->id,
            'estado' => 'agendado',
            'creado_por' => $vendedor->name,
        ]);
    }

    public function test_tipo_invalido_es_rechazado(): void
    {
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', $this->payload(['tipo' => 'paseo']))
            ->assertSessionHasErrors('tipo');
    }

    // --- Agenda del mes ---

    public function test_la_agenda_muestra_solo_el_mes_pedido(): void
    {
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-20', 'cliente_nombre' => 'Cliente Julio']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-08-05', 'cliente_nombre' => 'Cliente Agosto']);

        $this->actingAs($this->vendedor())->get('/admin/agenda-terreno?anio=2026&mes=7')
            ->assertOk()->assertSee('Cliente Julio')->assertDontSee('Cliente Agosto');

        $this->actingAs($this->vendedor())->get('/admin/agenda-terreno?anio=2026&mes=8')
            ->assertOk()->assertSee('Cliente Agosto')->assertDontSee('Cliente Julio');
    }

    public function test_tecnico_marca_un_trabajo_como_realizado(): void
    {
        $trabajo = AgendaTrabajo::factory()->create(['estado' => 'agendado']);

        $this->actingAs($this->tecnicoIndustrial())
            ->patch(route('admin.agenda-terreno.estado', $trabajo), [
                'estado' => 'realizado',
                'notas_tecnico' => 'Cambio de membranas OK.',
            ])
            ->assertRedirect();

        $fresh = $trabajo->fresh();
        $this->assertSame('realizado', $fresh->estado);
        $this->assertSame('Cambio de membranas OK.', $fresh->notas_tecnico);
    }

    public function test_editar_actualiza_el_trabajo(): void
    {
        $trabajo = AgendaTrabajo::factory()->create(['tipo' => 'mantencion']);

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $trabajo), $this->payload([
                'tipo' => 'instalacion',
                'estado' => 'agendado',
                'cliente_nombre' => 'Otro Cliente',
            ]))
            ->assertRedirect();

        $this->assertSame('instalacion', $trabajo->fresh()->tipo);
        $this->assertSame('Otro Cliente', $trabajo->fresh()->cliente_nombre);
    }

    // --- Catálogo ---

    public function test_seeder_carga_el_tarifario_de_la_foto(): void
    {
        $this->seed(ServiciosTerrenoSeeder::class);

        $this->assertSame(13, ServicioTerreno::count());
        $full = ServicioTerreno::where('nombre', 'Full planta 1T')->first();
        $this->assertNotNull($full);
        $this->assertSame('3.00', (string) $full->valor_uf);
        $this->assertSame('1 día', $full->duracion);

        // Re-ejecutar NO duplica ni pisa ediciones manuales.
        $full->update(['valor_uf' => 3.5]);
        $this->seed(ServiciosTerrenoSeeder::class);
        $this->assertSame(13, ServicioTerreno::count());
        $this->assertSame('3.50', (string) $full->fresh()->valor_uf);
    }

    public function test_vendedor_edita_el_catalogo_con_coma_decimal(): void
    {
        $servicio = ServicioTerreno::factory()->create(['nombre' => 'Membranas 1 a 2', 'valor_uf' => 1]);

        $this->actingAs($this->vendedor())
            ->put(route('admin.servicios-terreno.update', $servicio), [
                'nombre' => 'Membranas 1 a 2',
                'valor_uf' => '1,5',          // coma decimal chilena
                'duracion' => '1/2 día',
                'incluye' => 'Cambio de membranas, limpieza portamembrana.',
                'activo' => '1',
            ])
            ->assertRedirect(route('admin.servicios-terreno.index'));

        $this->assertSame('1.50', (string) $servicio->fresh()->valor_uf);
    }

    public function test_servicio_inactivo_no_aparece_en_el_selector(): void
    {
        ServicioTerreno::factory()->create(['nombre' => 'Activo Uno', 'activo' => true]);
        ServicioTerreno::factory()->create(['nombre' => 'Viejo Dos', 'activo' => false]);

        $this->actingAs($this->vendedor())->get('/admin/agenda-terreno/crear')
            ->assertOk()
            ->assertSee('Activo Uno')
            ->assertDontSee('Viejo Dos');
    }
}
