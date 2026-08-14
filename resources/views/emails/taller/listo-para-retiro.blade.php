@php
    /** @var \App\Models\OrdenServicio $orden */
    /** @var \App\Models\OrdenServicioCotizacion|null $cotizacion */
    $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
    // Dónde retirar: la sucursal de la orden. Sin sucursal (entró por ruta) la
    // carta NO inventa un lugar: se coordina.
    $lugar = $orden->sucursal?->nombre;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu equipo está listo</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5; font-family:Arial,Helvetica,sans-serif; color:#171717;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e5e5e5; border-radius:16px; overflow:hidden;">
                    {{-- Encabezado --}}
                    <tr>
                        <td style="background-color:#EA580C; padding:24px 32px;">
                            <span style="display:inline-block; width:36px; height:36px; line-height:36px; text-align:center; background-color:#ffffff; color:#EA580C; font-weight:bold; border-radius:8px; font-size:18px;">D</span>
                            <span style="color:#ffffff; font-size:18px; font-weight:bold; vertical-align:middle; margin-left:8px;">DaliGo · Servicio Técnico</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:22px; color:#171717;">Tu equipo está listo</h1>
                            <p style="margin:0 0 4px; font-size:13px; color:#a3a3a3;">Orden {{ $orden->folio }} · {{ now()->format('d-m-Y') }}</p>
                            <p style="margin:16px 0 20px; font-size:15px; color:#525252; line-height:1.6;">
                                Estimado(a) {{ $orden->cliente_nombre }}:<br>
                                Terminamos el trabajo en tu {{ mb_strtolower($orden->tipo_equipo_label) }}@if ($orden->numero_serie) (N° de serie {{ $orden->numero_serie }})@endif
                                y ya puedes pasar a retirarlo.
                            </p>

                            @if (filled($orden->trabajo_realizado))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; margin:0 0 16px;">
                                    <tr>
                                        <td style="padding:8px 0; color:#737373; width:40%; vertical-align:top;">Trabajo realizado</td>
                                        <td style="padding:8px 0; color:#171717;">{{ $orden->trabajo_realizado }}</td>
                                    </tr>
                                </table>
                            @endif

                            {{-- Qué paga y dónde: el monto es el que el cliente ACEPTÓ. --}}
                            @if ($esGarantia)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                    <tr>
                                        <td style="background-color:#fafafa; border:1px solid #e5e5e5; border-radius:12px; padding:16px 20px; text-align:center;">
                                            <div style="font-size:15px; color:#171717;"><strong>Sin costo</strong> — cubierto por la garantía.</div>
                                        </td>
                                    </tr>
                                </table>
                            @elseif ($cotizacion)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                    <tr>
                                        <td style="background-color:#fff7ed; border:1px solid #fed7aa; border-radius:12px; padding:16px 20px; text-align:center;">
                                            <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#9a3412;">Total a pagar (IVA incluido)</div>
                                            <div style="font-size:28px; font-weight:bold; color:#EA580C; letter-spacing:1px;">{{ $clp($cotizacion->costo_total) }}</div>
                                            <div style="font-size:13px; color:#9a3412;">El pago se realiza en sala de ventas al retirar.</div>
                                        </td>
                                    </tr>
                                </table>
                            @else
                                <p style="margin:0 0 20px; font-size:14px; color:#525252; line-height:1.6;">
                                    El detalle del costo lo coordinas en sala de ventas al momento del retiro.
                                </p>
                            @endif

                            {{-- Datos del retiro --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717; border:1px solid #e5e5e5; border-radius:8px; margin:0 0 20px;">
                                <tr>
                                    <td style="padding:10px 14px; background-color:#fafafa; color:#737373; font-size:12px; text-transform:uppercase; letter-spacing:1px;" colspan="2">Datos del retiro</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#737373; width:40%;">Dónde</td>
                                    <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717;">
                                        {{-- El equipo se entrega en BODEGA (dueño 14-08): decirlo acá
                                             y no solo en el texto de abajo es lo que evita que el
                                             cliente entre por sala de ventas a preguntar. --}}
                                        @if ($lugar) {{ $lugar }} — <strong>en bodega</strong> @else Coordinaremos contigo el punto de entrega. @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#737373;">Qué llevar</td>
                                    <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717;">El número de orden {{ $orden->folio }}.</td>
                                </tr>
                            </table>

                            {{-- EL ORDEN DEL RETIRO, cambiado el 14-08-2026 por el dueño: «se retira
                                 en bodega el dispensador, primero pasa por bodega para corroborar sus
                                 datos y luego a pagar por sala de ventas». Antes decía lo contrario
                                 (primero sala de ventas), así que el cliente entraba por la puerta
                                 equivocada y lo mandaban a caminar.

                                 En garantía no hay pago: el paso por sala de ventas no aplica y no se
                                 le nombra, para no hacerlo buscar un mostrador al que no tiene que ir. --}}
                            <p style="margin:0 0 16px; font-size:14px; color:#525252; line-height:1.6;">
                                @if ($esGarantia)
                                    Pasa por <strong>bodega</strong>, donde corroboramos tus datos y te entregamos el equipo.
                                    No tienes que pasar por sala de ventas: este trabajo va sin costo.
                                @else
                                    Pasa primero por <strong>bodega</strong> para corroborar tus datos, después por
                                    <strong>sala de ventas</strong> para el pago, y con el pago hecho retiras el equipo en bodega.
                                @endif
                            </p>

                            {{-- LA GARANTÍA DE LA REPARACIÓN (dueño 14-08): tres meses desde la fecha
                                 de reparación. Va con la fecha de VENCIMIENTO calculada y no solo con
                                 «tres meses»: el cliente guarda este correo y así no tiene que contar
                                 los meses ni discutir desde cuándo se cuentan.

                                 Es la garantía del TRABAJO y no la del producto (esa es de 6 meses
                                 desde la compra, ver OrdenServicio::GARANTIA_MESES). --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
                                <tr>
                                    <td style="background-color:#fafafa; border:1px solid #e5e5e5; border-radius:12px; padding:14px 18px; font-size:14px; color:#525252; line-height:1.6;">
                                        <strong style="color:#171717;">Garantía de la reparación:
                                        {{ $garantiaMeses }} meses</strong><br>
                                        Corre desde la fecha de la reparación ({{ $garantiaDesde->format('d-m-Y') }})
                                        y vence el {{ $garantiaVence->format('d-m-Y') }}. Guarda este correo: es tu respaldo.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; font-size:14px; color:#525252; line-height:1.6;">
                                Si tienes dudas, responde este correo.
                            </p>
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="background-color:#fafafa; padding:16px 32px; text-align:center; font-size:12px; color:#a3a3a3; border-top:1px solid #e5e5e5;">
                            DaliGo · {{ now()->year }} — Si tienes dudas, responde este correo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
