<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use Database\Seeders\CamionesSimulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LO QUE SE SIEMBRA TIENE QUE ENTRAR EN LA COLUMNA.
 *
 * El 13-08-2026 este candado no existía y el deploy a producción se cayó con
 * «SQLSTATE[22001]: Data too long for column 'notas'»: la nota del HINO quedó en 269
 * caracteres y la columna es VARCHAR(255).
 *
 * POR QUÉ NO LO VIO NADIE: los tests corren en **SQLite**, que **ignora el largo declarado**
 * de un varchar y guarda lo que le den. MySQL —producción— lo rechaza. Así que 2.023 tests
 * verdes y CI verde no decían nada sobre esto, y el error apareció en el único lugar donde
 * duele. Es la misma clase de agujero que el «un candado sobre el motor no prueba que la
 * pantalla lo use»: acá, un candado sobre el código no prueba que el DATO entre.
 *
 * Los límites se LEEN de las migraciones, no se tipean acá: una lista a mano se desactualiza
 * el día que alguien ensanche una columna, y este candado volvería a mentir.
 *
 * ⚠️ Al escribir una nota, tener presente que el contenedor ya está en **224 de 255**: es la
 * que tiene menos aire, y una aclaración de dos palabras la pasa de largo. Se dejó un solo
 * chequeo —el límite real— y no uno de «margen»: exigir margen habría obligado a reescribir
 * notas ajenas que hoy funcionan, y eso lo decide quien las escribió.
 */
class LargosSembradosTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, int> columna => largo máximo declarado */
    private function limitesDeclarados(): array
    {
        $limites = [];

        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            $fuente = file_get_contents($archivo);
            if (! str_contains($fuente, 'camiones_simulacion')) {
                continue;
            }
            // `$table->string('nombre', 120)` — solo las que declaran largo explícito.
            if (preg_match_all("/string\('([a-z_]+)',\s*(\d+)\)/", $fuente, $m, PREG_SET_ORDER)) {
                foreach ($m as [, $columna, $largo]) {
                    $limites[$columna] = (int) $largo;
                }
            }
        }

        return $limites;
    }

    public function test_ninguna_nota_ni_nombre_sembrado_excede_su_columna(): void
    {
        $limites = $this->limitesDeclarados();

        // Sin esto el test pasaría vacío el día que cambie la forma de declarar las columnas,
        // que es exactamente cómo un candado deja de cuidar sin avisar.
        $this->assertNotEmpty($limites, 'No se encontró ninguna columna con largo declarado: este candado dejó de mirar lo que dice mirar.');
        $this->assertArrayHasKey('notas', $limites, 'Se perdió el límite de `notas`, que es la columna que tumbó el deploy del 13-08.');

        $this->seed(CamionesSimulacionSeeder::class);

        foreach (CamionSimulacion::get() as $camion) {
            foreach ($limites as $columna => $maximo) {
                $valor = $camion->{$columna};
                if (! is_string($valor)) {
                    continue;
                }

                $largo = mb_strlen($valor);
                $this->assertLessThanOrEqual($maximo, $largo,
                    "«{$camion->nombre}»: `{$columna}` mide {$largo} y la columna admite {$maximo}. "
                    .'En SQLite entra y en MySQL el deploy se cae con «Data too long». '
                    .'La explicación larga va en el comentario del seeder o en docs/, no en la columna.');
            }
        }
    }

}
