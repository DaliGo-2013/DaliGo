<?php

namespace App\Services\Dte;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioRepuesto;
use App\Models\User;

/**
 * Convierte una orden de Servicio Técnico en el documento tributario a emitir
 * (M05 · el "armador").
 *
 * Es la pieza que faltaba entre las dos que ya existían: la orden de servicio
 * (con sus repuestos, su mano de obra y su total) y el traductor a Bsale. Acá es
 * donde las reglas que definió Contabilidad el 28-jul-2026 se aplican sobre datos
 * reales y no sobre ejemplos.
 *
 * NO emite: devuelve un DocumentoTributario. Emitir es de EmisionDte, y encima
 * está el candado (CandadoDeEmision). Esta clase se puede llamar sin ningún
 * riesgo, y de hecho es lo que permite el "ensayo en seco": armar el documento y
 * mirarlo sin mandarlo a ninguna parte.
 *
 * Las reglas aplicadas, y dónde:
 *
 *   Regla 1 (el total manda) → DesgloseNeto reparte el total CON IVA en netos por
 *   línea, y el residuo del redondeo se carga EXPLÍCITAMENTE a la línea de mano de
 *   obra. Nunca se recalcula el total desde los netos.
 *
 *   Regla 4 (desglose) → una línea por repuesto + una de mano de obra. El repuesto
 *   lleva su SKU de catálogo si el técnico lo eligió del buscador; si lo escribió a
 *   mano va como glosa libre (Bsale lo admite: es su "Nueva Glosa"). La mano de
 *   obra lleva el SKU 9771001 de `config('servicio_tecnico.sku_hora_servicio')`.
 *
 *   Regla 5 (garantía) → una orden en garantía NO se factura. Se rechaza acá, con
 *   un mensaje que dice qué corresponde en su lugar.
 *
 *   Regla 7 (sucursal) → va la sucursal de la orden. OJO con lo que esto significa
 *   hoy: `sucursal_id` es la sucursal de RECEPCIÓN, y Contabilidad pidió emitir
 *   desde donde se REPARA (ver la nota en sucursalDeEmision()).
 *
 *   Regla 8 (pago al emitir) → la forma de pago es un parámetro obligatorio de
 *   quien emite; no se adivina de la cotización.
 */
class DocumentoDesdeOrdenServicio
{
    /**
     * @param  int  $tipoDte  Boleta o factura: lo elige quien atiende (regla 2).
     * @param  string|null  $formaPago  Constante de FormaPago.
     *
     * @throws EmisionException si la orden no se puede facturar.
     */
    public function armar(
        OrdenServicio $orden,
        int $tipoDte,
        ?string $formaPago = null,
        ?User $emitidoPor = null,
    ): DocumentoTributario {
        $this->verificarQueSePuedaFacturar($orden, $tipoDte);

        $orden->loadMissing('repuestos');

        [$lineas, $indiceManoObra] = $this->lineasEnBruto($orden);

        if ($lineas === []) {
            throw new EmisionException(
                "La orden {$orden->codigo} no tiene nada que cobrar: sin repuestos ni mano de obra "
                .'no se puede emitir un documento.'
            );
        }

        // Regla 1: el total que paga el cliente es la cifra autoritativa, y el
        // ajuste del redondeo va a la mano de obra (no a "la línea mayor", que es
        // solo el default de DesgloseNeto).
        $brutos = array_map(fn (array $l) => $l['bruto'], $lineas);
        $desglose = DesgloseNeto::repartir($brutos, ajustarEn: $indiceManoObra);

        $cliente = $this->clienteDe($orden);

        return new DocumentoTributario(
            tipoDte: $tipoDte,
            salesId: $this->salesId($orden),
            lineas: $this->lineasDelDocumento($lineas, $desglose['netos'], $desglose['neto'], $indiceManoObra),
            receptorRut: $orden->cliente_rut,
            receptorNombre: $orden->cliente_nombre,
            receptorGiro: $cliente?->giro,
            receptorDireccion: $cliente?->direccion,
            receptorComuna: $cliente?->comuna,
            receptorCiudad: $cliente?->ciudad,
            receptorEmail: $cliente?->email,
            observacion: $this->observacion($orden),
            formaPago: $formaPago,
            // El total con IVA de la orden, tal cual. Ver DesgloseNeto.
            totalConIva: $desglose['total'],
            origen: [
                'orden_servicio_id' => $orden->id,
                'sucursal_id' => $this->sucursalDeEmision($orden),
                'emitido_por' => $emitidoPor?->id,
            ],
        );
    }

    /**
     * Clave de idempotencia: el CÓDIGO de la orden, que ya viene con la forma
     * ST-XXXXXXXX y es único y estable. Así reintentar la emisión de la misma
     * orden no puede producir dos documentos, ni acá ni en Bsale.
     *
     * Sin prefijo extra: agregarle "ST-" daba `ST-ST-XXXXXXXX`, que además de feo
     * es el identificador que queda guardado en Bsale para siempre.
     *
     * La nota de crédito de esta orden llevará su propia clave (`NC-{código}`):
     * una orden puede terminar con una boleta y después su anulación, pero no con
     * dos boletas.
     */
    public function salesId(OrdenServicio $orden): string
    {
        return (string) $orden->codigo;
    }

