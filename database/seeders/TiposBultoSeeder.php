<?php

namespace Database\Seeders;

use App\Models\TipoBulto;
use Illuminate\Database\Seeder;

/**
 * Bultos reales de Dali para el simulador de carga.
 *
 * Medidas entregadas por el dueño el 04-08-2026, con respaldo fotográfico, más
 * las etiquetas impresas de las cajas de dispensador (que traen las medidas de
 * fábrica en mm). El dueño advirtió que pueden variar 1-2 cm: el cálculo es por
 * división entera, así que eso solo mueve el resultado cuando queda al filo, y
 * para eso está el factor de aprovechamiento calibrado contra una carga real.
 *
 * Idempotente (updateOrCreate por nombre): se puede correr siempre.
 */
class TiposBultoSeeder extends Seeder
{
    public function run(): void
    {
        $bultos = [
            // --- Botellones: la unidad de carga es la BOLSA DE 5, no el botellón.
            // Van acostados con el pico hacia la puerta => orientación fija.
            [
                'nombre' => 'Bolsa 5× botellón 20 L (vacío)',
                'categoria' => 'botellones',
                'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51,
                'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true,
                'orientacion_fija' => true,
                'observaciones' => 'Medida del dueño 04-08-2026. Botellones SIEMPRE vacíos: el límite es volumen, no peso.',
            ],
            [
                'nombre' => 'Bolsa 5× botellón 10 L (vacío)',
                'categoria' => 'botellones',
                'largo_cm' => 110, 'ancho_cm' => 21, 'alto_cm' => 40,
                'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true,
                'orientacion_fija' => true,
                'observaciones' => 'Rinde casi el doble que el de 20 L: 54% del espacio por botellón.',
            ],

            // --- Cajas
            [
                'nombre' => 'Caja de soportes',
                'categoria' => 'cajas',
                'largo_cm' => 79, 'ancho_cm' => 24, 'alto_cm' => 43,
                'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            ],
            [
                'nombre' => 'Caja de tapas',
                'categoria' => 'cajas',
                'largo_cm' => 46, 'ancho_cm' => 37, 'alto_cm' => 42,
                'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
                'observaciones' => 'La etiqueta declara ~500 unidades por caja.',
            ],

            // --- Dispensadores: medidas de la etiqueta impresa (mm -> cm)
            [
                'nombre' => 'Dispensador LB-07B',
                'categoria' => 'dispensadores',
                'largo_cm' => 33, 'ancho_cm' => 29, 'alto_cm' => 87,
                'peso_kg' => 11.0,
                'unidades' => 1, 'apilable_max' => 2, 'soporta_peso_encima' => false,
                'observaciones' => 'Etiqueta: 290×325×870 mm · peso total 11 kg · neto 10 kg.',
            ],
            [
                'nombre' => 'Dispensador LB-93',
                'categoria' => 'dispensadores',
                'largo_cm' => 38, 'ancho_cm' => 33, 'alto_cm' => 98,
                'peso_kg' => 15.5,
                'unidades' => 1, 'apilable_max' => 2, 'soporta_peso_encima' => false,
                'observaciones' => 'Etiqueta: 325×380×980 mm · peso total 15,5 kg · neto 14 kg.',
            ],
        ];

        foreach ($bultos as $b) {
            TipoBulto::updateOrCreate(['nombre' => $b['nombre']], $b + ['activo' => true]);
        }

        /*
         * PENDIENTE DE MEDIR (no se siembran con números inventados a propósito:
         * un bulto con medida falsa es peor que un bulto ausente).
         *   - Jaula llenadora 100 y 200 botellones
         *   - Jaula planta de osmosis 500 y 1 tera
         *   - Jaula lavadora de botellones
         *   - Jaula sopladora
         * Se mide la JAULA DE MADERA CON EL PALLET, no la máquina desnuda, y todas
         * van con soporta_peso_encima = false (el rotulado de fábrica dice
         * "keep off / box lid may collapse") y orientacion_fija = true (van a lo
         * largo, pegadas a un costado y amarradas).
         */
    }
}
