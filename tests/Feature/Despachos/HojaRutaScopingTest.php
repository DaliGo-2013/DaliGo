<?php

namespace Tests\Feature\Despachos;

use App\Models\Despacho;
use App\Models\HojaDeRuta;
use App\Models\HojaRutaParada;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Scoping conductor↔hoja (R22, P-DSP-08): con hoja de ruta manda LA HOJA —
 * solo el conductor de una hoja EN RUTA ve y entrega su carga, aunque
 * despachos.conductor_id diga otra cosa. Sin hoja, la regla original sigue
 * viva (aditivo: la PWA en producción no se rompe). Cierra el hallazgo del
 * gate M07 («cualquiera con permiso entrega cualquier carga») donde hay
 * hoja, por diseño del dueño de la operación.
 */
class HojaRutaScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function conductor(): User
    {
        return tap(User::factory()->create())->assignRole('conductor');
    }

    /**
     * Un despacho retirado dentro de una hoja con el estado dado. La parada
     * nace PAGADA a propósito: estos tests prueban el SCOPING, y el default
     * cobrar_en_entrega exigiría además el cobro (eso lo cubre
     * EntregaSobreHojaTest).
     */
    private function despachoEnHoja(HojaDeRuta $hoja, array $despacho = []): Despacho
    {
        $d = Despacho::factory()->retirado()->create($despacho);
        HojaRutaParada::create([
            'hoja_de_ruta_id' => $hoja->id,
            'despacho_id' => $d->id,
            'orden' => (int) $hoja->paradas()->max('orden') + 1,
            'estado_cobro' => HojaRutaParada::COBRO_PAGADO,
        ]);

        return $d;
    }

    private function payload(): array
    {
        return [
            'entrega_uuid' => (string) Str::uuid(),
            'capturado_at' => now()->toIso8601String(),
            'foto' => UploadedFile::fake()->image('entrega.jpg', 800, 600),
            'firma' => UploadedFile::fake()->image('firma.png', 600, 300),
            // El receptor es obligatorio desde P-DSP-09 (R13).
            'receptor_nombre' => 'Receptor Sintético',
            'receptor_rut' => '33333333-3',
            'receptor_relacion' => 'otro',
        ];
    }

    public function test_el_conductor_de_la_hoja_en_ruta_ve_su_carga_en_el_orden_pactado(): void
    {
        $conductor = $this->conductor();
        $zona = Zona::factory()->create();
        $hoja = HojaDeRuta::factory()->enRuta()->create(['conductor_id' => $conductor->id, 'zona_id' => $zona->id]);

        // Dos paradas creadas en orden 1,2 — pero el pactado las invierte.
        $primero = $this->despachoEnHoja($hoja, ['zona_id' => $zona->id]);
        $segundo = $this->despachoEnHoja($hoja, ['zona_id' => $zona->id]);
        $primero->parada->update(['orden' => 2]);
        $segundo->parada->update(['orden' => 1]);

        $res = $this->actingAs($conductor)->get(route('entregas.index'))->assertOk();

        $vistos = $res->viewData('despachos');
        $this->assertSame([$segundo->id, $primero->id], $vistos->pluck('id')->all(),
            'El orden de la PANTALLA es el orden pactado de la hoja (R3), no la hora de retiro.');
    }

    public function test_manda_la_hoja_y_no_el_conductor_id_copiado_al_despacho(): void
    {
        // El despacho quedó con conductor A al crearse, pero la hoja se
        // asignó al conductor B: entrega B, no A — la hoja es la verdad (R22).
        $a = $this->conductor();
        $b = $this->conductor();
        $hoja = HojaDeRuta::factory()->enRuta()->create(['conductor_id' => $b->id]);
        $despacho = $this->despachoEnHoja($hoja, ['conductor_id' => $a->id]);

        // A no lo ve...
        $this->assertNotContains(
            $despacho->id,
            $this->actingAs($a)->get(route('entregas.index'))->viewData('despachos')->pluck('id'),
        );
        // ...ni lo puede confirmar (403 permanente para la cola).
        $this->actingAs($a)
            ->post(route('entregas.confirmar', $despacho), $this->payload(), ['Accept' => 'application/json'])
            ->assertForbidden();

        // B sí: la hoja en_ruta es suya.
        $this->actingAs($b)
            ->post(route('entregas.confirmar', $despacho), $this->payload(), ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertSame(Despacho::ENTREGADO, $despacho->fresh()->estado);
    }

    public function test_una_hoja_que_no_ha_salido_no_habilita_la_entrega(): void
    {
        // La hoja está CARGADA (el camión no partió): ni ver ni confirmar,
        // aunque la hoja sea mía — la entrega existe solo EN RUTA.
        $conductor = $this->conductor();
        $hoja = HojaDeRuta::factory()->create(['conductor_id' => $conductor->id, 'estado' => HojaDeRuta::CARGADA]);
        $despacho = $this->despachoEnHoja($hoja, ['conductor_id' => $conductor->id]);

        $this->assertNotContains(
            $despacho->id,
            $this->actingAs($conductor)->get(route('entregas.index'))->viewData('despachos')->pluck('id'),
        );

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload(), ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_un_despacho_sin_hoja_conserva_la_regla_original(): void
    {
        // Aditivo: la PWA en producción sigue operando con despachos sueltos.
        $conductor = $this->conductor();
        $suelto = Despacho::factory()->retirado()->create(['conductor_id' => $conductor->id]);

        $this->assertContains(
            $suelto->id,
            $this->actingAs($conductor)->get(route('entregas.index'))->viewData('despachos')->pluck('id'),
        );

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $suelto), $this->payload(), ['Accept' => 'application/json'])
            ->assertOk();
    }
}
