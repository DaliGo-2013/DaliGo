<?php

namespace App\Services\Dte;

/**
 * Lo que devuelve el emisor tras intentar emitir (o al reconsultar el estado).
 *
 * Incluye `urlXml` y `urlPdf` porque el respaldo del documento electrónico es
 * obligación del contribuyente por 6 años (Res. Ex. SII 45/2003): si mañana se
 * corta con el emisor, esas referencias son el rastro de lo emitido.
 *
 * `crudo` guarda la respuesta original del emisor. No se usa en la lógica, pero
 * cuando el SII rechaza un documento la causa suele estar en un campo que no
 * mapeamos, y sin el crudo hay que reproducir la emisión para averiguarlo.
 */
class ResultadoEmision
{
    /**
     * @param  bool  $exitoso  El emisor aceptó la solicitud (NO implica que el SII lo aceptara).
     * @param  string  $estado  Una constante de EstadoSii.
     * @param  int|null  $folio  Folio asignado por el emisor.
     * @param  string|null  $documentoExternoId  Id del documento en el emisor (para reconsultar).
     * @param  array<string, mixed>  $crudo  Respuesta original del emisor.
     */
    public function __construct(
        public bool $exitoso,
        public string $estado,
        public ?int $folio = null,
        public ?string $documentoExternoId = null,
        public ?string $mensaje = null,
        public ?string $urlXml = null,
        public ?string $urlPdf = null,
        public int $neto = 0,
        public int $iva = 0,
        public int $total = 0,
        public ?string $ted = null,
        public array $crudo = [],
    ) {}

    /**
     * Fracaso de la EMISIÓN (no del SII): red caída, 500 del emisor, caja
     * cerrada. El documento no existe, así que se puede reintentar con el mismo
     * salesId sin riesgo de duplicar.
     */
    public static function fallida(string $mensaje, array $crudo = []): self
    {
        return new self(
            exitoso: false,
            estado: EstadoSii::ERROR,
            mensaje: $mensaje,
            crudo: $crudo,
        );
    }

    /** ¿El SII lo rechazó? Se corrige con nota de crédito, no reintentando. */
    public function fueRechazadoPorSii(): bool
    {
        return $this->estado === EstadoSii::RECHAZADO;
    }

    /** ¿Hay que volver a consultar más tarde para saber el veredicto? */
    public function requiereReconsulta(): bool
    {
        return $this->exitoso && ! EstadoSii::esFinal($this->estado);
    }
}
