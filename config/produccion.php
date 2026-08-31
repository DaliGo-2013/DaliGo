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

    /*
    |--------------------------------------------------------------------------
    | La pantalla de Recetas está OCULTA (decisión del dueño, 31-08-2026)
    |--------------------------------------------------------------------------
    |
    | «No es una funcionalidad expresamente solicitada; quiero mantener la
    | lógica pero ocultar la vista por hoy» — pendiente de una reunión que
    | defina cómo usarla. Con el flag en false: la pestaña «Recetas» no se
    | dibuja en Configuración de producción y sus tres rutas redirigen al
    | anfitrión (sin puerta trasera por URL, mismo criterio que las claves
    | de audiencias).
    |
    | La LÓGICA queda VIVA a propósito: el backflush del kardex sigue
    | aplicando las recetas sembradas (hipótesis «por confirmar»: 1 preforma
    | + 1 tapa) y el seeder sigue corriendo en cada deploy. Lo único que se
    | pierde mientras esté oculta es poder EDITAR/confirmar recetas desde la
    | UI. Re-encender = true + deploy (nivel 2: decisión de despliegue, no
    | perilla de negocio en caliente).
    |
    */

    'pantalla_recetas' => false,

];
