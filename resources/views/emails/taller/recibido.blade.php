@php
    $tipo = $orden->tipo_equipo_label;
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
                                {{-- Sin adjetivo que concuerde en género: el tipo puede ser femenino
                                     (lavadora, bomba de agua, herramienta) y decía "quedó ingresado". --}}
                                Hola {{ $orden->cliente_nombre }}, registramos el ingreso de tu {{ mb_strtolower($tipo) }}
                                en nuestro taller. Guarda este correo: con el número de folio puedes consultarnos el
                                estado en cualquier momento.
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
                                    // SIN FECHA DE ENTREGA (dueño, 14-08-2026): «no quiero que la app lo
                                    // calcule, solo diga 15 días hábiles o 10 días, después el cliente lo
                                    // calcula por sí solo». Si el técnico se enferma o sale de vacaciones,
                                    // una fecha escrita es un compromiso que después trae reclamos; el
                                    // plazo en días hábiles va abajo, en «INFORMACIÓN IMPORTANTE».
                                    $filas = [
                                        'Equipo' => $tipo,
                                        'Código' => $orden->producto ? $orden->producto->sku.' — '.$orden->producto->nombre : null,
                                        'N° de serie' => $orden->numero_serie,
                                        'Sucursal' => $orden->sucursal?->nombre,
                                        'Fecha de ingreso' => $orden->fecha_ingreso?->format('d-m-Y'),
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
                                Te avisaremos por correo cuando el equipo esté listo para retirar; el plazo
                                se cuenta en días hábiles y puede variar según el diagnóstico.
                            </p>

                            {{-- ═══ INFORMACIÓN IMPORTANTE ═══
                                 Es el recuadro del comprobante impreso del taller, que el dueño
                                 pidió el 14-08-2026 que viaje también en el correo: el cliente que
                                 ingresa por QR nunca ve el papel, y estas son justo las condiciones
                                 que después se discuten en el mostrador (bodegaje, responsabilidad
                                 por la caja, plazo).

                                 EL PLAZO SALE DE LA SUCURSAL y no escrito a mano: cada una tiene el
                                 suyo (Mirador repara en 10 días hábiles; Coquimbo y Abate Molina
                                 mandan el equipo a Mirador y por eso son 15). Con un número fijo,
                                 el correo prometería 10 días en una sucursal que tarda 15.

                                 Y ES EL ÚNICO COMPROMISO DE TIEMPO QUE VIAJA: la fecha de entrega
                                 calculada se saca (dueño, 14-08-2026) porque el técnico se enferma o
                                 sale de vacaciones y una fecha escrita termina en reclamo. El plazo
                                 lo cuenta el cliente. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 0; border:1px solid #e5e5e5; border-radius:8px;">
                                <tr>
                                    <td style="padding:10px 14px; background-color:#fafafa; border-bottom:1px solid #e5e5e5; font-size:13px; font-weight:bold; color:#171717; text-align:center; letter-spacing:1px;">
                                        INFORMACIÓN IMPORTANTE
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px; font-size:13px; color:#525252; line-height:1.7;">
                                        <div style="font-weight:bold; color:#171717;">
                                            No nos hacemos responsables por entrega de dispensadores sin caja, por rayones o golpes.
                                        </div>
                                        @if ($plazoDias)
                                            <div style="font-weight:bold; color:#171717; margin-top:6px;">
                                                El plazo de reparación es de hasta {{ $plazoDias }} días hábiles.
                                            </div>
                                        @endif
                                        <div style="margin-top:10px;">
                                            · Cada pieza reparada tiene una garantía de {{ $garantiaMeses }} meses.<br>
                                            · A partir de los {{ $bodegajeDesdeMeses }} meses se cobrará un costo de
                                            {{ $bodegajeMensual }} + IVA mensual por concepto de bodegaje.<br>
                                            · En caso de cumplir {{ $bodegajeLimiteMeses }} meses de bodegaje podemos vender,
                                            regalar o dar de baja el dispensador según la Ley 19.496.
                                        </div>
                                        <div style="margin-top:10px; color:#737373;">
                                            <strong style="color:#171717;">Horario de atención:</strong>
                                            lunes a jueves de 09:00 a 13:00 y de 14:00 a 17:00 · viernes hasta las 16:00.
                                        </div>
                                    </td>
                                </tr>
                            </table>
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
