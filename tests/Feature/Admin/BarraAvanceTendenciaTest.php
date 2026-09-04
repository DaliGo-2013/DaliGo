<?php

namespace Tests\Feature\Admin;

use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las mini-barras del panel de Producción (dueño 03-09: «no me queda muy claro
 * qué significan esas barras naranjas»). Antes eran SIEMPRE «producido
 * respecto del mejor día», sin rótulo. Ahora tienen dos modos y se dicen:
 *  - en el panel del jefe (hay asignaciones por día) la barra es el AVANCE
 *    sobre lo asignado: llena = cumplió, «+» = se pasó, gris = sin asignación;
 *  - en los desgloses por máquina/tipo (sin meta diaria) sigue relativa al
 *    mejor día, y lo dice al pie. Los rankings/tipos también se rotulan.
 */
class BarraAvanceTendenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->freezeTime();
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function diaCon(int $asignadas, int $primera, ?Maquina $maquina = null, ?TipoBotellon $tipo = null, int $diasAtras = 0): ProduccionReporte
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $fecha = FechaNegocio::ahora()->subDays($diasAtras)->toDateString();
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => $fecha, 'turno' => 'dia', 'asignadas' => $asignadas,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id, 'fecha' => $fecha,
            'turno' => 'dia', 'asignadas' => $asignadas, 'estado' => ProduccionReporte::APROBADO, 'primera' => $primera,
        ]);
        if ($maquina && $tipo) {
            ProduccionRegistro::create([
                'reporte_id' => $reporte->id, 'maquina_id' => $maquina->id, 'tipo_botellon_id' => $tipo->id,
                'primera' => $primera, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            ]);
        }

        return $reporte;
    }

    public function test_en_el_panel_la_barra_es_el_avance_sobre_lo_asignado(): void
    {
        // Hoy: 600 de 800 → 75 %. Ayer: 1.380 de 1.200 → se pasó (115 %),
        // la barra se llena al 100 y el número lleva «+».
        $this->diaCon(800, 600);
        $this->diaCon(1200, 1380, diasAtras: 1);

        $res = $this->actingAs($this->jefe())->get(route('admin.produccion.index'))->assertOk();
        $html = $res->getContent();

        $this->assertSame('avance', $res->viewData('periodo')['modoBarra']);
        // Forma contigua del ancho: la barra ya no es relativa al mejor día
        // (en modo relativo hoy habría medido 43 %, no 75 %).
        $this->assertStringContainsString('style="width: 75%"', $html);
        $this->assertStringContainsString('style="width: 100%"', $html);
        $this->assertStringContainsString('+115%', $html);
        $this->assertStringContainsString('75%', $html);
        $this->assertStringContainsString('Barra: avance del día sobre lo asignado', $html);
    }

    public function test_un_dia_sin_asignacion_va_en_gris_y_sin_avance(): void
    {
        // Producción sin meta ese día: nada que comparar → barra gris vacía y «—».
        $this->diaCon(0, 300);

        $html = $this->actingAs($this->jefe())->get(route('admin.produccion.index'))->assertOk()->getContent();

        $this->assertStringContainsString('bg-neutral-300" style="width: 0%"', $html);
        $this->assertStringContainsString('>—<', $html);
    }

    public function test_en_el_detalle_por_maquina_la_barra_sigue_relativa_y_lo_dice(): void
    {
        $suc = Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['nombre' => 'Mirador']);
        $maquina = Maquina::create(['nombre' => 'Sopladora 1', 'sucursal_id' => $suc->id, 'activa' => true]);
        $tipo = TipoBotellon::firstOrCreate(['codigo' => 'AZUL-20L'], ['nombre' => 'Azul 20L', 'activo' => true]);
        $this->diaCon(800, 400, $maquina, $tipo);            // hoy: mitad del mejor día
        $this->diaCon(800, 800, $maquina, $tipo, diasAtras: 1); // ayer: el mejor día

        $res = $this->actingAs($this->jefe())->get(route('admin.produccion.maquina', $maquina))->assertOk();
        $html = $res->getContent();

        $this->assertSame('relativo', $res->viewData('tendencia')['modoBarra']);
        $this->assertStringContainsString('style="width: 50%"', $html);
        $this->assertStringContainsString('Barra: producido respecto del mejor día del período', $html);
        $this->assertStringNotContainsString('Barra: avance del día', $html);
    }

    public function test_los_desgloses_rotulan_su_barra(): void
    {
        $this->diaCon(800, 700);

        $html = $this->actingAs($this->jefe())->get(route('admin.produccion.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Barra: producido respecto del primero de la lista.', $html);
    }
}
