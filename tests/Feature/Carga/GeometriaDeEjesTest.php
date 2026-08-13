<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use Database\Seeders\CamionesSimulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA GEOMETRÍA DE LOS EJES TIENE QUE SER POSIBLE.
 *
 * El 13-08-2026 llegó del HINO «del frente de la caja al eje trasero: 499 cm» y no cerró con
 * los «435 cm entre ejes» del día anterior: `499 − 435 = +64` pondría el eje DELANTERO 64 cm
 * adentro de la caja de carga. En un camión cab-over la cabina va sobre el eje delantero, así
 * que ese eje está siempre ADELANTE de donde arranca la carga — en el Chevy 3, el único
 * medido con huincha, queda 58 cm adelante.
 *
 * No es un detalle de prolijidad: `RepartoPorEje` calcula con esa distancia como brazo de
 * palanca, y un eje delantero corrido hacia atrás le SACA kilos al trasero, que es justo el
 * que se pasa en la balanza. O sea que un par de números imposibles no rompe nada visible:
 * devuelve un verde tranquilizador sobre un camión multado.
 *
 * Por eso el candado no está en el servicio sino sobre los DATOS SEMBRADOS: el día que
 * alguien complete el entre ejes de un camión con un número que no puede ser, esto se pone
 * rojo antes de que el reparto salga a producción.
 */
class GeometriaDeEjesTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_todo_camion_medido_el_eje_delantero_queda_adelante_de_la_caja(): void
    {
        $this->seed(CamionesSimulacionSeeder::class);

        $medidos = CamionSimulacion::get()->filter(fn (CamionSimulacion $c) => $c->tieneEjes());

        $this->assertNotEmpty($medidos, 'Ningún camión tiene los dos datos: el candado no está mirando nada.');

        foreach ($medidos as $camion) {
            // EL SEMI ES OTRA CUENTA y queda afuera a propósito: ahí la carga apoya en el
            // TREN DE EJES y en la QUINTA RUEDA, que sí cae adentro de la huella del
            // contenedor. Si algún día se cargan esos dos números, este candado tiene que
            // dejarlos pasar en vez de declararlos imposibles.
            if ($camion->silueta === 'semirremolque') {
                continue;
            }

            $this->assertLessThan(0, $camion->ejeDelanteroCm(),
                "En «{$camion->nombre}» el eje delantero cae ADENTRO de la caja ({$camion->ejeDelanteroCm()} cm): "
                .'la cabina va sobre ese eje, así que los dos números no pueden ser los dos ciertos. '
                .'Ver docs/pendientes/01-camiones-y-ejes.md § B.');
        }
    }

    /**
     * Y la medida que sí llegó no se pierde por estar a medias: el 499 del HINO queda
     * sembrado aunque el reparto todavía no se muestre. Un dato de huincha que se borra
     * porque «no sirve solo» es un dato que hay que volver a ir a medir.
     */
    public function test_la_medida_del_hino_queda_guardada_aunque_falte_el_entre_ejes(): void
    {
        $this->seed(CamionesSimulacionSeeder::class);

        $hino = CamionSimulacion::where('nombre', 'HINO 500 (FC 1118)')->sole();

        $this->assertSame(499, $hino->eje_trasero_cm, 'Se perdió la medida del 13-08.');
        $this->assertNull($hino->entre_ejes_cm,
            'Se completó el entre ejes del HINO: si es el dato confirmado del padrón, este candado se actualiza a mano '
            .'—y el de arriba verifica que el par sea posible—. Si es el 435 del 12-08, no cierra: ver el seeder.');
        $this->assertFalse($hino->tieneEjes(), 'Con un solo dato no hay palanca: el reparto no se puede mostrar.');
    }
}
