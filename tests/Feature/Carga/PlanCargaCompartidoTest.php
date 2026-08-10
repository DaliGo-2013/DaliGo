<?php

namespace Tests\Feature\Carga;

use App\Http\Controllers\Publico\PlanCargaPublicoController;
use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        foreach (['Plan de carga (Excel)', 'Copiar link para compartir', 'Importar de Excel', 'Camiones'] as $control) {
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
}
