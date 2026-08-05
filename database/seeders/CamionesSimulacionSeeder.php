<?php

namespace Database\Seeders;

use App\Models\CamionSimulacion;
use Illuminate\Database\Seeder;

/**
 * Los camiones del simulador, con las medidas ÚTILES que dictó el dueño el
 * 04-08-2026 (verificadas: la rejilla del motor reproduce exactamente sus
 * cupos de referencia — 1.620 / 1.500 / 960 / 420 botellones de 20 L).
 *
 * updateOrCreate por nombre → idempotente, y una corrección de medida en el
 * código VIAJA al deploy (a diferencia de firstOrCreate). Si el dueño edita
 * algo desde la app el día que exista esa pantalla, habrá que revisitar esto —
 * hoy la fuente de verdad de las medidas es el repo, a propósito: son datos
 * verificados contra cálculo, no preferencias.
 *
 * El «H1» del dictado original NO está: ese vehículo se vendió en 2021 y el
 * dueño pidió descartar su fila (04-08). Las jaulas de máquinas siguen sin
 * medir — no se siembran números inventados.
 */
class CamionesSimulacionSeeder extends Seeder
{
    /**
     * Camiones que la empresa VENDIÓ y hay que sacar del catálogo.
     *
     * El Chevy 3 (NQR 919) estuvo sembrado y llegó a producción; el dueño avisó el
     * 05-08-2026 que ya no está («el chevy 3 no está más, lo vendieron»). Borrarlo del
     * array de arriba no alcanza: la fila ya existe en producción y `updateOrCreate` no
     * borra lo que dejó de estar en la lista.
     */
    private const VENDIDOS = ['Chevy 3 (NQR 919)'];

    public function run(): void
    {
        $camiones = [
            [
                'nombre' => "Contenedor 40'",
                'largo_cm' => 1203, 'ancho_cm' => 235, 'alto_cm' => 239,
                // De la placa del contenedor: 42G1, CU.CAP. 67,7 m³, NET 28.800 kg.
                'peso_max_kg' => 28800,
                // Su propia nota dice cómo viaja, y así se dibuja: el contenedor
                // NO tiene cabina propia.
                'silueta' => 'semirremolque',
                'notas' => 'Va sobre el semirremolque (Tremac), tirado por el Actros.',
            ],
            [
                'nombre' => 'HINO 500 (FC 1118)',
                'largo_cm' => 797, 'ancho_cm' => 260, 'alto_cm' => 266,
                'peso_max_kg' => 11000,
                // Silueta propia, moldeada sobre sus fotos (05-08). El Chevy sigue con
                // la genérica `camion` hasta que lleguen las suyas.
                'silueta' => 'camion_hino',
                'notas' => 'La misma caja en los dos HINO de la flota.',
            ],
            [
                'nombre' => 'Hyundai HD35',
                'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
                'peso_max_kg' => 1500,
                'silueta' => 'camion_liviano',
                'notas' => 'La misma caja en los tres HD35 de la flota.',
            ],
        ];

        foreach ($camiones as $c) {
            CamionSimulacion::updateOrCreate(
                ['nombre' => $c['nombre']],
                $c + ['pasillo_cm' => 0, 'activo' => true],
            );
        }

        // Camiones que salieron de la flota. Se BORRAN, no se desactivan: la fila
        // desactivada seguiría apareciendo en cualquier listado que olvide filtrar por
        // `activo`, y cotizar contra un camión que la empresa ya vendió es prometer un
        // viaje que no se puede hacer. Es el mismo criterio que con el «H1».
        //
        // Va acá y no en una migración a propósito: la fuente de verdad del catálogo es
        // este seeder, corre en cada deploy y es idempotente. Una migración lo borraría
        // una vez y el próximo deploy lo volvería a sembrar.
        CamionSimulacion::whereIn('nombre', self::VENDIDOS)->delete();
    }
}
