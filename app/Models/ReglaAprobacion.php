<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Regla del motor de aprobaciones (M14): define si un `tipo_accion` requiere
 * aprobación humana y de quién. `umbral_config` apunta a una CLAVE de
 * `configuraciones` (el admin edita el umbral en /admin/configuracion, sin UI
 * de reglas en v1); NULL = matchea siempre. `rol_aprobador`/`rol_escalamiento`
 * son nombres de rol spatie — renombrar un rol rompería el match (los 8 roles
 * del negocio son estables; no renombrar sin migrar estas columnas).
 */
class ReglaAprobacion extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'reglas_aprobacion';

    protected $fillable = [
        'tipo_accion',
        'descripcion',
        'activa',
        'umbral_config',
        'rol_aprobador',
        'rol_escalamiento',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function aprobaciones(): HasMany
    {
        return $this->hasMany(Aprobacion::class, 'regla_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * Umbral vigente de la regla (entero desde `configuraciones`), o null si
     * la regla no tiene umbral (matchea siempre).
     */
    public function umbral(): ?int
    {
        if ($this->umbral_config === null) {
            return null;
        }

        $valor = Configuracion::get($this->umbral_config);

        return $valor === null ? null : (int) $valor;
    }
}
