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

];
