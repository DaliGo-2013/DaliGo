<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Sucursal extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\SucursalFactory> */
    use HasFactory, AuditableTrait;

    // El pluralizador de Laravel haria 'sucursals'; fijamos la tabla correcta.
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'codigo',
        'ciudad',
        'direccion',
        'es_central',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'es_central' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    /**
     * Usuarios asignados a esta sucursal.
     *
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Maquinas sopladoras de esta sucursal.
     *
     * @return HasMany<Maquina>
     */
    public function maquinas(): HasMany
    {
        return $this->hasMany(Maquina::class);
    }
}
