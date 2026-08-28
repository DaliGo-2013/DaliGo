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
     * Y SE OFRECE SIEMPRE, también con una sola línea. Esto INVIERTE el candado del
     * 12-08 («con una sola línea no se ofrece: deja la carga vacía») por decisión del
     * dueño probando la Cabina (21-08, textual): «quiero la opción de eliminar o
     * borrar… quiero cargar lo que yo quiera sin previas». Lo que hacía peligroso el
     * vaciado ya no existe: el armador arranca vacío de fábrica, el estado vacío tiene
     * su cartel, y el botón de calcular se apaga solo sin líneas.
     */
    public function test_quitar_se_ofrece_tambien_con_una_sola_linea(): void
    {
        $cabecera = $this->cabeceraDeLaLinea($this->pantalla());

        $this->assertStringContainsString('quitar(i)', $cabecera);
        $this->assertStringNotContainsString('lineas.length > 1', $cabecera,
            'Volvió la condición que esconde el quitar con una sola línea: el dueño pidió lo contrario el 21-08.');

        // Y en la lista «En el camión» del panel de cubicar tampoco se esconde.
        $this->assertStringNotContainsString('x-show="lineas.length > 1"',
            file_get_contents(resource_path('views/admin/carga/_cubicar.blade.php')),
            'El quitar del panel de cubicar volvió a esconderse con una sola línea.');
    }

    /**
     * EL ARMADOR ARRANCA SIN PREVIAS (dueño 21-08: «salen siempre predeterminado los
     * bidones y no quiero, quiero cargar lo que yo quiera sin previas»). Antes se
     * sembraba una línea con el primer producto del catálogo × 100 — al abrir la
     * pestaña ya había una carga que nadie pidió. Ahora: cero líneas, un cartel que
     * dice el próximo paso, el selector de una línea nueva arranca en «Elegí un
     * producto…» y el botón de calcular se apaga mientras no haya nada que calcular.
     */
    public function test_el_armador_arranca_sin_previas(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $html = $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('lineas: [],', $html,
            'El armador volvió a arrancar con una línea precargada.');
        $this->assertStringContainsString('Elegí un producto…', $html);
        $this->assertStringContainsString('La carga arranca vacía', $html);
        $this->assertStringContainsString(':disabled="lineas.length === 0"', $html,
            'Calcular quedó activo sin líneas: mandaría un formulario vacío.');

        // Con líneas en la URL se respetan tal cual — el arranque vacío es solo
        // para quien llega sin nada.
        $this->pantalla();
        $conLineas = $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 100]],
        ]))->assertOk()->getContent();
        $this->assertStringContainsString('lineas: [{', $conLineas);
    }

    /**
     * LA LÍNEA SIN ELEGIR NO MUTA. Una línea que viaja con tipo vacío (el usuario
     * calculó sin terminar de elegir) el motor la descarta — eso ya era así—, pero al
     * re-sembrar el formulario un `(int) (tipo ?? 0)` la devolvía convertida en BULTO
     * A MEDIDA (0 es el centinela de a-medida): el formulario de medidas aparecía
     * donde el usuario había dejado un selector sin elegir. Es el «?? 0 sobre un dato
     * que se conserva» de la bitácora [2026-08-20].
     */
    public function test_una_linea_sin_elegir_no_vuelve_convertida_en_bulto_a_medida(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $res = $this->actingAs($vendedor)->get(route('admin.carga.index', [
            'camion_id' => $this->camion->id,
            'lineas' => [
                ['tipo' => '', 'cantidad' => 10],
                ['tipo' => $this->bolsa->id, 'cantidad' => 50],
            ],
        ]))->assertOk();

        $sel = $res->viewData('lineasSel')->values();
        $this->assertSame('', $sel[0]['tipo'], 'La línea sin elegir volvió como otra cosa (¿bulto a medida?).');
        $this->assertSame($this->bolsa->id, $sel[1]['tipo']);

        // Y el cálculo solo contó la línea real: la vacía se descarta sin reclamar.
        $this->assertCount(1, $res->viewData('mixta')['lineas']);
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
