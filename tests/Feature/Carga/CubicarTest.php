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
 * CUBICAR: medir un bulto que no está en el catálogo y verlo mientras se define.
 *
 * Pedido del dueño (12-08-2026) mostrando el panel de EasyCargo: «un botón que diga
 * cubicar… uno puede ver una caja como va quedando de tamaño, cuántas unidades agregar y
 * los kilos que van haciendo».
 */
class CubicarTest extends TestCase
{
    use RefreshDatabase;

    private CamionSimulacion $camion;

    private TipoBulto $bolsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->camion = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35',
            'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1500, 'pasillo_cm' => 0, 'activo' => true,
        ]);
        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 3.75,
            'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true,
            'orientacion_fija' => true, 'activo' => true,
        ]);
    }

    private function pantalla(): string
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        return $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id, 'tipo_bulto_id' => $this->bolsa->id,
        ]))->assertOk()->getContent();
    }

    public function test_el_boton_de_cubicar_vive_en_el_menu_del_visor(): void
    {
        // La doctrina del 06-08: los controles van TODOS en el menú lateral, no sueltos
        // por la pantalla. Cada botón nuevo que se cuela en una esquina es el que empieza
        // a devolver la confusión que ese menú vino a resolver.
        $html = $this->pantalla();
        $desde = strpos($html, 'Herramientas');
        $menu = substr($html, $desde, strpos($html, '</aside>', $desde) - $desde);

        $this->assertStringContainsString('Cubicar', $menu, 'El botón de cubicar quedó fuera del menú.');
        $this->assertStringContainsString('Medir un bulto', $menu);
    }

    public function test_el_panel_pide_medidas_unidades_y_kilos(): void
    {
        $html = $this->pantalla();

        foreach (['Cubicar un bulto', 'Largo', 'Ancho', 'Alto', 'Unidades', 'Peso total', 'Agregar a la carga'] as $texto) {
            $this->assertStringContainsString($texto, $html, "Al panel de cubicaje le falta [{$texto}].");
        }
    }

    /**
     * NO HAY UN SEGUNDO MOTOR EN JAVASCRIPT.
     *
     * El panel hace la aritmética del BULTO (volumen y kilos: tres multiplicaciones
     * verificables de memoria) y nada más. «Cuántas entran» lo contesta el motor de
     * siempre cuando el bulto se agrega a la carga.
     *
     * El candado es estructural: el partial no puede ni siquiera VER las medidas del
     * camión, así que no hay forma de que calcule un cupo por su cuenta. Si algún día
     * alguien le pasa el vehículo «para mostrarlo al toque», esto se pone rojo — y ahí hay
     * que decidir a conciencia, porque dos motores que difieren dejan a la pantalla sin
     * saber a cuál creerle.
     */
    public function test_el_panel_no_calcula_cupos_por_su_cuenta(): void
    {
        $fuente = file_get_contents(resource_path('views/admin/carga/_cubicar.blade.php'));

        foreach (['$camion', '$escena', 'vehiculo'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $fuente,
                "El panel de cubicaje recibió [{$prohibido}]: con las medidas del camión a mano, el próximo paso es calcular el cupo en JS.");
        }
    }

    public function test_el_link_compartido_no_trae_el_cubicaje(): void
    {
        // Cubicar es ARMAR la carga; el link es para mirarla. Y el panel escribe en las
        // líneas, que del lado público no existen.
        $link = URL::temporarySignedRoute('publico.plan-carga', now()->addDays(PlanCargaPublicoController::DIAS_VIGENCIA), [
            'camion_id' => $this->camion->id, 'tipo_bulto_id' => $this->bolsa->id,
        ]);

        $html = $this->get($link)->assertOk()->getContent();

        $this->assertStringNotContainsString('Cubicar un bulto', $html);
        $this->assertStringNotContainsString('Medir un bulto', $html);
    }
}
