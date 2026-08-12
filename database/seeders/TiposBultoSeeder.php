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
                // PESO REAL: 3,75 kg la bolsa (dueño, 11-08-2026: «cada preforma que se
                // sopla y se convierte en botellón pesa 750 gr, o sea que una bolsa de 5
                // bidones vacíos pesa 3,750 kg»). Hasta hoy viajaba SIN peso —o sea, 0 kg
                // para el motor—, que era inofensivo mientras no existía el aviso de
                // sobrepeso y dejó de serlo en cuanto existió: una carga de botellones
                // nunca habría disparado el cartel aunque se pasara.
                //
                // No mueve ningún cupo: 28.800 kg de contenedor a 3,75 la bolsa dan 7.680
                // bolsas y el espacio deja 324. Confirma lo que la nota decía desde el
                // 04-08 — acá el límite es volumen, no peso.
                'peso_kg' => 3.75,
                // SIN TOPE DE APILADO (dato de terreno del dueño, 11-08-2026: «no hay un
                // máximo para apilar, se llenan todos los camiones siempre y no pasa
                // nada»). El 30 no es un tope real: es un número por encima de lo que
                // cualquier caja del catálogo permite —el peor caso es la bolsa de 10 L
                // acostada en el HINO, 266/21 = 12 capas—, así que el que manda es
                // SIEMPRE la altura del camión. Candado:
                // `test_el_tope_de_la_bolsa_no_muerde_en_ningun_camion_del_catalogo`.
                //
                // Estuvo en 6 (prudente, sin medir) y unas horas en 10, cuando él dijo
                // «aguantan 9 encima». El 10 todavía mordía: en el HINO la bolsa acostada
                // da exactamente 10 capas, así que parecía correcto por casualidad, y en
                // la de 10 L recortaba de verdad.
                'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true,
                'orientacion_fija' => true,
                'observaciones' => 'Medida del dueño 04-08-2026. Botellones SIEMPRE vacíos: 750 g cada uno, el límite es volumen y no peso. Sin tope de apilado (dueño, 11-08-2026).',
            ],
            [
                'nombre' => 'Bolsa 5× botellón 10 L (vacío)',
                'categoria' => 'botellones',
                'largo_cm' => 110, 'ancho_cm' => 21, 'alto_cm' => 40,
                // Mismo dato de terreno que la de 20 L: la bolsa va vacía y no se aplasta.
                //
                // PESO REAL: 2 kg la bolsa (dueño, 12-08-2026: «cada bolsa de 5 botellones
                // de 10 lt pesa 2 kg, cada botellón de 10 pesa 400 gr»). Los dos datos
                // cierran entre sí —5 × 400 g = 2 kg— así que no hay que elegir cuál creer.
                //
                // Estuvo en null a propósito desde el 04-08: los 750 g eran del botellón
                // de 20 L y ponerle los mismos al de 10 habría inventado un número que
                // alimenta el cartel de sobrepeso. Ahora está medido.
                'peso_kg' => 2.0,
                'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true,
                'orientacion_fija' => true,
                'observaciones' => 'Rinde casi el doble que el de 20 L: 54% del espacio por botellón. Peso del dueño 12-08-2026 (400 g por botellón).',
            ],

            // --- Cajas
            [
                'nombre' => 'Caja de soportes',
                'categoria' => 'cajas',
                'largo_cm' => 79, 'ancho_cm' => 24, 'alto_cm' => 43,
                // Peso del dueño (12-08-2026). Hasta hoy iba en null: una carga de puras
                // cajas no se podía repartir entre los ejes ni disparar el sobrepeso.
                'peso_kg' => 6.0,
                'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
                'observaciones' => 'Peso del dueño 12-08-2026.',
            ],
            [
                'nombre' => 'Caja de tapas',
                'categoria' => 'cajas',
                'largo_cm' => 46, 'ancho_cm' => 37, 'alto_cm' => 42,
                'peso_kg' => 5.5,
                'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
                'observaciones' => 'La etiqueta declara ~500 unidades por caja. Peso del dueño 12-08-2026.',
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
