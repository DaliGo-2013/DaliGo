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
     * Filas que hay que SACAR del catálogo y que `updateOrCreate` no puede sacar solo:
     * ya existen en producción y dejar de nombrarlas arriba no las borra.
     *
     * Dos motivos posibles, y conviene distinguirlos al leer:
     *
     * · **Se vendió.** Cotizar contra un camión que la empresa ya no tiene es prometer un
     *   viaje imposible. Hoy no hay ninguno en este caso.
     * · **Cambió de nombre.** Es el del `Chevy 3 (NQR 919)`: se dio por vendido el 05-08 y
     *   el 11-08 el dueño confirmó con el jefe que **nunca se fue** — que «Chevy 3» y «H3»
     *   son el mismo camión con dos nombres. Se sembró unificado arriba como
     *   `Chevy 3 (NQR 919 · H3)`, así que esta fila vieja tiene que irse: si sobrevive, el
     *   selector muestra DOS veces el mismo furgón y el vendedor no sabe cuál elegir.
     *
     * La constante se llamaba `VENDIDOS` y se renombró el 11-08: quedarse con ese nombre
     * era afirmar en el código que este camión se vendió, que es lo que resultó falso.
     */
    private const FUERA_DEL_CATALOGO = ['Chevy 3 (NQR 919)'];

    public function run(): void
    {
        $camiones = [
            [
                'nombre' => "Contenedor 40'",
                'largo_cm' => 1203, 'ancho_cm' => 235, 'alto_cm' => 239,
                // De la PLACA del contenedor: 42G1, CU.CAP. 67,7 m³, NET 28.800 kg.
                //
                // El dueño pasó 30.000 el 11-08 en su lista de «tonelaje oficial» y SE
                // MANTIENE el 28.800 hasta que lo confirme, por dos motivos:
                //
                // 1. La placa es una fuente física y específica de ESTE contenedor; el
                //    30.000 es un número redondo que puede ser el de otro o el de memoria.
                // 2. Un 40' típico tiene bruto máximo ~30.480 kg y tara ~3.700, así que
                //    30.000 se parece mucho al BRUTO (contenedor + carga) y no a la carga
                //    sola, que es lo que este campo significa. Tomarlo prometería ~1.200 kg
                //    de más, y en peso pasarse no es un viaje a medias: es una multa.
                //
                // Es la única diferencia con su lista; los otros tres coinciden exactos.
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
                // Silueta propia, moldeada sobre sus fotos (05-08), con el rompeviento del
                // techo y los detalles del espejo agregados el 11-08 sobre tres fotos más.
                // Ya no queda ningún camión del catálogo con la silueta genérica.
                'silueta' => 'camion_hino',
                'notas' => 'La misma caja en los dos HINO de la flota.',
            ],
            [
                'nombre' => 'Hyundai HD35',
                // ANCHO MEDIDO CON HUINCHA: 200 (dueño, 11-08-2026). VUELVE del 204.
                //
                // El 204 fue una DEDUCCIÓN, no una medida: se buscó por fuerza bruta la
                // caja entera que reprodujera a la vez sus dos cupos de terreno —420 de
                // pie y 480 acostado— y el único ancho que lo lograba era 204-207. Quedó
                // documentado como pendiente de huincha, con la salida escrita de
                // antemano: «si la medición da menos de 204, los 480 no son alcanzables y
                // hay que volver a 200».
                //
                // La medición dio 200. Se vuelve, y el cupo acostado baja de 480 a 360:
                // con 200 entran 3 bolsas acostadas a lo ancho (3 × 51 = 153) y con 204
                // entraban 4. Cuatro centímetros valían 120 botellones — es la naturaleza
                // de la rejilla exacta.
                //
                // Lo que la huincha CONFIRMA es más importante que lo que corrige: con
                // 200 este camión da los 420 de pie que él dictó el 04-08, y las medidas
                // de los otros tres reproducen exactos los otros tres cupos de referencia
                // (1.620 / 1.500 / 960). Los cuatro números originales cierran. El que no
                // cierra es el 480 acostado, que llegó después — ver §3.5 de las reglas.
                'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
                'peso_max_kg' => 1500,
                'silueta' => 'camion_liviano',
                'notas' => 'La misma caja en los tres HD35 de la flota. Medidas de huincha (11-08-2026).',
            ],
            [
                // UN SOLO CAMIÓN CON DOS NOMBRES (confirmado por el dueño con el jefe,
                // 11-08-2026: «el chevy 3 y el h3 es el mismo camión, solo que le dicen de
                // las dos formas»). Entró como dos filas ese mismo día y se unificó antes
                // de llegar a producción, así que no hay nada que limpiar allá.
                //
                // EL NOMBRE LLEVA LOS DOS a propósito: el selector es la única parte
                // buscable de la pantalla, y quien lo conoce como «H3» no reconocería una
                // fila que dice solo «Chevy 3». El paréntesis además respeta la convención
                // del catálogo (`HINO 500 (FC 1118)`) y el visor lo descarta para la chapa
                // trasera, que queda en «CHEVY 3».
                'nombre' => 'Chevy 3 (NQR 919 · H3)',
                //
                // MEDIDAS: se toma el juego MENOR de los dos que dictó. Vinieron como
                // «chevy 3: 8,00 × 2,30 × 2,45» y «h3: 7,90 × 2,20 × 2,30», los dos
                // presentados como interiores, y no pueden ser los dos del mismo furgón.
                // La diferencia es uniforme —10 cm en largo y ancho, 15 en alto—, que es
                // justo lo que se espera entre EXTERIOR e INTERIOR: el juego chico es el
                // que parece medido por dentro.
                //
                // Y aunque no lo fuera, manda el credo (§2): los dos reproducen igual el
                // cupo de referencia de 960 botellones de pie, así que no se pierde nada
                // verificado, y para el resto del catálogo el chico es el que menos
                // promete (con el grande serían 570 cajas de tapas contra 525). Si el
                // dueño confirma que el interior es el grande, se sube — el error queda
                // del lado seguro mientras tanto.
                'largo_cm' => 790, 'ancho_cm' => 220, 'alto_cm' => 230,
                // Tonelaje oficial que pasó el dueño el 11-08. Estuvo unas horas en null
                // —«sin dato», con el motor sin recortar por peso— y era el único lugar
                // del módulo donde el error iba HACIA ARRIBA.
                'peso_max_kg' => 6430,
                // Silueta propia desde el 11-08, moldeada sobre sus fotos: un Chevrolet
                // NQR (Isuzu N-Series) con furgón — cab-over de cara plana, parabrisas de
                // una pieza y doble espejo por lado. Las fotos llevan pintado «NQR 919»,
                // que es lo que destapó que las dos filas eran el mismo camión.
                'silueta' => 'camion_nqr',
                // LA RUEDA DE REPUESTO VIAJA ADENTRO. En las fotos del interior (11-08) va
                // parada y amarrada en el rincón derecho del fondo. Son ~28 cm del ancho, y
                // acá el ancho no tiene ese margen: con 220 entran 8 bolsas a lo ancho
                // (208 cm) y con 192 entran 7 → el cupo de referencia de 960 botellones
                // pasaría a 840. NO se descuenta todavía porque los 960 los dictó el dueño
                // como cupo real, así que o la rueda sale para cargar o el 960 es teórico:
                // es una pregunta abierta, no un dato. Los listones de madera de las
                // paredes, en cambio, no cuestan nada (con 212 el cupo no se mueve).
                'notas' => 'Le dicen Chevy 3 y también H3: es el mismo camión (confirmado 11-08-2026). Medidas de huincha, el juego menor de los dos dictados. Tonelaje oficial del dueño (11-08).',
            ],
        ];

        foreach ($camiones as $c) {
            CamionSimulacion::updateOrCreate(
                ['nombre' => $c['nombre']],
                $c + ['pasillo_cm' => 0, 'activo' => true],
            );
        }

        // Se BORRAN, no se desactivan: la fila desactivada seguiría apareciendo en
        // cualquier listado que olvide filtrar por `activo`. Es el mismo criterio que con
        // el «H1».
        //
        // Va acá y no en una migración a propósito: la fuente de verdad del catálogo es
        // este seeder, corre en cada deploy y es idempotente. Una migración lo borraría
        // una vez y el próximo deploy lo volvería a sembrar.
        CamionSimulacion::whereIn('nombre', self::FUERA_DEL_CATALOGO)->delete();
    }
}
