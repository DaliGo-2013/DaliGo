<?php

/*
|--------------------------------------------------------------------------
| Feriados de Chile (para el calculo de dias habiles)
|--------------------------------------------------------------------------
|
| Lista de feriados en formato 'Y-m-d'. Se usan para calcular la fecha de
| entrega estimada de Servicio Tecnico (dias habiles, sin sabados/domingos
| ni estos feriados).
|
| OJO: hay que MANTENER ESTA LISTA cada ano. Algunos feriados son moviles
| (San Pedro y San Pablo, Encuentro de Dos Mundos se trasladan al lunes
| segun la ley; el Dia de los Pueblos Indigenas depende del solsticio).
| Verificar con el calendario oficial antes de fin de ano y agregar el ano
| siguiente. Fuente: feriados.cl / Diario Oficial.
|
*/

return [

    // --- 2026 ---
    '2026-01-01', // Ano Nuevo
    '2026-04-03', // Viernes Santo
    '2026-04-04', // Sabado Santo
    '2026-05-01', // Dia del Trabajo
    '2026-05-21', // Glorias Navales
    '2026-06-21', // Dia Nacional de los Pueblos Indigenas (solsticio - verificar)
    '2026-06-29', // San Pedro y San Pablo
    '2026-07-16', // Virgen del Carmen
    '2026-08-15', // Asuncion de la Virgen
    '2026-09-18', // Independencia Nacional
    '2026-09-19', // Glorias del Ejercito
    '2026-10-12', // Encuentro de Dos Mundos
    '2026-10-31', // Dia de las Iglesias Evangelicas y Protestantes
    '2026-11-01', // Dia de Todos los Santos
    '2026-12-08', // Inmaculada Concepcion
    '2026-12-25', // Navidad

    // --- 2027 ---
    '2027-01-01', // Ano Nuevo
    '2027-03-26', // Viernes Santo
    '2027-03-27', // Sabado Santo
    '2027-05-01', // Dia del Trabajo
    '2027-05-21', // Glorias Navales
    '2027-06-21', // Dia Nacional de los Pueblos Indigenas (solsticio - verificar)
    '2027-06-28', // San Pedro y San Pablo (trasladado a lunes - verificar)
    '2027-07-16', // Virgen del Carmen
    '2027-08-15', // Asuncion de la Virgen
    '2027-09-18', // Independencia Nacional
    '2027-09-19', // Glorias del Ejercito
    '2027-10-11', // Encuentro de Dos Mundos (trasladado a lunes - verificar)
    '2027-10-31', // Dia de las Iglesias Evangelicas y Protestantes
    '2027-11-01', // Dia de Todos los Santos
    '2027-12-08', // Inmaculada Concepcion
    '2027-12-25', // Navidad

];
