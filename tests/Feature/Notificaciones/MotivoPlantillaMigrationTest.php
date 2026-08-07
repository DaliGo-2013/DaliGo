<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Configuracion;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La migración 2026_08_06_140100 suma {motivo} al aviso interno
 * `cotizacion.respondida` en entornos ya sembrados (el seeder firstOrCreate no
 * pisa). En la suite normal es no-op (config vacía al momento de migrar), así
 * que aquí se ejerce a mano sobre una fila con el default anterior — mismo
 * patrón que EnriquecerPlantillasMigrationTest.
 */
class MotivoPlantillaMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $clave = 'notif_plantilla_cotizacion_respondida';

    private string $cuerpoViejo = "El cliente {cliente} respondió la cotización de la orden {folio}: {respuesta}.\nEquipo: {equipo} · Monto: {total}.";

    private function migracion(): object
    {
        return require database_path('migrations/2026_08_06_140100_agrega_motivo_a_plantilla_cotizacion_respondida.php');
    }

    private function insertar(string $cuerpo): void
    {
        DB::table('configuraciones')->insert([
            'clave' => $this->clave,
            'valor' => json_encode(['asunto' => 'Asunto original', 'cuerpo' => $cuerpo], JSON_UNESCAPED_UNICODE),
            'tipo' => Configuracion::TIPO_JSON,
            'grupo' => 'notificaciones',
            'descripcion' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_suma_el_motivo_conserva_el_asunto_y_es_idempotente(): void
    {
        $this->insertar($this->cuerpoViejo);

        $this->migracion()->up();

        $plantilla = Configuracion::get($this->clave);
        $this->assertStringContainsString('{motivo}', $plantilla['cuerpo']);
        $this->assertSame('Asunto original', $plantilla['asunto']);

        // Idempotente: una 2ª corrida ya no matchea el default viejo → no cambia.
        $this->migracion()->up();
        $this->assertSame($plantilla['cuerpo'], Configuracion::get($this->clave)['cuerpo']);
    }

    public function test_el_new_de_la_migracion_calza_con_el_default_del_seeder(): void
    {
        // El gate anti-pisado del PRÓXIMO cambio depende de esta igualdad: lo
        // que deja la migración en una BD vieja == lo que siembra el seeder en
        // una BD nueva (patrón de 2026_07_30_100000).
        $this->insertar($this->cuerpoViejo);
        $this->migracion()->up();
        $migrado = Configuracion::get($this->clave)['cuerpo'];

        DB::table('configuraciones')->where('clave', $this->clave)->delete();
        Cache::forget('config.'.$this->clave);
        $this->seed(ConfiguracionSeeder::class);

        $this->assertSame($migrado, Configuracion::get($this->clave)['cuerpo']);
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
