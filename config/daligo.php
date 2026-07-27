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

];
