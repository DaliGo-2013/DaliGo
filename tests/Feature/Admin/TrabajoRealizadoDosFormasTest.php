<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\TiempoReparacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * «TRABAJO REALIZADO»: LAS DOS FORMAS A LA VEZ (dueño, 20-08-2026: «dejá las dos
 * opciones, respuesta manual y la opción de respuesta automática, o sea para
 * completar con clic»).
 *
 * Antes eran EXCLUYENTES: un `<select>` con las respuestas fijas y, para escribir,
 * había que elegir «Otro — lo escribo yo» y recién ahí aparecía el campo. O sea que
 * ajustarle una palabra a una respuesta de la lista obligaba a re-escribirla entera
 * a mano.
 *
 * Ahora la lista COMPLETA el texto con un clic y el texto queda editable. El campo
 * de texto es la única respuesta; la lista es un rellenador que no viaja al
 * servidor.
 *
 * EL CONTRATO CON EL SERVIDOR NO CAMBIÓ, y eso es deliberado: sigue llegando
 * `trabajo_realizado` = centinela + `trabajo_realizado_otro` = el texto, así que la
 * validación del controlador, su colapso de espacios (esto se pega desde WhatsApp) y
 * su tope de 191 —el ancho de la columna donde la cotización guarda su snapshot—
 * siguen aplicando sin haber tocado una línea de PHP.
 */
class TrabajoRealizadoDosFormasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    private function orden(): OrdenServicio
    {
        return OrdenServicio::factory()->create([
            'facturacion' => 'reparacion',
            'estado' => 'recibido',
            'trabajo_realizado' => null,
        ]);
    }

    /**
     * Guarda el parte como lo manda el FORMULARIO nuevo: el centinela viaja en el
     * hidden y el texto en el textarea.
     */
    private function guardar(OrdenServicio $orden, ?string $texto)
    {
        return $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'cotizacion',
                'trabajo_realizado' => $texto === null ? '' : OrdenServicio::TRABAJO_OTRO,
                'trabajo_realizado_otro' => $texto,
            ]);
    }

    // --- Las dos formas conviven en la pantalla ------------------------------

    public function test_la_pantalla_ofrece_la_lista_y_el_texto_al_mismo_tiempo(): void
    {
        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $this->orden()))
            ->assertOk()
            ->getContent();

        // El texto SIEMPRE está (ya no vive detrás de elegir «Otro»)...
        $this->assertStringContainsString('name="trabajo_realizado_otro"', $html);
        // ...y la lista sigue estando para completarlo con un clic.
        $this->assertStringContainsString('id="trabajo_realizado_lista"', $html);
        $this->assertStringContainsString('Elige para completar', $html);

        // La lista NO viaja al servidor: lo que se guarda es el texto. Si volviera a
        // tener `name`, el servidor recibiría dos respuestas y ganaría la que no se
        // ve en el campo.
        $this->assertStringNotContainsString('name="trabajo_realizado_lista"', $html);

        // Y ya no se le pide al técnico elegir «Otro» para poder escribir.
        $this->assertStringNotContainsString('Otro — lo escribo yo', $html);
    }

    // --- Respuesta automática: se elige con un clic --------------------------

    /**
     * Una respuesta de la lista, sin tocarla, se guarda TAL CUAL — y eso es lo que
     * mantiene su tiempo estándar, porque la mano de obra se busca por el texto
     * exacto. Es el camino que decide plata.
     */
    public function test_una_respuesta_de_la_lista_se_guarda_exacta_y_conserva_su_tiempo_estandar(): void
    {
        $respuesta = collect(config('servicio_tecnico.respuestas_trabajo'))->flatten()->first();
        $this->assertNotNull($respuesta, 'La lista de respuestas del catálogo está vacía.');

        TiempoReparacion::create(['trabajo' => $respuesta, 'horas' => 1.5, 'activo' => true]);

        $orden = $this->orden();
        $this->guardar($orden, $respuesta)->assertSessionHasNoErrors();

        $this->assertSame($respuesta, $orden->fresh()->trabajo_realizado);
        $this->assertSame(1.5, (float) TiempoReparacion::horasDe($respuesta));
    }

    // --- Respuesta manual: se escribe, o se ajusta la de la lista ------------

    public function test_un_texto_propio_se_guarda(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, 'Cambio de bomba y limpieza de circuito — funciona normal')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'Cambio de bomba y limpieza de circuito — funciona normal',
            $orden->fresh()->trabajo_realizado
        );
    }

    /**
     * EL CASO QUE ANTES NO EXISTÍA, y es el que pidió el dueño: tomar una respuesta
     * de la lista y ajustarle algo. Antes había que elegir «Otro» y re-escribirla
     * entera.
     */
    public function test_una_respuesta_de_la_lista_ajustada_se_guarda_con_el_ajuste(): void
    {
        $respuesta = (string) collect(config('servicio_tecnico.respuestas_trabajo'))->flatten()->first();
        $orden = $this->orden();

        $this->guardar($orden, $respuesta.' y se limpió el filtro')->assertSessionHasNoErrors();

        $this->assertSame($respuesta.' y se limpió el filtro', $orden->fresh()->trabajo_realizado);
    }

    // --- Lo que el contrato intacto sigue protegiendo ------------------------

    /** Sin respuesta todavía: el parte se guarda igual (no es un campo obligatorio). */
    public function test_se_puede_guardar_el_parte_sin_respuesta(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, null)->assertSessionHasNoErrors();

        $this->assertNull($orden->fresh()->trabajo_realizado);
    }

    /**
     * El texto se pega desde WhatsApp y llega con saltos de línea adentro. El
     * colapso de espacios del controlador sigue aplicando: ahora TODA respuesta pasa
     * por ese camino, también las de la lista.
     */
    public function test_el_texto_pegado_con_saltos_de_linea_se_normaliza(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, "Cambio de caldera\n   y prueba de\ttemperatura")
            ->assertSessionHasNoErrors();

        $this->assertSame('Cambio de caldera y prueba de temperatura', $orden->fresh()->trabajo_realizado);
    }

    public function test_un_texto_demasiado_corto_o_demasiado_largo_se_rechaza(): void
    {
        $orden = $this->orden();

        $this->guardar($orden, 'ok')->assertSessionHasErrors('trabajo_realizado_otro');
        $this->assertNull($orden->fresh()->trabajo_realizado);

        // El tope es el ancho de la columna donde la cotización guarda su snapshot:
        // pasarlo revienta al ENVIAR la cotización, lejos de donde se escribió.
        $this->guardar($orden, str_repeat('a', OrdenServicio::TRABAJO_MAX + 1))
            ->assertSessionHasErrors('trabajo_realizado_otro');
        $this->assertNull($orden->fresh()->trabajo_realizado);
    }

    /**
     * Un trabajo histórico fuera de la lista (órdenes viejas con texto libre) sigue
     * apareciendo en el campo para poder corregirlo — que era el motivo original de
     * que el texto fuera editable.
     */
    public function test_un_trabajo_historico_se_puede_seguir_editando(): void
    {
        $historico = 'se reparo con un truco especial que no esta en la lista';
        $orden = OrdenServicio::factory()->create(['trabajo_realizado' => $historico]);

        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertSee($historico);
    }
}
