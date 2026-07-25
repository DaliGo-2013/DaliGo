<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use InvalidArgumentException;

class AppLayout extends Component
{
    /**
     * Anchos de página: DOS, por tipo de pantalla (decisión del dueño,
     * 2026-07-25). Antes había 7 anchos distintos elegidos a dedo vista por
     * vista y, como la banda del título estaba fija en el layout, 47 de las 69
     * pantallas con cabecera tenían el título desalineado del contenido de
     * abajo (hasta 352px de desfase).
     *
     *   listado     → listas y tablas de registros, informes, el tablero.
     *   formulario  → formularios y fichas de UN registro en una columna.
     *
     * El ancho NO se declara en la vista: se declara aquí y el layout lo aplica
     * a la banda del título, al aviso y al cuerpo desde la MISMA variable, así
     * que desalinearlos deja de ser posible por construcción (mismo criterio
     * que MenuPrincipal para el menú).
     *
     * Las CLASES viven en layouts/app.blade.php, no aquí: Tailwind v4 solo
     * escanea resources/**, así que un literal max-w-* dentro de app/ se
     * purgaría del bundle y la página quedaría sin tope de ancho.
     */
    public const ANCHOS = ['listado', 'formulario'];

    /**
     * Un token desconocido revienta en vez de caer en silencio al default: un
     * `ancho="lista"` mal escrito se ve igual que el correcto en pantalla, y la
     * suite renderiza todas las vistas, así que el typo se caza antes del deploy.
     */
    public function __construct(public string $ancho = 'listado')
    {
        if (! in_array($ancho, self::ANCHOS, true)) {
            throw new InvalidArgumentException(
                "Ancho de página desconocido [{$ancho}]. Válidos: ".implode(' · ', self::ANCHOS).'.'
            );
        }
    }

    public function render(): View
    {
        return view('layouts.app');
    }
}
