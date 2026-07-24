<?php

namespace App\View\Components\Layout;

use App\Support\MenuPrincipal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Sidebar V4 (menú Talana): computa el árbol podado por permisos, el módulo
 * activo y los badges desde MenuPrincipal (fuente única). Desde el pulido
 * 24-07 (pedido del dueño: máximo espacio vertical) también lleva el PIE con
 * la campanita M15 y el bloque de usuario — en desktop no hay topbar.
 */
class Sidebar extends Component
{
    /** @var array<string, array<string, mixed>> */
    public array $modulos;

    /** @var array<string, mixed>|null */
    public ?array $activo;

    /** @var array<string, int> */
    public array $badges;

    /** Campanita M15 (pie de la sidebar). */
    public Collection $noLeidas;

    public int $conteo;

    public string $iniciales;

    public function __construct()
    {
        $user = Auth::user();
        $this->modulos = MenuPrincipal::para($user);
        $this->activo = MenuPrincipal::moduloActivo();
        $this->badges = MenuPrincipal::badges($user);

        $campanita = MenuPrincipal::campanita($user);
        $this->noLeidas = $campanita['noLeidas'];
        $this->conteo = $campanita['conteo'];

        $this->iniciales = collect(explode(' ', $user?->name ?? ''))
            ->filter()
            ->map(fn (string $parte) => Str::substr($parte, 0, 1))
            ->take(2)
            ->implode('');
    }

    public function render(): View
    {
        return view('components.layout.sidebar');
    }
}
