<?php

namespace Database\Seeders;

use App\Models\Instalacion;
use Illuminate\Database\Seeder;

/**
 * Datos de EJEMPLO para el registro de Instalaciones (boceto de la reunión).
 *
 * Plasma unas cuantas filas realistas del Excel de Carlos Tablante para que la
 * pantalla no se vea vacía en la demo. NO se registra en DatabaseSeeder: se corre
 * a mano donde haga falta (local/staging) — NUNCA en producción con datos reales.
 *
 *   php artisan db:seed --class=Database\\Seeders\\InstalacionesDemoSeeder
 *
 * Idempotente: todas las filas llevan el marcador creado_por = 'Datos de ejemplo';
 * si ya existen, no se duplican. Para limpiarlas:
 *
 *   Instalacion::where('creado_por', 'Datos de ejemplo')->delete();
 */
class InstalacionesDemoSeeder extends Seeder
{
    /** Marca que identifica las filas de ejemplo (para no duplicar y poder limpiar). */
    private const MARCADOR = 'Datos de ejemplo';

    public function run(): void
    {
        if (Instalacion::where('creado_por', self::MARCADOR)->exists()) {
            $this->command?->warn('Instalaciones de ejemplo ya cargadas; nada que hacer.');

            return;
        }

        foreach ($this->filas() as $fila) {
            Instalacion::create(array_merge($fila, ['creado_por' => self::MARCADOR]));
        }

        $this->command?->info(count($this->filas()).' instalaciones de ejemplo cargadas.');
    }

    /**
     * Filas de muestra. Fechas relativas a hoy para que caigan en meses recientes
     * (el índice agrupa por mes). Mezcla de estados: instaladas/puestas en marcha,
     * pagadas y por pagar, con y sin factura.
     *
     * @return list<array<string, mixed>>
     */
    private function filas(): array
    {
        return [
            [
                'fecha' => now()->subDays(4)->toDateString(),
                'cliente_nombre' => 'Agua Purificada Canto del Agua',
                'cliente_rut' => '76.543.210-9',
                'comuna_region' => 'Copiapó, Atacama',
                'categoria' => 'lavadora',
                'producto' => 'LAVADORA DE BOTELLONES 20L-220V',
                'instalacion' => true,
                'puesta_en_marcha' => true,
                'dias' => 2,
                'vendedor' => 'Luis Figueroa',
                'n_factura' => '250868',
                'fecha_factura' => now()->subDays(3)->toDateString(),
                'forma_pago' => 'transferencia',
                'fecha_pago' => now()->subDays(1)->toDateString(),
            ],
            [
                'fecha' => now()->subDays(9)->toDateString(),
                'cliente_nombre' => 'Distribuidora El Manantial',
                'cliente_rut' => '77.120.045-K',
                'comuna_region' => 'La Serena, Coquimbo',
                'categoria' => 'planta',
                'producto' => 'PLANTA DE OSMOSIS INVERSA 1.000 L/H',
                'instalacion' => true,
                'puesta_en_marcha' => true,
                'dias' => 4,
                'vendedor' => 'Carlos Toledo',
                'n_factura' => '250712',
                'fecha_factura' => now()->subDays(8)->toDateString(),
                'forma_pago' => 'deposito',
                'fecha_pago' => now()->subDays(2)->toDateString(),
            ],
            [
                'fecha' => now()->subDays(15)->toDateString(),
                'cliente_nombre' => 'Purificadora Vida Sana Ltda.',
                'cliente_rut' => '78.334.901-1',
                'comuna_region' => 'Vallenar, Atacama',
                'categoria' => 'llenadora',
                'producto' => 'LLENADORA SEMIAUTOMÁTICA 4 BOQUILLAS',
                'instalacion' => true,
                'puesta_en_marcha' => false,
                'dias' => 3,
                'vendedor' => 'Danika Toledo',
                'n_factura' => '250655',
                'fecha_factura' => now()->subDays(14)->toDateString(),
                'forma_pago' => 'transferencia',
                'fecha_pago' => null,
            ],
            [
                'fecha' => now()->subDays(22)->toDateString(),
                'cliente_nombre' => 'Comercial Aguas del Norte SpA',
                'cliente_rut' => '76.998.223-4',
                'comuna_region' => 'Ovalle, Coquimbo',
                'categoria' => 'lavadora',
                'producto' => 'LAVADORA-LLENADORA DE BOTELLONES DALI',
                'instalacion' => true,
                'puesta_en_marcha' => true,
                'dias' => 2,
                'vendedor' => 'Sergio Céspedes',
                'n_factura' => '250540',
                'fecha_factura' => now()->subDays(21)->toDateString(),
                'forma_pago' => 'cheque',
                'fecha_pago' => now()->subDays(5)->toDateString(),
            ],
            [
                'fecha' => now()->subDays(34)->toDateString(),
                'cliente_nombre' => 'Embotelladora Cordillera',
                'cliente_rut' => '77.845.610-7',
                'comuna_region' => 'Caldera, Atacama',
                'categoria' => 'planta',
                'producto' => 'PLANTA DE OSMOSIS INVERSA 2.000 L/H',
                'instalacion' => true,
                'puesta_en_marcha' => true,
                'dias' => 5,
                'vendedor' => 'Cricelis Herrera',
                'n_factura' => '250398',
                'fecha_factura' => now()->subDays(33)->toDateString(),
                'forma_pago' => 'transferencia',
                'fecha_pago' => now()->subDays(20)->toDateString(),
            ],
            [
                'fecha' => now()->subDays(41)->toDateString(),
                'cliente_nombre' => 'Agua Cristalina Illapel',
                'cliente_rut' => '78.001.556-3',
                'comuna_region' => 'Illapel, Coquimbo',
                'categoria' => 'llenadora',
                'producto' => 'LLENADORA AUTOMÁTICA 6 BOQUILLAS',
                'instalacion' => false,
                'puesta_en_marcha' => false,
                'dias' => 1,
                'vendedor' => 'Héctor Martínez',
                'n_factura' => null,
                'fecha_factura' => null,
                'forma_pago' => null,
                'fecha_pago' => null,
            ],
            [
                'fecha' => now()->subDays(52)->toDateString(),
                'cliente_nombre' => 'Botellones San Joaquín',
                'cliente_rut' => '76.410.782-6',
                'comuna_region' => 'Copiapó, Atacama',
                'categoria' => 'lavadora',
                'producto' => 'LAVADORA DE BOTELLONES 20L-380V',
                'instalacion' => true,
                'puesta_en_marcha' => true,
                'dias' => 2,
                'vendedor' => 'Pedro Castillo',
                'n_factura' => '250221',
                'fecha_factura' => now()->subDays(51)->toDateString(),
                'forma_pago' => 'efectivo',
                'fecha_pago' => now()->subDays(51)->toDateString(),
            ],
        ];
    }
}
