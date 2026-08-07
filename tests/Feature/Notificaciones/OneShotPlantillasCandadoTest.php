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
     * One-shots de NOTIFICACIONES INTERNAS: cambian SOLO el `cuerpo` y conservan
     * el `asunto`, así que se compara esa clave del JSON.
     *
     * ⚠️ Van EN ORDEN CRONOLÓGICO. Una misma plantilla puede ser tocada por varias
     * one-shots a lo largo del tiempo (la de 22-07 entregó A→B; la de 30-07, tras
     * la auditoría de la ruta de taller, entrega B→C), y entonces el invariante NO
     * es «el new de cada one-shot es lo que siembra el seeder» — eso solo lo puede
     * cumplir la ÚLTIMA de la cadena. El invariante real, que es el que este test
     * verifica ahora, tiene dos mitades:
     *
     *   1. La cadena está ENCADENADA: el `new` de una one-shot es el `old` de la
     *      siguiente que toca esa misma clave. Si se rompe, la segunda no encuentra
     *      el texto que espera en una BD ya migrada → no-op silencioso.
     *   2. El `new` de la ÚLTIMA one-shot de cada clave es lo que siembra el seeder
     *      hoy. Si se rompe, el texto del seeder nunca llega a producción.
     *
     * Al agregar una one-shot nueva, súmala AL FINAL de la lista.
     */
    public function test_la_cadena_de_one_shots_de_notificaciones_internas_calza_con_el_seeder(): void
    {
        $enOrden = [
            'migrations/2026_07_22_180000_enriquecer_plantillas_notificaciones_internas.php',
            'migrations/2026_07_30_100000_limpia_plantillas_taller_terreno_auditoria.php',
            // 06-08: suma {motivo} a cotizacion.respondida (el «¿por qué?» del cliente).
            'migrations/2026_08_06_140100_agrega_motivo_a_plantilla_cotizacion_respondida.php',
            // 07-08: cotizacion.autorizada deja de hablarle al técnico (aviso de plata).
            'migrations/2026_08_07_150200_saca_el_tecnico_de_la_plantilla_de_autorizacion.php',
        ];

        /** @var array<string, list<array{archivo: string, old: string, new: string}>> */
        $cadenas = [];

        foreach ($enOrden as $archivo) {
            $migracion = require database_path($archivo);
            $metodo = new ReflectionMethod($migracion, 'plantillas');
            $metodo->setAccessible(true);
            $plantillas = $metodo->invoke($migracion);

            $this->assertNotEmpty($plantillas, "No se pudo leer plantillas() de {$archivo}.");

            foreach ($plantillas as $clave => $cuerpos) {
                $this->assertNotSame($cuerpos['old'], $cuerpos['new'],
                    "[{$clave}] el par old/new de {$archivo} es idéntico: no entrega ningún cambio.");

                $cadenas[$clave][] = ['archivo' => $archivo, 'old' => $cuerpos['old'], 'new' => $cuerpos['new']];
            }
        }

        foreach ($cadenas as $clave => $eslabones) {
            // (1) Cada eslabón parte del texto que dejó el anterior.
            foreach ($eslabones as $i => $eslabon) {
                if ($i === 0) {
                    continue;
                }
                $this->assertSame($eslabones[$i - 1]['new'], $eslabon['old'],
                    "[{$clave}] la cadena de one-shots está rota: el `old` de {$eslabon['archivo']} no es el ".
                    "`new` que dejó {$eslabones[$i - 1]['archivo']}. En una BD ya migrada no encontrará ese ".
                    'texto y no entregará nada. '.self::AYUDA);
            }

            // (2) El último eslabón deja exactamente lo que siembra el seeder.
            $ultimo = end($eslabones);
            $sembrada = Configuracion::get($clave);

            $this->assertIsArray($sembrada, "[{$clave}] no está sembrada como JSON en el seeder.");
            $this->assertSame($ultimo['new'], $sembrada['cuerpo'] ?? null,
                "[{$clave}] el cuerpo NUEVO de la última one-shot ({$ultimo['archivo']}) ya no es el que ".
                'siembra el seeder. '.self::AYUDA);
        }
    }
}
