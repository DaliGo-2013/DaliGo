@php
    /** @var \App\Models\OrdenServicioCotizacion $cotizacion */
    $orden = $cotizacion->orden;
    // Dónde retirar: la sucursal de la orden. Si la orden entró por ruta (sin
    // sucursal), el equipo coordina por teléfono — la carta no inventa un lugar.
    $lugar = $orden->sucursal?->nombre;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Retiro de tu equipo</title>
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

                    {{-- Cuerpo: carta formal --}}
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:22px; color:#171717;">Retiro de tu equipo</h1>
                            <p style="margin:0 0 4px; font-size:13px; color:#a3a3a3;">Orden {{ $orden->folio }} · {{ now()->format('d-m-Y') }}</p>
                            <p style="margin:16px 0 20px; font-size:15px; color:#525252; line-height:1.6;">
                                Estimado(a) {{ $orden->cliente_nombre }}:<br>
                                Registramos tu decisión de no realizar la reparación cotizada para tu
                                {{ mb_strtolower($orden->tipo_equipo_label) }}@if ($orden->numero_serie) (N° de serie {{ $orden->numero_serie }})@endif.
                                Tu equipo ya está disponible para que lo retires.
                            </p>

                            {{-- Dónde y qué llevar --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717; border:1px solid #e5e5e5; border-radius:8px; margin:0 0 20px;">
                                <tr>
                                    <td style="padding:10px 14px; background-color:#fafafa; color:#737373; font-size:12px; text-transform:uppercase; letter-spacing:1px;" colspan="2">Datos del retiro</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#737373; width:40%;">Dónde</td>
                                    <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717;">
                                        @if ($lugar) {{ $lugar }} @else Coordinaremos contigo el punto de entrega. @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#737373;">Qué llevar</td>
                                    <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717;">El número de orden {{ $orden->folio }}.</td>
                                </tr>
                            </table>

                            <p style="margin:0; font-size:14px; color:#525252; line-height:1.6;">
                                Si cambias de opinión, responde este correo y con gusto te enviamos la cotización de nuevo.
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
