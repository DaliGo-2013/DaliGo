<?php

namespace Database\Seeders;

use App\Models\ServicioTerreno;
use Illuminate\Database\Seeder;

/**
 * Catálogo de servicios de terreno (tarifario del técnico industrial, en UF).
 * Fuente: tarifario impreso del taller (foto del dueño, 2026-07).
 *
 * firstOrCreate A PROPÓSITO (no updateOrCreate): el catálogo es editable desde
 * la app y el equipo va actualizando precios/especificaciones — re-ejecutar el
 * seeder en cada deploy NO debe pisar esas ediciones; solo crea lo que falte.
 */
class ServiciosTerrenoSeeder extends Seeder
{
    public function run(): void
    {
        $obsPlanta = 'No incluye cambio de cabezal, estanque y/o reparaciones.';

        $servicios = [
            ['nombre' => 'Full planta 1T', 'valor_uf' => 3, 'duracion' => '1 día',
                'incluye' => 'Pack: cambio de filtro carbón, resina y gravilla. Cambio de membranas y filtro papel.',
                'observaciones' => $obsPlanta],
            ['nombre' => 'Full planta 0,25 y 0,5', 'valor_uf' => 2.5, 'duracion' => '1 día',
                'incluye' => 'Pack: cambio de filtro carbón, resina y gravilla. Cambio de membranas y filtro papel.',
                'observaciones' => $obsPlanta],
            ['nombre' => 'Simple planta 1T', 'valor_uf' => 2.5, 'duracion' => '1 día',
                'incluye' => 'Cambio de filtro carbón, resina y gravilla. Filtro papel.',
                'observaciones' => $obsPlanta],
            ['nombre' => 'Simple planta 0,25 y 0,5', 'valor_uf' => 2, 'duracion' => '1 día',
                'incluye' => 'Cambio de filtro carbón, resina y gravilla. Filtro papel.',
                'observaciones' => $obsPlanta],
            ['nombre' => 'Membranas 1 a 2', 'valor_uf' => 1, 'duracion' => '1/2 día',
                'incluye' => 'Cambio de membranas, limpieza portamembrana.',
                'observaciones' => null],
            ['nombre' => 'Membranas 3 a 4', 'valor_uf' => 2, 'duracion' => '1/2 día',
                'incluye' => 'Cambio de membranas, limpieza portamembrana.',
                'observaciones' => null],
            ['nombre' => 'Cambio de filtro papel', 'valor_uf' => 1, 'duracion' => '1/2 mañana',
                'incluye' => null,
                'observaciones' => null],
            ['nombre' => 'Reparación o cambio planta', 'valor_uf' => 1.5, 'duracion' => '1/2 día',
                'incluye' => 'Cambio de cabezal, bomba, estanque, válvula, PLC, etc.',
                'observaciones' => 'Incluye configuración y/o programación de PLC o cabezal.'],
            ['nombre' => 'Llenadora', 'valor_uf' => 2.5, 'duracion' => '1 día',
                'incluye' => 'Limpieza y lubricación de cadena, rodamiento y pistones.',
                'observaciones' => null],
            ['nombre' => 'Reparación llenadora', 'valor_uf' => 3, 'duracion' => '1 día',
                'incluye' => 'Cambio de bomba, válvula o pistones. Además se incluye mantención.',
                'observaciones' => null],
            ['nombre' => 'Lavadora reparación', 'valor_uf' => 1, 'duracion' => '1/2 día',
                'incluye' => 'Cambio de cepillo, rodamiento, botonera, motor o correa.',
                'observaciones' => null],
            ['nombre' => 'Full lavadora', 'valor_uf' => 2, 'duracion' => '1 día',
                'incluye' => 'Pack: cambio de cepillos y rodamientos. Cambio o ajuste de tensión de correas.',
                'observaciones' => null],
            ['nombre' => 'Visita ST', 'valor_uf' => 1, 'duracion' => null,
                'incluye' => 'Diagnóstico, cualquier reparación menor no indicada en lo anterior.',
                'observaciones' => 'Si hay que hacer algo mayor, se descuenta la visita del total.'],
        ];

        foreach ($servicios as $s) {
            ServicioTerreno::firstOrCreate(['nombre' => $s['nombre']], $s);
        }
    }
}
