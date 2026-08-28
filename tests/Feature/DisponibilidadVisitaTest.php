<?php

namespace Tests\Feature;

use App\Models\AgendaTrabajo;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * «¿ESTÁ LIBRE ESE DÍA?» — EL CÁLCULO, Y QUIÉN LO CONSULTA AHORA.
 *
 * Pedido del dueño (13-08-2026): «cuando el cliente ingrese una fecha que diga si está
 * disponible u ocupada, un cartel de advertencia que no se puede ese día o varios días».
 *
 * DE DÓNDE VIENE Y QUÉ CAMBIÓ EL 25-08. El cálculo nació para el cartel en vivo del
 * formulario PÚBLICO: el cliente elegía una fecha y la pantalla le decía al instante si se
 * podía, en vez de hacerle llenar seis campos para enterarse al enviar. El gerente retiró ese
 * formulario —la visita industrial la agendan ahora los vendedores— así que el endpoint JSON
 * que lo alimentaba se fue con él.
 *
 * **El cálculo NO se fue: cambió de cliente.** Vive en `AgendaTrabajo::disponibilidad()` y hoy
 * lo consume `AgendaTrabajoController::bloquearSiNoSeAtiende`, la guarda que rechaza agendar
 * un día que el técnico no atiende — la misma regla, aplicada donde ahora ocurren las visitas.
 * Por eso estos candados pasaron de consultar el ENDPOINT a consultar el MODELO: es el mismo
 * criterio, un nivel más abajo, y sobrevive a que la pantalla que lo muestra cambie otra vez.
 *
 * Qué cuidan, entonces:
 *   · que el cálculo diga la verdad (libre / ocupado / tramo / próximo libre / cerrado);
 *   · que el SERVIDOR rechace lo que el cálculo declara imposible — el puente que evita
 *     candados verdes sobre una regla que nadie aplica.
 *
 * Se retiraron con el endpoint, y vale decir cuáles para que nadie los busque: que la
 * respuesta pública NO contara de quién es el trabajo que ocupa el día (era público y sin
 * firma; ahora del otro lado hay staff con permiso y el mensaje sí nombra cliente y ciudad),
 * que rechazara fechas pasadas o de un futuro lejano (validación de ese endpoint), que
 * contestara sin login, y que el formulario del cliente estuviera cableado al chequeo.
 */
class DisponibilidadVisitaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function trabajo(string $fecha, ?string $fechaFin = null, array $extra = []): AgendaTrabajo
    {
        return AgendaTrabajo::create($extra + [
            'tipo' => 'visita_tecnica',
            'estado' => 'agendado',
            'fecha' => $fecha,
            'fecha_fin' => $fechaFin,
            'cliente_nombre' => 'Aguas Claras SpA',
            'ciudad' => 'Talca',
            'descripcion' => 'Mantención de la llenadora',
        ]);
    }

    /**
     * El cálculo, directo del modelo.
     *
     * Antes esto era un `getJson()` contra el endpoint público. Las claves son las mismas
     * —`estado`, `ocupado`, `dias`, `proximo_libre`, `motivo_cierre`— porque el endpoint las
     * devolvía tal cual; lo que se perdió son las etiquetas en castellano, que las armaba el
     * controlador para el cartel. El equivalente hoy es el mensaje de la guarda interna, y ese
     * texto tiene su propio candado en `VisitaIndustrialTest`.
     *
     * @return array<string, mixed>
     */
    private function consultar(string $fecha): array
    {
        return AgendaTrabajo::disponibilidad($fecha);
    }

    /**
     * Un día LABORABLE a partir de hoy + $dias. Ningún candado de acá puede usar «hoy + 3» a
     * secas: desde que el técnico atiende de lunes a viernes, ese día cae en fin de semana una
     * vez cada tres y la suite pasaría a fallar según el día en que se corre — que es la peor
     * clase de test, el que rompe sin que nadie haya tocado nada.
     */
    private function laborable(int $dias): string
    {
        $d = Carbon::parse(FechaNegocio::hoy())->addDays($dias);

        while (! AgendaTrabajo::esLaborable($d->toDateString())) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    /** El siguiente día laborable DESPUÉS de uno dado (lo que el cálculo debería ofrecer). */
    private function siguienteLaborable(string $desde): string
    {
        $d = Carbon::parse($desde)->addDay();

        while (! AgendaTrabajo::esLaborable($d->toDateString())) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    /** El próximo sábado (o domingo), sin fechas fijas: el candado no puede depender de hoy. */
    private function finDeSemana(int $isoDia = Carbon::SATURDAY): string
    {
        return Carbon::parse(FechaNegocio::hoy())->next($isoDia)->toDateString();
    }

    // ─────────────────────────────────────────────────────── el cálculo

    public function test_un_dia_sin_trabajos_esta_libre(): void
    {
        $r = $this->consultar($this->laborable(3));

        $this->assertSame('libre', $r['estado']);
        $this->assertFalse($r['ocupado']);
        $this->assertSame(0, $r['dias']);
    }

    public function test_un_dia_con_un_trabajo_agendado_esta_ocupado(): void
    {
        $dia = $this->laborable(3);
        $this->trabajo($dia);

        $r = $this->consultar($dia);

        $this->assertSame('ocupado', $r['estado']);
        $this->assertSame(1, $r['dias']);
        $this->assertSame($this->siguienteLaborable($dia), $r['proximo_libre']);
    }

    /**
     * Un VIAJE ocupa varios días y hay que decir el tramo completo, que es textualmente lo
     * que el dueño pidió («que no se puede ese día o varios días»). Y el tramo se informa
     * desde su ARRANQUE aunque se pregunte por un día del medio: «del 7 al 10» y no «del 9 al
     * 10», que sería una media verdad.
     */
    public function test_un_viaje_de_varios_dias_informa_el_tramo_completo(): void
    {
        // Arranca un LUNES para que el viaje de cuatro días caiga entero en semana: si
        // cruzara el fin de semana, el tramo mezclaría días ocupados con días cerrados y el
        // candado dejaría de probar lo que dice probar.
        $desde = Carbon::parse($this->laborable(7))->next(Carbon::MONDAY);
        $hasta = $desde->copy()->addDays(3);
        $this->trabajo($desde->toDateString(), $hasta->toDateString());

        $r = $this->consultar($desde->copy()->addDays(2)->toDateString());

        $this->assertSame('ocupado', $r['estado']);
        $this->assertSame(4, $r['dias'], 'El tramo tiene que contar los cuatro días del viaje.');
        $this->assertSame($desde->toDateString(), $r['desde'], 'El tramo se informa desde su arranque.');
        $this->assertSame($this->siguienteLaborable($hasta->toDateString()), $r['proximo_libre']);
    }

    /**
     * DOS TRABAJOS PEGADOS SON UN SOLO TRAMO. Lo que sirve no es saber dónde termina un
     * trabajo: es saber cuándo puede ir el técnico. Si el próximo libre cayera en el primer
     * día del segundo trabajo, se ofrecería un día que también está tomado — y la guarda lo
     * rechazaría igual al guardar.
     */
    public function test_dos_trabajos_pegados_no_dejan_un_hueco_falso(): void
    {
        // Lunes y martes: dos días pegados y los dos laborables.
        $lunes = Carbon::parse($this->laborable(7))->next(Carbon::MONDAY);
        $this->trabajo($lunes->toDateString());
        $this->trabajo($lunes->copy()->addDay()->toDateString());

        $r = $this->consultar($lunes->toDateString());

        $this->assertSame(2, $r['dias']);
        $this->assertSame($lunes->copy()->addDays(2)->toDateString(), $r['proximo_libre'],
            'El próximo libre cayó sobre el segundo trabajo: se ofrecería un día tomado.');
    }

    /**
     * Una SOLICITUD (estado `solicitado`, sin fecha real) no ocupa nada: es una visita anotada
     * que todavía nadie coordinó. Si contara, la agenda se llenaría de días falsos apenas
     * hubiera dos pendientes — y desde el 25-08 «anotarla sin fecha» es la forma normal de
     * dejar una visita para coordinar después.
     */
    public function test_una_solicitud_sin_coordinar_no_ocupa_el_dia(): void
    {
        $dia = $this->laborable(4);
        $this->trabajo($dia, null, ['estado' => 'solicitado', 'fecha' => null, 'fecha_preferida' => $dia]);

        $this->assertFalse($this->consultar($dia)['ocupado']);
    }

    public function test_un_trabajo_cancelado_libera_el_dia(): void
    {
        $dia = $this->laborable(5);
        $this->trabajo($dia, null, ['estado' => 'cancelado']);

        $this->assertFalse($this->consultar($dia)['ocupado']);
    }

    // ─────────────────────────────────────────────────────── días laborables

    /**
     * «Trabaja solo de lunes a viernes el técnico» (dueño, 13-08-2026). El sábado no está
     * OCUPADO —no hay ningún trabajo— sino CERRADO, y son cosas distintas: un día tomado
     * invita a probar el de al lado; un día que no se atiende, no. La distinción sobrevive al
     * cambio de pantalla porque es del cálculo: `motivo_cierre` la lleva, y la guarda interna
     * la usa para elegir entre «la agenda está cerrada» y «va a terreno de lunes a viernes».
     */
    public function test_el_sabado_y_el_domingo_estan_cerrados(): void
    {
        foreach ([Carbon::SATURDAY, Carbon::SUNDAY] as $dia) {
            $r = $this->consultar($this->finDeSemana($dia));

            $this->assertSame('cerrado', $r['estado']);
            $this->assertTrue($r['ocupado'], 'Cerrado también es «no se puede ese día».');
            $this->assertSame('no_laborable', $r['motivo_cierre']);
            $this->assertNull($r['desde'], 'Un día cerrado no tiene tramo que informar.');
        }
    }

    /** Y el día que ofrece después del sábado NO puede ser el domingo. */
    public function test_el_proximo_libre_de_un_sabado_es_un_dia_laborable(): void
    {
        $libre = $this->consultar($this->finDeSemana())['proximo_libre'];

        $this->assertNotNull($libre);
        $this->assertTrue(AgendaTrabajo::esLaborable($libre),
            "Se ofreció el {$libre}, que no es día laborable.");
    }

    /**
     * EL CASO QUE MÁS IMPORTA: un viernes ocupado NO puede ofrecer el sábado. Antes de la
     * regla de días laborables, el «próximo libre» solo esquivaba trabajos, así que un viernes
     * tomado ofrecía el sábado — un día sin nadie, que el servidor rechaza igual.
     */
    public function test_un_viernes_ocupado_ofrece_el_lunes_y_no_el_sabado(): void
    {
        $viernes = Carbon::parse(FechaNegocio::hoy())->next(Carbon::FRIDAY);
        $this->trabajo($viernes->toDateString());

        $r = $this->consultar($viernes->toDateString());

        $this->assertSame('ocupado', $r['estado']);
        $this->assertSame($viernes->copy()->next(Carbon::MONDAY)->toDateString(), $r['proximo_libre'],
            'Se ofreció un día del fin de semana: el técnico no atiende sábado ni domingo.');
    }

    // ─────────────────────────────────────────────────────── el puente

    /**
     * EL PUENTE: que el cálculo diga «no se puede» no sirve si quien guarda lo acepta igual.
     * Es la lección más repetida de este repo —candados verdes sobre una regla que nadie
     * aplica— y es justo lo que estuvo pasando con esta regla: se validaba SOLO en el
     * formulario público, así que el camino interno la ignoraba y un vendedor podía agendar un
     * sábado. Ahora el puente es `bloquearSiNoSeAtiende`, y este candado lo cruza de punta a
     * punta: el cálculo declara el día cerrado y el POST del formulario interno lo rechaza.
     */
    public function test_lo_que_el_calculo_declara_cerrado_el_servidor_lo_rechaza(): void
    {
        $sabado = $this->finDeSemana();

        $this->assertSame('cerrado', $this->consultar($sabado)['estado'], 'El fixture no es un día cerrado.');

        $this->actingAs(tap(User::factory()->create())->assignRole('vendedor'))
            ->post(route('admin.agenda-terreno.store'), $this->cita(['fecha' => $sabado]))
            ->assertSessionHasErrors('fecha');

        $this->assertSame(0, AgendaTrabajo::count());
    }

    /**
     * Y la otra mitad del puente: un día OCUPADO por otro trabajo también se rechaza. Son dos
     * guardas distintas (`bloquearSiNoSeAtiende` y `bloquearSiOcupado`) y cada una dice lo
     * suyo — la de ocupación además puede nombrar al cliente y la ciudad, porque del otro lado
     * hay alguien con permiso.
     */
    public function test_lo_que_el_calculo_declara_ocupado_el_servidor_lo_rechaza(): void
    {
        $dia = $this->laborable(8);
        $this->trabajo($dia);

        $this->assertSame('ocupado', $this->consultar($dia)['estado']);

        $this->actingAs(tap(User::factory()->create())->assignRole('vendedor'))
            ->post(route('admin.agenda-terreno.store'), $this->cita(['fecha' => $dia]))
            ->assertSessionHasErrors('fecha');

        $this->assertSame(1, AgendaTrabajo::count(), 'Se agendó encima del trabajo que ya estaba.');
    }

    /**
     * Una cita completa para el formulario interno. `mantencion` y no `visita_tecnica` a
     * propósito: los dos pasan por las mismas guardas de fecha, y con `mantencion` el candado
     * no se cruza con la autorización del jefe de ventas — lo que se prueba acá es la fecha.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function cita(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'mantencion',
            'estado' => 'agendado',
            'hora' => '10:00',
            'cliente_nombre' => 'Planta Norte SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@norte.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La llenadora traba la cadena.',
        ], $extra);
    }
}
