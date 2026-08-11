<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Services\Logistica\CompresorDeDocumentos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Respaldo digital de los documentos del vehículo (pedido del dueño 11-08-2026):
 * quien gestiona la flota sube la foto del permiso / SOAP / revisión, y el
 * conductor la muestra desde el teléfono si lo controlan en un reparto.
 *
 * SUBIR es de `manage vehiculos` (los documentos oficiales los maneja quien los
 * renueva); VER es de `ver vehiculos`, que ahora también lo tiene el rol
 * conductor. El archivo vive en storage/ —privado, fuera del docroot y fuera
 * del repo público (D-012)— y se sirve SOLO por acá, autenticado: lleva la
 * patente, que es dato personal bajo la 21.719.
 */
class VehiculoDocumentoController extends Controller
{
    /** El disco privado donde viven los respaldos. */
    public const DISCO = 'local';

    public function __construct(private CompresorDeDocumentos $compresor) {}

    /** Sube (y comprime) un respaldo nuevo. El anterior queda como historial. */
    public function store(Request $request, Vehiculo $vehiculo, string $documento): RedirectResponse
    {
        abort_unless(array_key_exists($documento, Vehiculo::DOCUMENTOS), 404);

        $request->validate(
            // 15 MB de entrada: una foto de teléfono son 3-8. La salida pesa
            // ~100-250 KB — la garantiza el compresor, no el que sube.
            ['archivo' => ['required', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,pdf']],
            ['archivo.max' => 'El archivo no puede superar los 15 MB.'],
        );

        $jpeg = $this->compresor->aJpeg($request->file('archivo'));

        $ruta = sprintf('vehiculos-documentos/%d/%s-%s.jpg', $vehiculo->id, $documento, now()->format('YmdHis'));
        Storage::disk(self::DISCO)->put($ruta, $jpeg);

        VehiculoDocumento::create([
            'vehiculo_id' => $vehiculo->id,
            'documento' => $documento,
            'ruta' => $ruta,
            'tamano_kb' => max(1, (int) round(strlen($jpeg) / 1024)),
            'subido_por' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.vehiculos.show', $vehiculo)
            ->with('status', Vehiculo::DOCUMENTOS[$documento].': respaldo subido ('.max(1, (int) round(strlen($jpeg) / 1024)).' KB).');
    }

    /**
     * El documento a pantalla completa, pensado para el CONTROL EN RUTA: el
     * conductor con el teléfono en la mano. La imagen es la protagonista y el
     * historial va debajo, plegado.
     */
    public function show(Vehiculo $vehiculo, string $documento): View
    {
        abort_unless(array_key_exists($documento, Vehiculo::DOCUMENTOS), 404);

        $versiones = VehiculoDocumento::delDocumento($vehiculo->id, $documento)->with('autor:id,name')->get();
        abort_if($versiones->isEmpty(), 404);

        // La fecha de vencimiento que la ficha ya conoce, para decirla junto al
        // documento: en el control, «vence el 30-09-2026» es la mitad del valor.
        $ficha = collect($vehiculo->documentos())->firstWhere('clave', $documento);

        return view('admin.vehiculos.documento', [
            'vehiculo' => $vehiculo,
            'clave' => $documento,
            'label' => Vehiculo::DOCUMENTOS[$documento],
            'vigente' => $versiones->first(),
            'historial' => $versiones->slice(1),
            'ficha' => $ficha,
        ]);
    }

    /** Los bytes del JPEG, autenticados. Nunca hay URL pública al archivo. */
    public function archivo(VehiculoDocumento $doc): Response
    {
        abort_unless(Storage::disk(self::DISCO)->exists($doc->ruta), 404);

        return response(Storage::disk(self::DISCO)->get($doc->ruta), 200, [
            'Content-Type' => 'image/jpeg',
            // `private`: puede cachearlo el TELÉFONO del conductor (así el
            // documento abre al toque en el control, aun con mala señal), pero
            // ningún proxy intermedio.
            'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline; filename="documento-'.$doc->id.'.jpg"',
        ]);
    }
}
