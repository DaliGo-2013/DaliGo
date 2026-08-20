{{-- GARANTÍAS DEL SERVICIO INDUSTRIAL, para que el cliente las tenga por escrito
     antes de que se haga el trabajo (dueño, 20-08-2026: «esto es importante para
     que todos los clientes sepan al momento de llevar a cabo un arreglo»).

     Los plazos NO están escritos acá: salen de `App\Support\GarantiasIndustrial`,
     que los lee de config. Un plazo escrito a mano en una plantilla es un plazo que
     algún día va a contradecir al de otro correo.

     Va como PARTIAL porque estos mismos plazos corresponden en más de una
     superficie (hoy el correo de la visita; la cotización industrial y la pantalla
     pública del QR son los siguientes candidatos naturales).

     Maquetado con tabla y padding fijo a propósito: los clientes de correo no
     entienden media queries (regla de la casa para los correos). --}}
@php
    $garantiaEquipos = \App\Support\GarantiasIndustrial::equipoNuevo();
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="margin:0 0 20px; background-color:#fff7ed; border:1px solid #fed7aa; border-radius:10px;">
    <tr>
        <td style="padding:16px 20px;">
            <p style="margin:0 0 10px; font-size:13px; font-weight:bold; color:#c2410c; text-transform:uppercase; letter-spacing:0.03em;">
                Garantías
            </p>

            @if ($garantiaEquipos !== [])
                <p style="margin:0 0 6px; font-size:13px; color:#525252; line-height:1.6;">
                    <strong style="color:#171717;">Equipo nuevo</strong> (desde su instalación):
                </p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 10px;">
                    @foreach ($garantiaEquipos as $equipo)
                        <tr>
                            <td style="padding:3px 0; font-size:13px; color:#525252; width:55%;">{{ $equipo['label'] }}</td>
                            <td style="padding:3px 0; font-size:13px; color:#171717; font-weight:bold;">{{ $equipo['plazo'] }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:3px 0; font-size:13px; color:#525252; width:55%;">Reparación</td>
                    <td style="padding:3px 0; font-size:13px; color:#171717; font-weight:bold;">{{ \App\Support\GarantiasIndustrial::reparacion() }}</td>
                </tr>
                <tr>
                    <td style="padding:3px 0; font-size:13px; color:#525252;">Instalación</td>
                    <td style="padding:3px 0; font-size:13px; color:#171717; font-weight:bold;">{{ \App\Support\GarantiasIndustrial::instalacion() }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
