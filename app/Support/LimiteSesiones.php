<?php

namespace App\Support;

use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Límite de sesiones paralelas por usuario (pedido del dueño 01-09-2026):
 * default 3, paramétrico por ROL y por USUARIO puntual desde Configuración
 * (pantalla propia, molde de la matriz de avisos). Al topar, el login nuevo
 * SIEMPRE entra y se expulsa la sesión más antigua (decisión del dueño).
 *
 * Semántica de los valores (los tres niveles, con precedencia
 * usuario > rol > default):
 *  - 0        = SIN límite (válvula de escape elegida por el dueño);
 *  - 1..MAX   = tope de sesiones simultáneas;
 *  - podrido (no entero, negativo) = ese nivel se ignora y decide el
 *    siguiente — un dato roto no puede dejar a nadie sin poder entrar;
 *  - > MAX    = clamp a MAX (un 999 metido por fuera intenta «muchas»,
 *    no «vuelve al default») — clamp-del-consumidor, idioma de la casa.
 *  - usuario con varios roles con override: gana el MÁS PERMISIVO (max, y
 *    el 0 gana como infinito) — los overrides de rol son concesiones; un
 *    min() convertiría un rol legado olvidado en estrangulador silencioso.
 *
 * La pantalla de Configuración pinta y guarda usando LOS MISMOS saneadores
 * que consume el listener: cero divergencia entre lo que se ve y lo que rige.
 */
final class LimiteSesiones
{
    /** Grupo de las claves: OCULTO del índice técnico de Configuración. */
    public const GRUPO = 'sesiones_limite';

    public const CLAVE_DEFAULT = 'sesiones_limite_default';

    /** TIPO_JSON: mapa rol => n. */
    public const CLAVE_ROLES = 'sesiones_limite_roles';

    /** TIPO_JSON: mapa userId => n. */
    public const CLAVE_USUARIOS = 'sesiones_limite_usuarios';

    /** El default histórico-de-nacimiento: rige si la clave no existe o está podrida. */
    public const DEFAULT = 3;

    public const MIN = 0;

    public const MAX = 20;

    /**
     * El límite vigente para este usuario. `null` = sin límite.
     */
    public static function de(User $user): ?int
    {
        $porUsuario = self::overridesUsuarios()[$user->getKey()] ?? null;
        if ($porUsuario !== null) {
            return $porUsuario === 0 ? null : $porUsuario;
        }

        $deSusRoles = array_intersect_key(
            self::overridesRoles(),
            array_flip($user->getRoleNames()->all()),
        );
        if ($deSusRoles !== []) {
            if (in_array(0, $deSusRoles, true)) {
                return null; // el 0 gana como infinito
            }

            return max($deSusRoles);
        }

        $default = self::defaultVigente();

        return $default === 0 ? null : $default;
    }

    /**
     * Deja al usuario con espacio para la sesión que está naciendo: conserva
     * las (límite − 1) filas más nuevas de `sessions` y borra el resto.
     *
     * El timing lo garantiza el guard: SessionGuard regenera la sesión
     * (destruyendo la fila vieja) ANTES de disparar el evento Login, así que
     * la fila del propio login nunca está en la tabla cuando esto corre — el
     * que entra no puede auto-expulsarse. `$sesionActual` se excluye igual,
     * como cinturón para llamadores fuera del evento.
     *
     * Dos queries a propósito (MySQL 5.7 no tiene DELETE con OFFSET). Con
     * límite 1 el pluck queda vacío y `whereNotIn('id', [])` compila como
     * `1 = 1` → borra TODAS las demás filas del usuario: acá ese compilado
     * es exactamente el comportamiento deseado (en la bitácora [2026-06-12]
     * el mismo compilado era el bug — por eso se deja dicho).
     */
    public static function recortar(User $user, ?string $sesionActual = null): void
    {
        $limite = self::de($user);
        if ($limite === null) {
            return;
        }

        $base = fn () => DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->when($sesionActual !== null, fn ($q) => $q->where('id', '!=', $sesionActual));

        // Desempate por id para que dos filas del mismo segundo no hagan
        // aleatorio quién sobrevive.
        $mantener = $base()
            ->orderByDesc('last_activity')
            ->orderByDesc('id')
            ->limit($limite - 1)
            ->pluck('id')
            ->all();

        $base()->whereNotIn('id', $mantener)->delete();
    }

    /** El default saneado (0 = sin límite se conserva; podrido → DEFAULT). */
    public static function defaultVigente(): int
    {
        return self::sanear(Configuracion::get(self::CLAVE_DEFAULT)) ?? self::DEFAULT;
    }

    /** @return array<string, int> mapa rol => límite, ya saneado. */
    public static function overridesRoles(): array
    {
        return self::sanearMapa(Configuracion::get(self::CLAVE_ROLES));
    }

    /** @return array<int, int> mapa userId => límite, ya saneado. */
    public static function overridesUsuarios(): array
    {
        $mapa = [];
        foreach (self::sanearMapa(Configuracion::get(self::CLAVE_USUARIOS)) as $id => $limite) {
            if ((string) (int) $id === (string) $id) {
                $mapa[(int) $id] = $limite;
            }
        }

        return $mapa;
    }

    /** @return array<string, int> descarta entradas podridas, conserva las sanas. */
    private static function sanearMapa(mixed $crudo): array
    {
        if (! is_array($crudo)) {
            return [];
        }

        $mapa = [];
        foreach ($crudo as $clave => $valor) {
            $sano = self::sanear($valor);
            if ($sano !== null) {
                $mapa[$clave] = $sano;
            }
        }

        return $mapa;
    }

    /** Entero 0..MAX o null si el valor está podrido (no entero / negativo). */
    private static function sanear(mixed $valor): ?int
    {
        if (! is_numeric($valor) || (int) $valor != $valor) {
            return null;
        }
        $n = (int) $valor;
        if ($n < self::MIN) {
            return null;
        }

        return min($n, self::MAX);
    }
}
