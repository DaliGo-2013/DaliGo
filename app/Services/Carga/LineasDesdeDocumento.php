<?php

namespace App\Services\Carga;

use App\Models\DocumentoVenta;

/**
 * DE UNA FACTURA A LAS LÍNEAS DEL SIMULADOR.
 *
 * Pedido del jefe de logística (10-09-2026): «exportar documentos a la sección de carga
 * del camión para saber su capacidad total». La factura ya estaba espejada
 * (`documento_venta_detalles`: producto + cantidad) y el simulador ya sabe dividir «200
 * botellones» en «40 bolsas» —lo hace con `unidades` del bulto—; lo que faltaba era el
 * dato del medio, `productos.tipo_bulto_id`, y esta conversión, que es lo único que
 * decide qué entra al cálculo y qué no.
 *
 * TRES REGLAS, y las tres son de HONESTIDAD del veredicto:
 *
 *  1. LO QUE NO TIENE BULTO SE LISTA, NO SE SALTA. Si una factura trae 8 líneas y solo 5
 *     tienen «cómo viaja» declarado, el simulador diría «cabe todo» sobre una carga a la
 *     que le falta medio pedido — con cara de verificado. Las que no se pudieron
 *     convertir salen en `sin_bulto`, con su motivo, para dibujarlas al lado del
 *     veredicto. Un bulto INACTIVO cuenta como no declarado: mandarlo al motor armaría un
 *     plan con un bulto que ya nadie puede elegir en la pantalla.
 *
 *  2. LAS CANTIDADES SE REDONDEAN HACIA ARRIBA. `cantidad` es decimal(14,4) y no se
 *     cargan 2,5 cajas: se cargan 3. Redondear hacia abajo prometería espacio de menos
 *     del que hace falta, que es la dirección en la que el simulador nunca se equivoca.
 *
 *  3. DOS PRODUCTOS DEL MISMO BULTO SON UNA LÍNEA. Dos SKU que viajan en la misma bolsa
 *     se suman: el motor acomoda bultos, no códigos. Y si aun así quedan más de las
 *     ocho líneas que admite la pantalla, las de más se devuelven en `fuera_de_tope`
 *     —también con nombre y cantidad— en vez de caerse en silencio.
 */
class LineasDesdeDocumento
{
    /** El tope de líneas del formulario y del validador del simulador. */
    public const TOPE_LINEAS = 8;

    public const MOTIVO_SIN_PRODUCTO = 'sin_producto';

    public const MOTIVO_SIN_DECLARAR = 'sin_declarar';

    public const MOTIVO_BULTO_INACTIVO = 'bulto_inactivo';

    /**
     * @return array{
     *   lineas: list<array{tipo:int,cantidad:int,nombre:string}>,
     *   sin_bulto: list<array{nombre:string,sku:?string,cantidad:int,motivo:string}>,
     *   fuera_de_tope: list<array{nombre:string,cantidad:int}>
     * }
     */
    public function convertir(DocumentoVenta $documento): array
    {
        $documento->loadMissing('detalles.producto.tipoBulto');

        $porBulto = [];
        $sinBulto = [];

        foreach ($documento->detalles as $detalle) {
            $cantidad = (int) ceil((float) $detalle->cantidad);
            if ($cantidad <= 0) {
                continue;
            }

            $producto = $detalle->producto;
            $nombre = $producto?->nombre ?? $detalle->descripcion ?? 'Línea sin descripción';

            if ($producto === null) {
                $sinBulto[] = ['nombre' => $nombre, 'sku' => null, 'cantidad' => $cantidad, 'motivo' => self::MOTIVO_SIN_PRODUCTO];

                continue;
            }

            $bulto = $producto->tipoBulto;
            if ($bulto === null || ! $bulto->activo) {
                $sinBulto[] = [
                    'nombre' => $nombre,
                    'sku' => $producto->sku,
                    'cantidad' => $cantidad,
                    'motivo' => $bulto === null ? self::MOTIVO_SIN_DECLARAR : self::MOTIVO_BULTO_INACTIVO,
                ];

                continue;
            }

            $porBulto[$bulto->id] ??= ['tipo' => $bulto->id, 'cantidad' => 0, 'nombre' => $bulto->nombre];
            $porBulto[$bulto->id]['cantidad'] += $cantidad;
        }

        $lineas = array_values($porBulto);

        return [
            'lineas' => array_slice($lineas, 0, self::TOPE_LINEAS),
            'sin_bulto' => $sinBulto,
            'fuera_de_tope' => array_map(
                fn (array $l) => ['nombre' => $l['nombre'], 'cantidad' => $l['cantidad']],
                array_slice($lineas, self::TOPE_LINEAS),
            ),
        ];
    }

    /**
     * Cómo se le explica al usuario cada motivo, en una frase que dice qué hacer.
     *
     * @return array<string,string>
     */
    public static function etiquetasDeMotivo(): array
    {
        return [
            self::MOTIVO_SIN_PRODUCTO => 'no está en el catálogo',
            self::MOTIVO_SIN_DECLARAR => 'no tiene definido cómo viaja (ficha del producto → «Cómo viaja»)',
            self::MOTIVO_BULTO_INACTIVO => 'su tipo de bulto está desactivado',
        ];
    }
}
