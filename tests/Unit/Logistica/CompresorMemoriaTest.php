<?php

namespace Tests\Unit\Logistica;

use App\Services\Logistica\CompresorDeDocumentos;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * SUBIR LA FOTO DE UN DOCUMENTO NO PUEDE MATAR LA PETICION (15-09-2026).
 *
 * El incidente: al crear un vehiculo con la foto de un documento, la pantalla
 * devolvia el 500 generico con codigo de incidente. La causa no estaba en el
 * alta sino en la compresion: GD descomprime la imagen a ~12 bytes por pixel y
 * eso NO depende de lo que pese el archivo. Medido sobre este mismo compresor:
 *
 *   foto de 12 MP (telefono comun, 1,8 MB) -> 134 MB de pico
 *   foto de 48 MP (gama alta,      5,7 MB) -> 440 MB de pico
 *
 * Con el `memory_limit` de 128M habitual en un hosting compartido, la foto de
 * CUALQUIER telefono se lo come. Y un agotamiento de memoria es FATAL: no lo
 * atrapa ningun try/catch, se lleva el proceso entero y sale como 500.
 *
 * POR ESO EL CANDADO DE ORDEN ES ESTRUCTURAL Y NO DE CONDUCTA: un fatal por
 * memoria mata tambien a PHPUnit, asi que NINGUN test puede ejecutar el caso
 * malo y sobrevivir para contarlo. Lo unico verificable desde la suite es que
 * la decision se siga tomando ANTES de decodificar.
 */
class CompresorMemoriaTest extends TestCase
{
    /**
     * Un PNG de 45 bytes cuya cabecera IHDR DECLARA las medidas que se le pidan.
     *
     * Es el fixture que hace verificable el arreglo: pesa nada y dice ser
     * enorme, asi que solo puede rechazarlo quien mira la CABECERA. Un archivo
     * de 100 MP de verdad ocuparia 400 MB en el proceso de test — o sea que el
     * propio test seria el fatal que venimos a evitar.
     */
    private function pngQueDeclara(int $ancho, int $alto): string
    {
        $ihdr = pack('NN', $ancho, $alto).chr(8).chr(2).chr(0).chr(0).chr(0);

        $png = "\x89PNG\r\n\x1a\n"
            .pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        $ruta = tempnam(sys_get_temp_dir(), 'dg').'.png';
        file_put_contents($ruta, $png);

        return $ruta;
    }

    public function test_una_foto_gigante_avisa_en_vez_de_matar_la_peticion(): void
    {
        $ruta = $this->pngQueDeclara(11000, 9100);   // 100 MP en 45 bytes

        // Control de que el fixture mide lo que dice: si `getimagesize` dejara de
        // leerlo, el guard se abstendria y este test pasaria por la razon
        // equivocada (el archivo tampoco es una imagen decodificable).
        $this->assertSame([11000, 9100], array_slice((array) getimagesize($ruta), 0, 2));

        try {
            (new CompresorDeDocumentos)->aJpeg(new UploadedFile($ruta, 'doc.png', 'image/png', null, true));
            $this->fail('Una foto de 100 MP tiene que avisar, no seguir de largo hasta el fatal.');
        } catch (ValidationException $e) {
            $mensaje = (string) collect($e->errors())->flatten()->first();

            // El aviso nombra el tamano y da una salida: sin eso el usuario solo
            // sabe que "no se pudo" y vuelve a intentar lo mismo.
            $this->assertStringContainsString('100 megapíxeles', $mensaje);
            $this->assertStringContainsString('captura', $mensaje);
        } finally {
            @unlink($ruta);
        }
    }

    /**
     * CONTROL POSITIVO, y no es decorativo: sin el, un guard que rechazara TODA
     * imagen pasaria el test de arriba y habriamos cambiado un 500 por la
     * imposibilidad de subir documentos.
     */
    public function test_una_foto_normal_se_sigue_comprimiendo(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'dg').'.jpg';
        $im = imagecreatetruecolor(800, 600);
        imagefilledellipse($im, 400, 300, 300, 300, imagecolorallocate($im, 10, 120, 200));
        imagejpeg($im, $ruta, 90);
        imagedestroy($im);

        $jpeg = (new CompresorDeDocumentos)->aJpeg(new UploadedFile($ruta, 'doc.jpg', 'image/jpeg', null, true));

        $this->assertNotSame('', $jpeg);
        $this->assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->buffer($jpeg));

        @unlink($ruta);
    }

    /**
     * EL ORDEN ES EL ARREGLO: primero se asegura la memoria, DESPUES se
     * decodifica. Al reves el guard es decorativo — el fatal ocurre en la
     * decodificacion y nunca se llega a preguntar si cabia.
     *
     * Se compara la POSICION de las dos llamadas dentro del cuerpo de `aJpeg`, y
     * no su mera presencia: las dos seguirian presentes con el orden invertido.
     */
    public function test_la_memoria_se_asegura_antes_de_decodificar(): void
    {
        $fuente = file_get_contents(app_path('Services/Logistica/CompresorDeDocumentos.php'));

        $ini = strpos($fuente, 'public function aJpeg(');
        $this->assertNotFalse($ini, 'Se fue `aJpeg()`: el candado dejaria de mirar el metodo que protege.');

        $cuerpo = substr($fuente, $ini, strpos($fuente, "\n    }", $ini) - $ini);

        $guarda = strpos($cuerpo, 'asegurarMemoriaPara(');
        $decodifica = strpos($cuerpo, 'imagecreatefromstring(');

        $this->assertNotFalse($guarda, 'Se fue la guarda de memoria: vuelve el 500 al subir una foto de telefono.');
        $this->assertNotFalse($decodifica, 'Se fue la decodificacion: el candado ya no mide lo que dice medir.');
        $this->assertLessThan(
            $decodifica,
            $guarda,
            'La memoria se asegura DESPUES de decodificar: para cuando se pregunta si cabia, el fatal ya ocurrio.'
        );
    }
}
