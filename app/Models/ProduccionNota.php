<?php

namespace App\Models;

use App\Support\FechaNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Nota del jefe para los sopladores (P-M11-22): un mensaje operativo que se
 * PINTA en mi-reporte del destinatario mientras este vigente («hoy llegan
 * preformas nuevas», «cuidado con el molde 3»). No persigue a nadie (sin
 * M15): la nota vive en la pantalla. soplador_id NULL = para todos.
 *
 * Auditable como Maquina: es contenido del jefe y conviene la traza de quien
 * escribio/edito que.
 */
class ProduccionNota extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'produccion_notas';

    protected $fillable = [
        'autor_id',
        'soplador_id',
        'texto',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function soplador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soplador_id');
    }

    /**
     * Vigentes en el dia de negocio dado (default: hoy chileno). whereDate en
     * AMBOS bordes, JAMAS whereBetween (cast date guarda 'Y-m-d 00:00:00' y
     * el borde superior se escapa — bitacora 2026-07-01/07-02); bordes null =
     * sin limite (molde de vigencia de AgendaTrabajo).
     */
    public function scopeVigentes($query, ?string $hoy = null)
    {
        $hoy ??= FechaNegocio::hoy();

        return $query
            ->where(function ($q) use ($hoy) {
                $q->whereNull('vigente_desde')->orWhereDate('vigente_desde', '<=', $hoy);
            })
            ->where(function ($q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $hoy);
            });
    }

    /**
     * Las que le hablan a ESTE soplador: las suyas + las globales. El orWhere
     * va AGRUPADO en su closure para no fugarse del resto del query.
     */
    public function scopeParaSoplador($query, int $sopladorId)
    {
        return $query->where(function ($q) use ($sopladorId) {
            $q->whereNull('soplador_id')->orWhere('soplador_id', $sopladorId);
        });
    }

    /**
     * ¿Vigente en el dia de negocio dado? Version en PHP del scope, para
     * badges de listado sin una query por fila. Compara FECHAS (toDateString),
     * no instantes: los casts date se hidratan a medianoche UTC y un lt/gt
     * contra la hora chilena se corre un dia (bitacora 2026-08-04).
     */
    public function esVigente(?string $hoy = null): bool
    {
        $hoy ??= FechaNegocio::hoy();

        $desdeOk = $this->vigente_desde === null || $this->vigente_desde->toDateString() <= $hoy;
        $hastaOk = $this->vigente_hasta === null || $this->vigente_hasta->toDateString() >= $hoy;

        return $desdeOk && $hastaOk;
    }
}
