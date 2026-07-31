@php
    /** @var \App\Models\OrdenServicio $orden */
    // Donde retira: la sucursal, o la ruta si entro por un retiro en ruta.
    $donde = $orden->sucursal?->nombre ?: ($orden->ruta ? 'nuestra ruta de '.$orden->ruta : null);

    // La frase se arma ACA y no con un @if inline: `retires@if ($donde)` NO compila
    // —Blade exige un no-caracter-de-palabra antes de la arroba (gotcha 24-07)— y
    // pegar el @if con un espacio dejaba un espacio suelto antes del punto final.
    $fraseRetiro = $donde
        ? 'Tu equipo está disponible para que lo retires en <strong>'.e($donde).'</strong>.'
        : 'Tu equipo está disponible para que lo retires.';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sobre la revisión de tu equipo</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5; font-family:Arial,Helvetica,sans-serif; color:#171717;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e5e5e5; border-radius:16px; overflow:hidden;">
                    {{-- Encabezado (idéntico al resto del flujo al cliente) --}}
                    <tr>
                        <td style="background-color:#EA580C; padding:24px 32px;">
                            <span style="display:inline-block; width:36px; height:36px; line-height:36px; text-align:center; background-color:#ffffff; color:#EA580C; font-weight:bold; border-radius:8px; font-size:18px;">D</span>
                            <span style="color:#ffffff; font-size:18px; font-weight:bold; vertical-align:middle; margin-left:8px;">DaliGo · Servicio Técnico</span>
                        </td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:22px; color:#171717;">Sobre la revisión de tu equipo</h1>
                            <p style="margin:0 0 4px; font-size:13px; color:#a3a3a3;">Orden {{ $orden->folio }} · {{ now()->format('d-m-Y') }}</p>

                            <p style="margin:16px 0 20px; font-size:15px; color:#525252; line-height:1.6;">
                                Estimado(a) {{ $orden->cliente_nombre }}:<br>
                                Revisamos tu {{ mb_strtolower($orden->tipo_equipo_label) }}@if ($orden->numero_serie) (N° de serie {{ $orden->numero_serie }})@endif
                                y lamentablemente <strong>no fue posible repararlo</strong>.
                            </p>

                            {{-- Qué se revisó. Va la falla que reportó el CLIENTE (la reconoce
                                 como suya) y, si el técnico dejó detalle de lo que probó, también:
                                 es lo que hace que el correo no parezca una respuesta automática.
                                 El DIAGNÓSTICO (causa de la falla) NO va aquí a propósito — ver el
                                 docblock de App\Mail\SinSolucionCliente. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717; margin:0 0 20px;">
                                @if (filled($orden->falla_reportada))
                                    <tr>
                                        <td style="padding:8px 0; color:#737373; width:40%; border-bottom:1px solid #f5f5f5; vertical-align:top;">Lo que nos contaste</td>
                                        <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $orden->falla_reportada }}</td>
                                    </tr>
                                @endif
                                @if (filled($orden->trabajo_realizado))
                                    <tr>
                                        <td style="padding:8px 0; color:#737373; width:40%; vertical-align:top;">Lo que revisamos</td>
                                        <td style="padding:8px 0; color:#171717;">{{ $orden->trabajo_realizado }}</td>
                                    </tr>
                                @endif
                            </table>

                            {{-- Qué pasa ahora. Sin verde (la paleta lo reserva) y sin rojo: es
                                 una mala noticia, no un error del sistema. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                                <tr>
                                    <td style="background-color:#fafafa; border:1px solid #e5e5e5; border-radius:12px; padding:16px 20px;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#737373; margin-bottom:6px;">Qué sigue</div>
                                        <div style="font-size:15px; color:#171717; line-height:1.6;">
                                            {!! $fraseRetiro !!}
                                            Nos vamos a contactar contigo para coordinarlo y contarte las alternativas que tienes.
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:20px 0 0; font-size:14px; color:#525252; line-height:1.6;">
                                Si quieres saber más sobre la revisión, responde este correo y te explicamos.
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
