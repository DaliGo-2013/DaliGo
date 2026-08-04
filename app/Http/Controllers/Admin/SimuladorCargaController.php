<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoBulto;
use App\Models\Vehiculo;
use App\Services\Carga\CalculoDeCarga;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Simulador de carga (módulo LOGÍSTICA, pedido del dueño 04-08-2026).
 *
 * PARA QUÉ EXISTE. Un vendedor con un cliente lejos pregunta «¿alcanza tanta
 * cantidad en tal camión?» y hoy se responde de memoria. La flota abarca de 18,9
 * a 67,7 m³ —3,6 veces— así que adivinar sale mal en las dos direcciones: se
 * manda un camión a medio llenar, o se promete carga que queda en el andén.
 *
 * NO ES OPERATIVO. No escribe despachos, no toca facturación, no registra nada:
 * es una calculadora. Por eso es el módulo más seguro de la app — si se apaga, no
 * se pierde ni un dato.
 *
 * El cálculo vive en App\Services\Carga\CalculoDeCarga (rejilla por división
 * entera, no división de volúmenes) y este controlador solo arma la pantalla.
 */
class SimuladorCargaController extends Controller
{
    public function __construct(private CalculoDeCarga $calculo) {}

    public function index(Request $request): View
    {
        $datos = $request->validate([
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'tipo_bulto_id' => ['nullable', 'integer', 'exists:tipos_bulto,id'],
        ]);

        // Solo los vehículos con las tres medidas útiles cargadas: sin ellas no
        // hay nada que calcular, y mostrarlos en el selector para que después
        // salga un cero sería prometer una función que no está.
        $vehiculos = Vehiculo::query()
            ->whereNotNull('largo_util_cm')->whereNotNull('ancho_util_cm')->whereNotNull('alto_util_cm')
            ->orderByRaw('(largo_util_cm * ancho_util_cm * alto_util_cm) desc')
            ->get();

        $bultos = TipoBulto::where('activo', true)->orderBy('categoria')->orderBy('nombre')->get();

        $sinMedidas = Vehiculo::whereNull('largo_util_cm')->count();

        $vehiculo = isset($datos['vehiculo_id'])
            ? $vehiculos->firstWhere('id', (int) $datos['vehiculo_id'])
            : $vehiculos->first();

        $bulto = isset($datos['tipo_bulto_id'])
            ? $bultos->firstWhere('id', (int) $datos['tipo_bulto_id'])
            : $bultos->first();

        $resultado = ($vehiculo && $bulto)
            ? $this->calculo->cupo($this->vehiculoParaCalculo($vehiculo), $bulto->paraCalculo())
            : null;

        return view('admin.carga.index', [
            'vehiculos' => $vehiculos,
            'bultos' => $bultos,
            'vehiculo' => $vehiculo,
            'bulto' => $bulto,
            'resultado' => $resultado,
            'sinMedidas' => $sinMedidas,
            // La escena 3D se arma en el cliente con estos números: no hay ningún
            // modelo 3D: la silueta y los bultos son prismas derivados de las medidas.
            'escena' => ($vehiculo && $bulto && $resultado) ? [
                'vehiculo' => [
                    'nombre' => $vehiculo->alias ?: trim($vehiculo->marca.' '.$vehiculo->modelo) ?: $vehiculo->ppu,
                    'largo' => $vehiculo->largo_util_cm / 100,
                    'ancho' => $vehiculo->ancho_util_cm / 100,
                    'alto' => $vehiculo->alto_util_cm / 100,
                    'ejes' => 2,
                ],
                'bulto' => [
                    'nombre' => $bulto->nombre,
                    'unidades' => $bulto->unidades,
                ],
                'rejilla' => $resultado['rejilla'],
                'orientacion' => [
                    'largo' => $resultado['orientacion']['largo'] / 100,
                    'ancho' => $resultado['orientacion']['ancho'] / 100,
                    'alto' => $resultado['orientacion']['alto'] / 100,
                ],
                'tope' => $resultado['bultos'],
            ] : null,
        ]);
    }

    /** @return array{largo:int,ancho:int,alto:int,peso_max_kg:int|null,pasillo:int} */
    private function vehiculoParaCalculo(Vehiculo $v): array
    {
        return [
            'largo' => (int) $v->largo_util_cm,
            'ancho' => (int) $v->ancho_util_cm,
            'alto' => (int) $v->alto_util_cm,
            'peso_max_kg' => $v->capacidad_carga_kg ? (int) $v->capacidad_carga_kg : null,
            'pasillo' => (int) ($v->pasillo_cm ?? 0),
        ];
    }
}
