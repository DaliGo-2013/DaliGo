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

    public function test_cubicar_es_una_pestana_principal_y_no_una_herramienta_escondida(): void
    {
        // CAMBIÓ DE LUGAR, NO DE REGLA. Vivía en el menú lateral del visor, dentro de la
        // hoja «Herramientas», por la doctrina del 06-08 (los controles van todos en el
        // menú y no sueltos por la pantalla). El dueño lo subió a pestaña el 21-08:
        // *«quiero dejar como una de las opciones principales cubicar»* — a dos clics
        // dentro de una hoja había que SABER que existía para encontrarlo, y medir un
        // bulto que no está en el catálogo es de lo primero que hace alguien con una
        // carga nueva. La doctrina del menú sigue en pie para todo lo demás; lo vigila
        // `SimuladorCargaMixtaPantallaTest`.
        $html = $this->pantalla();

        // Es una pestaña de verdad: el mismo `role="tab"` y el mismo `modo` que las otras
        // dos, no un botón que se parece a una pestaña.
        $this->assertStringContainsString('@click="modo = \'cubicar\'"', $html,
            'Cubicar dejó de ser una pestaña.');
        $this->assertStringContainsString('medir un bulto', $html);

        // Y NO quedó además el botón viejo en el menú: dos entradas al mismo panel serían
        // dos estados independientes, y el que se abre de un lado no se cierra del otro.
        $desde = strpos($html, 'Herramientas');
        $menu = substr($html, $desde, strpos($html, '</aside>', $desde) - $desde);
        $this->assertStringNotContainsString('Medir un bulto', $menu,
            'Quedó el botón viejo en el menú además de la pestaña.');
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

    /**
     * AGREGAR NO DEJA LA PANTALLA VACÍA (reclamo del dueño 12-08, textual: «le doy clic y se
     * sale todo y me deja la interfaz sin nada»).
     *
     * El cálculo vive en el servidor —un solo motor— así que agregar recarga la página: es
     * la única forma de que el camión que se ve sea el que el motor verificó. Lo que estaba
     * mal era volver con el panel CERRADO. El panel manda `cubicar=1` en el formulario y la
     * página vuelve con el cubicaje abierto, la lista de lo que ya subió a la vista y el
     * próximo bulto listo para tipear.
     */
    public function test_al_volver_de_agregar_el_panel_sigue_abierto(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $html = $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id,
            'cubicar' => 1,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 50]],
        ]))->assertOk()->getContent();

        // La página arranca EN la pestaña Cubicar. Desde el 21-08 el estado no es un
        // `cubicar` del visor sino el `modo` de la página —el mismo que eligen las
        // pestañas—, así que la regla se lee en la semilla del `x-data`.
        $this->assertStringContainsString("modo: 'cubicar'", $html,
            'La página volvió en otra pestaña: hay que buscar el cubicaje de nuevo.');
        // …y sin el parámetro arranca en la carga, que es la pestaña de trabajo.
        $sinParam = $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 50]],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString("modo: 'mixta'", $sinParam);
        $this->assertStringNotContainsString("modo: 'cubicar'", $sinParam);
    }

    public function test_el_panel_lista_lo_que_ya_va_en_el_camion(): void
    {
        // La lista que pidió para «ir agregando de a uno»: sale del mismo estado que manda
        // el formulario, así que no puede desincronizarse de lo que calculó el motor.
        $html = $this->pantalla();

        $this->assertStringContainsString('En el camión', $html);
        $this->assertStringContainsString('Cubicá el siguiente y volvé a agregar', $html);
    }

    /**
     * UN BULTO AJENO AL CATÁLOGO SE DESCRIBE CON SU NOMBRE Y SU PESO.
     *
     * Pedido del dueño (12-08): «si un producto no existe, para no ir hasta el catálogo y
     * crearlo, que se pueda poner el peso y un nombre aunque sea para describirlo cuando
     * esté cargado en el camión — a veces (muy pocas) se cargan cosas que no son
     * específicamente productos».
     *
     * No hace falta nada nuevo: la línea a medida ya existe y es DESCARTABLE a propósito
     * (decisión del 07-08) — vive en esta simulación y no siembra el catálogo, que es de
     * donde salen los cupos que se le prometen a un cliente. Lo que este candado fija es
     * que el nombre tipeado LLEGUE a todos los lugares donde se lee la carga, porque un
     * bulto sin nombre en el dibujo es una caja anónima que nadie sabe qué es.
     */
    public function test_un_bulto_ajeno_al_catalogo_viaja_con_su_nombre(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $respuesta = $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id,
            'lineas' => [
                ['tipo' => $this->bolsa->id, 'cantidad' => 50],
                [
                    // `tipo => 0` es LO QUE MANDA EL FORMULARIO para un bulto a medida (la
                    // opción «— Bulto a medida —» del <select>, contrato desde el 07-08). Se
                    // manda así a propósito: la validación lo rechazaba con «el campo
                    // seleccionado no es válido» y la línea NUNCA se agregaba, así que la
                    // función estuvo muerta desde que existe. Ningún test mandaba el 0.
                    'tipo' => 0, 'cantidad' => 2,
                    'medida_nombre' => 'Motor de repuesto (jaula soldada)',
                    'medida_largo' => 120, 'medida_ancho' => 100, 'medida_alto' => 80,
                    'medida_peso' => 55,
                ],
            ],
        ]))->assertOk();

        // 1. En el DIBUJO: el rótulo de su bloque.
        $escena = $respuesta->viewData('escena');
        $nombres = array_column($escena['bloques'], 'nombre');
        $this->assertContains('Motor de repuesto (jaula soldada)', $nombres,
            'El bulto a medida llegó al lienzo sin nombre: en el dibujo es una caja anónima.');

        // 2. En la PANTALLA: el detalle producto por producto y el panel de cubicaje.
        $respuesta->assertSee('Motor de repuesto (jaula soldada)');

        // 3. Y su PESO entra en la cuenta del camión, que es la otra mitad del pedido:
        // 2 × 55 kg del bulto ajeno + 10 bolsas × 3,75 kg de los 50 botellones.
        $mixta = $respuesta->viewData('mixta');
        $this->assertSame(147.5, $mixta['resultado']['peso_kg'],
            'Los 55 kg × 2 del bulto ajeno no se sumaron al peso de la carga.');
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
