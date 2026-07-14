<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Zona comercial (D-006 · DESPACHOS-v1). Catalogo simple: un vendedor atiende
 * una zona; la zona del cliente se deriva de su vendedor salvo override
 * explicito (ver Cliente::zonaEfectiva). Alimenta la hoja de ruta por zona.
 */
class Zona extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\ZonaFactory> */
    use HasFactory, AuditableTrait;

    protected $table = 'zonas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    /**
     * Vendedores (usuarios) que atienden esta zona.
     *
     * @return HasMany<User, $this>
     */
    public function vendedores(): HasMany
    {
        return $this->hasMany(User::class, 'zona_id');
    }
}
