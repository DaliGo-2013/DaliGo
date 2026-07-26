<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Agrupa los permisos por dominio para la UI de Roles, según las categorías de
 * `config('permissions.grupos')`. Cada categoría define substrings; el PRIMER
 * keyword que matchea el nombre técnico del permiso (en el orden del config)
 * gana. Los que no matchean caen en "Generales" (fallback).
 *
 * Objetivo: cuando se agrega un permiso nuevo con el tiempo, se deriva solo a su
 * categoría (por keyword); si abre un dominio nuevo, se agrega una categoría en
 * el config. Nada queda sin mostrar (el fallback los junta).
 */
class PermisosAgrupados
{
    public const FALLBACK = 'Generales';

    /**
     * @param  iterable<\Spatie\Permission\Models\Permission>  $permisos
     * @return array<string, Collection> categoría => permisos, en el orden del
     *                                    config (+ "Generales" al final); solo
     *                                    categorías con al menos un permiso.
     */
    public static function agrupar(iterable $permisos): array
    {
        $grupos = (array) config('permissions.grupos', []);

        $out = [];
        foreach (array_keys($grupos) as $categoria) {
            $out[$categoria] = collect();
        }
        $out[self::FALLBACK] = collect();

        foreach ($permisos as $permiso) {
            $out[self::categoriaDe($permiso->name, $grupos)]->push($permiso);
        }

        return array_filter($out, fn (Collection $c) => $c->isNotEmpty());
    }

    /**
     * Categoría de un permiso: primer keyword (en orden del config) contenido en
     * su nombre. Si ninguno matchea → FALLBACK.
     *
     * @param  array<string, array<int, string>>  $grupos
     */
    public static function categoriaDe(string $nombre, ?array $grupos = null): string
    {
        $grupos ??= (array) config('permissions.grupos', []);

        foreach ($grupos as $categoria => $claves) {
            foreach ((array) $claves as $clave) {
                if (str_contains($nombre, $clave)) {
                    return $categoria;
                }
            }
        }

        return self::FALLBACK;
    }
}
