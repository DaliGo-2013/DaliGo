<?php

use App\Models\Sucursal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pone en MAYUSCULAS los codigos de sucursal ya guardados.
 *
 * EL PROBLEMA QUE ARREGLA (hallazgo del 14-08-2026, mirando el listado de Sucursales de
 * produccion): el codigo de la casa matriz estaba guardado como «Mirador» y el de Coquimbo como
 * «Coquimbo» — los dos retipeados al editar la ficha (son justo las dos que tienen `ciudad`
 * cargada). El plazo de reparacion se busca por codigo en config/servicio_tecnico.php, y esa
 * busqueda es un indice de array de PHP: case-sensitive. Con «Mirador» no encontraba la clave
 * `MIRADOR` y caia al default de 15 dias habiles, asi que el correo le prometia al cliente 15
 * donde el dueño dijo 10 — la diferencia exacta del correo real que mostro (ingreso 06-08 →
 * entrega 27-08 en vez del 20-08).
 *
 * El accessor ya quedo tolerante (compara normalizado) y el formulario ya normaliza al guardar.
 * Esta migracion cierra la tercera punta: los datos que ya estan. Sin ella, el codigo sigue
 * mostrandose en minusculas en el listado —donde justamente se ve raro al lado de ABATE-MOLINA—
 * y cualquier comparacion nueva que alguien escriba a mano vuelve a tropezar.
 *
 * NO FUERZA UNA COLISION: si otra fila ya tiene el codigo normalizado, esta se deja como esta.
 * Dos sucursales que difieren solo en mayusculas son un duplicado, y eso se resuelve mirando el
 * listado —moviendo ordenes, usuarios y maquinas— no en una migracion a ciegas. En MySQL el
 * unique es case-insensitive, asi que ese caso no puede existir en produccion; el guardia es
 * para SQLite (local y tests), donde si podria.
 *
 * One-shot e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('sucursales')->select('id', 'codigo')->get() as $sucursal) {
            $normalizado = Sucursal::normalizaCodigo($sucursal->codigo);

            if ($normalizado === $sucursal->codigo) {
                continue;
            }

            $ocupado = DB::table('sucursales')
                ->where('codigo', $normalizado)
                ->where('id', '!=', $sucursal->id)
                ->exists();

            if ($ocupado) {
                continue;
            }

            DB::table('sucursales')->where('id', $sucursal->id)->update(['codigo' => $normalizado]);
        }
    }

    public function down(): void
    {
        // No se revierte: volver a minusculas seria reponer el error que rompia el plazo.
    }
};
