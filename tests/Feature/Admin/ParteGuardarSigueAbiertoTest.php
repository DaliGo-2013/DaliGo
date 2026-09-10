<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GUARDAR EL PARTE NO CIERRA EL PARTE.
 *
 * Pedido del dueño (10-09-2026), señalando el botón «Guardar» del pie: «hay alguna chance de que
 * cuando uno apreta para guardar no te saque a la otra pantalla, sino que se guarde y quede en la
 * misma [pantalla] para luego hacer clic en el botón revisar y enviar cotización».
 *
 * La pantalla YA era la misma —el controlador redirige a `reparacion` desde el 20-08— pero eso no
 * alcanzaba: el parte se dibuja CERRADO por defecto, así que cada guardado devolvía el modo
 * lectura y se llevaba con él «Revisar y enviar cotización», que es un submit de ESE formulario.
 * Había que apretar «Editar» y bajar otra vez, y desde afuera eso se ve igual a haber cambiado de
 * pantalla.
 *
 * Los candados van sobre el HTML renderizado porque la suite de PHP no evalúa Alpine (bitácora
 * [2026-08-25]): lo único verificable desde acá es con qué estado NACE la pantalla.
 */
class ParteGuardarSigueAbiertoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    /** `estado` FIJO: la factory lo sortea y un test que mira el estado no puede depender del azar. */
    private function orden(): OrdenServicio
    {
        return OrdenServicio::factory()->create([
            'estado' => 'en_revision',
            'facturacion' => 'reparacion',
        ]);
    }

    /**
     * El atributo completo, con el `x-data="` incluido. La expresión suelta no sirve: `editando`
     * también aparece en los `x-on:click="editando = true"` de la pantalla, así que un assert de
     * la subcadena pasaría por el elemento equivocado (bitácora [2026-08-21]).
     */
    private function assertNaceAbierto(string $html, bool $abierto, string $porque): void
    {
        $this->assertStringContainsString(
            'x-data="{ editando: '.($abierto ? 'true' : 'false').' }"',
            $html,
            $porque,
        );
    }

    /**
     * El tag de un panel, hasta su primer `>`, con los espacios colapsados — porque el `x-cloak`
     * se emite con un `{{ }}` que deja el hueco cuando no corresponde. Los `@js()` del formulario
     * no pueden cortar el recorte: `Js::from` escapa `<` y `>` como secuencia unicode.
     */
    private function tag(string $html, string $marcador): string
    {
        $i = strpos($html, $marcador);
        $this->assertNotFalse($i, "No se encontró «{$marcador}» en la pantalla: el candado dejó de mirar algo.");

        return preg_replace('/\s+/', ' ', substr($html, $i, strpos($html, '>', $i) - $i));
    }

    /**
     * LA INVARIANTE ANTI-PARPADEO: de los dos paneles, el que nace con `x-cloak` es exactamente
     * el que NO se va a mostrar. Si el cloak estuviera en el que sí se muestra, el navegador
     * pintaría el otro hasta que Alpine arranque; y si no estuviera en ninguno, se verían los dos.
     */
    private function assertCloakEnElPanelQueNoSeVe(string $html, bool $abierto): void
    {
        $lectura = $this->tag($html, '<div x-show="!editando"');
        $formulario = $this->tag($html, '<form x-show="editando"');

        // Control positivo: el recorte del formulario llegó de verdad hasta sus atributos.
        $this->assertStringContainsString('id="reparacion-form"', $formulario);

        $this->assertSame($abierto, str_contains($lectura, 'x-cloak'),
            'El panel de lectura nace con el cloak equivocado: se va a ver el panel que no corresponde hasta que arranque Alpine.');
        $this->assertSame(! $abierto, str_contains($formulario, 'x-cloak'),
            'El formulario nace con el cloak equivocado: se va a ver el panel que no corresponde hasta que arranque Alpine.');
    }

    // ─────────────────────────────────────────── entrar a mirar: cerrado

    /** El otro lado del borde: entrar a la ficha no abre el formulario (se entra a leer). */
    public function test_al_entrar_al_parte_arranca_cerrado(): void
    {
        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $this->orden()))
            ->assertOk()
            ->getContent();

        $this->assertNaceAbierto($html, false, 'Entrar a mirar el parte no debería abrir el formulario de edición.');
        $this->assertCloakEnElPanelQueNoSeVe($html, false);
    }

    // ─────────────────────────────────────────── guardar: sigue abierto

    public function test_despues_de_guardar_el_parte_sigue_abierto(): void
    {
        $orden = $this->orden();

        $this->actingAs($this->tecnico())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), ['estado' => 'en_revision'])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertSessionHas('sigue_editando');
    }

    /** Y lo que importa de verdad: la pantalla a la que se vuelve trae el formulario abierto. */
    public function test_la_pantalla_a_la_que_se_vuelve_tras_guardar_trae_el_formulario_abierto(): void
    {
        $orden = $this->orden();

        $html = $this->actingAs($this->tecnico())
            ->followingRedirects()
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), ['estado' => 'en_revision'])
            ->assertOk()
            ->getContent();

        $this->assertNaceAbierto($html, true, 'Tras guardar, el parte volvió al modo lectura: «Revisar y enviar cotización» vive dentro del formulario y desaparece.');
        $this->assertCloakEnElPanelQueNoSeVe($html, true);
        // Y el técnico ve que se guardó, no solo el formulario abierto.
        $this->assertStringContainsString('actualizada', $html);
    }

    // ─────────────────────────────────────────── volver de la ventana previa

    /**
     * «Volver a editar» de la ventana de la carta cae en el FORMULARIO. La carta se revisa
     * justamente para corregir algo: caer en el modo lectura obligaba a apretar «Editar».
     */
    public function test_al_volver_de_la_vista_previa_el_parte_sigue_abierto(): void
    {
        $orden = $this->orden();

        $html = $this->actingAs($this->tecnico())
            ->followingRedirects()
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'cotizacion',
                'previsualizar' => '1',
            ])
            ->assertOk()
            ->getContent();

        $this->assertNaceAbierto($html, true, 'Al volver de la vista previa el parte quedó en modo lectura.');
    }

    // ─────────────────────────────────────────── lo que ya andaba

    /**
     * REGRESIÓN de lo que ya existía antes de este cambio: con errores de validación el
     * formulario tiene que quedar abierto, o el técnico no ve dónde está el error.
     */
    public function test_un_error_de_validacion_tambien_deja_el_parte_abierto(): void
    {
        $orden = $this->orden();

        $html = $this->actingAs($this->tecnico())
            ->from(route('admin.servicio-tecnico.reparacion', $orden))
            ->followingRedirects()
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), ['estado' => 'volando'])
            ->assertOk()
            ->getContent();

        $this->assertNaceAbierto($html, true, 'Con un error de validación el formulario tiene que quedar abierto: el error se marca adentro.');
        $this->assertCloakEnElPanelQueNoSeVe($html, true);
    }
}
