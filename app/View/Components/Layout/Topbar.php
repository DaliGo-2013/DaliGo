<?php

namespace App\View\Components\Layout;

use App\Models\Notificacion;
use App\Support\MenuPrincipal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Topbar V4: título del módulo activo (derivado de MenuPrincipal, sin
 * hardcodeos por vista), campanita M15 (absorbe las queries que antes
 * vivían inline en navigation.blade.php) y avatar con menú de usuario.
 */
class Topbar extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $activo;

    /** Conteo del badge del módulo activo (0 = sin badge). */
    public int $badgeActivo = 0;

    /** Campanita M15: v1 sin polling, se refresca al navegar. */
    public Collection $noLeidas;

    public int $conteo;

    public string $iniciales;

    public function __construct()
    {
        $user = Auth::user();

        $this->activo = MenuPrincipal::moduloActivo();
        if ($this->activo && isset($this->activo['badge'])) {
            $this->badgeActivo = MenuPrincipal::badges($user)[$this->activo['badge']] ?? 0;
        }

        $this->noLeidas = Notificacion::campanitaDe($user?->id)->latest('id')->take(5)->get();
        $this->conteo = Notificacion::campanitaDe($user?->id)->count();

        $this->iniciales = collect(explode(' ', $user?->name ?? ''))
            ->filter()
            ->map(fn (string $parte) => Str::substr($parte, 0, 1))
            ->take(2)
            ->implode('');
    }

    public function render(): View
    {
        return view('components.layout.topbar');
    }
}
