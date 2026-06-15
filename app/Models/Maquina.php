<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Maquina extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'maquinas';

    protected $fillable = [
        'nombre',
        'sucursal_id',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function registros(): HasMany
    {
        return $this->hasMany(ProduccionRegistro::class, 'maquina_id');
    }

    /**
     * Maquinas que un soplador puede elegir: las activas de SU sucursal.
     * Fallback: si no tiene sucursal (o su sucursal no tiene maquinas),
     * todas las activas — un soplador nunca queda bloqueado para reportar.
     *
     * UNICA fuente para el selector de la vista Y la validacion (Rule::in):
     * sincronia por construccion.
     */
    public static function paraSoplador(User $user): Collection
    {
        $activas = static::query()->with('sucursal')->where('activa', true)->orderBy('nombre')->get();

        if ($user->sucursal_id) {
            $deSuSucursal = $activas->where('sucursal_id', $user->sucursal_id)->values();

            if ($deSuSucursal->isNotEmpty()) {
                return $deSuSucursal;
            }
        }

        return $activas;
    }
}
