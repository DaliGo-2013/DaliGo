<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Comprime y guarda una imagen subida en el disco PRIVADO `local`.
 *
 * Redimensiona el lado largo a <= MAX_LADO y re-encoda a JPEG (calidad JPEG_Q).
 * Re-encodear ademas SANEA el archivo (defensa en profundidad: descarta cualquier
 * payload disfrazado de imagen en un endpoint publico). Corrige la orientacion
 * EXIF para que las fotos de celular no salgan giradas. Si GD no puede decodificar
 * el archivo (p. ej. HEIC en un servidor sin soporte), guarda el original tal cual
 * como fallback para no bloquear al cliente.
 *
 * Devuelve la ruta relativa dentro del disco `local`.
 */
class ImagenComprimida
{
    private const MAX_LADO = 1280;

    private const JPEG_Q = 72;

    public static function guardar(UploadedFile $file, string $carpeta): string
    {
        $base = trim($carpeta, '/').'/'.Str::random(40);
        $bytes = (string) file_get_contents($file->getRealPath());
        $img = @imagecreatefromstring($bytes);

        // GD no pudo leerla (ej. HEIC sin soporte): guardar el original.
        if (! $img instanceof GdImage) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'img');
            Storage::disk('local')->put("{$base}.{$ext}", $bytes);

            return "{$base}.{$ext}";
        }

        $img = self::corregirOrientacion($img, $file);

        $lado = max(imagesx($img), imagesy($img));
        if ($lado > self::MAX_LADO) {
            $escalada = imagescale($img, (int) round(imagesx($img) * self::MAX_LADO / $lado));
            if ($escalada instanceof GdImage) {
                imagedestroy($img);
                $img = $escalada;
            }
        }

        ob_start();
        imagejpeg($img, null, self::JPEG_Q);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        Storage::disk('local')->put("{$base}.jpg", $jpeg);

        return "{$base}.jpg";
    }

    /** Rota la imagen segun el EXIF de la foto (celulares) si exif esta disponible. */
    private static function corregirOrientacion(GdImage $img, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($file->getRealPath());
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($grados === 0) {
            return $img;
        }

        $rotada = imagerotate($img, $grados, 0);
        if (! $rotada instanceof GdImage) {
            return $img;
        }

        imagedestroy($img);

        return $rotada;
    }
}
