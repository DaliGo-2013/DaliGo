<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CUÁNDO EL VISOR ABRE CARGADO Y CUÁNDO VACÍO.
 *
 * Son DOS decisiones del dueño que parecen contradecirse y no:
 *
 *  · 05-08: el visor abre VACÍO — «no quiero que el camión esté contabilizado a cuánto
 *    tiene que llegar». Se probó abrir lleno y lo descartó. La nota en `carga3d.js` dice
 *    «no volver a cambiarlo sin preguntarle», y no se cambió.
 *  · 12-08: después de apretar «Aplicar al camión», que aparezca CARGADO — «no tener que
 *    apretar el botón de nuevo de cargar el camión para ver cómo queda».
 *
 * La diferencia no es de gusto: es si el usuario **venía de hacer algo** o recién llegó a
 * la pantalla. Por eso la señal viaja en la URL (`ver=todo`) y la manda solo el botón que
 * aplica un acomodo.
 */
class VisorArrancaLlenoTest extends TestCase
{
    use RefreshDatabase;

    private CamionSimulacion $camion;

    private TipoBulto $bolsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->camion = CamionSimulacion::create([
            'nombre' => 'Chevy 3', 'largo_cm' => 790, 'ancho_cm' => 220, 'alto_cm' => 230,
            'peso_max_kg' => 6430, 'pasillo_cm' => 0, 'activo' => true,
        ]);
        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 3.75,
            'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true,
            'orientacion_fija' => true, 'activo' => true,
        ]);
    }

    private function escena(array $extra = []): array
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        return $this->actingAs($vendedor)->get(route('admin.carga.index', $extra + [
            'camion_id' => $this->camion->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 100]],
        ]))->assertOk()->viewData('escena');
    }

    public function test_al_entrar_a_la_pantalla_abre_vacio(): void
    {
        $this->assertFalse($this->escena()['arranca_lleno'],
            'El visor abriría lleno al entrar: es la decisión del dueño del 05-08 y no se cambia sin preguntarle.');
    }

    public function test_al_volver_de_aplicar_un_acomodo_abre_cargado(): void
    {
        $this->assertTrue($this->escena(['ver' => 'todo'])['arranca_lleno'],
            'Después de aplicar el acomodo el camión sigue apareciendo vacío: hay que apretar «Todo» para ver lo que se acaba de acomodar.');
    }

    /**
     * Y la señal la manda el BOTÓN que aplica, no cualquier recálculo: si «Volver al
     * automático» también la mandara, volver a empezar dejaría el camión lleno, que es lo
     * contrario de empezar de nuevo.
     */
    public function test_solo_el_boton_de_aplicar_manda_la_senal(): void
    {
        $panel = file_get_contents(resource_path('views/admin/carga/_acomodo.blade.php'));

        $this->assertStringContainsString("u.searchParams.set('ver', 'todo')", $panel,
            'El botón «Aplicar al camión» dejó de pedir que el camión se vea cargado.');
        $this->assertStringContainsString("u.searchParams.delete('ver')", $panel,
            'Sin el delete, la señal queda pegada en la URL y el camión abriría lleno para siempre.');
    }
}
