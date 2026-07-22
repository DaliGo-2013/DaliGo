@php
    /** @var \App\Models\AgendaTrabajo $trabajo */
    $t = $trabajo;
    $cuando = $t->fecha?->translatedFormat('l d \d\e F');
    $hora = $t->hora_corta ? ($t->hora_fin_corta && $t->hora_fin_corta !== $t->hora_corta
        ? $t->hora_corta.' a '.$t->hora_fin_corta.' hrs' : 'a las '.$t->hora_corta.' hrs') : null;
    // Sin link de confirmar = se agendó el día que el cliente pidió → informativo.
    $agendadaInformativa = $motivo === 'agendada' && ! $urlConfirmar;
    $titulos = [
        'agendada' => $agendadaInformativa ? 'Tu visita quedó agendada' : 'Confirmación de tu visita',
        'reprogramada' => 'Cambiamos la fecha de tu visita',
        'anulada' => 'Tu visita fue cancelada',
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulos[$motivo] ?? 'Tu visita' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5; font-family:Arial,Helvetica,sans-serif; color:#171717;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e5e5e5; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#EA580C; padding:24px 32px;">
                            <span style="display:inline-block; width:36px; height:36px; line-height:36px; text-align:center; background-color:#ffffff; color:#EA580C; font-weight:bold; border-radius:8px; font-size:18px;">D</span>
                            <span style="color:#ffffff; font-size:18px; font-weight:bold; vertical-align:middle; margin-left:8px;">DaliGo · Servicio Técnico</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:22px; color:#171717;">{{ $titulos[$motivo] ?? 'Tu visita' }}</h1>
                            <p style="margin:0 0 20px; font-size:15px; color:#525252; line-height:1.6;">
                                Estimado(a) {{ $t->cliente_nombre }}:
                                @if ($motivo === 'anulada')
                                    @if ($t->fecha)
                                        lamentablemente tuvimos que <strong>cancelar</strong> la visita que teníamos coordinada.
                                    @else
                                        lamentablemente <strong>no podremos realizar</strong> el servicio que solicitaste ({{ mb_strtolower($t->tipo_label) }}).
                                    @endif
                                    @if (filled($t->motivo_cancelacion))
                                        <br><strong>Motivo:</strong> {{ $t->motivo_cancelacion }}.
                                    @endif
                                    <br>Si tienes dudas o quieres revisar otra alternativa, contáctanos.
                                @elseif ($motivo === 'reprogramada')
                                    tuvimos que <strong>cambiar la fecha</strong> de tu visita. Estos son los nuevos datos:
                                @elseif ($agendadaInformativa)
                                    agendamos tu {{ mb_strtolower($t->tipo_label) }} para el <strong>día que pediste</strong>. Estos son los datos:
                                @else
                                    coordinamos tu {{ mb_strtolower($t->tipo_label) }}. Estos son los datos:
                                @endif
                            </p>

                            @if ($motivo !== 'anulada')
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#171717; margin:0 0 20px;">
                                    <tr>
                                        <td style="padding:8px 0; color:#737373; width:35%; border-bottom:1px solid #f5f5f5;">Trabajo</td>
                                        <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $t->tipo_label }}@if ($t->servicio) · {{ $t->servicio->nombre }} @endif</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px 0; color:#737373; border-bottom:1px solid #f5f5f5;">Día</td>
                                        <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5; text-transform:capitalize;">{{ $cuando }}</td>
                                    </tr>
                                    @if ($hora)
                                        <tr>
                                            <td style="padding:8px 0; color:#737373; border-bottom:1px solid #f5f5f5;">Horario</td>
                                            <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $hora }}</td>
                                        </tr>
                                    @endif
                                    @if ($t->tecnico)
                                        <tr>
                                            <td style="padding:8px 0; color:#737373; border-bottom:1px solid #f5f5f5;">Técnico</td>
                                            <td style="padding:8px 0; color:#171717; border-bottom:1px solid #f5f5f5;">{{ $t->tecnico->name }}</td>
                                        </tr>
                                    @endif
                                    @if ($t->direccion || $t->ciudad)
                                        <tr>
                                            <td style="padding:8px 0; color:#737373;">Dirección</td>
                                            <td style="padding:8px 0; color:#171717;">{{ collect([$t->direccion, $t->ciudad])->filter()->implode(', ') }}</td>
                                        </tr>
                                    @endif
                                </table>
                            @endif

                            @if ($urlConfirmar)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $urlConfirmar }}"
                                               style="display:inline-block; background-color:#EA580C; color:#ffffff; font-size:16px; font-weight:bold; text-decoration:none; padding:14px 32px; border-radius:10px;">
                                                Confirmar si puedo ese día
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0; font-size:13px; color:#525252; line-height:1.5; text-align:center;">
                                    Con un clic nos confirmas que estarás, o nos avisas si <strong>no puedes ese día</strong> para reagendar.
                                </p>
                            @endif

                            @if ($agendadaInformativa)
                                <p style="margin:0; font-size:14px; color:#525252; line-height:1.6;">
                                    ¡Nos vemos ese día! Si necesitas cambiarlo, responde este correo o llámanos.
                                </p>
                            @endif
                        </td>
                    </tr>
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
