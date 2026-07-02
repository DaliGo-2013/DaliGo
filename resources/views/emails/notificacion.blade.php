{{-- Correo generico M15. Estilos inline (clientes de correo no cargan el bundle).
     El cuerpo viene de payload (dato de usuario) → SIEMPRE escapado con {{ }}. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background-color:#fafaf9; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafaf9; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background-color:#ffffff; border:1px solid #e7e5e4; border-radius:12px;">
                    <tr>
                        <td style="padding:20px 28px; border-bottom:1px solid #f5f5f4;">
                            <span style="font-size:16px; font-weight:bold; color:#ea580c;">{{ config('app.name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px;">
                            <h1 style="margin:0 0 12px; font-size:18px; color:#1c1917;">{{ $titulo }}</h1>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#44403c; white-space:pre-line;">{{ $cuerpo }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px; border-top:1px solid #f5f5f4;">
                            <p style="margin:0; font-size:12px; color:#a8a29e;">Notificación automática — no responder a este correo.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
