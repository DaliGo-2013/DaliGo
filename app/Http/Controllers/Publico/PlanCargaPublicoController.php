<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Admin\SimuladorCargaController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El plan de carga en 3D, compartible por link y SIN login (pedido del dueño
 * 10-08-2026, mirando el link compartible de EasyCargo): mandarle al cliente o al
 * conductor una URL con el dibujo, sin darle una cuenta.
 *
 * POR QUÉ NO HAY TABLA NI TOKEN GUARDADO. El simulador es una función pura de su
 * query string: los mismos parámetros dan siempre el mismo plan. Así que el link
 * ES el escenario, firmado con la app key. Eso evita una migración, un modelo y
 * —sobre todo— una tabla de links viejos que hay que limpiar y que nadie limpia.
 *
 * LO QUE PROTEGE EL LINK, que es la parte delicada de publicar algo sin login:
 *
 *  1. Va FIRMADO (`signed`). Sin la firma no se puede fabricar ni retocar: cambiar
 *     una cantidad a mano invalida la URL entera. Es el mismo mecanismo que ya usan
 *     el QR del taller y las cotizaciones públicas.
 *  2. VENCE. La firma lleva expiración (7 días por defecto, ver `paraCompartir`).
 *     Un link eterno es una filtración esperando su turno; siete días cubre el
 *     ciclo de una cotización y después deja de existir.
 *  3. Es de SOLO LECTURA y no toca la base: el simulador es una calculadora.
 *  4. La pantalla pública NO trae los controles que navegan hacia adentro de la
 *     app —comparar camiones, bajar el Excel, armar pallets, importar—. Sin eso,
 *     el link sería una puerta a rutas que sí piden permiso, y aunque rebotaran,
 *     mostrarle al cliente que existen no aporta nada.
 *  5. Va con `throttle`, como el resto de lo público.
 *
 * Lo que el link SÍ muestra es lo que ya se le iba a mostrar al cliente igual: qué
 * camión, qué productos y cuántos entran. No hay datos de otros clientes ni
 * precios; el simulador no los conoce.
 */
class PlanCargaPublicoController extends Controller
{
    /** Días que vive un link compartido antes de vencer. */
    public const DIAS_VIGENCIA = 7;

    /**
     * El plan, calculado por el MISMO camino que la pantalla interna.
     *
     * Se invoca el `index()` del simulador y se leen los datos que le pasó a la
     * vista, sin renderizarla — el mismo recurso que usa la descarga en Excel. Un
     * segundo cálculo acá sería un plan público que difiere del que el vendedor
     * miró antes de mandarlo, que es exactamente lo que no puede pasar.
     */
    public function show(Request $request, SimuladorCargaController $simulador): View
    {
        $datos = $simulador->index($request)->getData();

        return view('publico.plan-carga.show', [
            'camion' => $datos['camion'] ?? null,
            'escena' => $datos['escena'] ?? null,
            'mixta' => $datos['mixta'] ?? null,
            'enPallet' => $datos['enPallet'] ?? null,
            'bulto' => $datos['bulto'] ?? null,
            'vence' => now()->addDays(self::DIAS_VIGENCIA),
            // DOS VISTAS DEL MISMO LINK (pedido del dueño 11-08: «que la otra persona lo
            // pueda ver pero no editar, y si jefatura lo pueda editar»).
            //
            // La diferencia la hace QUIÉN abre, no la URL, y eso es lo que la mantiene
            // segura: un segundo link «editable» sería una puerta al simulador sin login
            // para cualquiera que tenga la dirección, y tiraría abajo los cinco puntos de
            // arriba. Acá el cliente ve exactamente lo mismo de siempre —solo mirar— y
            // quien ya tiene permiso ve además el atajo para abrirlo y tocarlo adentro,
            // donde el permiso se vuelve a chequear en la ruta.
            //
            // Se pregunta por el PERMISO y no por «estar logueado»: un técnico con cuenta
            // no puede editar planes de carga, y el botón lo mandaría a un 403.
            'puedeEditar' => $request->user()?->can('simular carga') ?? false,
            // El mismo escenario, para abrirlo adentro tal cual se está mirando.
            'urlEditar' => route('admin.carga.index', $request->except(['signature', 'expires'])),
        ]);
    }
}
