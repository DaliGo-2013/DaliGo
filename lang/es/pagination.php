<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    |
    | Este archivo FALTABA y por eso los listados paginados mostraban los
    | botones en inglés. Importa sobre todo en MÓVIL: la vista de paginación de
    | Tailwind oculta los números de página bajo el breakpoint `sm` y deja solo
    | estos dos botones, así que en el celular lo único que se veía era
    | «Previous» y «Next». Reportado por el dueño (03-08-2026).
    |
    | Las flechas van con `&laquo;`/`&raquo;` como en el original: la vista las
    | imprime sin escapar y esperan la entidad HTML.
    |
    */

    'previous' => '&laquo; Anterior',
    'next' => 'Siguiente &raquo;',

];
