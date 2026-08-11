<?php

namespace App\Services\Logistica;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Comprime el documento subido a UNA imagen JPEG liviana (~100-250 KB).
 *
 * El caso de uso manda (pedido del dueño 11-08-2026): el conductor mostrando el
 * permiso en un control de ruta, desde el teléfono y con la señal que haya. La
 * foto que saca el teléfono pesa 3-8 MB; nadie tiene que saber comprimirla — se
 * garantiza acá, en el servidor, para TODO lo que se suba.
 *
 * · Imágenes (JPG/PNG/WebP): GD, que está en cualquier hosting. Se reduce el
 *   lado mayor a MAX_LADO px y se reencoda a JPEG CALIDAD. El reencode además
 *   DESCARTA los metadatos EXIF — incluida la coordenada GPS que el teléfono
 *   graba en cada foto, que en un archivo que lleva patente y se muestra a
 *   terceros es exactamente lo que no debe viajar (Ley 21.719).
 * · PDF (el SOAP llega así por correo): necesita Imagick, que no está en todos
 *   los entornos. Se detecta la capacidad; sin Imagick el PDF se RECHAZA con el
 *   camino alternativo en el mensaje (sacarle una foto o captura), en vez de
 *   guardarlo tal cual — un PDF de 5 MB con visor distinto en iPhone y Android
 *   es justo lo que este servicio existe para evitar.
 *
 * Siempre JPEG y no WebP/AVIF a propósito: es el formato que CUALQUIER teléfono
 * y navegador de los conductores muestra sin sorpresas, y la diferencia de peso
 * a esta calidad no cambia el caso de uso.
 */
class CompresorDeDocumentos
{
    /** Lado mayor del JPEG final: suficiente para leer el documento con zoom. */
    public const MAX_LADO = 1600;

    /** Calidad JPEG: legible con zoom y ~100-250 KB por documento. */
    public const CALIDAD = 72;

    public function __construct(
        private ?bool $pdfDisponible = null,
    ) {
        $this->pdfDisponible ??= extension_loaded('imagick');
    }

    /**
     * @return string  binario JPEG comprimido
     *
     * @throws ValidationException si el archivo no se puede leer o es un PDF sin Imagick
     */
    public function aJpeg(UploadedFile $archivo): string
    {
        if (strtolower($archivo->getClientOriginalExtension()) === 'pdf'
            || $archivo->getMimeType() === 'application/pdf') {
            return $this->desdePdf($archivo);
        }

        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if ($imagen === false) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer la imagen. Subí una foto JPG o PNG del documento.',
            ]);
        }

        return $this->comprimir($imagen);
    }

    /** Reduce al lado máximo y reencoda. El reencode con GD no copia EXIF: la
     *  coordenada GPS de la foto original muere acá. */
    private function comprimir(\GdImage $imagen): string
    {
        $w = imagesx($imagen);
        $h = imagesy($imagen);

        $escala = min(1, self::MAX_LADO / max($w, $h));
        if ($escala < 1) {
            $imagen = imagescale($imagen, (int) round($w * $escala), (int) round($h * $escala), IMG_BICUBIC);
        }

        // Fondo blanco bajo cualquier transparencia (PNG de una captura): JPEG
        // no tiene alfa y sin esto la transparencia sale NEGRA — ilegible.
        $plano = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
        imagefill($plano, 0, 0, imagecolorallocate($plano, 255, 255, 255));
        imagecopy($plano, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));

        ob_start();
        imagejpeg($plano, null, self::CALIDAD);

        return (string) ob_get_clean();
    }

    /** Primera página del PDF a JPEG (los documentos del vehículo son de una). */
    private function desdePdf(UploadedFile $archivo): string
    {
        if (! $this->pdfDisponible) {
            throw ValidationException::withMessages([
                'archivo' => 'Este servidor no puede convertir PDF. Sacale una foto al documento '
                    .'(o una captura de pantalla al PDF) y subí esa imagen.',
            ]);
        }

        $im = new \Imagick;
        $im->setResolution(150, 150);
        $im->readImage($archivo->getRealPath().'[0]');
        $im->setImageFormat('jpeg');

        $gd = @imagecreatefromstring($im->getImageBlob());
        $im->clear();
        if ($gd === false) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo convertir el PDF. Sacale una foto al documento y subí esa imagen.',
            ]);
        }

        return $this->comprimir($gd);
    }
}
