<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una mantención del molde (P-M11-12). La preventiva la registra un humano
 * desde la ficha (nace completada y resetea el contador); la CORRECTIVA
 * puede nacer sola — pendiente — desde un reporte aprobado con parada
 * «Molde dañado» (el `reporte_id` es su guard de idempotencia y su traza).
 */
class MoldeMantencion extends Model
{
    public const TIPO_PREVENTIVA = 'preventiva';
    public const TIPO_CORRECTIVA = 'correctiva';

    public const TIPOS = [
        self::TIPO_PREVENTIVA => 'Preventiva',
        self::TIPO_CORRECTIVA => 'Correctiva',
    ];

    protected $table = 'molde_mantenciones';

    protected $fillable = [
        'molde_id',
        'reporte_id',
        'tipo',
        'user_id',
        'user_nombre',
        'nota',
        'ciclos_al_momento',
        'realizada_at',
    ];

    protected function casts(): array
    {
        return [
            'ciclos_al_momento' => 'integer',
            'realizada_at' => 'datetime',
        ];
    }

    public function molde(): BelongsTo
    {
        return $this->belongsTo(Molde::class, 'molde_id');
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ProduccionReporte::class, 'reporte_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function pendiente(): bool
    {
        return $this->realizada_at === null;
    }
}
