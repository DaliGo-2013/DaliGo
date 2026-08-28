<?php

namespace App\Support;

/**
 * LOS PERMISOS QUE REPARTEN PERMISOS: solo los lleva el rol `admin`.
 *
 * Pedido del dueño el 27-08-2026, mirando la ficha del rol jefe_ventas: *«es la
 * opción para cambiar y habilitar permisos, no debe tener acceso ningún perfil
 * salvo admin, que es el que administra el sistema completo»*.
 *
 * Por qué hace falta un candado y no alcanza con «hoy solo admin los tiene»: la
 * pantalla de Roles reparte CUALQUIER permiso a CUALQUIER rol, estos cuatro
 * incluidos. Y estos cuatro son los que se reparten a sí mismos:
 *
 *  - `manage roles`  edita el mapa rol→permisos: quien lo tiene se puede dar
 *                    todo lo demás, incluido volver a dárselo si se lo quitan.
 *  - `edit users`    cambia el ROL de una cuenta: con esto alguien se asigna
 *                    `admin` a sí mismo y no necesitó `manage roles` para nada.
 *  - `create users`  crea una cuenta nueva Y le elige el rol — el mismo camino,
 *                    con una cuenta recién hecha en vez de la propia.
 *  - `delete users`  borra cuentas; la única barrera que hay es no dejar el
 *                    sistema sin ningún admin (`wouldRemoveLastAdmin`).
 *
 * O sea que darle uno solo de los cuatro a un rol equivale a darle todos los
 * permisos del sistema, con un paso extra. Por eso el veto es estructural: el
 * controlador los DESCARTA del POST y la vista los dibuja bloqueados. Para que
 * alguien administre accesos se le asigna el rol `admin`; no hay medio camino, y
 * eso es deliberado.
 *
 * `view users` NO está en la lista: es el listado de cuentas del equipo (nombre,
 * correo, rol) y no cambia nada. Se sigue pudiendo dar desde Roles a quien
 * gerencia quiera — lo que pasa es que hoy ningún jefe lo lleva de fábrica, así
 * que a ninguno le aparece ADMINISTRACIÓN en el menú.
 */
class PermisosSoloAdmin
{
    /** El único rol que puede llevarlos. */
    public const ROL = 'admin';

    public const PERMISOS = [
        'manage roles',
        'create users',
        'edit users',
        'delete users',
    ];

    /** ¿Este rol es el que sí puede llevarlos? (null = rol nuevo, todavía sin nombre). */
    public static function esElRolAdmin(?string $rol): bool
    {
        return $rol === self::ROL;
    }

    /**
     * Deja fuera los permisos de acceso si el rol no es `admin`.
     *
     * Se aplica al crear Y al editar: un rol nuevo nunca es `admin` (el nombre es
     * único y `admin` ya existe), así que al crear el filtro los descarta todos.
     */
    public static function filtrar(array $permisos, ?string $rol): array
    {
        if (self::esElRolAdmin($rol)) {
            return $permisos;
        }

        return array_values(array_diff($permisos, self::PERMISOS));
    }

    /**
     * Los que la vista tiene que dibujar bloqueados y en OFF para este rol.
     *
     * Es el espejo del bloqueo que ya existía al revés: `manage roles` va
     * bloqueado y en ON para `admin` (no se puede auto-encerrar).
     */
    public static function vetadosPara(?string $rol): array
    {
        return self::esElRolAdmin($rol) ? [] : self::PERMISOS;
    }
}
