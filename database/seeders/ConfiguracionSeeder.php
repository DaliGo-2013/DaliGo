<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    /**
     * Parametros globales base. Idempotente (firstOrCreate por clave): es seguro
     * re-ejecutarlo; nunca pisa el valor editado desde la UI. Mas parametros se
     * agregan a medida que existan los modulos que los consumen.
     */
    public function run(): void
    {
        $ajustes = [
            [
                'clave' => 'umbral_aprobacion_clp',
                'valor' => '1000000',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'cotizaciones',
                'descripcion' => 'Monto en CLP sobre el cual una cotización requiere aprobación.',
            ],
            [
                'clave' => 'cotizacion_vigencia_dias',
                'valor' => '5',
                'tipo' => Configuracion::TIPO_INTEGER,
                'grupo' => 'cotizaciones',
                'descripcion' => 'Días de vigencia por defecto de una cotización.',
            ],
        ];

        foreach ($ajustes as $a) {
            Configuracion::firstOrCreate(
                ['clave' => $a['clave']],
                [
                    'valor' => $a['valor'],
                    'tipo' => $a['tipo'],
                    'grupo' => $a['grupo'],
                    'descripcion' => $a['descripcion'],
                ],
            );
        }
    }
}
