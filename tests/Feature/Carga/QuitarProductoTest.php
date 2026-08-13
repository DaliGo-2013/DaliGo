<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SACAR UN PRODUCTO DE LA CARGA TIENE QUE VERSE.
 *
 * Reclamo del dueño (12-08-2026, textual): «quiero un botón o una opción para quitar
 * productos porque siempre comienza la opción con bidones pero no encuentro ninguna opción
 * para quitar o eliminar».
 *
 * El botón EXISTÍA. Estaba adentro de la tarjeta del producto, junto a «Duplicar», así que
 * para descubrir que se podía sacar una línea había que abrirla primero. Es la misma
 * lección del pallet enterrado en un desplegable (10-08): una función que no se ve, no
 * existe. Por eso estos candados no miran si `quitar` está definido —lo estaba— sino si el
 * control está en la parte de la pantalla que SIEMPRE se ve.
 *
 * Son dos lugares, porque son los dos donde se lee la lista de la carga:
 *   · la cabecera de cada producto en el formulario;
 *   · la lista «En el camión» del panel de cubicar, que es donde el dueño lo buscó.
 */
class QuitarProductoTest extends TestCase
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

    private function pantalla(): string
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        return $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 100]],
        ]))->assertOk()->getContent();
    }

    /**
     * La cabecera de la línea es lo que se ve con la tarjeta cerrada; el cuerpo, lo que
     * aparece al abrirla. El candado corta la pantalla justo en ese límite y exige el
     * `quitar` del lado de arriba. Si alguien lo vuelve a mover adentro del cuerpo
     * —«para no cargar la cabecera»— esto se pone rojo y el reclamo vuelve.
     */
    public function test_el_boton_de_quitar_esta_en_la_cabecera_y_no_solo_dentro_de_la_tarjeta(): void
    {
        $cabecera = $this->cabeceraDeLaLinea($this->pantalla());

        $this->assertStringContainsString('quitar(i)', $cabecera,
            'Quitar volvió a quedar solo dentro de la tarjeta: con el producto cerrado no hay forma de sacarlo.');
    }

    /**
     * Y no se ofrece con una sola línea: una carga sin ningún producto no es una carga —el
     * formulario no tendría qué calcular y el validador la rechazaría—, así que el botón
     * que la deja vacía sería un botón que solo sabe fallar.
     */
    public function test_con_un_solo_producto_no_se_ofrece_quitar(): void
    {
        $cabecera = $this->cabeceraDeLaLinea($this->pantalla());

        $this->assertStringContainsString('x-show="lineas.length > 1"', $cabecera,
            'El botón de quitar de la cabecera se ofrece con una sola línea: deja la carga vacía.');
    }

    /**
     * En la lista del panel de cubicar, que es donde el dueño fue a buscarlo: si ahí se
     * agrega de a un bulto, ahí se saca.
     */
    public function test_la_lista_del_panel_de_cubicar_deja_sacar_un_bulto(): void
    {
        $html = $this->pantalla();

        $this->assertStringContainsString('quitarDelCamion(i)', $html,
            'La lista «En el camión» no tiene con qué sacar un bulto: es la lista que el dueño mira mientras carga.');
    }

    /**
     * SACAR RECALCULA, igual que agregar. Si la lista se actualizara sola, la lista diría
     * una cosa y el camión dibujado otra: el dibujo es el último resultado del SERVIDOR, y
     * el servidor no se enteró de que la línea se fue.
     *
     * Y recalcula con `cubicar=1`, así que la página vuelve con el panel abierto — el
     * pedido del 12-08 de que «no salga todo» vale para sacar como valía para agregar.
     */
    public function test_sacar_desde_el_panel_recalcula_sin_cerrarlo(): void
    {
        $fuente = file_get_contents(resource_path('views/admin/carga/_cubicar.blade.php'));

        $this->assertMatchesRegularExpression('/quitarDelCamion\(i\)\s*\{[^}]*this\.quitar\(i\);[^}]*this\.recalcular\(\);/s', $fuente,
            'Sacar desde el panel dejó de usar el `quitar` del formulario o de recalcular: la lista y el camión se van a contradecir.');
        $this->assertStringContainsString("volver.name = 'cubicar'", $fuente,
            'El recálculo dejó de mandar `cubicar=1`: la página vuelve con el panel cerrado.');
    }

    /**
     * LA TRAMPA DEL NOMBRE. El x-data del panel TAPA el del formulario (por eso su
     * `agregar` conviven sin pisarse), así que un método llamado `quitar` acá adentro se
     * llamaría a sí mismo para siempre y el navegador se cuelga con la pila desbordada.
     * Cuesta un assert y se paga una sola vez.
     */
    public function test_el_panel_no_le_pone_quitar_a_su_propio_metodo(): void
    {
        $fuente = file_get_contents(resource_path('views/admin/carga/_cubicar.blade.php'));

        $this->assertDoesNotMatchRegularExpression('/^\s*quitar\s*\(/m', $fuente,
            'El panel definió su propio `quitar`: tapa al del formulario y se llama a sí mismo hasta desbordar la pila.');
    }

    /**
     * Corta la pantalla en el límite entre lo que se ve con la tarjeta cerrada y lo que
     * aparece al abrirla: desde el `@click` que despliega hasta el `x-show` del cuerpo.
     */
    private function cabeceraDeLaLinea(string $html): string
    {
        $abre = strpos($html, '@click="expandido = expandido === i ? null : i"');
        $this->assertNotFalse($abre, 'No se encontró la cabecera plegable de la línea: cambió el markup y este candado dejó de mirar lo que dice mirar.');

        $cierra = strpos($html, 'x-show="expandido === i"', $abre);
        $this->assertNotFalse($cierra, 'No se encontró el cuerpo desplegable de la línea.');

        $cabecera = substr($html, $abre, $cierra - $abre);

        // El corte tiene que ser de verdad: si el trozo se pasara de largo se comería el
        // cuerpo de la tarjeta —donde vive el «Quitar» viejo, al lado de «Duplicar»— y los
        // candados de arriba pasarían sin que se vea ningún botón con el producto cerrado.
        $this->assertStringNotContainsString('Duplicar', $cabecera,
            'El trozo llamado «cabecera» incluye el cuerpo de la tarjeta: así estos candados no prueban nada.');

        return $cabecera;
    }
}
