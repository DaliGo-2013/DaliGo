<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un tipo de documento CREADO desde la app (pedido del dueño 11-08-2026: «otra
 * opción para crear uno nuevo si a futuro pidieran»).
 *
 * Los cinco de la ley —revisión técnica, emisiones, permiso, SOAP, extintor— siguen
 * siendo columnas de `vehiculos` (ver la migración). Esto es para los que aparezcan
 * después, sin tocar código.
 *
 * LA CLAVE ES `tipo:{id}` y no el nombre: el nombre se puede corregir («Póliza carga
 * peligrosa» → «Póliza de carga peligrosa») y si la clave dependiera de él, las
 * fotos y las fechas ya cargadas quedarían apuntando a un documento que no existe.
 */
class VehiculoDocumentoTipo extends Model
{
    protected $table = 'vehiculo_documento_tipos';

    protected $fillable = ['nombre', 'aplica_a', 'activo', 'orden'];

    protected $casts = [
        'aplica_a' => 'array',
        'activo' => 'boolean',
    ];

    /** La clave con la que este tipo viaja por el resto del sistema. */
    public function getClaveAttribute(): string
    {
        return self::clavePara($this->id);
    }

    public static function clavePara(int $id): string
    {
        return 'tipo:'.$id;
    }

    /** El id que hay adentro de una clave `tipo:12`, o null si no es una de estas. */
    public static function idDeClave(string $clave): ?int
    {
        return str_starts_with($clave, 'tipo:') ? (int) substr($clave, 5) : null;
    }

    /** ¿Le toca a un vehículo de este tipo? Lista vacía o nula = a todos. */
    public function aplicaA(?string $tipoVehiculo): bool
    {
        return blank($this->aplica_a) || in_array($tipoVehiculo, $this->aplica_a, true);
    }

    /**
     * Los que hoy están en uso, en el orden en que se muestran.
     *
     * @param  Builder<VehiculoDocumentoTipo>  $q
     * @return Builder<VehiculoDocumentoTipo>
     */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->where('activo', true)->orderBy('orden')->orderBy('id');
    }
}
