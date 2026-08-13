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

    // ─────────────────────────────────────────────────────── el cálculo

    public function test_un_dia_sin_trabajos_esta_libre(): void
    {
        $this->consultar(Carbon::parse(FechaNegocio::hoy())->addDays(3)->toDateString())
            ->assertOk()
            ->assertJson(['ocupado' => false, 'dias' => 0, 'etiqueta_tramo' => null]);
    }

    public function test_un_dia_con_un_trabajo_agendado_esta_ocupado(): void
    {
        $dia = Carbon::parse(FechaNegocio::hoy())->addDays(3)->toDateString();
        $this->trabajo($dia);

        $r = $this->consultar($dia)->assertOk();

        $this->assertTrue($r->json('ocupado'));
        $this->assertSame(1, $r->json('dias'));
        $this->assertSame(Carbon::parse($dia)->addDay()->toDateString(), $r->json('proximo_libre'));
    }

    /**
     * Un VIAJE ocupa varios días y hay que decir el tramo completo, que es textualmente lo
     * que el dueño pidió («que no se puede ese día o varios días»). Y el tramo se informa
     * desde su ARRANQUE aunque el cliente haya elegido un día del medio: «del 7 al 10» y no
     * «del 9 al 10», que sería una media verdad.
     */
    public function test_un_viaje_de_varios_dias_informa_el_tramo_completo(): void
    {
        $desde = Carbon::parse(FechaNegocio::hoy())->addDays(7);
        $hasta = $desde->copy()->addDays(3);
        $this->trabajo($desde->toDateString(), $hasta->toDateString());

        $r = $this->consultar($desde->copy()->addDays(2)->toDateString())->assertOk();

        $this->assertTrue($r->json('ocupado'));
        $this->assertSame(4, $r->json('dias'), 'El tramo tiene que contar los cuatro días del viaje.');
        $this->assertSame($hasta->copy()->addDay()->toDateString(), $r->json('proximo_libre'));
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
        $lunes = Carbon::parse(FechaNegocio::hoy())->addDays(10);
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
        $dia = Carbon::parse(FechaNegocio::hoy())->addDays(4)->toDateString();
        $this->trabajo($dia, null, ['estado' => 'solicitado', 'fecha' => null, 'fecha_preferida' => $dia]);

        $this->consultar($dia)->assertOk()->assertJson(['ocupado' => false]);
    }

    public function test_un_trabajo_cancelado_libera_el_dia(): void
    {
        $dia = Carbon::parse(FechaNegocio::hoy())->addDays(5)->toDateString();
        $this->trabajo($dia, null, ['estado' => 'cancelado']);

        $this->consultar($dia)->assertOk()->assertJson(['ocupado' => false]);
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
        $dia = Carbon::parse(FechaNegocio::hoy())->addDays(6)->toDateString();
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
        $dia = Carbon::parse(FechaNegocio::hoy())->addDays(8)->toDateString();
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
        $dia = Carbon::parse(FechaNegocio::hoy())->addDays(2)->toDateString();

        $this->assertGuest();
        $this->consultar($dia)->assertOk()->assertJson(['ocupado' => false]);

        // Y logueado contesta igual: no hay dos verdades según quién pregunte.
        $this->actingAs(tap(User::factory()->create())->assignRole('vendedor'));
        $this->consultar($dia)->assertOk()->assertJson(['ocupado' => false]);
    }
}
