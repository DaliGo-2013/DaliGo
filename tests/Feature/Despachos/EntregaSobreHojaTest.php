<?php

namespace Tests\Feature\Despachos;

use App\Models\Despacho;
use App\Models\HojaDeRuta;
use App\Models\HojaRutaParada;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P-DSP-09: la confirmación de entrega SOBRE la hoja de ruta — receptor
 * obligatorio (R13), cobro en entrega condicional al estado de la parada
 * (R4+R7) y el cierre de la parada (resultado=entregada) atómico con el
 * cambio de estado del despacho.
 */
class EntregaSobreHojaTest extends TestCase
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

    /** Despacho retirado dentro de una hoja EN RUTA del conductor dado. */
    private function despachoEnHoja(User $conductor, string $cobro = HojaRutaParada::COBRO_EN_ENTREGA): Despacho
    {
        $hoja = HojaDeRuta::factory()->enRuta()->create(['conductor_id' => $conductor->id]);
        $despacho = Despacho::factory()->retirado()->create(['conductor_id' => $conductor->id]);
        HojaRutaParada::create([
            'hoja_de_ruta_id' => $hoja->id,
            'despacho_id' => $despacho->id,
            'orden' => 1,
            'estado_cobro' => $cobro,
        ]);

        return $despacho;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'entrega_uuid' => (string) Str::uuid(),
            'capturado_at' => now()->toIso8601String(),
            'foto' => UploadedFile::fake()->image('entrega.jpg', 800, 600),
            'firma' => UploadedFile::fake()->image('firma.png', 600, 300),
            'receptor_nombre' => 'Conserje Sintético',
            'receptor_rut' => '22222222-2',
            'receptor_relacion' => 'conserje',
        ], $overrides);
    }

    // ─── Receptor obligatorio (R13) ─────────────────────────────────

    public function test_sin_receptor_no_hay_entrega(): void
    {
        $conductor = $this->conductor();
        $despacho = Despacho::factory()->retirado()->create(['conductor_id' => $conductor->id]);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho),
                collect($this->payload())->except(['receptor_nombre', 'receptor_rut', 'receptor_relacion'])->all(),
                ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['receptor_nombre', 'receptor_rut', 'receptor_relacion']);

        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);
    }

    public function test_el_receptor_queda_en_el_despacho(): void
    {
        $conductor = $this->conductor();
        $despacho = Despacho::factory()->retirado()->create(['conductor_id' => $conductor->id]);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload([
                'receptor_nombre' => "Rosa D'Angelo",
                'receptor_rut' => '12345678-K',
                'receptor_relacion' => 'empresa',
            ]), ['Accept' => 'application/json'])
            ->assertOk();

        $fresco = $despacho->fresh();
        $this->assertSame("Rosa D'Angelo", $fresco->receptor_nombre);
        $this->assertSame('12345678-K', $fresco->receptor_rut, 'El DV K debe pasar (gotcha 28-07).');
        $this->assertSame('empresa', $fresco->receptor_relacion);
    }

    public function test_una_relacion_fuera_del_catalogo_se_rechaza(): void
    {
        $conductor = $this->conductor();
        $despacho = Despacho::factory()->retirado()->create(['conductor_id' => $conductor->id]);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho),
                $this->payload(['receptor_relacion' => 'vecino']),
                ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['receptor_relacion']);
    }

    // ─── Cobro en entrega (R4+R7) ───────────────────────────────────

    public function test_parada_cobrar_en_entrega_exige_metodo_y_monto(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despachoEnHoja($conductor);   // cobrar_en_entrega

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload(), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cobro_metodo', 'cobro_monto']);

        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);
        $this->assertNull($despacho->fresh()->parada->resultado);
    }

    public function test_el_cobro_queda_en_la_parada_y_la_parada_queda_entregada(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despachoEnHoja($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload([
                'cobro_metodo' => 'transbank',
                'cobro_monto' => 119000,
            ]), ['Accept' => 'application/json'])
            ->assertOk();

        $parada = $despacho->fresh()->parada;
        $this->assertSame(HojaRutaParada::RESULTADO_ENTREGADA, $parada->resultado);
        $this->assertSame('transbank', $parada->cobro_metodo);
        $this->assertSame(119000, $parada->cobro_monto);
    }

    public function test_una_parada_pagada_ignora_el_cobro_aunque_venga(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despachoEnHoja($conductor, HojaRutaParada::COBRO_PAGADO);

        // Sin cobro: pasa (no se exige)...
        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload([
                'cobro_metodo' => 'efectivo',   // ...y si igual viene, se IGNORA
                'cobro_monto' => 5000,
            ]), ['Accept' => 'application/json'])
            ->assertOk();

        $parada = $despacho->fresh()->parada;
        $this->assertSame(HojaRutaParada::RESULTADO_ENTREGADA, $parada->resultado);
        $this->assertNull($parada->cobro_metodo, 'Una parada pagada no registra cobro en puerta.');
        $this->assertNull($parada->cobro_monto);
    }

    public function test_un_despacho_suelto_no_exige_cobro(): void
    {
        $conductor = $this->conductor();
        $suelto = Despacho::factory()->retirado()->create(['conductor_id' => $conductor->id]);

        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $suelto), $this->payload(), ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame(Despacho::ENTREGADO, $suelto->fresh()->estado);
    }

    // ─── Idempotencia con los campos nuevos ─────────────────────────

    public function test_el_duplicado_de_la_cola_no_pisa_receptor_ni_cobro(): void
    {
        $conductor = $this->conductor();
        $despacho = $this->despachoEnHoja($conductor);
        $uuid = (string) Str::uuid();

        $primero = $this->payload([
            'entrega_uuid' => $uuid,
            'receptor_nombre' => 'Primer Receptor',
            'cobro_metodo' => 'efectivo',
            'cobro_monto' => 100,
        ]);
        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $primero, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('duplicado', false);

        // El reintento de la cola con el MISMO uuid trae otros valores (no
        // debería, pero la red no promete orden): no pisa nada.
        $this->actingAs($conductor)
            ->post(route('entregas.confirmar', $despacho), $this->payload([
                'entrega_uuid' => $uuid,
                'receptor_nombre' => 'Impostor',
                'cobro_monto' => 999999,
            ]), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('duplicado', true);

        $fresco = $despacho->fresh();
        $this->assertSame('Primer Receptor', $fresco->receptor_nombre);
        $this->assertSame(100, $fresco->parada->cobro_monto);
    }
}
