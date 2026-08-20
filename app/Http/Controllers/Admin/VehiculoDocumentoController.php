<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Services\Logistica\RespaldoDeDocumento;
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
    /**
     * El disco privado donde viven los respaldos.
     *
     * Queda acá como alias del que manda (`RespaldoDeDocumento::DISCO`) porque lo
     * usan `archivo()` y los tests; el valor vive en un solo lugar.
     */
    public const DISCO = RespaldoDeDocumento::DISCO;

    public function __construct(private RespaldoDeDocumento $respaldos) {}

    /**
     * Sube (y comprime) un respaldo nuevo. El anterior queda como historial.
     *
     * Este es el camino de la FICHA: de a un documento y sin botón de guardar. El
     * otro camino es la pantalla de Editar, que sube la foto junto con la fecha de
     * vencimiento — los dos guardan por `RespaldoDeDocumento`, no por copias.
     */
    public function store(Request $request, Vehiculo $vehiculo, string $documento): RedirectResponse
    {
        abort_unless(array_key_exists($documento, Vehiculo::catalogoDocumentos()), 404);

        $request->validate(
            ['archivo' => ['required', ...RespaldoDeDocumento::reglas()]],
            ['archivo.max' => 'El archivo no puede superar los '.RespaldoDeDocumento::topeLegible().'.'],
        );

        $doc = $this->respaldos->guardar($vehiculo, $documento, $request->file('archivo'), $request->user()->id);

        return redirect()
            ->route('admin.vehiculos.show', $vehiculo)
            ->with('status', Vehiculo::catalogoDocumentos()[$documento].': respaldo subido ('.$doc->tamano_kb.' KB).');
    }

    /**
     * Quita una foto subida (pedido del dueño 11-08-2026: «dame la opción para
     * eliminar o quitar el documento»).
     *
     * BORRA UNA VERSIÓN, NO EL DOCUMENTO ENTERO. El caso real es «subí la foto
     * equivocada» o «esta quedó ilegible», no «este camión no tiene SOAP»: si había
     * una versión anterior, vuelve a quedar a la vista, que es exactamente lo que se
     * espera al deshacer una subida. Para dejar el documento sin respaldo se quitan
     * todas, de a una, y eso es a propósito — cada clic borra un archivo real.
     *
     * El archivo se borra del disco además de la fila: un respaldo huérfano en
     * storage es la patente de alguien ocupando lugar sin que nadie lo vea.
     */
    public function destroy(VehiculoDocumento $doc): RedirectResponse
    {
        $vehiculo = $doc->vehiculo;
        $label = Vehiculo::catalogoDocumentos()[$doc->documento] ?? 'Documento';

        Storage::disk(self::DISCO)->delete($doc->ruta);
        $doc->delete();

        $queda = VehiculoDocumento::delDocumento($vehiculo->id, $doc->documento)->first();

        return redirect()
            ->route('admin.vehiculos.show', $vehiculo)
            ->with('status', $queda
                ? $label.': se quitó la foto y volvió a quedar la anterior.'
                : $label.': se quitó la foto. El documento queda sin respaldo digital.');
    }

    /**
     * El documento a pantalla completa, pensado para el CONTROL EN RUTA: el
     * conductor con el teléfono en la mano. La imagen es la protagonista y el
     * historial va debajo, plegado.
     */
    public function show(Vehiculo $vehiculo, string $documento): View
    {
        abort_unless(array_key_exists($documento, Vehiculo::catalogoDocumentos()), 404);

        $versiones = VehiculoDocumento::delDocumento($vehiculo->id, $documento)->with('autor:id,name')->get();
        abort_if($versiones->isEmpty(), 404);

        // La fecha de vencimiento que la ficha ya conoce, para decirla junto al
        // documento: en el control, «vence el 30-09-2026» es la mitad del valor.
        $ficha = collect($vehiculo->documentos())->firstWhere('clave', $documento);

        return view('admin.vehiculos.documento', [
            'vehiculo' => $vehiculo,
            'clave' => $documento,
            'label' => Vehiculo::catalogoDocumentos()[$documento],
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
