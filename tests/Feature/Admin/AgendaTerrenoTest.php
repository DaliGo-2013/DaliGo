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

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tipo' => 'mantencion',
            'fecha' => '2026-07-20',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@aguasclaras.cl',
            'direccion' => 'Av. Los Andes 123',
            'ciudad' => 'Curicó',
            'descripcion' => 'Mantención full planta 1T',
        ], $overrides);
    }

    // --- Vista unificada (calendario + lista) / hora ---

    public function test_calendario_redirige_a_la_vista_unica(): void
    {
        // La antigua ruta "calendario" ahora redirige a index (vista fusionada).
        $this->actingAs($this->tecnicoIndustrial())
            ->get('/admin/agenda-terreno/calendario')
            ->assertRedirect(route('admin.agenda-terreno.index'));
    }

    public function test_la_agenda_muestra_calendario_lista_y_franjas(): void
    {
        AgendaTrabajo::factory()->create([
            'tipo' => 'mantencion',
            'estado' => 'agendado',
            'fecha' => '2026-07-20',
            'hora' => '10:00',
            'cliente_nombre' => 'Aguas del Maule SpA',
        ]);

        $this->actingAs($this->tecnicoIndustrial())
            ->get('/admin/agenda-terreno?anio=2026&mes=7')
            ->assertOk()
            ->assertSee('Lun')                          // grilla del calendario
            ->assertSee('Aguas del Maule SpA')          // la lista del día
            ->assertSee('10:00 hs')                     // franja de 2 horas
            ->assertSee('dia-2026-07-20', false)        // ancla del día (clic en el calendario)
            ->assertSee('sin trabajo por realizar');    // aviso de los días libres (al tocarlos)
    }

    public function test_hora_cae_en_su_franja_de_dos_horas(): void
    {
        // Una hora impar (09:00) se agrupa bajo la franja 08:00 hs.
        AgendaTrabajo::factory()->create([
            'estado' => 'agendado', 'fecha' => '2026-07-20', 'hora' => '09:00',
            'cliente_nombre' => 'Planta Nueve',
        ]);

        $this->actingAs($this->tecnicoIndustrial())
            ->get('/admin/agenda-terreno?anio=2026&mes=7')
            ->assertOk()
            ->assertSee('08:00 hs')       // franja (redondea hacia abajo al bloque par)
            ->assertDontSee('09:00 hs');
    }

    public function test_agendar_ofrece_las_franjas_de_hora(): void
    {
        $this->actingAs($this->vendedor())
            ->get('/admin/agenda-terreno/crear')
            ->assertOk()
            ->assertSee('— Sin hora —')
            ->assertSee('08:00 hs')
            ->assertSee('18:00 hs');
    }

    // --- Rango de días (viajes) y bloqueo por ocupación ---

    public function test_trabajo_de_varios_dias_aparece_en_cada_dia(): void
    {
        AgendaTrabajo::factory()->create([
            'estado' => 'agendado',
            'fecha' => '2026-07-07',
            'fecha_fin' => '2026-07-10',
            'ciudad' => 'Puerto Montt',
            'cliente_nombre' => 'Planta del Sur',
        ]);

        $this->actingAs($this->tecnicoIndustrial())
            ->get('/admin/agenda-terreno?anio=2026&mes=7')
            ->assertOk()
            ->assertSee('En terreno:')                 // etiqueta de viaje
            ->assertSee('al 10 de julio')              // rango de fechas
            ->assertSee('dia-2026-07-07', false)       // día inicial
            ->assertSee('dia-2026-07-09', false);      // día intermedio (se expande)
    }

    public function test_no_admin_no_puede_agendar_sobre_dias_ocupados(): void
    {
        AgendaTrabajo::factory()->create([
            'estado' => 'agendado', 'fecha' => '2026-07-07', 'fecha_fin' => '2026-07-10',
            'ciudad' => 'Puerto Montt',
        ]);

        // El vendedor intenta agendar un día dentro del viaje → bloqueado.
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', $this->payload(['fecha' => '2026-07-08']))
            ->assertSessionHasErrors('fecha');
    }

    public function test_admin_si_puede_agendar_sobre_dias_ocupados(): void
    {
        AgendaTrabajo::factory()->create([
            'estado' => 'agendado', 'fecha' => '2026-07-07', 'fecha_fin' => '2026-07-10',
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/agenda-terreno', $this->payload(['fecha' => '2026-07-08']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_agenda_guarda_el_rango_de_horas(): void
    {
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', $this->payload(['hora' => '08:00', 'hora_fin' => '18:00']))
            ->assertRedirect();

        $t = AgendaTrabajo::latest('id')->first();
        $this->assertSame('08:00', $t->hora_corta);
        $this->assertSame('18:00', $t->hora_fin_corta);
    }

    public function test_agendar_preselecciona_al_unico_tecnico(): void
    {
        $carlos = tap(User::factory()->create(['name' => 'Carlos Tablante']))->assignRole('tecnico_industrial');

        $this->actingAs($this->vendedor())
            ->get('/admin/agenda-terreno/crear')
            ->assertOk()
            ->assertSee(sprintf('value="%d" selected', $carlos->id), false)
            ->assertSee('único técnico industrial');
    }

    public function test_agenda_guarda_la_hora(): void
    {
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', $this->payload(['hora' => '09:30']))
            ->assertRedirect();

        $this->assertSame('09:30', AgendaTrabajo::latest('id')->first()->hora_corta);
    }

    public function test_hora_invalida_se_rechaza(): void
    {
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', $this->payload(['hora' => '99:99']))
            ->assertSessionHasErrors('hora');
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

    public function test_agenda_cliente_nuevo_que_no_esta_en_el_catalogo(): void
    {
        // El hidden cliente_id llega "0" cuando NO se eligió de la lista (el
        // caso común: cliente nuevo). No debe rebotar por exists.
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', $this->payload(['cliente_id' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('agenda_trabajos', [
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_id' => null,
        ]);
    }

    public function test_tecnico_no_puede_cancelar_ni_reabrir(): void
    {
        $tecnico = $this->tecnicoIndustrial();

        // Cancelar exige el permiso de agendar.
        $agendado = AgendaTrabajo::factory()->create(['estado' => 'agendado']);
        $this->actingAs($tecnico)
            ->patch(route('admin.agenda-terreno.estado', $agendado), ['estado' => 'cancelado'])
            ->assertForbidden();

        // Reabrir un realizado también.
        $realizado = AgendaTrabajo::factory()->create(['estado' => 'realizado']);
        $this->actingAs($tecnico)
            ->patch(route('admin.agenda-terreno.estado', $realizado), ['estado' => 'agendado'])
            ->assertForbidden();

        // El vendedor (agendar) sí puede cancelar.
        $this->actingAs($this->vendedor())
            ->patch(route('admin.agenda-terreno.estado', $agendado), ['estado' => 'cancelado'])
            ->assertRedirect();
        $this->assertSame('cancelado', $agendado->fresh()->estado);
    }

    public function test_editar_conserva_un_servicio_que_quedo_inactivo(): void
    {
        $servicio = ServicioTerreno::factory()->create(['nombre' => 'Servicio Retirado', 'activo' => false]);
        $trabajo = AgendaTrabajo::factory()->create(['servicio_terreno_id' => $servicio->id]);

        // El form de edición DEBE ofrecer el servicio actual aunque esté inactivo
        // (si no, guardar cualquier edición lo desvincularía en silencio).
        $this->actingAs($this->vendedor())
            ->get(route('admin.agenda-terreno.edit', $trabajo))
            ->assertOk()
            ->assertSee('Servicio Retirado');
    }

    public function test_tipo_invalido_es_rechazado(): void
    {
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', $this->payload(['tipo' => 'paseo']))
            ->assertSessionHasErrors('tipo');
    }

    public function test_agendar_exige_los_datos_de_contacto(): void
    {
        // RUT, teléfono, correo, dirección y ciudad son OBLIGATORIOS al agendar
        // (paridad con el formulario público del QR).
        $this->actingAs($this->vendedor())
            ->post('/admin/agenda-terreno', $this->payload([
                'cliente_rut' => '',
                'cliente_telefono' => '',
                'cliente_email' => '',
                'direccion' => '',
                'ciudad' => '',
                'descripcion' => '',
            ]))
            ->assertSessionHasErrors(['cliente_rut', 'cliente_telefono', 'cliente_email', 'direccion', 'ciudad', 'descripcion']);
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
                'observaciones' => 'No incluye cambio de cabezal.',
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
