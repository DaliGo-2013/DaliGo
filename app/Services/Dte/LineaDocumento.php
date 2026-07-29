<?php

namespace App\Services\Dte;

/**
 * Una línea de detalle de un documento tributario.
 *
 * El precio va NETO y en pesos enteros: el IVA lo calcula el emisor a partir
 * del impuesto asociado al producto (así lo hace Bsale, y así lo exige el SII
 * al desglosar). Guardar "precio con IVA" aquí obligaría a desarmarlo después y
 * es la fuente clásica de descuadres de $1 por redondeo.
 *
 * `codigoProducto` es el SKU del catálogo (en Bsale: el `code` de la variante).
 * Se manda ese y no el id interno porque el SKU es el identificador que el
 * negocio reconoce y el que ya espejamos en `productos`.
 *
 * OJO con `descripcion`: la Res. Ex. SII N°36/2024 exige describir el producto
 * sin abreviaturas ni códigos internos, así que acá va el nombre real.
 */
class LineaDocumento
{
    /**
     * @param  string  $descripcion  Nombre del producto tal como va en el DTE.
     * @param  int  $cantidad  Unidades.
     * @param  int  $precioNetoUnitario  Precio unitario NETO, en pesos enteros.
     * @param  string|null  $codigoProducto  SKU del catálogo, si la línea corresponde a un producto.
     * @param  int|null  $varianteExternaId  Id de variante en el emisor, si ya se conoce (evita búsqueda por SKU).
     * @param  float  $descuentoPct  Descuento de la línea, 0 a 100.
     * @param  string|null  $comentario  Nota libre bajo la línea.
     */
    public function __construct(
        public string $descripcion,
        public int $cantidad,
        public int $precioNetoUnitario,
        public ?string $codigoProducto = null,
        public ?int $varianteExternaId = null,
        public float $descuentoPct = 0.0,
        public ?string $comentario = null,
    ) {}

    /** Neto de la línea ya con el descuento aplicado (pesos enteros). */
    public function netoLinea(): int
    {
        $bruto = $this->precioNetoUnitario * $this->cantidad;

        return (int) round($bruto * (1 - $this->descuentoPct / 100));
    }
}
