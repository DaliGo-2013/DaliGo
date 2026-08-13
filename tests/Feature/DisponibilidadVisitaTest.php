<?php

namespace Tests\Feature;

use App\Models\AgendaTrabajo;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * «¿ESTÁ LIBRE ESE DÍA?» EN EL FORMULARIO PÚBLICO.
 *
 * Pedido del dueño (13-08-2026): «cuando el cliente ingrese una fecha que diga si está
 * disponible u ocupada, un cartel de advertencia que no se puede ese día o varios días».
 *
 * El chequeo YA EXISTÍA en el servidor —`store()` rechaza una fecha preferida en día
 * ocupado— pero recién al ENVIAR: el cliente llenaba nombre, RUT, teléfono, correo,
 * dirección y ciudad, apretaba Enviar y ahí se enteraba. Lo que se agregó es adelantarlo, con
 * LA MISMA REGLA (`AgendaTrabajo::conflictos`, la que también impide agendar encima de un
 * técnico ocupado). Dos criterios de «ocupado» serían una pantalla que promete un día que el
 * servidor después rechaza.
 *
 * Y DOS RESPUESTAS DISTINTAS, no una (dueño, 13-08): un día puede estar **ocupado** (hay un
 * trabajo encima) o **cerrado** (el técnico no atiende ese día: atiende de lunes a viernes).
 * Al cliente le sirven distinto — un día tomado invita a probar el de al lado, un día que no
 * se atiende no— y del motivo de fondo no se dice nada.
 *
 * Por eso estos candados cuidan tres cosas distintas:
 *   · que el cálculo diga la verdad (libre / ocupado / tramo / próximo libre);
 *   · que el endpoint público NO cuente de quién es el trabajo que ocupa el día;
 *   · que la PANTALLA esté conectada al endpoint — un candado sobre el servicio no prueba
 *     que el formulario lo consulte, y esa lección ya salió cara en este repo.
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

    private function consultar(string $fecha)
    {
        return $this->getJson(route('visita-industrial.disponibilidad', ['fecha' => $fecha]));
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

    /** El siguiente día laborable DESPUÉS de uno dado (lo que el cartel debería ofrecer). */
    private function siguienteLaborable(string $desde): string
    {
        $d = Carbon::parse($desde)->addDay();

        while (! AgendaTrabajo::esLaborable($d->toDateString())) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    // ─────────────────────────────────────────────────────── el cálculo

    public function test_un_dia_sin_trabajos_esta_libre(): void
    {
        $this->consultar($this->laborable(3))
            ->assertOk()
            ->assertJson(['estado' => 'libre', 'ocupado' => false, 'dias' => 0, 'etiqueta_tramo' => null]);
    }

    public function test_un_dia_con_un_trabajo_agendado_esta_ocupado(): void
    {
        $dia = $this->laborable(3);
        $this->trabajo($dia);

        $r = $this->consultar($dia)->assertOk();

        $this->assertSame('ocupado', $r->json('estado'));
        $this->assertSame(1, $r->json('dias'));
        $this->assertSame($this->siguienteLaborable($dia), $r->json('proximo_libre'));
    }

    /**
     * Un VIAJE ocupa varios días y hay que decir el tramo completo, que es textualmente lo
     * que el dueño pidió («que no se puede ese día o varios días»). Y el tramo se informa
     * desde su ARRANQUE aunque el cliente haya elegido un día del medio: «del 7 al 10» y no
     * «del 9 al 10», que sería una media verdad.
     */
    public function test_un_viaje_de_varios_dias_informa_el_tramo_completo(): void
    {
        // Arranca un LUNES para que el viaje de cuatro días caiga entero en semana: si
        // cruzara el fin de semana, el tramo mezclaría días ocupados con días cerrados y el
        // candado dejaría de probar lo que dice probar.
        $desde = Carbon::parse($this->laborable(7))->next(Carbon::MONDAY);
        $hasta = $desde->copy()->addDays(3);
        $this->trabajo($desde->toDateString(), $hasta->toDateString());

        $r = $this->consultar($desde->copy()->addDays(2)->toDateString())->assertOk();

        $this->assertSame('ocupado', $r->json('estado'));
        $this->assertSame(4, $r->json('dias'), 'El tramo tiene que contar los cuatro días del viaje.');
        $this->assertSame($this->siguienteLaborable($hasta->toDateString()), $r->json('proximo_libre'));
        $this->assertStringStartsWith('del ', (string) $r->json('etiqueta_tramo'));
    }

    /**
     * DOS TRABAJOS PEGADOS SON UN SOLO TRAMO. Al cliente no le sirve saber dónde termina un
     * trabajo: le sirve saber cuándo puede ir el técnico. Si el próximo libre cayera en el
     * primer día del segundo trabajo, el cartel le ofrecería un día que también está tomado
     * — y el servidor se lo rechazaría igual al enviar.
     */
    public function test_dos_trabajos_pegados_no_dejan_un_hueco_falso(): void
    {
        // Lunes y martes: dos días pegados y los dos laborables.
        $lunes = Carbon::parse($this->laborable(7))->next(Carbon::MONDAY);
        $this->trabajo($lunes->toDateString());
        $this->trabajo($lunes->copy()->addDay()->toDateString());

        $r = $this->consultar($lunes->toDateString())->assertOk();

        $this->assertSame(2, $r->json('dias'));
        $this->assertSame($lunes->copy()->addDays(2)->toDateString(), $r->json('proximo_libre'),
            'El próximo libre cayó sobre el segundo trabajo: se le ofrecería al cliente un día tomado.');
    }

    /**
     * Una SOLICITUD (estado `solicitado`, sin fecha real) no ocupa nada: es un pedido que
     * todavía nadie coordinó. Si contara, la agenda se llenaría de días falsos apenas
     * entraran dos pedidos por el QR.
     */
    public function test_una_solicitud_sin_coordinar_no_ocupa_el_dia(): void
    {
        $dia = $this->laborable(4);
        $this->trabajo($dia, null, ['estado' => 'solicitado', 'fecha' => null, 'fecha_preferida' => $dia]);

        $this->consultar($dia)->assertOk()->assertJson(['ocupado' => false]);
    }

    public function test_un_trabajo_cancelado_libera_el_dia(): void
    {
        $dia = $this->laborable(5);
        $this->trabajo($dia, null, ['estado' => 'cancelado']);

        $this->consultar($dia)->assertOk()->assertJson(['ocupado' => false]);
    }

    // ─────────────────────────────────────────────────────── días laborables

    /** El próximo sábado (o domingo), sin fechas fijas: el candado no puede depender de hoy. */
    private function finDeSemana(int $isoDia = Carbon::SATURDAY): string
    {
        return Carbon::parse(FechaNegocio::hoy())->next($isoDia)->toDateString();
    }

    /**
     * «Trabaja solo de lunes a viernes el técnico» (dueño, 13-08-2026). El sábado no está
     * OCUPADO —no hay ningún trabajo— sino CERRADO, y son cosas distintas para el que pide:
     * un día tomado invita a probar el de al lado; un día que no se atiende, no.
     */
    public function test_el_sabado_y_el_domingo_estan_cerrados(): void
    {
        foreach ([Carbon::SATURDAY, Carbon::SUNDAY] as $dia) {
            $r = $this->consultar($this->finDeSemana($dia))->assertOk();

            $this->assertSame('cerrado', $r->json('estado'));
            $this->assertTrue($r->json('ocupado'), 'Cerrado también es «no se puede pedir ese día».');
            $this->assertNotNull($r->json('etiqueta_cerrado'));
            $this->assertNull($r->json('etiqueta_tramo'), 'Un día cerrado no tiene tramo que informar.');
        }
    }

    /** Y el día que sigue al sábado que ofrece NO puede ser el domingo. */
    public function test_el_proximo_libre_de_un_sabado_es_un_dia_laborable(): void
    {
        $libre = $this->consultar($this->finDeSemana())->assertOk()->json('proximo_libre');

        $this->assertNotNull($libre);
        $this->assertTrue(AgendaTrabajo::esLaborable($libre),
            "Se ofreció el {$libre}, que no es día laborable.");
    }

    /**
     * EL CASO QUE MÁS IMPORTA: un viernes ocupado NO puede ofrecer el sábado. Antes de la
     * regla de días laborables, el «próximo libre» solo esquivaba trabajos, así que un viernes
     * tomado ofrecía el sábado — un día sin nadie, que el servidor rechazaría al enviar.
     */
    public function test_un_viernes_ocupado_ofrece_el_lunes_y_no_el_sabado(): void
    {
        $viernes = Carbon::parse(FechaNegocio::hoy())->next(Carbon::FRIDAY);
        $this->trabajo($viernes->toDateString());

        $r = $this->consultar($viernes->toDateString())->assertOk();

        $this->assertSame('ocupado', $r->json('estado'));
        $this->assertSame($viernes->copy()->next(Carbon::MONDAY)->toDateString(), $r->json('proximo_libre'),
            'Se ofreció un día del fin de semana: el técnico no atiende sábado ni domingo.');
    }

    public function test_el_envio_rechaza_una_fecha_de_fin_de_semana(): void
    {
        // El cartel y el servidor tienen que decir lo mismo: si el cartel dijo «no se
        // atiende», el envío no puede aceptarlo igual.
        $sucursal = Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);

        $this->post(route('visita-industrial.store'), [
            'sucursal_id' => $sucursal->id,
            'cliente_nombre' => 'Planta Norte SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@norte.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La llenadora traba la cadena.',
            'fecha_preferida' => $this->finDeSemana(),
        ])->assertSessionHasErrors('fecha_preferida');
    }

    // ─────────────────────────────────────────────────────── privacidad

    /**
     * EL ENDPOINT ES PÚBLICO Y SIN FIRMA. Contestar «ocupado porque el técnico está en Aguas
     * Claras, en Talca» sería contarle a cualquiera para quién trabaja la empresa y dónde. El
     * mensaje interno sí nombra cliente y ciudad, y ahí está bien: del otro lado hay alguien
     * con permiso.
     */
    public function test_no_cuenta_de_quien_es_el_trabajo_que_ocupa_el_dia(): void
    {
        $dia = $this->laborable(6);
        $this->trabajo($dia);

        $cuerpo = $this->consultar($dia)->assertOk()->getContent();

        foreach (['Aguas Claras', 'Talca', 'llenadora', 'Mantención'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $cuerpo,
                "La respuesta pública filtró «{$secreto}»: dice de quién es el trabajo que ocupa el día.");
        }
    }

    public function test_no_acepta_fechas_pasadas_ni_de_un_futuro_lejano(): void
    {
        // El pasado no se puede pedir (lo mismo que valida el envío) y el futuro se acota:
        // sin tope, esto es un recorrido gratis por la agenda de la empresa.
        $this->consultar(Carbon::parse(FechaNegocio::hoy())->subDay()->toDateString())
            ->assertStatus(422);
        $this->consultar(Carbon::parse(FechaNegocio::hoy())->addYears(2)->toDateString())
            ->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────── el puente

    /**
     * EL PUENTE: que el cálculo funcione no prueba que el formulario lo consulte. Es la
     * lección más repetida de este repo — candados verdes sobre una función que la pantalla
     * nunca llama.
     */
    public function test_el_formulario_consulta_la_disponibilidad_al_elegir_la_fecha(): void
    {
        $sucursal = Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
        $html = $this->get(URL::signedRoute('visita-industrial.create', ['sucursal' => $sucursal->id]))
            ->assertOk()->getContent();

        // `@js()` escapa las barras al serializar (`http:\/\/…`), que es correcto para meter
        // una cadena en un atributo pero rompe una comparación literal contra `route()`.
        $html = str_replace('\\/', '/', $html);

        $this->assertStringContainsString(route('visita-industrial.disponibilidad'), $html,
            'El formulario no trae la dirección del chequeo: el cartel no puede consultar nada.');
        $this->assertStringContainsString('@change="revisar()"', $html,
            'El campo de fecha dejó de disparar la consulta al cambiar.');
        $this->assertStringContainsString('Ese día hay disponibilidad', $html);
        $this->assertStringContainsString('coordinamos por teléfono', $html);
    }

    /**
     * Y EL SERVIDOR SIGUE SIENDO EL QUE MANDA. El cartel es un aviso temprano; si alguien
     * envía el formulario con la fecha ocupada de todas formas —JS apagado, o eligiendo la
     * fecha sin disparar el `change`— la solicitud tiene que rebotar igual.
     */
    public function test_el_envio_sigue_rechazando_una_fecha_ocupada(): void
    {
        $sucursal = Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
        $dia = $this->laborable(8);
        $this->trabajo($dia);

        $this->post(route('visita-industrial.store'), [
            'sucursal_id' => $sucursal->id,
            'cliente_nombre' => 'Planta Norte SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1234 5678',
            'cliente_email' => 'planta@norte.cl',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'La llenadora traba la cadena.',
            'fecha_preferida' => $dia,
        ])->assertSessionHasErrors('fecha_preferida');
    }

    /** Es público: el cliente que entra por el QR no tiene sesión. */
    public function test_no_pide_login_y_contesta_lo_mismo_con_sesion(): void
    {
        $dia = $this->laborable(2);

        $this->assertGuest();
        $this->consultar($dia)->assertOk()->assertJson(['ocupado' => false]);

        // Y logueado contesta igual: no hay dos verdades según quién pregunte.
        $this->actingAs(tap(User::factory()->create())->assignRole('vendedor'));
        $this->consultar($dia)->assertOk()->assertJson(['ocupado' => false]);
    }
}
