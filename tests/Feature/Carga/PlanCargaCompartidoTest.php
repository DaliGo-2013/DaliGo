<?php

namespace Tests\Feature\Carga;

use App\Http\Controllers\Publico\PlanCargaPublicoController;
use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Link compartible del plan de carga en 3D (pedido del dueño 10-08-2026, mirando
 * el de EasyCargo): mandarle al cliente o al conductor una URL con el dibujo, sin
 * darle una cuenta.
 *
 * ES LA ÚNICA PANTALLA DEL SIMULADOR QUE SE SIRVE SIN LOGIN, así que el grueso de
 * estos candados no es que funcione — es que no se convierta en una puerta:
 * firmada, con vencimiento, de solo lectura, y sin los controles que navegan
 * hacia adentro de la app.
 */
class PlanCargaCompartidoTest extends TestCase
{
    use RefreshDatabase;

    private CamionSimulacion $hd35;

    private TipoBulto $bolsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->hd35 = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35',
            'largo_cm' => 430, 'ancho_cm' => 204, 'alto_cm' => 220,
            'peso_max_kg' => 1500, 'pasillo_cm' => 0, 'activo' => true,
        ]);
        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 5,
            'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => true, 'activo' => true,
        ]);
    }

    private function link(array $params = []): string
    {
        return URL::temporarySignedRoute(
            'publico.plan-carga',
            now()->addDays(PlanCargaPublicoController::DIAS_VIGENCIA),
            $params + ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id],
        );
    }

    // --- Que sirva ----------------------------------------------------------

    public function test_el_link_firmado_abre_el_plan_sin_login(): void
    {
        $this->get($this->link())
            ->assertOk()
            ->assertSee('Plan de carga')
            ->assertSee('Hyundai HD35')
            ->assertSee('420');   // el cupo verificado, en palabras
    }

    // --- Que no sea una puerta ---------------------------------------------

    public function test_sin_firma_no_entra(): void
    {
        // Es LA prueba de que el link no se puede fabricar a mano.
        $this->get(route('publico.plan-carga', [
            'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id,
        ]))->assertForbidden();
    }

    public function test_un_link_retocado_deja_de_valer(): void
    {
        // Cambiar un parámetro a mano invalida la firma: nadie puede transformar el
        // link de un cliente en otra simulación.
        $manipulado = $this->link().'&apilado=12';

        $this->get($manipulado)->assertForbidden();
    }

    public function test_el_link_vence(): void
    {
        // Un link eterno es una filtración esperando su turno.
        $link = $this->link();

        $this->travel(PlanCargaPublicoController::DIAS_VIGENCIA + 1)->days();

        $this->get($link)->assertForbidden();
    }

    /**
     * La página pública NO trae los controles que navegan hacia adentro.
     *
     * Rebotarían igual por permiso, pero ofrecerle al cliente botones que no puede
     * usar —y que le cuentan qué más hay en el sistema— no aporta nada.
     */
    public function test_no_ofrece_los_controles_internos(): void
    {
        $html = $this->get($this->link())->assertOk()->getContent();

        // «Comparar los» es el title del chip de la comparativa (Cabina, 21-08). Antes
        // acá decía «Camiones» —el header de la sección vieja—, una cadena que la
        // pantalla ya no produce NI en la variante interna: un assert negativo de algo
        // que no puede existir pasa siempre y no vigila nada (bitácora [2026-08-20]).
        foreach (['Plan de carga (Excel)', 'Copiar link para compartir', 'Importar de Excel', 'Comparar los'] as $control) {
            $this->assertStringNotContainsString($control, $html,
                "La página pública ofrece [{$control}], que es un control interno.");
        }
        // Y no hay rutas de admin escritas en la página.
        $this->assertStringNotContainsString('/admin/carga', $html);
    }

    /** Lo que SÍ tiene: el visor y sus controles de mirar. */
    public function test_conserva_el_visor_y_los_controles_de_mirar(): void
    {
        $html = $this->get($this->link())->assertOk()->getContent();

        foreach (['carga3d', 'carga3dVista3d', 'carga3dSuma1', 'carga3dBarra'] as $control) {
            $this->assertStringContainsString('id="'.$control.'"', $html,
                "Al plan compartido le falta [{$control}]: es para mirar el dibujo.");
        }
    }

    public function test_dice_que_es_una_referencia_y_cuando_vence(): void
    {
        // Afuera de la app, un número sin contexto se lee como una promesa.
        $this->get($this->link())
            ->assertOk()
            ->assertSee('no un compromiso de despacho', false)
            ->assertSee('Este enlace vence');
    }

    // --- El botón, del lado de adentro --------------------------------------

    public function test_el_boton_de_copiar_esta_en_el_menu_del_visor(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $html = $this->actingAs($vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id,
            ]))
            ->assertOk()->getContent();

        $desde = strpos($html, 'Herramientas');
        $menu = substr($html, $desde, strpos($html, '</aside>', $desde) - $desde);

        $this->assertStringContainsString('Copiar link para compartir', $menu,
            'El botón de compartir quedó fuera del menú lateral.');
        // Y el link que copia es uno FIRMADO, no la URL pelada.
        $this->assertStringContainsString('signature=', $menu,
            'El link que se copia no está firmado.');
    }

    /**
     * EL DIBUJO LLEGA AL LIENZO. Es el candado del defecto del 11-08: la página
     * compartida traía el visor y sus controles, pero NO el <script> con la escena, y
     * `montarCarga3d` (app.js) sale sin hacer nada si no lo encuentra. O sea: el link
     * abría un recuadro VACÍO desde el día que se publicó, y lo único que se comparte
     * acá es el dibujo. Ninguno de los otros candados lo vio porque todos preguntan por
     * texto, y el texto estaba bien.
     *
     * Se mira contra la pantalla INTERNA a propósito: las dos tienen que mandar lo
     * mismo, y así el test no se puede satisfacer con un `<script>` vacío.
     */
    public function test_la_pantalla_compartida_manda_la_escena_al_lienzo(): void
    {
        $publico = $this->get($this->link())->assertOk()->getContent();

        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $interno = $this->actingAs($vendedor)
            ->get(route('admin.carga.index', [
                'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id,
            ]))->assertOk()->getContent();

        foreach (['la pantalla compartida' => $publico, 'la pantalla interna' => $interno] as $donde => $html) {
            $this->assertStringContainsString('id="carga3d-datos"', $html,
                "Sin el <script> de la escena, el lienzo de {$donde} queda en blanco.");
            // Y lleva la escena de verdad: el nombre del camión viaja dentro del JSON.
            $desde = strpos($html, 'id="carga3d-datos"');
            $json = substr($html, $desde, strpos($html, '</script>', $desde) - $desde);
            $this->assertStringContainsString('Hyundai HD35', $json,
                "El JSON de la escena de {$donde} no trae el vehículo.");
        }
    }

    /**
     * EL CARD SE ENSANCHA DE VERDAD. Segundo defecto del 11-08, independiente del
     * anterior y con el mismo resultado en pantalla: el camión aplastado.
     *
     * `GuestLayout` no declaraba la propiedad `$ancho`, así que Blade trataba
     * `ancho="listado"` como un ATRIBUTO HTML suelto, la variable nunca llegaba al layout
     * y su `?? 'formulario'` la resolvía al default **en silencio** — justo lo que el
     * `throw_unless` de al lado decía evitar. El plan compartido salía en un card de
     * 448 px con el visor 3D adentro.
     *
     * Se mira la CLASE y no la existencia del token: la versión rota tenía el token, el
     * mapa y hasta el guard, y aun así rendereaba el ancho equivocado.
     */
    public function test_el_plan_compartido_se_muestra_en_el_card_ancho(): void
    {
        $html = $this->get($this->link())->assertOk()->getContent();

        $this->assertStringContainsString('max-w-6xl', $html,
            'El plan compartido salió con el ancho de formulario: el visor 3D no se puede mirar en 448 px.');
        $this->assertStringNotContainsString('sm:max-w-md', $html,
            'Quedó el card angosto del login.');

        // Y el ancho angosto sigue siendo el default para el resto de lo público: esto no
        // puede haber ensanchado el login de paso.
        $login = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('sm:max-w-md', $login);
        $this->assertStringNotContainsString('max-w-6xl', $login);
    }

    // --- Dos vistas del mismo link ------------------------------------------

    /**
     * EL CLIENTE MIRA, JEFATURA EDITA (pedido del dueño 11-08).
     *
     * La diferencia la hace QUIÉN abre, no la URL, y es lo que mantiene el link seguro:
     * un segundo link «editable» sería una puerta al simulador sin login para cualquiera
     * que tenga la dirección, y tiraría abajo todo lo que protegen los tests de arriba.
     */
    public function test_quien_no_tiene_permiso_solo_mira(): void
    {
        // (1) El cliente, sin cuenta: la página de siempre, sin atajo a la app.
        $html = $this->get($this->link())->assertOk()->getContent();
        $this->assertStringNotContainsString('Abrir en el simulador', $html);
        $this->assertStringNotContainsString('Así lo ve el cliente', $html);

        // (2) CON cuenta pero SIN el permiso — un técnico, por ejemplo. Tampoco: el botón
        // lo mandaría a un 403, que es peor que no ofrecerlo. Se pregunta por el PERMISO,
        // no por estar logueado.
        $sinPermiso = User::factory()->create();
        $html = $this->actingAs($sinPermiso)->get($this->link())->assertOk()->getContent();
        $this->assertStringNotContainsString('Abrir en el simulador', $html);
    }

    public function test_quien_puede_simular_ve_el_atajo_para_editarlo(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $r = $this->actingAs($vendedor)->get($this->link())->assertOk();

        $r->assertSee('Así lo ve el cliente');
        $r->assertSee('Abrir en el simulador para editar');
        $this->assertTrue($r->viewData('puedeEditar'));

        // El atajo abre el MISMO escenario: sin los parámetros que se llevan el camión y
        // el producto, editar arrancaría de cero y el botón no serviría de nada.
        $destino = $r->viewData('urlEditar');
        $this->assertStringContainsString(route('admin.carga.index'), $destino);
        $this->assertStringContainsString('camion_id='.$this->hd35->id, $destino);
        $this->assertStringContainsString('tipo_bulto_id='.$this->bolsa->id, $destino);

        // Y NO arrastra la firma: pegada a una URL interna no sirve para nada y, en un
        // historial o un log compartido, es el secreto del link viajando de más.
        $this->assertStringNotContainsString('signature=', $destino);
        $this->assertStringNotContainsString('expires=', $destino);
    }

    public function test_el_atajo_lleva_a_una_ruta_que_sigue_pidiendo_permiso(): void
    {
        // Lo que hace que ofrecer el atajo sea seguro: el destino NO es público. Si
        // alguien copia esa URL y se la manda al cliente, rebota igual.
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $destino = $this->actingAs($vendedor)->get($this->link())->assertOk()->viewData('urlEditar');

        // `actingAs` deja la sesión puesta para el resto del test, así que sin esto la
        // petición «de invitado» iría autenticada y el candado pasaría sin probar nada.
        Auth::logout();
        $this->get($destino)->assertRedirect(route('login'));

        $ajeno = User::factory()->create();
        $this->actingAs($ajeno)->get($destino)->assertRedirect(route('dashboard'));
    }

    /**
     * El cartel de «No cabe todo» llega al link, pero SIN el lenguaje de adentro.
     *
     * Desde el 12-08 el veredicto vive en la franja de arriba del visor, que es
     * compartida por la pantalla interna y la pública. La versión interna dice «con eso
     * se negocia», que es una instrucción para el vendedor: del otro lado del link hay
     * un cliente o un conductor, y leerlo ahí suena a que se está calculando cuánto
     * apretarlo.
     */
    public function test_el_link_dice_que_no_cabe_pero_no_habla_de_negociar(): void
    {
        // 600 botellones son 120 bolsas y en el HD35 entran 84.
        $res = $this->get($this->link(['lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 600]]]));

        $res->assertOk()
            ->assertSee('No cabe todo')
            ->assertSee('Queda carga afuera')
            ->assertDontSee('se negocia');

        // Y una sola vez: la tabla de abajo ya no repite el veredicto.
        $this->assertSame(1, substr_count($res->getContent(), 'No cabe todo'));
    }
}
