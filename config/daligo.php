<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona horaria del NEGOCIO (PLAN-TIMEZONE, opción C — P-TZ-01)
    |--------------------------------------------------------------------------
    | La planta vive en hora chilena pero el storage sigue en UTC
    | (app.timezone NO se toca). Esta zona define ÚNICAMENTE el "día de
    | negocio" (App\Support\FechaNegocio) y, en P-TZ-02, el render de
    | timestamps. Vive en config (no en Configuracion editable) a propósito:
    | cambiarla en caliente desplazaría el "hoy" de toda la operación — es una
    | decisión de despliegue, no un parámetro de usuario.
    */

    'tz_negocio' => env('DALIGO_TZ_NEGOCIO', 'America/Santiago'),

    /*
    |--------------------------------------------------------------------------
    | Lista de precios OFICIAL de ventas (decisión del dueño, 2026-07-28)
    |--------------------------------------------------------------------------
    | Nombre de la lista espejada de Bsale de la que se toma el precio de venta
    | (repuestos y valor hora de servicio técnico, y más adelante las líneas del
    | documento tributario).
    |
    | Por qué existe: la empresa tiene 5 listas ACTIVAS con precios (COQUIMBO-1,
    | CSTS, EXTERIOR 1, EXTERIOR 2, GENERAL) y el criterio anterior era «la
    | primera activa que aparezca», o sea la que devolviera la BD primero → una
    | reparación de Mirador podía cotizarse con precios de Coquimbo, en silencio.
    | El dueño fijó GENERAL porque el origen de las otras no está claro.
    |
    | Si el producto NO tiene precio en esta lista, Producto::precioVentaConIva()
    | devuelve null a propósito (el precio se ingresa a mano) en vez de sacarlo
    | de otra lista. Dejarlo en null desactiva la regla y vuelve al criterio
    | antiguo — solo para entornos de prueba sin catálogo espejado.
    */

    'lista_precios_ventas' => env('DALIGO_LISTA_PRECIOS_VENTAS', 'GENERAL'),

];
