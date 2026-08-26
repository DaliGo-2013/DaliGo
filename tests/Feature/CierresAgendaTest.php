<?php

namespace Tests\Feature;

use App\Models\AgendaCierre;
use App\Models\AgendaTrabajo;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\FeriadosChileSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FERIADOS, VACACIONES Y MEDIA JORNADA.
 *
 * Pedido del dueño (13-08-2026): «los feriados no trabaja… habría que dejar una opción que
 * diga ocupado por si el técnico está de vacaciones, pero no es tan importante que la gente
 * sepa que está de vacaciones, simplemente no está disponible… y lo mismo por si un día
 * trabaja hasta las dos de la tarde o las doce o las tres».
 *
 * Las tres cosas son UNA sola en el sistema —un tramo de fechas con un tipo— y por eso viven
 * en `AgendaCierre`. Lo que estos candados cuidan:
 *   · que un día cerrado no se pueda pedir, venga de un feriado o de unas vacaciones;
 *   · que la media jornada NO cierre el día, sino que avise hasta qué hora;
 *   · que el MOTIVO no salga jamás por el endpoint público — es la parte que el dueño pidió
 *     explícitamente y la que un descuido futuro puede romper sin que se note.
 */
class CierresAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function consultar(string $fecha): array
    {
        return AgendaTrabajo::disponibilidad($fecha);
    }

    /** Un día laborable a partir de hoy + $dias (la suite no puede depender del día en que corre). */
    private function laborable(int $dias): string
    {
        $d = Carbon::parse(FechaNegocio::hoy())->addDays($dias);

        while (! AgendaTrabajo::esLaborable($d->toDateString())) {
            $d->addDay();
        }

        return $d->toDateString();
    }

    private function cierre(string $desde, ?string $hasta = null, array $extra = []): AgendaCierre
    {
        return AgendaCierre::create($extra + [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta ?? $desde,
            'tipo' => AgendaCierre::TIPO_CERRADO,
            'motivo' => 'Vacaciones de Carlos',
            'origen' => AgendaCierre::ORIGEN_MANUAL,
        ]);
    }

    // ─────────────────────────────────────────────────────── cerrado

    public function test_un_dia_cerrado_no_se_puede_pedir(): void
    {
        $dia = $this->laborable(3);
        $this->cierre($dia);

        $r = $this->consultar($dia);

        $this->assertSame('cerrado', $r['estado']);
        $this->assertTrue($r['ocupado']);
        // `motivo_cierre` en vez de la etiqueta en castellano: la etiqueta la armaba el
        // endpoint público, que se retiró el 26-08 con el formulario del cliente. El dato que
        // distingue «cierre cargado» de «fin de semana» es este, y es el que lee la guarda
        // interna para elegir su mensaje.
        $this->assertSame('cierre', $r['motivo_cierre']);
    }

    /**
     * UNAS VACACIONES SON UN RANGO y cierran todos sus días. Y el próximo libre tiene que
     * saltar el rango entero: ofrecer el segundo día de las vacaciones sería peor que no
     * ofrecer nada.
     */
    public function test_un_rango_cerrado_se_salta_completo(): void
    {
        $desde = Carbon::parse($this->laborable(7))->next(Carbon::MONDAY);
        $hasta = $desde->copy()->addDays(4);   // lunes a viernes
        $this->cierre($desde->toDateString(), $hasta->toDateString());

        $r = $this->consultar($desde->copy()->addDays(2)->toDateString());

        $this->assertSame('cerrado', $r['estado']);
        $libre = $r['proximo_libre'];
        $this->assertNotNull($libre);
        $this->assertTrue(Carbon::parse($libre)->greaterThan($hasta),
            "Se ofreció el {$libre}, que cae dentro del tramo cerrado.");
        $this->assertTrue(AgendaTrabajo::esLaborable($libre));
    }

    /**
     * EL TEXTO DISTINGUE fin de semana de cierre cargado, porque son cosas distintas para
     * quien lee: «no se atiende sábados» es una regla estable; «la agenda está cerrada» es
     * este día en particular.
     *
     * La distinción vivía en el cartel del formulario público y se mudó con la regla: hoy la
     * hace `bloquearSiNoSeAtiende` leyendo `motivo_cierre`. Se verifican las DOS puntas —el
     * dato del cálculo y el texto que ve quien agenda—: con solo el dato, nada garantiza que
     * el mensaje lo use; con solo el texto, no se sabe si el cálculo los separa.
     */
    public function test_el_texto_de_un_cierre_no_es_el_del_fin_de_semana(): void
    {
        $sabado = Carbon::parse(FechaNegocio::hoy())->next(Carbon::SATURDAY)->toDateString();
        $cerrado = $this->laborable(4);
        $this->cierre($cerrado);

        $this->assertSame('no_laborable', $this->consultar($sabado)['motivo_cierre']);
        $this->assertSame('cierre', $this->consultar($cerrado)['motivo_cierre']);

        $this->assertStringContainsString('lunes a viernes', $this->errorAlAgendar($sabado));
        $this->assertStringContainsString('la agenda está cerrada', $this->errorAlAgendar($cerrado));
    }

    /**
     * El mensaje que recibe quien intenta agendar ese día. Es el reemplazo del cartel en vivo
     * del formulario público: el mismo criterio, dicho del otro lado del mostrador.
     */
    private function errorAlAgendar(string $dia): string
    {
        $this->actingAs(tap(User::factory()->create())->assignRole('vendedor'))
            ->post(route('admin.agenda-terreno.store'), [
                'tipo' => 'mantencion',
                'estado' => 'agendado',
                'fecha' => $dia,
                'hora' => '10:00',
                'cliente_nombre' => 'Planta Norte SpA',
                'cliente_rut' => '12.345.678-5',
                'cliente_telefono' => '+56 9 1234 5678',
                'cliente_email' => 'planta@norte.cl',
                'direccion' => 'Camino Industrial 500',
                'ciudad' => 'Talca',
                'descripcion' => 'La llenadora traba la cadena.',
            ])->assertSessionHasErrors('fecha');

        return (string) session('errors')->getBag('default')->first('fecha');
    }

    // ─────────────────────────────────────────────────────── media jornada

    /**
     * MEDIA JORNADA NO ES UN DÍA CERRADO: se puede pedir, y se dice hasta qué hora. Cerrarlo
     * sería perder un día de trabajo por avisar de más.
     */
    public function test_media_jornada_deja_pedir_el_dia_y_avisa_la_hora(): void
    {
        $dia = $this->laborable(3);
        $this->cierre($dia, null, [
            'tipo' => AgendaCierre::TIPO_MEDIA_JORNADA,
            'hora_hasta' => '14:00',
            'motivo' => 'Carlos sale temprano',
        ]);

        $r = $this->consultar($dia);

        $this->assertSame('parcial', $r['estado']);
        $this->assertFalse($r['ocupado'], 'La media jornada dejó de permitir pedir ese día.');
        $this->assertSame('14:00', $r['hora_hasta']);
    }

    /** Y AGENDAR lo acepta: es un día disponible, con una aclaración. */
    public function test_se_puede_agendar_un_dia_de_media_jornada(): void
    {
        $dia = $this->laborable(3);
        $this->cierre($dia, null, ['tipo' => AgendaCierre::TIPO_MEDIA_JORNADA, 'hora_hasta' => '12:00']);

        $this->actingAs(tap(User::factory()->create())->assignRole('vendedor'))
            ->post(route('admin.agenda-terreno.store'), [
                'tipo' => 'mantencion',
                'estado' => 'agendado',
                'fecha' => $dia,
                'hora' => '10:00',
                'cliente_nombre' => 'Planta Norte SpA',
                'cliente_rut' => '12.345.678-5',
                'cliente_telefono' => '+56 9 1234 5678',
                'cliente_email' => 'planta@norte.cl',
                'direccion' => 'Camino Industrial 500',
                'ciudad' => 'Talca',
                'descripcion' => 'La llenadora traba la cadena.',
            ])->assertSessionHasNoErrors();

        $this->assertSame($dia, AgendaTrabajo::sole()->fecha->toDateString());
    }

    /** Si un día tiene los dos, manda el que CIERRA: media jornada no reabre un día cerrado. */
    public function test_entre_cerrado_y_media_jornada_gana_cerrado(): void
    {
        $dia = $this->laborable(5);
        $this->cierre($dia, null, ['tipo' => AgendaCierre::TIPO_MEDIA_JORNADA, 'hora_hasta' => '14:00']);
        $this->cierre($dia);

        $this->assertSame('cerrado', $this->consultar($dia)['estado']);
    }

    // ─────────────────────────────────────────────────────── privacidad

    /**
     * LA PARTE QUE EL DUEÑO PIDIÓ EXPRESAMENTE: «no es tan importante que la gente sepa que
     * está de vacaciones». El motivo es para el jefe de ventas.
     *
     * ANTES SE VERIFICABA CONTRA LA RESPUESTA PÚBLICA, que se retiró el 26-08. La regla no se
     * fue con ella: el motivo es texto libre que alguien escribe («Vacaciones de Carlos en
     * Pucón») y **no tiene por qué viajar a ningún mensaje**, ni siquiera al del vendedor —
     * decir «la agenda está cerrada» alcanza para que elija otro día, y el motivo aparece en
     * la pantalla de cierres, que es de jefatura. Un mensaje de error es lo más fácil de
     * copiar y pegar en un correo al cliente.
     */
    public function test_el_motivo_del_cierre_no_sale_en_el_mensaje(): void
    {
        $dia = $this->laborable(3);
        $this->cierre($dia, null, ['motivo' => 'Vacaciones de Carlos en Pucón']);

        $error = $this->errorAlAgendar($dia);

        foreach (['Vacaciones de Carlos', 'Pucón'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $error,
                "El mensaje filtró «{$secreto}»: el motivo del cierre es de jefatura.");
        }
        // Y sí dice lo que hace falta para elegir otro día.
        $this->assertStringContainsString('la agenda está cerrada', $error);
    }

    // ─────────────────────────────────────────────────────── feriados

    public function test_los_feriados_quedan_cargados_y_cierran_el_dia(): void
    {
        $this->seed(FeriadosChileSeeder::class);

        // El 18 y 19 de septiembre de 2026 son feriados irrenunciables y caen viernes y
        // sábado: el 18 tiene que quedar cerrado por FERIADO, no por fin de semana.
        $r = $this->consultar('2026-09-18');

        $this->assertSame('cerrado', $r['estado']);
        $this->assertSame('cierre', $r['motivo_cierre'],
            'El 18 de septiembre de 2026 es viernes: si sale «no_laborable», el feriado no se cargó.');
        $this->assertStringNotContainsString('Feriado', $this->errorAlAgendar('2026-09-18'),
            'Ni el motivo del feriado sale en el mensaje.');
    }

    public function test_el_seeder_de_feriados_es_idempotente_y_no_pisa_lo_cargado_a_mano(): void
    {
        $this->seed(FeriadosChileSeeder::class);
        $cuantos = AgendaCierre::count();

        // Unas vacaciones cargadas por el jefe que caen justo sobre un feriado.
        $this->cierre('2026-12-25', '2027-01-05', ['motivo' => 'Vacaciones de fin de año']);

        $this->seed(FeriadosChileSeeder::class);

        $this->assertSame($cuantos + 1, AgendaCierre::count(), 'El seeder duplicó feriados al correr dos veces.');
        $this->assertSame('Vacaciones de fin de año',
            AgendaCierre::where('origen', AgendaCierre::ORIGEN_MANUAL)->sole()->motivo,
            'El seeder pisó un cierre cargado a mano.');
    }

    /**
     * Los feriados REGIONALES quedan afuera a propósito: la empresa atiende desde Talca y
     * cerrar por un feriado de Arica sería perder un día de trabajo por nada.
     */
    public function test_no_se_cargan_feriados_regionales(): void
    {
        $this->seed(FeriadosChileSeeder::class);

        foreach (['2026-06-07', '2026-08-20', '2027-06-07'] as $regional) {
            $this->assertSame(0, AgendaCierre::whereDate('fecha_desde', $regional)->count(),
                "Se cargó el feriado regional {$regional}, que no aplica a la empresa.");
        }
    }
}
