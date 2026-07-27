<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Database\Schema\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Candado contra el incidente del 2026-07-27 (deploy de DESPACHOS-v1 caído dos veces):
 * una `descripcion` larga reventó el seeder en producción con SQLSTATE[22001]
 * «Data too long for column 'descripcion'» — con la suite VERDE, porque SQLite
 * NO valida el largo de un VARCHAR y MySQL sí.
 *
 * El segundo intento de arreglo también falló por asumir varchar(255): el proyecto
 * fija `Schema::defaultStringLength(191)` (AppServiceProvider) por el límite de
 * índice de InnoDB en MySQL 5.7 con utf8mb4. Por eso este test NO hardcodea el
 * número: lo lee de la misma fuente que usan las migraciones.
 */
class ConfiguracionSeedLongitudTest extends TestCase
{
    use RefreshDatabase;

    /** Columnas string de `configuraciones`; `valor` es TEXT y queda fuera. */
    private const COLUMNAS = ['clave', 'tipo', 'grupo', 'descripcion'];

    public function test_ningun_valor_sembrado_excede_el_largo_de_su_columna(): void
    {
        $limite = Builder::$defaultStringLength ?? 255;

        $this->seed(ConfiguracionSeeder::class);

        foreach (Configuracion::all() as $fila) {
            foreach (self::COLUMNAS as $columna) {
                $contenido = $fila->{$columna};

                if (! is_string($contenido)) {
                    continue;
                }

                $this->assertLessThanOrEqual(
                    $limite,
                    mb_strlen($contenido),
                    "La clave '{$fila->clave}' tiene un '{$columna}' de ".mb_strlen($contenido).
                    " caracteres y la columna admite {$limite}: MySQL lo rechaza con ".
                    'SQLSTATE[22001] y tumba el deploy (SQLite no lo caza). '.
                    'Acórtalo — el detalle largo va en docs/, no en la descripción.'
                );
            }
        }
    }

    /**
     * Guarda del guarda: si alguien sube o baja `defaultStringLength`, o si
     * `descripcion` deja de ser un string, el límite de arriba deja de valer.
     */
    public function test_el_largo_por_defecto_sigue_siendo_el_de_mysql57_utf8mb4(): void
    {
        $this->assertSame(
            191,
            Builder::$defaultStringLength,
            'AppServiceProvider fija defaultStringLength(191) por el límite de índice de '.
            'InnoDB en MySQL 5.7 con utf8mb4. Si cambió, revisa este candado y las migraciones.'
        );

        // El driver decide el nombre del tipo ('varchar' en sqlite/mysql, 'string'
        // en versiones viejas de Doctrine): basta con que siga siendo de largo fijo.
        $this->assertContains(
            Schema::getColumnType('configuraciones', 'descripcion'),
            ['string', 'varchar'],
            "La columna 'descripcion' ya no es un varchar: revisa el límite que fija este test."
        );
    }
}
