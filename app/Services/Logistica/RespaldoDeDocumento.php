<?php

namespace App\Services\Logistica;

use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guardar la foto de un documento del vehículo: comprimir, dejarla en el disco
 * privado y anotar la versión.
 *
 * POR QUÉ ES UN SERVICIO Y NO UN MÉTODO DEL CONTROLADOR. El mismo respaldo se sube
 * desde DOS pantallas y por dos motivos distintos:
 *
 *  · La FICHA, de a un documento y sin botón de guardar: es el camino del teléfono,
 *    parado al lado del camión. Elegir la foto ya es la acción.
 *  · EDITAR, junto a la fecha de vencimiento y guardando todo con «Guardar cambios»
 *    (pedido del dueño 11-08-2026). Un documento son dos datos —la foto y hasta
 *    cuándo vale— y estaban en pantallas distintas: se subía la foto en la ficha y
 *    había que entrar a Editar para escribir la fecha. Dos viajes para un papel.
 *
 * Las dos tienen que dejar EXACTAMENTE el mismo rastro. Con la lógica copiada, la
 * de Editar se habría quedado sin la compresión o sin el `subido_por` el día que
 * una de las dos cambie, y eso no se nota hasta que alguien abre un documento de
 * 6 MB en el celular en medio de un control.
 */
class RespaldoDeDocumento
{
    /** El disco privado donde viven los respaldos: fuera del docroot y del repo. */
    public const DISCO = 'local';

    /**
     * Tope de ENTRADA en KB (15 MB): una foto de teléfono son 3-8 MB; la salida
     * pesa 100-250 KB y eso lo garantiza el compresor, no quien sube. Los
     * mensajes de error que nombran el tope DERIVAN de acá (LOG-1: el «15 MB»
     * vivía retipeado en dos controllers y mentiría al cambiar esto).
     */
    public const MAX_KB = 15360;

    /**
     * Reglas del archivo.
     *
     * @return list<string>
     */
    public static function reglas(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'mimes:jpg,jpeg,png,webp,pdf'];
    }

    /** El tope legible para mensajes al usuario («15 MB»), derivado de MAX_KB. */
    public static function topeLegible(): string
    {
        return round(self::MAX_KB / 1024).' MB';
    }

    public function __construct(private CompresorDeDocumentos $compresor) {}

    /** Comprime, guarda y deja anotada la versión nueva. La anterior queda de historial. */
    public function guardar(Vehiculo $vehiculo, string $documento, UploadedFile $archivo, int $usuarioId): VehiculoDocumento
    {
        $jpeg = $this->compresor->aJpeg($archivo);

        $ruta = sprintf('vehiculos-documentos/%d/%s-%s.jpg', $vehiculo->id, $documento, now()->format('YmdHis'));
        Storage::disk(self::DISCO)->put($ruta, $jpeg);

        return VehiculoDocumento::create([
            'vehiculo_id' => $vehiculo->id,
            'documento' => $documento,
            'ruta' => $ruta,
            'tamano_kb' => max(1, (int) round(strlen($jpeg) / 1024)),
            'subido_por' => $usuarioId,
        ]);
    }
}
