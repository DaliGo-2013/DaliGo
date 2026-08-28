<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Support\AudienciasNotificacion;
use App\Support\RolesDelSistema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «Avisos y destinatarios»: la matriz evento × rol (pedido del dueño 28-08:
 * una tabla tipo excel con checkboxes donde se vea qué aviso recibe cada rol).
 * Vive dentro de Configuración (mismo permiso `manage settings`); las claves
 * `notif_roles_*` que edita están OCULTAS del index técnico — esta pantalla
 * es su único editor.
 */
class AvisosNotificacionController extends Controller
{
    public function edit(): View
    {
        // Eventos editables agrupados por familia, en el orden del registry.
        $familias = [];
        foreach (array_keys(AudienciasNotificacion::DEFAULTS) as $evento) {
            $titulo = AudienciasNotificacion::FAMILIAS[explode('.', $evento)[0]];
            $familias[$titulo][$evento] = Notificacion::EVENTOS[$evento];
        }

        $marcados = [];
        foreach (array_keys(AudienciasNotificacion::DEFAULTS) as $evento) {
            $marcados[$evento] = AudienciasNotificacion::rolesPara($evento);
        }

        // La ⓘ de cada fila reusa la descripción curada de su PLANTILLA (dice
        // cuándo se dispara el aviso) — cero textos nuevos que puedan divergir.
        $ayudas = Configuracion::query()
            ->where('clave', 'like', 'notif_plantilla_%')
            ->pluck('descripcion', 'clave');

        return view('admin.configuracion.avisos', [
            'familias' => $familias,
            'marcados' => $marcados,
            'roles' => RolesDelSistema::opciones(),
            'fijos' => AudienciasNotificacion::NO_EDITABLES,
            'ayudas' => $ayudas,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $roles = RolesDelSistema::opciones();

        // `nullable`: desmarcar TODO (incluso la matriz entera) es válido —
        // decisión del dueño 28-08: un aviso puede quedar sin destinatarios,
        // y la pantalla lo muestra («Nadie recibe este aviso»).
        $data = $request->validate([
            'audiencias' => ['nullable', 'array'],
            'audiencias.*' => ['nullable', 'array'],
            'audiencias.*.*' => ['string', Rule::in(array_keys($roles))],
        ]);

        $cambiados = 0;

        // Se recorre EXACTAMENTE el catálogo editable: un evento ausente del
        // POST son checkboxes desmarcados (= lista vacía), y un evento
        // desconocido del request se ignora (no hay clave que tocar).
        foreach (array_keys(AudienciasNotificacion::DEFAULTS) as $evento) {
            $nueva = array_values(array_unique($data['audiencias'][$evento] ?? []));

            // Solo se persiste lo que cambió (como conjunto): así la
            // auditoría registra cambios reales, no 25 filas por guardado.
            $a = $nueva;
            $b = AudienciasNotificacion::rolesPara($evento);
            sort($a);
            sort($b);

            if ($a === $b) {
                continue;
            }

            Configuracion::set(AudienciasNotificacion::clave($evento), $nueva);
            $cambiados++;
        }

        return redirect()->route('admin.configuracion.avisos.edit')
            ->with('status', $cambiados > 0
                ? 'Destinatarios actualizados ('.$cambiados.' '.($cambiados === 1 ? 'aviso' : 'avisos').').'
                : 'No había cambios que guardar.');
    }
}
