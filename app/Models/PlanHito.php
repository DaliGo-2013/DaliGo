<?php

namespace App\Models;

use App\Support\FechaNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Hito del plan del proyecto (/plan). Vive en BD y se edita desde la UI con el
 * permiso 'gestionar plan proyecto' (pedido del dueño 10-09-2026: «agregar
 * rápido cuando el gerente pide algo»). Los siete re-baselinados originales
 * los siembra PlanHitosSeeder desde PlanProyecto::HITOS, solo si faltan.
 */
class PlanHito extends Model
{
    protected $table = 'plan_hitos';

    protected $fillable = ['clave', 'etiqueta', 'fecha', 'cumplido'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'cumplido' => 'boolean',
        ];
    }

    /**
     * Countdown contra el DÍA DE NEGOCIO chileno (P-TZ-01), comparando fechas
     * y no instantes (gotcha [2026-08-04]): cumplido / atrasado / pendiente.
     *
     * @return array{dias: int, estado: string}
     */
    public function countdown(): array
    {
        $hoy = Carbon::parse(FechaNegocio::hoy());
        $dias = (int) $hoy->diffInDays(Carbon::parse($this->fecha->toDateString()), false);

        return [
            'dias' => $dias,
            'estado' => $this->cumplido ? 'cumplido' : ($dias < 0 ? 'atrasado' : 'pendiente'),
        ];
    }
}
