<?php

namespace App\View\Components\Layout;

use App\Support\MenuPrincipal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Sidebar V4 (menú Talana): computa el árbol podado por permisos, el módulo
 * activo y los badges desde MenuPrincipal (fuente única). Lleva la campanita
 * M15 (cabecera, desktop) y el bloque de usuario (pie) — en desktop no hay
 * topbar (pedido del dueño 24-07: máximo espacio vertical).
 */
class Sidebar extends Component
{
    /** @var array<string, array<string, mixed>> */
    public array $modulos;

    /** @var array<string, mixed>|null */
    public ?array $activo;

    /** @var array<string, int> */
    public array $badges;

    /** Campanita M15 (cabecera de la sidebar). */
    public Collection $noLeidas;

    public int $conteo;

    public function __construct()
    {
        $user = Auth::user();
        $this->modulos = MenuPrincipal::para($user);
        $this->activo = MenuPrincipal::moduloActivo();
        $this->badges = MenuPrincipal::badges($user);

        $campanita = MenuPrincipal::campanita($user);
        $this->noLeidas = $campanita['noLeidas'];
        $this->conteo = $campanita['conteo'];
    }

    public function render(): View
    {
        return view('components.layout.sidebar');
    }
}
