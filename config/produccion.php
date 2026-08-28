<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Qué producto del catálogo cuenta como PREFORMA asignable a un turno
    |--------------------------------------------------------------------------
    |
    | Patrones LIKE que deciden el universo del selector «Preforma del turno»
    | al asignar producción (y de su validación: comparten el criterio, así que
    | un id fuera del selector tampoco entra al kardex):
    |
    | - `patron_preforma`: la CATEGORÍA que menciona esto es preforma. Si
    |   ningún producto activo calza, el selector degrada con gracia a todos
    |   los activos (misma exclusión de dañadas).
    | - `patron_danada`: el NOMBRE que menciona esto queda FUERA — las
    |   preformas dañadas registran merma en el catálogo, no son material
    |   asignable. El consumidor aplica el patrón y su versión en MAYÚSCULAS
    |   (el LIKE de SQLite solo case-foldea ASCII: 'Ñ' != 'ñ'; en MySQL la
    |   segunda es redundante pero inofensiva).
    |
    | Es config de DEPLOY y no una clave de Configuración (nivel 2 de la vara
    | de PLAN-PARAMETRICOS, mismo criterio que `servicio_tecnico.categorias_equipo`):
    | el criterio que decide qué inventario ES preforma no se mueve en caliente
    | — cambiarlo a mitad de turno puede vaciar el selector con el jefe
    | asignando. Cambiarlo acá lo cambia en selector y validación a la vez.
    |
    */

    'patron_preforma' => '%preforma%',

    'patron_danada' => '%dañada%',

];
