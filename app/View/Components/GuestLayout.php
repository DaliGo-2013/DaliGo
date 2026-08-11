<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * ANCHO DEL CARD, por token. `formulario` (448 px) es el de siempre: login, QR
     * público, confirmaciones. `listado` (max-w-6xl) existe para el plan de carga
     * compartido — un visor 3D dentro de 448 px no se puede mirar.
     *
     * ESTA PROPIEDAD FALTABA, y por eso el token no servía (11-08-2026). El layout tenía
     * el mapa de clases y hasta un `throw_unless` para el token desconocido desde el
     * 10-08, pero el componente no declaraba `$ancho`: Blade trataba `ancho="listado"`
     * como un ATRIBUTO HTML suelto, la variable nunca llegaba, y el `?? 'formulario'` del
     * layout la resolvía al default **en silencio** — justo lo que ese `throw_unless`
     * decía evitar. El plan compartido se veía en un card de 448 px con el camión
     * aplastado adentro, que es como lo reportó el dueño.
     *
     * Las CLASES viven en `layouts/guest.blade.php` y no acá: Tailwind v4 solo barre
     * `resources/**`, así que un `max-w-*` escrito en `app/` se purgaría del bundle.
     * Acá viaja solo el token, y el layout lo valida.
     */
    public function __construct(public string $ancho = 'formulario') {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
