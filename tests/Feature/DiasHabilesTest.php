<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Support\DiasHabiles;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Día hábil siguiente para citar al cliente (dueño 07-08): salta fin de semana
 * y los feriados de `feriados_chile` (Configuración, sembrada por el seeder).
 */
class DiasHabilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConfiguracionSeeder::class);
    }

    public function test_un_dia_de_semana_normal_cita_al_dia_siguiente(): void
    {
        // Miércoles 05-08-2026 (tarde) → jueves 06-08-2026.
        $siguiente = DiasHabiles::siguiente(Carbon::parse('2026-08-05 16:30'));

        $this->assertSame('2026-08-06', $siguiente->toDateString());
    }

    public function test_el_viernes_y_el_fin_de_semana_citan_al_lunes(): void
    {
        foreach (['2026-08-07', '2026-08-08', '2026-08-09'] as $dia) { // vie, sáb, dom
            $this->assertSame(
                '2026-08-10', // lunes
                DiasHabiles::siguiente(Carbon::parse($dia))->toDateString(),
                "falló partiendo de {$dia}"
            );
        }
    }

    public function test_salta_los_feriados_sembrados(): void
    {
        // Fiestas Patrias 2026: jueves 17-09 → vie 18 (feriado), sáb 19 (feriado),
        // dom 20 → cita al lunes 21-09. El cliente jamás es citado un feriado.
        $siguiente = DiasHabiles::siguiente(Carbon::parse('2026-09-17'));

        $this->assertSame('2026-09-21', $siguiente->toDateString());
    }

    public function test_sin_lista_de_feriados_degrada_a_saltar_solo_fines_de_semana(): void
    {
        // Si en algún año la lista no se renovó, el cálculo no se cae: cita de
        // más (podría caer un feriado no cargado), nunca rompe.
        Configuracion::set('feriados_chile', []);

        $this->assertSame('2026-09-18', DiasHabiles::siguiente(Carbon::parse('2026-09-17'))->toDateString());
    }

    public function test_el_rotulo_va_en_castellano(): void
    {
        $this->assertSame('lunes 10-08-2026', DiasHabiles::rotulo(Carbon::parse('2026-08-10')));
    }
}
