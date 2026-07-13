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

    /*
    |--------------------------------------------------------------------------
    | SKU de la "hora de servicio técnico" (mano de obra)
    |--------------------------------------------------------------------------
    |
    | Producto del catálogo cuyo precio (con IVA) es el VALOR HORA de mano de
    | obra. En la pantalla de reparación el técnico indica cuántas horas trabajó
    | y la app calcula la mano de obra = horas × valor hora. Si el SKU no existe
    | o no tiene precio, el campo de mano de obra sigue siendo manual.
    |
    */

    'sku_hora_servicio' => '9771001',

    'categorias_equipo' => [
        'agua bomba usb',
        // Dispensadores: en Bsale son 4 categorías separadas (pedestal/sobremesa
        // × compresor/ventilador), no una sola "compresor y ventilador".
        'agua disp. pedestal compresor',
        'agua disp. pedestal ventilador',
        'agua disp. sobremesa compresor',
        'agua disp. sobremesa ventilador',
        'agua lavadora',
        'herramientas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Respuestas fijas de "Trabajo realizado" (pantalla de reparación)
    |--------------------------------------------------------------------------
    |
    | El técnico ELIGE de esta lista (no escribe): son las resoluciones más
    | recurrentes del historial real del taller, agrupadas por resultado. El
    | orden dentro de cada grupo es por frecuencia (de más a menos usado).
    | Para agregar una respuesta nueva, súmala al grupo que corresponda: la
    | pantalla la ofrece de inmediato, sin tocar código.
    |
    | La clave de cada grupo es el rótulo del <optgroup> que ve el técnico.
    |
    */

    'respuestas_trabajo' => [
        'Reparada' => [
            'Revisión general, mantención y limpieza — funciona normal',
            'Ajuste o cambio de termostato — funciona normal',
            'Cambio de llave(s) de agua (fría/caliente), se corrige filtración — funciona normal',
            'Cambio de celda de peltier — funciona normal',
            'Cambio de caldera — funciona normal',
            'Máquina bloqueada: se desbloquea — funciona normal',
            'Cambio de manguera, se corrige filtración — funciona normal',
            'Se ajusta cable/conexión suelta — funciona normal',
            'Cambio de placa eléctrica — funciona normal',
            'Cambio de tapa lateral (derecha/izquierda) — queda en óptimas condiciones',
            'Reparación o cambio de base — queda en óptimas condiciones',
            'Cambio de ventilador — funciona normal',
            'Cambio de relé — funciona normal',
            'Cambio de bomba de agua — funciona normal',
            'Cambio de limitador de temperatura — funciona normal',
            'Cambio de filtro — funciona normal',
        ],
        'Revisada sin falla' => [
            'Revisión general, se deja en observación y no presenta fallas — funciona normal',
            'Se verifica buen funcionamiento, temperatura dentro del rango normal',
        ],
        'Sin solución (irreparable)' => [
            'Motor/compresor trabado o pegado — irreparable',
            'Fuga o falta de gas refrigerante — irreparable',
            'Se desarma para repuestos (unidad dada de baja)',
        ],
    ],

];
