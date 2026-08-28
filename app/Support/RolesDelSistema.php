<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Rótulos en español de los roles del sistema. Hueco real hasta hoy: el
 * idioma de facto era Str::headline('jefe_ventas') = «Jefe Ventas» — sin
 * «de», sin tildes. La matriz de avisos necesita cabeceras legibles, así que
 * el mapa nace acá; migrar las vistas de usuarios/roles que aún usan
 * headline es un aditivo futuro (no se tocan en este lote para no mover
 * sus candados).
 */
final class RolesDelSistema
{
    /**
     * Etiqueta humana por rol, en el ORDEN CURADO en que la matriz de avisos
     * pinta sus casillas (administración → ventas → taller → logística →
     * planta → base). Un rol creado después desde la UI de Roles no está acá:
     * cae al fallback de headline y se pinta al final.
     */
    public const ETIQUETAS = [
        'admin' => 'Administrador',
        'jefe_ventas' => 'Jefe de Ventas',
        'vendedor' => 'Vendedor',
        'tecnico' => 'Técnico de taller',
        'tecnico_industrial' => 'Técnico industrial',
        'jefe_bodega' => 'Jefe de Bodega',
        'jefe_sucursal' => 'Jefe de Sucursal',
        'jefe_logistica' => 'Jefe de Logística',
        'jefe_despacho' => 'Jefe de Despacho',
        'conductor' => 'Conductor',
        'soplador' => 'Soplador',
        'member' => 'Miembro',
    ];

    public static function etiqueta(string $rol): string
    {
        return self::ETIQUETAS[$rol] ?? Str::headline($rol);
    }

    /**
     * rol => etiqueta de TODOS los roles existentes en la BD, con los curados
     * primero (en su orden) y los desconocidos al final por nombre. Es la
     * fuente de las columnas de la matriz: un rol nuevo aparece solo.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $existentes = Role::pluck('name')->all();

        $curados = array_values(array_intersect(array_keys(self::ETIQUETAS), $existentes));
        $extras = array_diff($existentes, $curados);
        sort($extras);

        $opciones = [];
        foreach ([...$curados, ...$extras] as $rol) {
            $opciones[$rol] = self::etiqueta($rol);
        }

        return $opciones;
    }
}
