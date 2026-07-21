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

    public function test_la_agenda_abre_en_hoy_con_el_trabajo_del_dia_como_formulario(): void
    {
        $hoy = now()->toDateString();
        AgendaTrabajo::factory()->create([
            'tipo' => 'mantencion', 'estado' => 'agendado', 'fecha' => $hoy, 'hora' => '10:00',
            'cliente_nombre' => 'Aguas del Maule SpA',
        ]);
        $t = AgendaTrabajo::first();

        // Sin ?dia: la vista abre en HOY y muestra su trabajo como formulario editable.
        $this->actingAs($this->tecnicoIndustrial())
            ->get('/admin/agenda-terreno')
            ->assertOk()
            ->assertSee('Lun')                                                   // calendario
            ->assertSee('Editar trabajo')                                        // formato de formulario
            ->assertSee('Aguas del Maule SpA')                                   // trabajo de hoy
            ->assertSee(route('admin.agenda-terreno.update', $t), false);        // form apunta a update
    }

    public function test_dia_sin_trabajos_muestra_formulario_para_agregar(): void
    {
        // Un día del mes sin trabajos: la derecha muestra el form de "Nuevo trabajo"
        // con la fecha prellenada (como cuando se ingresa por primera vez).
        $this->actingAs($this->tecnicoIndustrial())
            ->get('/admin/agenda-terreno?anio=2026&mes=7&dia=2026-07-15')
            ->assertOk()
            ->assertSee('Nuevo trabajo')
            ->assertSee('Agendar trabajo')
            ->assertSee('value="2026-07-15"', false);   // fecha prellenada con el día elegido
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

    public function test_trabajo_de_varios_dias_ocupa_cada_dia_del_viaje(): void
    {
        AgendaTrabajo::factory()->create([
            'estado' => 'agendado',
            'fecha' => '2026-07-07',
            'fecha_fin' => '2026-07-10',
            'ciudad' => 'Puerto Montt',
            'cliente_nombre' => 'Planta del Sur',
        ]);

        // Al seleccionar un día INTERMEDIO del viaje, el trabajo sigue apareciendo
        // (se expande a cada día que abarca).
        $this->actingAs($this->tecnicoIndustrial())
            ->get('/admin/agenda-terreno?anio=2026&mes=7&dia=2026-07-09')
            ->assertOk()
            ->assertSee('Editar trabajo')
            ->assertSee('Planta del Sur');
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

    public function test_tecnico_industrial_ve_y_ahora_agenda(): void
    {
        $tecnico = $this->tecnicoIndustrial();

        // Carlos (técnico industrial) ahora gestiona su agenda: ve, agenda y edita.
        $this->actingAs($tecnico)->get('/admin/agenda-terreno')->assertOk();
        $this->actingAs($tecnico)->get('/admin/agenda-terreno/crear')->assertOk();
        $this->actingAs($tecnico)->post('/admin/agenda-terreno', $this->payload())->assertRedirect();
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
            ->assertRedirect(route('admin.agenda-terreno.index', ['anio' => 2026, 'mes' => 7, 'dia' => '2026-07-20']));

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

    public function test_solo_ver_agenda_puede_marcar_realizado_pero_no_cancelar(): void
    {
        // Un usuario que SOLO ve la agenda (sin 'agendar servicio terreno') puede
        // cerrar un trabajo pero no cancelar/reabrir (el guard de estado sigue).
        $soloVe = tap(User::factory()->create())->givePermissionTo('ver agenda terreno');

        $agendado = AgendaTrabajo::factory()->create(['estado' => 'agendado']);
        $this->actingAs($soloVe)
            ->patch(route('admin.agenda-terreno.estado', $agendado), ['estado' => 'cancelado'])
            ->assertForbidden();

        $this->actingAs($soloVe)
            ->patch(route('admin.agenda-terreno.estado', $agendado), ['estado' => 'realizado'])
            ->assertRedirect();
        $this->assertSame('realizado', $agendado->fresh()->estado);
    }

    public function test_tecnico_industrial_ahora_puede_cancelar(): void
    {
        // Con su nuevo permiso de agendar, el técnico también cambia estados.
        $agendado = AgendaTrabajo::factory()->create(['estado' => 'agendado']);
        $this->actingAs($this->tecnicoIndustrial())
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

    // --- Día seleccionado ---

    public function test_la_agenda_muestra_solo_el_dia_seleccionado(): void
    {
        AgendaTrabajo::factory()->create(['estado' => 'agendado', 'fecha' => '2026-07-20', 'cliente_nombre' => 'Cliente Julio']);
        AgendaTrabajo::factory()->create(['estado' => 'agendado', 'fecha' => '2026-08-05', 'cliente_nombre' => 'Cliente Agosto']);

        $this->actingAs($this->vendedor())->get('/admin/agenda-terreno?anio=2026&mes=7&dia=2026-07-20')
            ->assertOk()->assertSee('Cliente Julio')->assertDontSee('Cliente Agosto');

        $this->actingAs($this->vendedor())->get('/admin/agenda-terreno?anio=2026&mes=8&dia=2026-08-05')
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
