<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Filas por página de los listados (COM-2, PLAN-PARAMETRICOS): la
     * convención de densidad de la casa — el mapa F0-COMERCIAL (#3) la
     * encontró repetida ×3 en su módulo y ×15 en la app. Nivel 3 por
     * veredicto del dueño (una perilla por módulo fragmentaría la UX);
     * el duplicado se paga acá: los módulos la van adoptando cuando su
     * propia auditoría marque sus `paginate(25)`.
     */
    public const POR_PAGINA = 25;
}
