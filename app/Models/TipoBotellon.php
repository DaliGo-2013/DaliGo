<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TipoBotellon extends Model implements AuditableContract
{
    use AuditableTrait;

    // El pluralizador de Laravel haria 'tipo_botellons'; fijamos la tabla correcta.
    protected $table = 'tipos_botellon';

    protected $fillable = [
        'codigo',
        'nombre',
        'producto_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Producto del catalogo que representa este botellon terminado. Nullable:
     * un tipo sin enlazar aun al catalogo igual sirve para reportar (el kardex
     * registra el movimiento sin producto hasta que se enlace).
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(ProduccionRegistro::class, 'tipo_botellon_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
