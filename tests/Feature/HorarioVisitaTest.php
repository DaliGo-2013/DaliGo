<?php

namespace Tests\Feature;

use App\Models\AgendaCierre;
use App\Models\AgendaTrabajo;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
 *   · que un día cerrado no ofrezca ninguna hora.
 *
 * ────────────────────────────────────────────────────────────────────────────────────────────
 * QUÉ CAMBIÓ EL 25-08. El gerente retiró la visita industrial de la vista del cliente, así que
 * el formulario donde el cliente pinchaba su hora ya no existe. **El horario sí sigue vivo**:
 * es el del técnico, lo usa `AgendaTrabajo::horasDisponibles()` y de ahí sale también el
 * criterio de la guarda que ahora rechaza agendar un día que no se atiende.
 *
 * Los candados que probaban el ENVÍO del cliente (guardar la hora elegida, rechazar una hora
 * fuera del horario, ignorar el texto libre) se retiraron con ese formulario.
 *
 * LA HORA SE DEJA SIN CANDADO, Y ES UNA DECISIÓN TOMADA (dueño, 26-08-2026). La verificación
 * «la hora elegida está dentro del horario del día» vivía SOLO en el formulario público, así
 * que hoy nada impide que un vendedor agende a las 19:00 un miércoles que cierra 16:30. Se
 * planteó cerrarlo —es hermano del hueco de los días, que sí se cerró— y el dueño resolvió
 * **dejarlo abierto a propósito**: *«luego cuando hagamos pruebas con los vendedores que ellos
 * digan si se va a modificar a un rango de horas más extensas o no»*.
 *
 * O sea: el horario de `HORARIO` puede estar más angosto que la realidad, y validar contra él
 * bloquearía visitas que sí se hacen. Primero se mira cómo agendan los vendedores y después se
 * decide si el rango se ensancha, si se valida, o las dos. **No es un olvido: es un dato que
 * falta.** Si alguien viene a cerrar este hueco sin esa retroalimentación, esto es lo que hay
 * que leer primero.
 * ────────────────────────────────────────────────────────────────────────────────────────────
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

    /**
     * Una visita anotada con su fecha y hora PREFERIDAS, como las que quedaron de cuando el
     * cliente las pedía por el QR y como las que anota hoy quien atiende el teléfono.
     * Reemplaza al POST del formulario público, retirado el 25-08.
     *
     * @param  array<string, mixed>  $extra
     */
    private function anotada(array $extra = []): AgendaTrabajo
    {
        return AgendaTrabajo::create($extra + [
            'tipo' => AgendaTrabajo::TIPO_PUBLICO,
            'estado' => 'solicitado',
            'fecha' => null,
            'cliente_nombre' => 'Planta Norte SpA',
            'cliente_rut' => '12345678-5',
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

    // ─────────────────────────────────────────────────── el horario del día

    /**
     * El ROTULO del horario, que es lo que se le dice a alguien cuando pregunta «¿hasta qué
     * hora van?». Lo armaba el endpoint público leyendo el modelo; el modelo lo sigue
     * armando, así que el candado baja un nivel y sobrevive al cambio de pantalla.
     */
    public function test_el_dia_dice_su_horario_completo(): void
    {
        $lunes = $this->proximo(Carbon::MONDAY);

        $this->assertSame('08:00 a 17:30', AgendaTrabajo::horarioLabel($lunes));
        $this->assertSame('08:00 a 16:30', AgendaTrabajo::horarioLabel($this->proximo(Carbon::WEDNESDAY)));
        $this->assertNull(AgendaTrabajo::horarioLabel($this->proximo(Carbon::SATURDAY)),
            'Un día que no se atiende no tiene horario que informar.');
    }

    /** Un día que no se atiende no ofrece horas: sería invitar a elegir algo imposible. */
    public function test_un_dia_cerrado_no_ofrece_horas(): void
    {
        $this->assertSame([], AgendaTrabajo::horasDisponibles($this->proximo(Carbon::SATURDAY)));
    }

    // El candado «un día OCUPADO tampoco ofrece horas» se retiró con el endpoint público: ese
    // blanqueo lo hacía el controlador (`$d['ocupado'] ? [] : horasDisponibles(...)`), no el
    // modelo — un día tomado sigue teniendo su horario, lo que no tiene es al técnico libre.
    // Que no se pueda agendar encima lo cuida `bloquearSiOcupado`, con su candado en
    // `DisponibilidadVisitaTest`.

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

    // ─────────────────────────────────────────────────── lo que quedó del envío
    //
    // Los cuatro candados del ENVÍO DEL CLIENTE —guardar la hora elegida, rechazar una hora
    // fuera del horario del día, no guardar una hora sin fecha, y aceptar una fecha sin hora—
    // se retiraron con el formulario público. El segundo es el que dejó un hueco: esa
    // verificación no existe en el camino interno (ver el encabezado).
    //
    // Y los dos de la PANTALLA del cliente (que ya no pedía el texto libre, que ofrecía elegir
    // la hora y decía el horario de la semana) se fueron con esa pantalla. El campo de texto
    // libre NO desapareció del sistema: quien coordina lo sigue teniendo en el formulario
    // interno para anotar lo que el cliente cuenta por teléfono, y eso lo cuida
    // `VisitaIndustrialTest::test_el_formulario_interno_conserva_la_disponibilidad_escrita`.

    /** Quien coordina tiene que VER la hora preferida que quedó anotada, junto a la fecha. */
    public function test_la_agenda_muestra_la_hora_preferida(): void
    {
        $lunes = $this->proximo(Carbon::MONDAY);
        $this->anotada(['fecha_preferida' => $lunes, 'hora_preferida' => '10:00']);

        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');

        $this->actingAs($jefe)
            ->get(route('admin.agenda-terreno.index'))
            ->assertOk()
            ->assertSee('a las 10:00');
    }
}