    /**
     * Reglas 3 y 5: la orden tiene que ser cobrable.
     */
    private function verificarQueSePuedaFacturar(OrdenServicio $orden, int $tipoDte): void
    {
        if ($orden->condicion_efectiva === 'garantia') {
            throw new EmisionException(
                "La orden {$orden->codigo} está en GARANTÍA: no se cobra, así que no corresponde boleta "
                .'ni factura. Al devolver el equipo corresponde una guía de despacho por traslado que no '
                .'constituye venta (regla 5 de Contabilidad).'
            );
        }

        // Regla 3: en servicio técnico no hay nada exento. Si alguien pide un tipo
        // exento es un error de configuración, no una operación válida.
        if (in_array($tipoDte, [DocumentoTributario::FACTURA_EXENTA, DocumentoTributario::BOLETA_EXENTA], true)) {
            throw new EmisionException(
                'En servicio técnico no hay ventas exentas de IVA (regla 3 de Contabilidad): '
                .'no corresponde un documento exento.'
            );
        }

        if ((int) $orden->costo_total <= 0) {
            throw new EmisionException(
                "La orden {$orden->codigo} tiene total $0: no hay nada que facturar."
            );
        }
    }

    /**
     * Las líneas con su monto CON IVA (como está guardado), antes de convertir a
     * neto. Devuelve [líneas, índice de la línea de mano de obra].
     *
     * El descuento se aplica ACÁ, repartido sobre las líneas, y no viaja como
     * campo de descuento al emisor. Razón: el descuento de la orden es un
     * porcentaje sobre el total, y si el emisor lo recalculara línea por línea su
     * total podría diferir en pesos del que el cliente aceptó — que es justo lo
     * que la regla 1 prohíbe. El motivo del descuento queda en la observación del
     * documento para que sea visible.
     *
     * ⬜ PENDIENTE de Contabilidad: si el descuento debe aparecer DESGLOSADO en el
     * documento impreso o alcanza con el precio ya rebajado. Hoy se hace lo
     * segundo, que es lo que garantiza que el total cuadre.
     *
     * @return array{0: list<array{bruto:int,descripcion:string,cantidad:int,sku:?string}>, 1: int|null}
     */
    private function lineasEnBruto(OrdenServicio $orden): array
    {
        $lineas = [];

        foreach ($orden->repuestos as $repuesto) {
            $bruto = (int) $repuesto->subtotal;
            if ($bruto <= 0) {
                continue;
            }

            $lineas[] = [
                'bruto' => $bruto,
                'descripcion' => (string) $repuesto->nombre,
                'cantidad' => max(1, (int) $repuesto->cantidad),
                'sku' => $this->skuDe($repuesto),
            ];
        }

        $indiceManoObra = null;
        $manoObra = (int) ($orden->mano_obra ?? 0);

        if ($manoObra > 0) {
            $indiceManoObra = count($lineas);
            $lineas[] = [
                'bruto' => $manoObra,
                // Res. SII 36/2024: la glosa impresa es el producto real, sin
                // abreviaturas ni códigos internos.
                'descripcion' => 'Hora servicio técnico',
                'cantidad' => 1,
                'sku' => (string) config('servicio_tecnico.sku_hora_servicio') ?: null,
            ];
        }

        // El descuento se reparte proporcionalmente; el residuo lo absorbe después
        // DesgloseNeto al cuadrar contra el total de la orden.
        $pct = (int) ($orden->descuento_pct ?? 0);
        if ($pct > 0) {
            foreach ($lineas as $i => $linea) {
                $lineas[$i]['bruto'] = (int) round($linea['bruto'] * (100 - $pct) / 100);
            }
        }

        // Si no hubo mano de obra, el ajuste del redondeo cae en la línea mayor
        // (default de DesgloseNeto).
        return [array_values($lineas), $indiceManoObra];
    }

