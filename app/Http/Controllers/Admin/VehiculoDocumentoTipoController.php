<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\VehiculoDocumentoFecha;
use App\Models\VehiculoDocumentoTipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Crear tipos de documento para la flota (pedido del dueño 11-08-2026: «otra opción
 * para crear uno nuevo si a futuro pidieran»).
 *
 * Los cinco de la ley —revisión técnica, emisiones, permiso de circulación, SOAP y
 * extintor— NO se administran acá y no se pueden borrar: son columnas del vehículo,
 * están en el Excel, en el comando de avisos y en el semáforo. Esta pantalla es para
 * los que aparezcan después: una póliza de carga peligrosa, el certificado de la
 * grúa, lo que pidan.
 *
 * DOS DECISIONES QUE IMPORTAN:
 *
 *  · SE DESACTIVA, NO SE BORRA. Un tipo con fechas y fotos ya cargadas es el registro
 *    de que ese papel existió; borrarlo se llevaría el historial puesto. Desactivado
 *    sale del semáforo, de los avisos y de los formularios, y lo cargado queda.
 *    Solo se puede borrar de verdad un tipo que nunca se usó.
 *  · APLICA A TIPOS DE VEHÍCULO. Sin esto, crear un documento deja a las 17 unidades
 *    de la flota en «sin fecha» de un día para el otro, y ese rojo es ruido si el
 *    papel era solo para los camiones.
 */
class VehiculoDocumentoTipoController extends Controller
{
    public function index(): View
    {
        return view('admin.vehiculos.tipos-documento.index', [
            'tipos' => VehiculoDocumentoTipo::orderBy('orden')->orderBy('id')->get(),
            'usos' => $this->usos(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tipo = VehiculoDocumentoTipo::create($this->datosValidados($request));
        Vehiculo::olvidarTiposCreados();

        return redirect()->route('admin.vehiculos.tipos-documento.index')
            ->with('status', "Documento «{$tipo->nombre}» creado. Ya aparece en la ficha de cada vehículo al que le aplica.");
    }

    public function update(Request $request, VehiculoDocumentoTipo $tipo): RedirectResponse
    {
        $tipo->update($this->datosValidados($request, $tipo));
        Vehiculo::olvidarTiposCreados();

        return redirect()->route('admin.vehiculos.tipos-documento.index')
            ->with('status', "Documento «{$tipo->nombre}» actualizado.");
    }

    /**
     * Borra un tipo que NUNCA se usó. Si tiene fechas o fotos cargadas, se desactiva
     * en vez de borrarse: lo cargado es el registro de que ese papel existió, y un
     * clic no se puede llevar el historial de la flota.
     */
    public function destroy(VehiculoDocumentoTipo $tipo): RedirectResponse
    {
        $nombre = $tipo->nombre;

        if (($this->usos()[$tipo->id] ?? 0) > 0) {
            $tipo->update(['activo' => false]);
            Vehiculo::olvidarTiposCreados();

            return redirect()->route('admin.vehiculos.tipos-documento.index')
                ->with('status', "«{$nombre}» tiene fechas o fotos cargadas, así que se desactivó en vez de borrarse: sale del semáforo y lo cargado queda guardado.");
        }

        $tipo->delete();
        Vehiculo::olvidarTiposCreados();

        return redirect()->route('admin.vehiculos.tipos-documento.index')
            ->with('status', "Documento «{$nombre}» eliminado.");
    }

    /**
     * Cuántos vehículos tienen algo cargado de cada tipo (una fecha o una foto).
     *
     * Es lo que decide si un tipo se puede borrar, y lo que la pantalla muestra para
     * que nadie tenga que adivinar qué se lleva puesto al desactivar.
     *
     * @return array<int, int>
     */
    private function usos(): array
    {
        $porFecha = VehiculoDocumentoFecha::whereNotNull('vence')
            ->selectRaw('tipo_id, count(*) as n')->groupBy('tipo_id')->pluck('n', 'tipo_id');

        $porFoto = VehiculoDocumento::where('documento', 'like', 'tipo:%')
            ->pluck('documento')
            ->countBy(fn (string $c) => VehiculoDocumentoTipo::idDeClave($c));

        return $porFecha->keys()->merge($porFoto->keys())->unique()
            ->mapWithKeys(fn ($id) => [(int) $id => (int) ($porFecha[$id] ?? 0) + (int) ($porFoto[$id] ?? 0)])
            ->all();
    }

    /** @return array<string, mixed> */
    private function datosValidados(Request $request, ?VehiculoDocumentoTipo $tipo = null): array
    {
        $datos = $request->validate([
            // Unique por nombre: dos «Póliza de carga» en el semáforo son dos filas
            // que nadie puede distinguir en la ficha.
            'nombre' => ['required', 'string', 'max:80', Rule::unique('vehiculo_documento_tipos', 'nombre')->ignore($tipo)],
            'aplica_a' => ['nullable', 'array'],
            'aplica_a.*' => [Rule::in(array_keys(Vehiculo::TIPOS))],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.unique' => 'Ya existe un documento con ese nombre.',
        ]);

        // Todos los tipos marcados = a todos, que se guarda como lista vacía: así, si
        // mañana se agrega un tipo de vehículo al catálogo, el documento le aplica
        // solo porque quien lo creó dijo «a todos», y no porque olvidó tildarlo.
        $marcados = $datos['aplica_a'] ?? [];
        $datos['aplica_a'] = count($marcados) === count(Vehiculo::TIPOS) ? [] : array_values($marcados);
        $datos['orden'] = $datos['orden'] ?? 0;
        $datos['activo'] = (bool) ($datos['activo'] ?? true);

        return $datos;
    }
}
