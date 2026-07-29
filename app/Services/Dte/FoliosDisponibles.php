<?php

namespace App\Services\Dte;

use Illuminate\Support\Carbon;

/**
 * Estado del stock de folios (CAF) para un tipo de DTE.
 *
 * Importa vigilarlo por dos razones distintas, y las dos frenan la operación:
 *   1. AGOTAMIENTO: sin folios no se puede emitir, y pedirlos al SII no es
 *      instantáneo.
 *   2. VENCIMIENTO: desde la Res. Ex. SII N°58/2017 los CAF valen SEIS MESES
 *      desde su autorización. Vencido el plazo, "el Servicio rechazará al
 *      momento de su recepción" los documentos con folios de ese CAF, y los no
 *      usados hay que anularlos. Un CAF con folios de sobra pero vencido es
 *      igual de inútil que uno agotado.
 */
class FoliosDisponibles
{
    public function __construct(
        public int $tipoDte,
        public int $disponibles,
        public ?int $ultimoUsado = null,
        public ?int $desde = null,
        public ?int $hasta = null,
        public ?string $venceEl = null,
        public bool $vencido = false,
    ) {}

    /** ¿Quedan menos folios que el umbral? (para avisar antes de quedarse en cero) */
    public function estaPorAgotarse(int $umbral = 50): bool
    {
        return $this->disponibles <= $umbral;
    }

    /** ¿El CAF vence dentro de los próximos N días? */
    public function estaPorVencer(int $dias = 15): bool
    {
        if ($this->vencido) {
            return true;
        }

        if (blank($this->venceEl)) {
            return false;
        }

        return Carbon::parse($this->venceEl)->lessThanOrEqualTo(
            \App\Support\FechaNegocio::ahora()->addDays($dias)
        );
    }

    /** ¿Hay algo que avisar sobre este tipo de documento? */
    public function requiereAtencion(int $umbral = 50, int $dias = 15): bool
    {
        return $this->estaPorAgotarse($umbral) || $this->estaPorVencer($dias);
    }
}
