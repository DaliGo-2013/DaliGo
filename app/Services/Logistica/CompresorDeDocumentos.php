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

    /**
     * Lo que GD gasta por píxel al decodificar, MEDIDO y no deducido: 4 bytes son
     * el bitmap y el resto lo usa el decodificador de paso. Sobre las mediciones
     * (12 MP → 134 MB = 11,2 B/px; 48 MP → 440 MB = 9,2 B/px) se toma 12 para
     * quedar del lado seguro — equivocarse hacia abajo acá es volver al 500.
     */
    private const BYTES_POR_PIXEL = 12;

    /** Lo que la petición ya tenía ocupado antes de llegar acá (Laravel, la sesión, el archivo). */
    private const COLCHON_BYTES = 48 * 1024 * 1024;

    /**
     * Techo de lo que se pide para UNA foto: cubre **48 MP**, que es el máximo que
     * saca hoy un teléfono de gama alta con la cámara al máximo (lo común son 12).
     * Más arriba de eso ya no es una foto de un documento, y conviene pedir una
     * captura antes que dejar que una sola petición se lleve el servidor.
     *
     * OJO con lo que significa: `ini_set` sube el PERMISO, no reserva la memoria.
     * Una foto de 48 MP termina usando ~440 MB medidos, y solo mientras se
     * comprime; el resto de las peticiones no paga nada por este techo.
     */
    private const TECHO_BYTES = 640 * 1024 * 1024;

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

        // LA MEMORIA SE ASEGURA ANTES DE DECODIFICAR, y ese orden es el arreglo.
        // `getimagesize` lee solo la cabecera (no decodifica, no cuesta memoria),
        // así que se sabe cuánto va a pesar ANTES de intentarlo.
        $this->asegurarMemoriaPara($archivo);

        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if ($imagen === false) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer la imagen. Subí una foto JPG o PNG del documento.',
            ]);
        }

        return $this->comprimir($imagen);
    }

    /**
     * EL ARREGLO DEL 500 AL SUBIR UNA FOTO (15-09-2026).
     *
     * GD descomprime la imagen a ~12 bytes por píxel (4 del bitmap más lo que el
     * decodificador usa de paso), y eso NO depende de lo que pese el archivo:
     * medido, una foto de teléfono común de 12 MP ocupa **134 MB de pico** pesando
     * 1,8 MB, y una de 48 MP llega a **440 MB** pesando 5,7 MB. Con el
     * `memory_limit` de 128M habitual en un hosting compartido, la foto de
     * CUALQUIER teléfono mata la petición — y un agotamiento de memoria es fatal:
     * no hay `try/catch` que lo atrape, sale como 500 y el alta del vehículo se
     * corta a la mitad.
     *
     * Por eso no alcanza con liberar las imágenes intermedias (se hizo, y el pico
     * NO bajó: lo causa la decodificación, antes de que haya algo que liberar).
     * Hay que decidir ANTES de decodificar, y para eso `getimagesize` es gratis:
     * lee la cabecera del archivo, no la imagen.
     *
     * Con eso hay dos salidas y NINGUNA es un 500:
     *  · entra (subiendo el tope de esta petición si hace falta) → se comprime;
     *  · no entra ni con el tope arriba → `ValidationException` con un camino
     *    concreto, que es lo que el usuario puede hacer algo con.
     */
    private function asegurarMemoriaPara(UploadedFile $archivo): void
    {
        $medidas = @getimagesize($archivo->getRealPath());

        // Sin cabecera legible no se puede estimar. No se rechaza acá: puede ser
        // un formato que `getimagesize` no conoce y `imagecreatefromstring` sí,
        // y el caso de archivo ilegible ya lo cubre el llamador.
        if ($medidas === false) {
            return;
        }

        $pixeles = (int) $medidas[0] * (int) $medidas[1];
        $necesita = (int) ($pixeles * self::BYTES_POR_PIXEL) + self::COLCHON_BYTES;

        if ($necesita <= self::topeActualEnBytes()) {
            return;
        }

        // Se sube el tope SOLO lo necesario y hasta un techo: este servidor es
        // compartido y pedir 1 GB por una foto perjudica al resto de la casa.
        if ($necesita <= self::TECHO_BYTES) {
            @ini_set('memory_limit', (string) $necesita);

            // Se vuelve a LEER en vez de confiar en el retorno: hay hosting donde
            // `ini_set` está deshabilitado o tiene un techo duro más bajo, y ahí
            // daría por bueno un aumento que no ocurrió.
            if ($necesita <= self::topeActualEnBytes()) {
                return;
            }
        }

        $mp = round($pixeles / 1_000_000);

        throw ValidationException::withMessages([
            'archivo' => "La foto es demasiado grande para procesarla ({$mp} megapíxeles). "
                .'Sacale una captura de pantalla, o bajá la resolución de la cámara, y subí esa.',
        ]);
    }

    /** El `memory_limit` vigente en bytes; -1 (sin tope) se trata como infinito. */
    private static function topeActualEnBytes(): int
    {
        $tope = trim((string) ini_get('memory_limit'));

        if ($tope === '' || $tope === '-1') {
            return PHP_INT_MAX;
        }

        $n = (int) $tope;

        return match (strtolower(substr($tope, -1))) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /**
     * Reduce al lado máximo y reencoda. El reencode con GD no copia EXIF: la
     * coordenada GPS de la foto original muere acá.
     *
     * CADA IMAGEN SE LIBERA APENAS DEJA DE USARSE, y no es microoptimización: es
     * lo que evita el 500. GD guarda la imagen descomprimida a 4 bytes por píxel,
     * así que una foto de 12 MP son 48 MB — y esta función llegaba a tener TRES a
     * la vez (la original, la reducida y el plano). Medido antes de liberar:
     * 134 MB de pico con una foto de teléfono común de 1,8 MB, o sea más que el
     * `memory_limit` de 128M típico de un hosting compartido. Y un agotamiento de
     * memoria es FATAL: no lo atrapa ningún try/catch y sale como 500.
     *
     * Toma posesión de `$imagen`: quien llama no la vuelve a usar (los dos
     * llamadores hacen `return $this->comprimir(...)`).
     */
    private function comprimir(\GdImage $imagen): string
    {
        $w = imagesx($imagen);
        $h = imagesy($imagen);

        $escala = min(1, self::MAX_LADO / max($w, $h));
        if ($escala < 1) {
            $reducida = imagescale($imagen, (int) round($w * $escala), (int) round($h * $escala), IMG_BICUBIC);

            // `imagescale` devuelve false si no pudo. Sin esta guarda el false
            // seguía camino a `imagesx()`, que lanza un TypeError — otro 500.
            if ($reducida === false) {
                imagedestroy($imagen);

                throw ValidationException::withMessages([
                    'archivo' => 'No se pudo procesar la imagen. Probá con una foto más chica o una captura de pantalla.',
                ]);
            }

            imagedestroy($imagen);
            $imagen = $reducida;
        }

        // Fondo blanco bajo cualquier transparencia (PNG de una captura): JPEG
        // no tiene alfa y sin esto la transparencia sale NEGRA — ilegible.
        $plano = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
        imagefill($plano, 0, 0, imagecolorallocate($plano, 255, 255, 255));
        imagecopy($plano, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
        imagedestroy($imagen);

        ob_start();
        imagejpeg($plano, null, self::CALIDAD);
        imagedestroy($plano);

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
