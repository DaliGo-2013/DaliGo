<?php

namespace Tests\Feature\Admin;

use App\Models\Bodega;
use App\Models\Sucursal;
use Database\Seeders\ClasificacionBodegasSeeder;
use Database\Seeders\SucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados del seeder de pre-carga D-003 (M04-F1, P-M04-10). La regla que
 * protege: el seeder corre EN CADA DEPLOY (DatabaseSeeder) y despues del
 * primer run manda la UI — una fila confirmada no se toca jamas.
 */
class ClasificacionBodegasSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Bodega espejada con el office_id REAL del catastro (evidencia 02-07). */
    private function bodega(int $officeId, string $nombre): Bodega
    {
        return Bodega::factory()->create(['bsale_office_id' => $officeId, 'nombre' => $nombre]);
    }

    public function test_aplica_el_veredicto_del_anexo_dos(): void
    {
        $this->seed(SucursalSeeder::class);
        $mirador = $this->bodega(4, 'MIRADOR');            // ✅ vive
        $certificaciones = $this->bodega(13, 'CERTIFICACIONES'); // ❌ muere
        $santaRosa = $this->bodega(10, 'BODEGA SANTA ROSA');     // ⏳ [B]
        $mermas = $this->bodega(16, 'BODEGA MERMAS');            // ✅ vive, transversal

        $this->seed(ClasificacionBodegasSeeder::class);

        $mirador->refresh();
        $this->assertSame(Sucursal::where('codigo', 'MIRADOR')->value('id'), $mirador->sucursal_id);
        $this->assertSame('fisica', $mirador->proposito);
        $this->assertTrue($mirador->en_operacion);
        $this->assertTrue($mirador->clasificacion_confirmada);

        $certificaciones->refresh();
        $this->assertSame('cerrada', $certificaciones->proposito);
        $this->assertFalse($certificaciones->en_operacion, 'Una muerta de D-003 queda fuera de operación.');
        $this->assertTrue($certificaciones->clasificacion_confirmada, 'El veredicto MUERE es del dueño: confirmado.');
        $this->assertNull($certificaciones->estado_baja, 'La baja FORMAL es de F2, no del seeder.');

        $santaRosa->refresh();
        $this->assertSame('insumos', $santaRosa->proposito, 'La [B] lleva la hipótesis del anexo.');
        $this->assertFalse($santaRosa->clasificacion_confirmada, 'La [B] queda por confirmar (pregunta en curso).');

        $mermas->refresh();
        $this->assertNull($mermas->sucursal_id, 'MERMAS es transversal: sin sucursal.');
        $this->assertSame('virtual_operativa', $mermas->proposito);
    }

    public function test_dos_corridas_dan_el_mismo_estado_y_no_pisan_una_confirmada_en_el_medio(): void
    {
        $this->seed(SucursalSeeder::class);
        $this->bodega(4, 'MIRADOR');
        $santaRosa = $this->bodega(10, 'BODEGA SANTA ROSA');
        $reserva = $this->bodega(11, 'RESERVA SUCURSALES');

        $this->seed(ClasificacionBodegasSeeder::class);

        // Entre corrida y corrida, un humano CONFIRMA Santa Rosa desde la UI
        // con valores DISTINTOS a la hipótesis del anexo.
        $santaRosa->refresh()->update([
            'sucursal_id' => null,
            'proposito' => 'taller',
            'clasificacion_confirmada' => true,
        ]);

        $this->seed(ClasificacionBodegasSeeder::class); // el re-deploy

        $santaRosa->refresh();
        $this->assertSame('taller', $santaRosa->proposito, 'La UI manda: el seeder no pisa una fila confirmada.');
        $this->assertNull($santaRosa->sucursal_id);
        $this->assertTrue($santaRosa->clasificacion_confirmada);

        // Y el resto queda EXACTAMENTE igual que tras la primera corrida.
        $reserva->refresh();
        $this->assertSame('virtual_operativa', $reserva->proposito);
        $this->assertFalse($reserva->clasificacion_confirmada);
        $this->assertSame(3, Bodega::count(), 'El seeder clasifica, jamás crea bodegas.');
    }

    public function test_sin_bodegas_espejadas_es_un_no_op(): void
    {
        $this->seed(SucursalSeeder::class);

        $this->seed(ClasificacionBodegasSeeder::class);

        $this->assertSame(0, Bodega::count());
    }

    public function test_sin_sucursales_clasifica_igual_sin_asignar(): void
    {
        $mirador = $this->bodega(4, 'MIRADOR');

        $this->seed(ClasificacionBodegasSeeder::class);

        $mirador->refresh();
        $this->assertNull($mirador->sucursal_id, 'Sin la sucursal creada, clasifica sin asignar (no revienta).');
        $this->assertSame('fisica', $mirador->proposito);
    }

    /**
     * Candado 4 del dictado v36: las 6 muertas de D-003 quedan fuera del
     * contrato de los selectores operativos (scope enOperacion()).
     */
    public function test_las_seis_muertas_quedan_fuera_del_scope_operativo(): void
    {
        $this->seed(SucursalSeeder::class);
        $catastro = [
            4 => 'MIRADOR', 6 => 'COQUIMBO', 5 => 'ABATE MOLINA', 7 => 'BUZETA',
            16 => 'BODEGA MERMAS', 10 => 'BODEGA SANTA ROSA', 14 => 'BODEGA SERVICIO TECNICO',
            1 => 'SERVICIO TECNICO', 11 => 'RESERVA SUCURSALES', 15 => 'CONTENEDORES',
            13 => 'CERTIFICACIONES', 9 => 'SERAFIN ZAMORA', 8 => 'CONCEPCIÓN',
            12 => 'VIÑA DEL MAR', 2 => 'ABATE PRUEBA', 3 => 'COQUIMBO PRUEBA',
        ];
        foreach ($catastro as $officeId => $nombre) {
            $this->bodega($officeId, $nombre);
        }

        $this->seed(ClasificacionBodegasSeeder::class);

        $enOperacion = Bodega::enOperacion()->pluck('nombre')->sort()->values()->all();

        $this->assertSame(10, count($enOperacion), '16 bodegas − 6 muertas = 10 en operación.');
        foreach (['CERTIFICACIONES', 'SERAFIN ZAMORA', 'CONCEPCIÓN', 'VIÑA DEL MAR', 'ABATE PRUEBA', 'COQUIMBO PRUEBA'] as $muerta) {
            $this->assertNotContains($muerta, $enOperacion, "{$muerta} (muere en D-003) no puede aparecer en operación.");
        }
    }
}