    /**
     * Convierte los netos POR LÍNEA en precios netos POR UNIDAD, que es lo que el
     * emisor espera (multiplica unitario × cantidad para rearmar la línea).
     *
     * ⚠️ ACÁ SE ESCONDÍA UN PESO, y es la razón por la que este método es más largo
     * de lo que parece necesario. Dividir el neto de la línea por la cantidad y
     * redondear **rompe el total**: una línea de 2 unidades con neto 7.563 da
     * unitario 3.782 (3.781,5 redondeado hacia arriba) y al multiplicar vuelve
     * 7.564. Un peso de más que el emisor va a cobrar y el cliente no pagó — el
     * mismo descuadre que DesgloseNeto existe para evitar, reintroducido un paso
     * después. Se cazó viendo la pantalla, no leyendo el código.
     *
     * La corrección: después de calcular los unitarios se recalcula cuánto suman
     * DE VERDAD (unitario × cantidad) y la diferencia contra el neto autoritativo
     * se absorbe en una línea de cantidad 1 — donde ajustar el unitario mueve el
     * total exactamente en esa cifra. Se prefiere la de mano de obra, que es donde
     * Contabilidad quiere el ajuste y que en una reparación casi siempre existe.
     *
     * @param  list<array{bruto:int,descripcion:string,cantidad:int,sku:?string}>  $lineas
     * @param  list<int>  $netos  Neto por línea (ya cuadran contra el total).
     * @param  int  $netoDocumento  Neto autoritativo del documento.
     * @return list<LineaDocumento>
     */
    private function lineasDelDocumento(array $lineas, array $netos, int $netoDocumento, ?int $indiceManoObra): array
    {
        $unitarios = [];
        foreach ($lineas as $i => $linea) {
            $unitarios[$i] = (int) round(($netos[$i] ?? 0) / max(1, $linea['cantidad']));
        }

        $sumaReal = 0;
        foreach ($lineas as $i => $linea) {
            $sumaReal += $unitarios[$i] * $linea['cantidad'];
        }

        $residuo = $netoDocumento - $sumaReal;
        if ($residuo !== 0 && ($absorbe = $this->lineaQueAbsorbe($lineas, $indiceManoObra)) !== null) {
            $unitarios[$absorbe] += $residuo;
        }

        $documento = [];
        foreach ($lineas as $i => $linea) {
            $documento[] = new LineaDocumento(
                descripcion: $linea['descripcion'],
                cantidad: $linea['cantidad'],
                precioNetoUnitario: $unitarios[$i],
                codigoProducto: $linea['sku'],
            );
        }

        return $documento;
    }

    /**
     * Índice de la línea que puede absorber el residuo: tiene que ser de cantidad
     * 1, porque en una de cantidad 3 un ajuste de $1 en el unitario mueve el total
     * en $3. Se prefiere la de mano de obra.
     *
     * Null si ninguna línea tiene cantidad 1 (todas las líneas de varias unidades y
     * sin mano de obra). En ese caso el neto del documento puede quedar a unos pesos
     * del ideal; el total con IVA sigue siendo el que pagó el cliente, porque el IVA
     * se calcula como la diferencia. Es un caso raro y explícito, no un silencio.
     *
     * @param  list<array{cantidad:int}>  $lineas
     */
    private function lineaQueAbsorbe(array $lineas, ?int $indiceManoObra): ?int
    {
        if ($indiceManoObra !== null && ($lineas[$indiceManoObra]['cantidad'] ?? 0) === 1) {
            return $indiceManoObra;
        }

        foreach ($lineas as $i => $linea) {
            if ($linea['cantidad'] === 1) {
                return $i;
            }
        }

        return null;
    }

    /**
     * SKU del repuesto. Null cuando el técnico lo escribió a mano en vez de
     * elegirlo del catálogo: esa línea va como glosa libre, que es un caso
     * legítimo y no un error.
     */
    private function skuDe(OrdenServicioRepuesto $repuesto): ?string
    {
        $sku = trim((string) ($repuesto->sku ?? ''));

        return $sku === '' ? null : $sku;
    }

    /**
     * Ficha del cliente, para los datos que la FACTURA exige y la orden no guarda
     * (giro, dirección, comuna). Se busca por RUT porque la orden guarda el nombre
     * y el RUT desnormalizados, sin enlace.
     *
     * Null si el cliente no tiene ficha: para una boleta no hace falta, y si es
     * factura el emisor va a rechazarla con un mensaje claro (falta el giro).
     */
    private function clienteDe(OrdenServicio $orden): ?Cliente
    {
        if (blank($orden->cliente_rut)) {
            return null;
        }

        return Cliente::where('rut', $orden->cliente_rut)->first();
    }

    /**
     * Sucursal desde la que se emite.
     *
     * ⚠️ OJO, ACÁ HAY UNA DEUDA CONOCIDA. Contabilidad pidió emitir desde donde se
     * REPARA (regla 7), y `orden_servicios.sucursal_id` es la sucursal de
     * RECEPCIÓN — donde el cliente entregó el equipo. En la operación actual casi
     * siempre coinciden porque la reparación se hace en Mirador, así que un equipo
     * recibido en Coquimbo se repara en Mirador y debería facturarse por Mirador.
     *
     * No se resuelve inventando: la orden no guarda hoy dónde se reparó. Mientras
     * no exista ese dato se emite con la sucursal de la orden, y queda anotado como
     * pendiente. Es exactamente el tipo de hueco que este armador existe para
     * destapar antes de la primera emisión.
     */
    private function sucursalDeEmision(OrdenServicio $orden): ?int
    {
        return $orden->sucursal_id;
    }

    /** Referencia legible en el documento: de qué orden salió, y el descuento si hubo. */
    private function observacion(OrdenServicio $orden): string
    {
        $partes = ["Orden de servicio {$orden->codigo}"];

        if ($orden->numero_serie) {
            $partes[] = "N° serie {$orden->numero_serie}";
        }

        $pct = (int) ($orden->descuento_pct ?? 0);
        if ($pct > 0) {
            $motivo = $orden->descuento_motivo_label;
            $partes[] = "Descuento {$pct}%".($motivo ? " ({$motivo})" : '');
        }

        return implode(' · ', $partes);
    }
}
