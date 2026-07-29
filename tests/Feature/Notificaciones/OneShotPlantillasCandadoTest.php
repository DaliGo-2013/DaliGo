<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Configuracion;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use ReflectionObject;
use Tests\TestCase;

/**
 * Candado del patrón de ENTREGA de plantillas (dictado v29, deuda anotada por
 * el Director al integrar NOTIF-1).
 *
 * El patrón: `ConfiguracionSeeder` usa `firstOrCreate`, que JAMÁS pisa una clave
 * ya sembrada en producción, así que un texto nuevo del seeder viaja además en
 * una migración de datos one-shot que solo actualiza si el valor vigente es
 * EXACTAMENTE el texto anterior (respetando ediciones desde la UI).
 *
 * Es correcto pero FRÁGIL por diseño: si alguien edita el texto en el seeder y
 * no actualiza el par viejo/nuevo de la migración, la one-shot se vuelve un
 * **no-op silencioso** — no falla, no avisa, simplemente deja de entregar el
 * texto a producción. Este candado ata las dos puntas: para cada clave que una
 * one-shot toca, su valor NUEVO debe ser idéntico al que siembra el seeder hoy.
 *
 * Cubre las DOS one-shot vivas (la de aprobaciones y la de notificaciones
 * internas de Marcos) y cualquiera futura que siga el patrón: al agregarla,
 * súmala al proveedor de datos de abajo.
 */
class OneShotPlantillasCandadoTest extends TestCase
{
    use RefreshDatabase;

    private const AYUDA = 'La one-shot quedaría en NO-OP SILENCIOSO: cambiaste el texto en '
        .'ConfiguracionSeeder sin actualizar el par viejo/nuevo de la migración, así que en '
        .'producción (donde firstOrCreate no pisa) el texto nuevo no se entrega y nada falla.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConfiguracionSeeder::class);
    }

    /**
     * One-shot de APROBACIONES (2026_07_22_100000): reemplaza el JSON completo
     * (asunto + cuerpo), así que se compara el arreglo entero.
     */
    public function test_la_one_shot_de_aprobaciones_calza_con_el_seeder(): void
    {
        $migracion = require database_path(
            'migrations/2026_07_22_100000_actualiza_plantillas_aprobacion_notif1.php'
        );
        $plantillas = (new ReflectionObject($migracion))->getConstant('PLANTILLAS');

        $this->assertNotEmpty($plantillas, 'No se pudo leer PLANTILLAS de la one-shot de aprobaciones.');

        foreach ($plantillas as $clave => [$viejo, $nuevo]) {
            $this->assertSame($nuevo, Configuracion::get($clave),
                "[{$clave}] el valor NUEVO de la one-shot ya no es el que siembra el seeder. ".self::AYUDA);

            // El par tiene que ser un cambio real: si viejo == nuevo, la
            // migración no entrega nada y su existencia engaña.
            $this->assertNotSame($viejo, $nuevo,
                "[{$clave}] el par viejo/nuevo de la one-shot es idéntico: no entrega ningún cambio.");
        }
    }

    /**
     * One-shot de NOTIFICACIONES INTERNAS (2026_07_22_180000, de Marcos):
     * cambia SOLO el `cuerpo` y conserva el `asunto`, así que se compara esa
     * clave del JSON.
     */
    public function test_la_one_shot_de_notificaciones_internas_calza_con_el_seeder(): void
    {
        $migracion = require database_path(
            'migrations/2026_07_22_180000_enriquecer_plantillas_notificaciones_internas.php'
        );
        $metodo = new ReflectionMethod($migracion, 'plantillas');
        $metodo->setAccessible(true);
        $plantillas = $metodo->invoke($migracion);

        $this->assertNotEmpty($plantillas, 'No se pudo leer plantillas() de la one-shot de internas.');

        foreach ($plantillas as $clave => $cuerpos) {
            $sembrada = Configuracion::get($clave);

            $this->assertIsArray($sembrada, "[{$clave}] no está sembrada como JSON en el seeder.");
            $this->assertSame($cuerpos['new'], $sembrada['cuerpo'] ?? null,
                "[{$clave}] el cuerpo NUEVO de la one-shot ya no es el que siembra el seeder. ".self::AYUDA);
            $this->assertNotSame($cuerpos['old'], $cuerpos['new'],
                "[{$clave}] el par old/new de la one-shot es idéntico: no entrega ningún cambio.");
        }
    }
}
