<?php

namespace App\View\Components\Layout;

use App\Support\MenuPrincipal;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Sidebar V4 (menú Talana): computa el árbol podado por permisos, el módulo
 * activo y los badges desde MenuPrincipal (fuente única). Reemplaza al
 * View::composer de layouts.navigation — el componente trae sus datos.
 */
class Sidebar extends Component
{
    /** @var array<string, array<string, mixed>> */
    public array $modulos;

    /** @var array<string, mixed>|null */
    public ?array $activo;

    /** @var array<string, int> */
    public array $badges;

    public function __construct()
    {
        $user = Auth::user();
        $this->modulos = MenuPrincipal::para($user);
        $this->activo = MenuPrincipal::moduloActivo();
        $this->badges = MenuPrincipal::badges($user);
    }

    public function render(): View
    {
        return view('components.layout.sidebar');
    }
}
