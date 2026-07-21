@php
    /** @var \App\Models\OrdenServicioCotizacion $cotizacion */
    $orden = $cotizacion->orden;
    $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cotización de reparación</title>
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
                            <h1 style="margin:0 0 8px; font-size:22px; color:#171717;">Cotización de reparación</h1>
                            <p style="margin:0 0 4px; font-size:13px; color:#a3a3a3;">Orden {{ $orden->folio }} · {{ $cotizacion->created_at->format('d-m-Y') }}</p>
                            <p style="margin:16px 0 20px; font-size:15px; color:#525252; line-height:1.6;">
                                Estimado(a) {{ $orden->cliente_nombre }}:<br>
                                Revisamos tu {{ mb_strtolower($orden->tipo_equipo_label) }}
                                @if ($orden->numero_serie) (N° de serie {{ $orden->numero_serie }}) @endif
                                y te presentamos el detalle del trabajo necesario para dejarlo funcionando.
                            </p>

                            {{-- Por qué: la falla encontrada --}}
                            @if (filled($cotizacion->causa_falla) || filled($orden->falla_reportada))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717; margin:0 0 16px;">
                                    @if (filled($orden->falla_reportada))
                                        <tr>
                                            <td style="padding:8px 0; color:#737373; width:40%; border-bottom:1px solid #f5f5f5; vertical-align:top;">Falla reportada</td>
                                            <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $orden->falla_reportada }}</td>
                                        </tr>
                                    @endif
                                    @if (filled($cotizacion->causa_falla))
                                        <tr>
                                            <td style="padding:8px 0; color:#737373; width:40%; border-bottom:1px solid #f5f5f5; vertical-align:top;">Diagnóstico del técnico</td>
                                            <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $cotizacion->causa_falla }}</td>
                                        </tr>
                                    @endif
                                    @if (filled($cotizacion->trabajo_realizado))
                                        <tr>
                                            <td style="padding:8px 0; color:#737373; width:40%; vertical-align:top;">Trabajo a realizar</td>
                                            <td style="padding:8px 0; color:#171717;">{{ $cotizacion->trabajo_realizado }}</td>
                                        </tr>
                                    @endif
                                </table>
                            @endif

                            {{-- Detalle de valores --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717; border:1px solid #e5e5e5; border-radius:8px; margin:0 0 16px;">
                                <tr>
                                    <td style="padding:10px 14px; background-color:#fafafa; color:#737373; font-size:12px; text-transform:uppercase; letter-spacing:1px;" colspan="2">Detalle</td>
                                </tr>
                                @foreach ($cotizacion->repuestos ?? [] as $r)
                                    <tr>
                                        <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717;">{{ $r['cantidad'] }}× {{ $r['nombre'] }}</td>
                                        <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717; text-align:right; white-space:nowrap;">{{ $clp($r['subtotal']) }}</td>
                                    </tr>
                                @endforeach
                                @if ($cotizacion->mano_obra > 0)
                                    <tr>
                                        <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717;">Mano de obra</td>
                                        <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#171717; text-align:right; white-space:nowrap;">{{ $clp($cotizacion->mano_obra) }}</td>
                                    </tr>
                                @endif
                                @if ($cotizacion->descuento_monto > 0)
                                    <tr>
                                        <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#15803d;">Descuento ({{ $cotizacion->descuento_pct }}%)</td>
                                        <td style="padding:8px 14px; border-top:1px solid #f5f5f5; color:#15803d; text-align:right; white-space:nowrap;">−{{ $clp($cotizacion->descuento_monto) }}</td>
                                    </tr>
                                @endif
                            </table>

                            {{-- Total destacado --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="background-color:#fff7ed; border:1px solid #fed7aa; border-radius:12px; padding:16px 20px; text-align:center;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#9a3412;">Costo total a pagar</div>
                                        <div style="font-size:28px; font-weight:bold; color:#EA580C; letter-spacing:1px;">{{ $clp($cotizacion->costo_total) }}</div>
                                    </td>
                                </tr>
                            </table>

                            {{-- Botón de respuesta --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $urlRespuesta }}"
                                           style="display:inline-block; background-color:#EA580C; color:#ffffff; font-size:16px; font-weight:bold; text-decoration:none; padding:14px 32px; border-radius:10px;">
                                            Revisar y responder
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 4px; font-size:13px; color:#525252; line-height:1.5; text-align:center;">
                                En el link puedes indicar si <strong>aceptas</strong> o <strong>no aceptas</strong> esta cotización.
                            </p>
                            @if ($cotizacion->vence_at)
                                <p style="margin:0; font-size:13px; color:#a3a3a3; line-height:1.5; text-align:center;">
                                    Cotización válida hasta el {{ $cotizacion->vence_at->format('d-m-Y') }}.
                                </p>
                            @endif
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
