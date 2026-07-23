@php
    /** @var \App\Models\OrdenServicio $orden */
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de su servicio</title>
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
                            <h1 style="margin:0 0 8px; font-size:22px; color:#171717;">Detalle de su servicio</h1>
                            <p style="margin:0 0 4px; font-size:13px; color:#a3a3a3;">Orden {{ $orden->folio }} · {{ now()->format('d-m-Y') }}</p>
                            <p style="margin:16px 0 20px; font-size:15px; color:#525252; line-height:1.6;">
                                Estimado(a) {{ $orden->cliente_nombre }}:<br>
                                Revisamos su {{ mb_strtolower($orden->tipo_equipo_label) }}
                                @if ($orden->numero_serie) (N° de serie {{ $orden->numero_serie }}) @endif
                                y le informamos el detalle del trabajo realizado.
                            </p>

                            {{-- Qué se hizo --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717; margin:0 0 16px;">
                                @if (filled($orden->falla_reportada))
                                    <tr>
                                        <td style="padding:8px 0; color:#737373; width:40%; border-bottom:1px solid #f5f5f5; vertical-align:top;">Falla reportada</td>
                                        <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $orden->falla_reportada }}</td>
                                    </tr>
                                @endif
                                @if (filled($orden->causa_falla))
                                    <tr>
                                        <td style="padding:8px 0; color:#737373; width:40%; border-bottom:1px solid #f5f5f5; vertical-align:top;">Diagnóstico del técnico</td>
                                        <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $orden->causa_falla_label }}</td>
                                    </tr>
                                @endif
                                @if (filled($orden->trabajo_realizado))
                                    <tr>
                                        <td style="padding:8px 0; color:#737373; width:40%; vertical-align:top;">Trabajo realizado</td>
                                        <td style="padding:8px 0; color:#171717;">{{ $orden->trabajo_realizado }}</td>
                                    </tr>
                                @endif
                            </table>

                            {{-- Repuestos usados (sin precios) --}}
                            @if ($orden->repuestos->isNotEmpty())
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717; border:1px solid #e5e5e5; border-radius:8px; margin:0 0 16px;">
                                    <tr>
                                        <td style="padding:10px 14px; background-color:#fafafa; color:#737373; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Repuestos usados</td>
                                    </tr>
                                    @foreach ($orden->repuestos as $r)
                                        <tr>
                                            <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717;">{{ $r->cantidad }}× {{ $r->nombre }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            {{-- Sin costo (garantía) --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                                <tr>
                                    <td style="background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:16px 20px; text-align:center;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#15803d;">Sin costo</div>
                                        <div style="font-size:18px; font-weight:bold; color:#15803d;">Este servicio está cubierto por la garantía.</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="background-color:#fafafa; padding:16px 32px; text-align:center; font-size:12px; color:#a3a3a3; border-top:1px solid #e5e5e5;">
                            DaliGo · {{ now()->year }} — Si tienes dudas, responde este correo o llámanos.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
