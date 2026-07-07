<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dias habiles de reparacion por sucursal
    |--------------------------------------------------------------------------
    |
    | Plazo estimado de entrega segun la sucursal de recepcion (por su codigo):
    | fecha de entrega = fecha de ingreso + N dias habiles, sin contar sabados,
    | domingos ni feriados (ver config/feriados.php). Si una sucursal no aparece
    | aqui, se usa 'dias_reparacion_default'.
    |
    */

    'dias_reparacion' => [
        'MIRADOR' => 10,
        'COQUIMBO' => 15,
        'ABATE-MOLINA' => 15,
        'BUZETA' => 15,
    ],

    'dias_reparacion_default' => 15,

    /*
    |--------------------------------------------------------------------------
    | Sucursales que RECIBEN servicio técnico
    |--------------------------------------------------------------------------
    |
    | Códigos de las sucursales donde un cliente puede ingresar un equipo al
    | taller (por QR o mostrador). La reparación siempre es en Mirador (casa
    | matriz); Coquimbo y Abate Molina reciben pero no reparan. Buzeta NO recibe
    | servicio técnico, por eso no aparece en el selector de la portada ni en la
    | página de códigos QR. Si alguna sucursal empieza a recibir, agrega su
    | código aquí (debe coincidir con `sucursales.codigo`).
    |
    */

    'sucursales_recepcion' => ['MIRADOR', 'COQUIMBO', 'ABATE-MOLINA'],

];
