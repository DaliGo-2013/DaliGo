<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Rules\RutChileno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * El RUT terminado en K.
 *
 * `Cliente::dvRut()` devuelve 'K' cuando el resto del cálculo es 10, y eso pasa
 * en **9 de cada 100** RUT (medido sobre 1.000 consecutivos). O sea: uno de cada
 * once clientes tiene un RUT que el sistema DEBE poder recibir, y no era un caso
 * cubierto por ningún test.
 *
 * El riesgo no es teórico. Una rama de trabajo (`feature/responsive-tactil-forms`)
 * le puso `inputmode="numeric"` al campo del RUT en 5 pantallas —incluidos los
 * tres formularios públicos del QR, donde el cliente escribe su propio RUT en su
 * propio celular—. El teclado numérico de iOS no tiene la K, no tiene el guión y
 * no tiene tecla para cambiar a letras: ese 9% de clientes quedaba sin poder
 * terminar el formulario. Y el defecto es silencioso, porque el 91% de las
 * pruebas que uno hace a mano funcionan perfecto.
 *
 * Por eso el último test es ESTRUCTURAL: no prueba comportamiento, prohíbe el
 * teclado que bloquea.
 */
class RutConKTest extends TestCase
{
    use RefreshDatabase;

    /** Un cuerpo de RUT cuyo dígito verificador es K. */
    private function cuerpoConDvK(): int
    {
        for ($i = 10000000; $i < 10001000; $i++) {
            if (Cliente::dvRut($i) === 'K') {
                return $i;
            }
        }

        $this->fail('No se encontró ningún RUT con DV=K en el rango de prueba.');
    }

    public function test_el_validador_acepta_la_k_como_la_escribe_la_gente(): void
    {
        $cuerpo = $this->cuerpoConDvK();
        $conPuntos = number_format($cuerpo, 0, '', '.');

        // Las cinco formas que llegan de verdad desde un formulario.
        $formas = [
            $conPuntos.'-K',   // con puntos y guión
            $cuerpo.'-K',      // sin puntos
            $cuerpo.'K',       // sin guión
            $cuerpo.'k',       // minúscula
            $conPuntos.'-k',   // con puntos y minúscula
        ];

        $regla = new RutChileno;

        foreach ($formas as $forma) {
            $error = null;
            $regla->validate('cliente_rut', $forma, function ($mensaje) use (&$error) {
                $error = $mensaje;
            });

            $this->assertNull($error, "El RUT [{$forma}] debe ser válido: el DV K es legítimo y lo tiene 1 de cada 11 personas.");
        }
    }

    public function test_la_k_se_normaliza_a_mayuscula_y_con_guion(): void
    {
        $cuerpo = $this->cuerpoConDvK();

        // La columna guarda el formato normalizado (ver la migración de clientes),
        // así que dos personas que escriban distinto deben caer en el MISMO valor
        // — si no, el `unique` del rut dejaría entrar duplicados.
        $esperado = $cuerpo.'-K';

        foreach ([$cuerpo.'k', $cuerpo.'K', $cuerpo.'-k', number_format($cuerpo, 0, '', '.').'-k'] as $entrada) {
            $this->assertSame($esperado, Cliente::normalizarRut($entrada),
                "[{$entrada}] debe normalizarse a [{$esperado}].");
        }
    }

    public function test_un_cliente_con_rut_k_se_guarda_y_se_encuentra_buscando(): void
    {
        $cuerpo = $this->cuerpoConDvK();
        $cliente = Cliente::factory()->create([
            'rut' => Cliente::normalizarRut($cuerpo.'K'),
            'razon_social' => 'Comercial Terminada En Ka',
        ]);

        // La búsqueda quita puntos y espacios pero conserva la K y el guión.
        foreach ([$cuerpo, $cuerpo.'-K', number_format($cuerpo, 0, '', '.').'-K'] as $consulta) {
            $limpio = preg_replace('/[.\s]/', '', $consulta);
            $encontrado = Cliente::where('rut', 'like', "%{$limpio}%")->first();

            $this->assertNotNull($encontrado, "Buscando [{$consulta}] debe encontrarse el cliente con RUT K.");
            $this->assertSame($cliente->id, $encontrado->id);
        }
    }

    public function test_ningun_campo_de_rut_usa_un_teclado_que_no_tiene_la_k(): void
    {
        // Candado ESTRUCTURAL. El teclado numérico de iOS (inputmode numeric o
        // decimal, y type=number) no ofrece la K ni el guión ni una tecla para
        // pasar a letras. En un campo de RUT eso es un bloqueo, no una comodidad.
        $infractores = [];

        foreach (File::allFiles(resource_path('views')) as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            $lineas = explode("\n", File::get($archivo->getPathname()));
            $ruta = str_replace(resource_path('views').'/', '', $archivo->getPathname());

            foreach ($lineas as $n => $linea) {
                // Se mira el ATRIBUTO, no la línea suelta: `\brut\b` NO matchea
                // dentro de `cliente_rut` —el guión bajo cuenta como carácter de
                // palabra, así que no hay frontera— y por eso la primera versión
                // de este candado dejó pasar la mutación. Y buscar 'rut' como
                // substring daría falso positivo con el campo `ruta` que existe
                // en el formulario de servicio técnico. Se exige entonces que el
                // nombre del campo TERMINE en rut (rut, cliente_rut, …).
                if (! preg_match('/(?:id|name|x-model(?:\.\w+)*)="[^"]*rut"/i', $linea)) {
                    continue;
                }
                if (preg_match('/inputmode="(numeric|decimal)"|type="number"/', $linea)) {
                    $infractores[] = $ruta.':'.($n + 1);
                }
            }
        }

        $this->assertSame([], $infractores,
            "Estos campos de RUT usan un teclado sin la K: 1 de cada 11 personas no podría escribir su RUT.\n".
            "Si hace falta mejorar ese teclado, `inputmode=\"text\"` con `autocapitalize=\"characters\"` sí sirve.\n  ".
            implode("\n  ", $infractores));
    }
}
