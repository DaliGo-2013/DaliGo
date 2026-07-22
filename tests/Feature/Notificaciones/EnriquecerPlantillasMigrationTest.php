<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La migración 2026_07_22_180000 propaga los cuerpos enriquecidos a entornos ya
 * sembrados (donde el seeder firstOrCreate no los pisa). En la suite normal es
 * no-op (config vacía al momento de migrar), así que aquí se ejerce a mano
 * sobre una fila con el default ANTERIOR.
 */
class EnriquecerPlantillasMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $clave = 'notif_plantilla_terreno_solicitada';

    private string $cuerpoViejo = "{cliente} pidió {tipo} en {ciudad}.\nTeléfono: {telefono} · Prefiere: {preferida}\n\nCoordínala en la agenda de terreno: {url}";

    private function migracion(): object
    {
        return require database_path('migrations/2026_07_22_180000_enriquecer_plantillas_notificaciones_internas.php');
    }

    private function insertar(string $cuerpo, string $asunto = 'Asunto original'): void
    {
        DB::table('configuraciones')->insert([
            'clave' => $this->clave,
            'valor' => json_encode(['asunto' => $asunto, 'cuerpo' => $cuerpo], JSON_UNESCAPED_UNICODE),
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => 'notificaciones',
            'descripcion' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_actualiza_el_default_viejo_conserva_el_asunto_y_es_idempotente(): void
    {
        $this->insertar($this->cuerpoViejo);

        $this->migracion()->up();

        $plantilla = Configuracion::get($this->clave);
        foreach (['{servicio}', '{direccion}', '{descripcion}'] as $ph) {
            $this->assertStringContainsString($ph, $plantilla['cuerpo']);
        }
        // El asunto (incluso si estaba personalizado) no se toca.
        $this->assertSame('Asunto original', $plantilla['asunto']);

        // Idempotente: una 2ª corrida ya no matchea el default viejo → no cambia.
        $nuevoCuerpo = $plantilla['cuerpo'];
        $this->migracion()->up();
        $this->assertSame($nuevoCuerpo, Configuracion::get($this->clave)['cuerpo']);
    }

    public function test_no_pisa_un_cuerpo_personalizado_desde_la_ui(): void
    {
        $custom = 'Texto que el admin escribió a mano para {cliente}.';
        $this->insertar($custom);

        $this->migracion()->up();

        $this->assertSame($custom, Configuracion::get($this->clave)['cuerpo']);
    }

    public function test_down_revierte_al_cuerpo_viejo(): void
    {
        $this->insertar($this->cuerpoViejo);

        $this->migracion()->up();
        $this->migracion()->down();

        $this->assertSame($this->cuerpoViejo, Configuracion::get($this->clave)['cuerpo']);
    }
}
