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

    /*
    |--------------------------------------------------------------------------
    | Categorías consideradas "equipo de taller"
    |--------------------------------------------------------------------------
    |
    | El buscador de código del formulario PÚBLICO (QR del cliente) solo sugiere
    | productos cuyo `categoria` (el "tipo de producto" espejado desde Bsale)
    | esté en esta lista: los cuatro tipos que sí van a servicio técnico
    | (dispensadores, lavadoras, bombas, herramientas). Así el cliente no ve
    | accesorios/repuestos (ej. "Soporte rosa") que no aplican.
    |
    | La comparación es case-insensitive, pero el resto del nombre (acentos,
    | puntuación) debe coincidir EXACTO con lo que Bsale manda en el sync. Si un
    | tipo de dispensador/bomba nuevo no aparece en el buscador, agrega aquí su
    | categoría tal como sale en el catálogo (admin → Productos muestra la
    | categoría de cada uno). Lista vacía = no filtra (muestra todo el catálogo).
    |
    | El buscador del MOSTRADOR (staff) NO usa este filtro: ve todo el catálogo.
    |
    */

    'categorias_equipo' => [
        'agua bomba usb',
        'agua disp. pedestal compresor y ventilador',
        'agua disp. sobremesa compresor y ventilador',
        'agua lavadora',
        'herramientas',
    ],

];
