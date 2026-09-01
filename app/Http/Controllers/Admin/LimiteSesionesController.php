<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\User;
use App\Support\LimiteSesiones;
use App\Support\RolesDelSistema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pantalla «Sesiones por usuario» (Configuración): cuántas sesiones abiertas
 * a la vez permite cada cuenta — default global, override por rol y override
 * por usuario puntual. Molde de AvisosNotificacionController: vive dentro de
 * Configuración con el mismo permiso, sus claves (grupo
 * LimiteSesiones::GRUPO) están ocultas del índice técnico, y esta pantalla
 * es su único editor. 0 = sin límite; vacío = hereda.
 */
class LimiteSesionesController extends Controller
{
    public function edit(): View
    {
        $overridesUsuarios = LimiteSesiones::overridesUsuarios();

        return view('admin.configuracion.sesiones', [
            'limiteDefault' => LimiteSesiones::defaultVigente(),
            'roles' => RolesDelSistema::opciones(),
            'overridesRoles' => LimiteSesiones::overridesRoles(),
            // Solo usuarios que EXISTEN: un id huérfano del mapa no se pinta
            // (y se limpia solo en el próximo guardado).
            'usuariosConOverride' => User::whereIn('id', array_keys($overridesUsuarios))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'overridesUsuarios' => $overridesUsuarios,
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rangoNum = ['integer', 'min:'.LimiteSesiones::MIN, 'max:'.LimiteSesiones::MAX];

        $v = $request->validate([
            'limite_default' => array_merge(['required'], $rangoNum),
            'roles' => ['nullable', 'array'],
            'roles.*' => array_merge(['nullable'], $rangoNum),
            'usuarios' => ['nullable', 'array'],
            'usuarios.*' => array_merge(['nullable'], $rangoNum),
            'nuevo_usuario_id' => ['nullable', 'integer', 'exists:users,id'],
            'nuevo_limite' => array_merge(['nullable', 'required_with:nuevo_usuario_id'], $rangoNum),
        ], [], [
            'limite_default' => 'límite por defecto',
            'nuevo_usuario_id' => 'usuario a agregar',
            'nuevo_limite' => 'límite del usuario a agregar',
        ]);

        // Un rol desconocido se rechaza NOMBRÁNDOLO (molde
        // validarRolesExistentes): guardarlo sería un override fantasma que
        // nunca aplica a nadie y nadie ve.
        $rolesValidos = array_keys(RolesDelSistema::opciones());
        $mapaRoles = [];
        foreach ($v['roles'] ?? [] as $rol => $limite) {
            if ($limite === null) {
                continue; // vacío = hereda el default
            }
            if (! in_array($rol, $rolesValidos, true)) {
                throw ValidationException::withMessages([
                    'roles' => "«{$rol}» no es un rol del sistema.",
                ]);
            }
            $mapaRoles[$rol] = (int) $limite;
        }

        // Ídem para usuarios: la clave del array es el id del override
        // existente; valor vacío = quitar el override.
        $idsEnviados = array_keys($v['usuarios'] ?? []);
        $idsExistentes = $idsEnviados === []
            ? []
            : User::whereIn('id', $idsEnviados)->pluck('id')->all();
        $mapaUsuarios = [];
        foreach ($v['usuarios'] ?? [] as $id => $limite) {
            if ($limite === null) {
                continue; // vaciar el número = quitar el override
            }
            if (! in_array((int) $id, $idsExistentes, true)) {
                throw ValidationException::withMessages([
                    'usuarios' => "El usuario con id {$id} no existe.",
                ]);
            }
            $mapaUsuarios[(int) $id] = (int) $limite;
        }

        // La fila «agregar»: pisa un override existente del mismo usuario.
        if (($v['nuevo_usuario_id'] ?? null) !== null) {
            $mapaUsuarios[(int) $v['nuevo_usuario_id']] = (int) $v['nuevo_limite'];
        }

        // Solo se persiste lo que CAMBIÓ (auditoría limpia, molde avisos).
        $cambios = 0;
        $cambios += $this->persistirSiCambio(LimiteSesiones::CLAVE_DEFAULT, (int) $v['limite_default'], LimiteSesiones::defaultVigente());
        $cambios += $this->persistirSiCambio(LimiteSesiones::CLAVE_ROLES, $mapaRoles, LimiteSesiones::overridesRoles());
        $cambios += $this->persistirSiCambio(LimiteSesiones::CLAVE_USUARIOS, $mapaUsuarios, LimiteSesiones::overridesUsuarios());

        return redirect()->route('admin.configuracion.sesiones.edit')
            ->with('status', $cambios === 0
                ? 'Sin cambios que guardar.'
                : 'Límites de sesiones guardados.');
    }

    private function persistirSiCambio(string $clave, int|array $nuevo, int|array $vigente): int
    {
        if (is_array($nuevo)) {
            ksort($nuevo);
        }
        if (is_array($vigente)) {
            ksort($vigente);
        }
        if ($nuevo == $vigente) {
            return 0;
        }

        // Los mapas JSON se guardan con claves string (json_encode de un mapa
        // int-keyed emite objeto igual); el int viaja como int.
        Configuracion::set($clave, is_array($nuevo) ? json_encode((object) $nuevo) : $nuevo);

        return 1;
    }
}
