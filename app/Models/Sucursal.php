<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    /** @use HasFactory<\Database\Factories\SucursalFactory> */
    use HasFactory;

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
}
