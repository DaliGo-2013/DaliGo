<?php

namespace Tests\Feature;

use App\Models\AgendaCierre;
use App\Models\AgendaTrabajo;
use App\Models\Sucursal;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * EL CLIENTE ELIGE LA HORA DE UNA LISTA.
 *
 * Pedido del dueño (14-08-2026): «quitar el apartado de cuándo puedes y cuándo no, sino
 * agregar un horario de trabajo para asistir lunes y martes 08:00 a 17:30 y miércoles a
 * viernes 08:00 a 16:30 hs, cliente pincha y elige el horario».
 *
 * Reemplaza un campo de texto libre donde el cliente escribía «fines de semana no, después de
 * las 15, avisar antes de llegar»: eso había que leerlo y traducirlo a mano en cada llamada.
 * Una hora elegida de una lista se cruza con la agenda.
 *
 * Lo que estos candados cuidan:
 *   · que las horas ofrecidas sean las del día de la semana correcto (dos horarios distintos);
 *   · que un día de media jornada RECORTE la lista, y que nadie pueda estirarla;
 *   · que el servidor verifique la hora con la MISMA lista que ofreció la pantalla — un
 *     `<select>` se edita, y una cita fuera de horario la descubre alguien llamando;
 *   · que el texto libre ya no se acepte por el formulario público.
 */
class HorarioVisitaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function proximo(int $isoDia): string
    {
        return Carbon::parse(FechaNegocio::hoy())->next($isoDia)->toDateString();
    }

    private function consultar(string $fecha)
    {
        return $this->getJson(route('visita-industrial.disponibilidad', ['fecha' => $fecha]));
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
    }

    /** @param  array<string, mixed>  $extra */
    private function pedir(array $extra = [])
    {
        return $this->post(route('visita-industrial.store'), $extra + [
            'sucursal_id' => $this->sucursal()->id,
            'cliente_nombre' => 'Planta Norte SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@norte.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La llenadora traba la cadena.',
        ]);
    }

    // ─────────────────────────────────────────────────── las dos jornadas

    public function test_lunes_y_martes_terminan_mas_tarde_que_el_resto(): void
    {
        // Lunes y martes cierran 17:30; miércoles a viernes, 16:30. La última hora que se
        // ofrece es media hora ANTES del cierre: es la hora de llegada del técnico, y ofrecer
        // las 17:30 cuando el día termina 17:30 es ofrecer una visita de cero minutos.
        foreach ([Carbon::MONDAY, Carbon::TUESDAY] as $dia) {
            $horas = AgendaTrabajo::horasDisponibles($this->proximo($dia));
            $this->assertSame('08:00', $horas[0]);
            $this->assertSame('17:00', end($horas));
        }

        foreach ([Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY] as $dia) {
            $horas = AgendaTrabajo::horasDisponibles($this->proximo($dia));
            $this->assertSame('08:00', $horas[0]);
            $this->assertSame('16:00', end($horas));
        }
    }

    public function test_el_fin_de_semana_no_ofrece_ninguna_hora(): void
    {
        foreach ([Carbon::SATURDAY, Carbon::SUNDAY] as $dia) {
            $this->assertSame([], AgendaTrabajo::horasDisponibles($this->proximo($dia)));
        }
    }

    public function test_las_horas_van_de_media_hora(): void
    {
        $horas = AgendaTrabajo::horasDisponibles($this->proximo(Carbon::WEDNESDAY));

        $this->assertSame(['08:00', '08:30', '09:00'], array_slice($horas, 0, 3));
        // 08:00 → 16:00 de a 30 minutos son 17 horas ofrecidas.
        $this->assertCount(17, $horas);
    }

    // ─────────────────────────────────────────────────── el endpoint

    public function test_el_endpoint_manda_las_horas_del_dia_elegido(): void
    {
        $lunes = $this->proximo(Carbon::MONDAY);

        $r = $this->consultar($lunes)->assertOk();

        $this->assertSame(AgendaTrabajo::horasDisponibles($lunes), $r->json('horarios'));
        $this->assertSame('08:00 a 17:30', $r->json('horario_label'));
    }

    /** Un día que no se puede pedir no ofrece horas: sería invitar a elegir algo imposible. */
    public function test_un_dia_cerrado_no_ofrece_horas(): void
    {
        $sabado = $this->proximo(Carbon::SATURDAY);

        $this->consultar($sabado)->assertOk()->assertJson(['horarios' => []]);
    }

    public function test_un_dia_ocupado_tampoco_ofrece_horas(): void
    {
        $lunes = $this->proximo(Carbon::MONDAY);
        AgendaTrabajo::create([
            'tipo' => 'visita_tecnica', 'estado' => 'agendado', 'fecha' => $lunes,
            'cliente_nombre' => 'Otro cliente', 'descripcion' => 'Mantención',
        ]);

        $this->consultar($lunes)->assertOk()->assertJson(['horarios' => []]);
    }

    // ─────────────────────────────────────────────────── media jornada

    public function test_la_media_jornada_recorta_las_horas(): void
    {
        $lunes = $this->proximo(Carbon::MONDAY);
        AgendaCierre::create([
            'fecha_desde' => $lunes, 'fecha_hasta' => $lunes,
            'tipo' => AgendaCierre::TIPO_MEDIA_JORNADA, 'hora_hasta' => '14:00',
            'motivo' => 'Sale temprano', 'origen' => AgendaCierre::ORIGEN_MANUAL,
        ]);

        $horas = AgendaTrabajo::horasDisponibles($lunes);

        $this->assertSame('13:30', end($horas), 'La lista no se recortó a la media jornada.');
        $this->assertNotContains('14:00', $horas);
    }

    /**
     * Y una media jornada NO puede ESTIRAR el día: si alguien carga «hasta las 19:00» en un
     * miércoles que cierra 16:30, el día sigue cerrando 16:30. Una excepción que agrega horas
     * que nadie trabaja es peor que no tener excepciones.
     */
    public function test_una_media_jornada_no_puede_estirar_el_dia(): void
    {
        $miercoles = $this->proximo(Carbon::WEDNESDAY);
        AgendaCierre::create([
            'fecha_desde' => $miercoles, 'fecha_hasta' => $miercoles,
            'tipo' => AgendaCierre::TIPO_MEDIA_JORNADA, 'hora_hasta' => '19:00',
            'motivo' => 'Cargado con un error', 'origen' => AgendaCierre::ORIGEN_MANUAL,
        ]);

        // `end()` toma la variable por referencia: no acepta el retorno de una función.
        $horas = AgendaTrabajo::horasDisponibles($miercoles);
        $this->assertSame('16:00', end($horas));
    }

    // ─────────────────────────────────────────────────── el envío

    public function test_guarda_la_hora_elegida(): void
    {
        $lunes = $this->proximo(Carbon::MONDAY);

        $this->pedir(['fecha_preferida' => $lunes, 'hora_preferida' => '09:30'])
            ->assertSessionHasNoErrors();

        $this->assertSame('09:30', AgendaTrabajo::sole()->hora_preferida_corta);
    }

    /** El `<select>` se puede editar: la hora se verifica con la misma lista que se ofreció. */
    public function test_una_hora_fuera_del_horario_se_rechaza(): void
    {
        $miercoles = $this->proximo(Carbon::WEDNESDAY);

        // 17:00 existe los lunes, pero el miércoles cierra 16:30.
        $this->pedir(['fecha_preferida' => $miercoles, 'hora_preferida' => '17:00'])
            ->assertSessionHasErrors('hora_preferida');

        $this->assertSame(0, AgendaTrabajo::count());
    }

    /** Una hora sin fecha no se puede usar: se descarta en silencio, no se guarda a medias. */
    public function test_una_hora_sin_fecha_no_se_guarda(): void
    {
        $this->pedir(['hora_preferida' => '09:30'])->assertSessionHasNoErrors();

        $this->assertNull(AgendaTrabajo::sole()->hora_preferida);
    }

    public function test_la_fecha_sin_hora_sigue_valiendo(): void
    {
        // La hora es opcional: «la que se pueda» es una respuesta válida.
        $this->pedir(['fecha_preferida' => $this->proximo(Carbon::MONDAY)])
            ->assertSessionHasNoErrors();

        $this->assertNull(AgendaTrabajo::sole()->hora_preferida);
    }

    // ─────────────────────────────────────────────────── la pantalla

    public function test_el_formulario_publico_ya_no_pide_el_texto_libre(): void
    {
        $html = $this->get(URL::signedRoute('visita-industrial.create', ['sucursal' => $this->sucursal()->id]))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('¿Cuándo puedes y cuándo no?', $html);
        $this->assertStringNotContainsString('name="disponibilidad"', $html);
    }

    public function test_el_formulario_ofrece_elegir_la_hora_y_dice_el_horario(): void
    {
        $html = $this->get(URL::signedRoute('visita-industrial.create', ['sucursal' => $this->sucursal()->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="hora_preferida"', $html);
        $this->assertStringContainsString('¿A qué hora te acomoda?', $html);
        // Sin fecha elegida no hay lista posible (depende del día de la semana), así que la
        // pantalla dice el horario de la semana en vez de mostrar un desplegable vacío.
        $this->assertStringContainsString('lunes y martes de 08:00 a 17:30', $html);
        $this->assertStringContainsString('miércoles a viernes de 08:00 a 16:30', $html);
    }

    /** Y el texto libre ya no entra ni forzándolo: el campo público dejó de existir. */
    public function test_el_texto_libre_enviado_a_mano_se_ignora(): void
    {
        $this->pedir(['disponibilidad' => 'Fines de semana no, después de las 15'])
            ->assertSessionHasNoErrors();

        $this->assertNull(AgendaTrabajo::sole()->disponibilidad);
    }

    /** Quien coordina tiene que VER la hora que eligió el cliente, junto a la fecha. */
    public function test_la_agenda_muestra_la_hora_preferida(): void
    {
        $lunes = $this->proximo(Carbon::MONDAY);
        $this->pedir(['fecha_preferida' => $lunes, 'hora_preferida' => '10:00']);

        $jefe = tap(\App\Models\User::factory()->create())->assignRole('jefe_ventas');

        $this->actingAs($jefe)
            ->get(route('admin.agenda-terreno.index'))
            ->assertOk()
            ->assertSee('a las 10:00');
    }
}
