<?php

namespace Tests\Feature;

use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Candado contra el incidente del 2026-07-27 (deploy de DESPACHOS-v1 caído):
 * una `descripcion` de 267 chars reventó el seeder en producción con
 * SQLSTATE[22001] «Data too long for column 'descripcion'» — y la suite estaba
 * VERDE porque SQLite NO valida el largo de un VARCHAR; MySQL sí.
 *
 * Este test cierra ese hueco a nivel de datos: recorre lo que el seeder deja en
 * la tabla y exige que cada string quepa en su columna real. Sin él, cualquier
 * descripción verbosa vuelve a romper el deploy sin aviso local.
 */
class ConfiguracionSeedLongitudTest extends TestCase
{
    use RefreshDatabase;

    /** Largo real de un `$table->string()` de Laravel; MySQL corta ahí. */
    private const VARCHAR = 255;

    public function test_ningun_valor_sembrado_excede_el_varchar_de_su_columna(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        // Solo las columnas string de la tabla; `valor` es TEXT y queda fuera.
        $columnas = ['clave', 'tipo', 'grupo', 'descripcion'];

        foreach (\App\Models\Configuracion::all() as $fila) {
            foreach ($columnas as $columna) {
                $contenido = $fila->{$columna};

                if (! is_string($contenido)) {
                    continue;
                }

                $this->assertLessThanOrEqual(
                    self::VARCHAR,
                    mb_strlen($contenido),
                    "La clave '{$fila->clave}' tiene un '{$columna}' de ".mb_strlen($contenido).
                    ' caracteres: MySQL lo rechaza con SQLSTATE[22001] y tumba el deploy. '.
                    'Acórtalo a '.self::VARCHAR.' o menos (el detalle largo va en docs/, no en la descripción).'
                );
            }
        }
    }

    /**
     * Guarda del guarda: si algún día `descripcion` deja de ser varchar(255)
     * (p. ej. pasa a TEXT), este test avisa para relajar el límite de arriba en
     * vez de seguir restringiendo de más.
     */
    public function test_la_columna_descripcion_sigue_siendo_un_varchar(): void
    {
        $tipo = Schema::getColumnType('configuraciones', 'descripcion');

        $this->assertContains(
            $tipo,
            ['string', 'varchar'],
            "La columna 'descripcion' ya no es varchar (ahora: {$tipo}). ".
            'Revisa el límite de '.self::VARCHAR.' que fija este test.'
        );
    }
}
