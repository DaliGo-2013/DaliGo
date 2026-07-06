@php
    $tipo = ucfirst($orden->tipo_equipo);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibimos tu equipo</title>
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

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:22px; color:#171717;">¡Recibimos tu equipo!</h1>
                            <p style="margin:0 0 20px; font-size:15px; color:#525252; line-height:1.5;">
                                Hola {{ $orden->cliente_nombre }}, tu {{ mb_strtolower($tipo) }} quedó ingresado en nuestro
                                taller. Guarda este correo: el número de folio te sirve para consultar el estado.
                            </p>

                            {{-- Folio destacado --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="background-color:#fff7ed; border:1px solid #fed7aa; border-radius:12px; padding:16px 20px; text-align:center;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#9a3412;">Tu folio</div>
                                        <div style="font-size:28px; font-weight:bold; color:#EA580C; letter-spacing:1px;">{{ $orden->folio }}</div>
                                    </td>
                                </tr>
                            </table>

                            {{-- Detalle --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717;">
                                @php
                                    $filas = [
                                        'Equipo' => $tipo,
                                        'Código' => $orden->producto ? $orden->producto->sku.' — '.$orden->producto->nombre : null,
                                        'N° de serie' => $orden->numero_serie,
                                        'Sucursal' => $orden->sucursal?->nombre,
                                        'Fecha de ingreso' => $orden->fecha_ingreso?->format('d-m-Y'),
                                        'Entrega estimada' => $orden->fecha_entrega?->format('d-m-Y'),
                                        'RUT' => $orden->cliente_rut,
                                        'Teléfono' => $orden->cliente_telefono,
                                    ];
                                @endphp
                                @foreach ($filas as $etiqueta => $valor)
                                    @if (filled($valor))
                                        <tr>
                                            <td style="padding:8px 0; color:#737373; width:40%; border-bottom:1px solid #f5f5f5;">{{ $etiqueta }}</td>
                                            <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $valor }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr>
                                    <td style="padding:8px 0; color:#737373; vertical-align:top;">Falla reportada</td>
                                    <td style="padding:8px 0; color:#171717;">{{ $orden->falla_reportada }}</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; font-size:13px; color:#a3a3a3; line-height:1.5;">
                                La fecha de entrega es estimada (días hábiles) y puede variar según el diagnóstico.
                                Te avisaremos cuando el equipo esté listo para retirar.
                            </p>
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="background-color:#fafafa; padding:16px 32px; text-align:center; font-size:12px; color:#a3a3a3; border-top:1px solid #e5e5e5;">
                            DaliGo · {{ now()->year }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
