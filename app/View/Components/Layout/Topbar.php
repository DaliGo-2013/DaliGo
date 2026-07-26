<?php

namespace App\View\Components\Layout;

use App\Support\MenuPrincipal;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Barra superior SOLO MÓVIL (bajo lg:) del shell V4: hamburguesa + título del
 * módulo activo + campana. En desktop no se muestra — el espacio vertical es
 * área de trabajo (pedido del dueño 24-07); campanita y usuario viven en el
 * pie de la sidebar.
 */
class Topbar extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $activo;

    /** Conteo del badge del módulo activo (0 = sin badge). */
    public int $badgeActivo = 0;

    /** Campana móvil: conteo de no-leídas (aria-label, contrato de CampanitaTest). */
    public int $conteo;

    public function __construct()
    {
        $user = Auth::user();

        $this->activo = MenuPrincipal::moduloActivo();
        if ($this->activo && isset($this->activo['badge'])) {
            $this->badgeActivo = MenuPrincipal::badges($user)[$this->activo['badge']] ?? 0;
        }

        $this->conteo = MenuPrincipal::campanita($user)['conteo'];
    }

    public function render(): View
    {
        return view('components.layout.topbar');
    }
}
