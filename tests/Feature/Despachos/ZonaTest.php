<?php

namespace Tests\Feature\Despachos;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DESPACHOS-v1 · P-DSP-02 — catalogo de zonas y la regla de precedencia de la
 * zona efectiva del cliente (D-006 + ajuste del dueno 2026-07-13).
 */
class ZonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_zona_explicita_del_cliente_gana_sobre_la_del_vendedor(): void
    {
        $zonaVendedor = Zona::factory()->create(['nombre' => 'Norte']);
        $zonaCliente = Zona::factory()->create(['nombre' => 'Sur']);
        $vendedor = User::factory()->create(['zona_id' => $zonaVendedor->id]);

        $cliente = Cliente::factory()->create([
            'vendedor_id' => $vendedor->id,
            'zona_id' => $zonaCliente->id, // override explicito
        ]);

        $this->assertSame($zonaCliente->id, $cliente->zonaEfectiva()?->id);
    }

    public function test_sin_zona_explicita_el_cliente_hereda_la_del_vendedor(): void
    {
        $zona = Zona::factory()->create();
        $vendedor = User::factory()->create(['zona_id' => $zona->id]);

        $cliente = Cliente::factory()->create([
            'vendedor_id' => $vendedor->id,
            'zona_id' => null,
        ]);

        $this->assertSame($zona->id, $cliente->zonaEfectiva()?->id);
    }

    public function test_sin_zona_ni_vendedor_la_zona_efectiva_es_null(): void
    {
        $cliente = Cliente::factory()->create(['vendedor_id' => null, 'zona_id' => null]);

        $this->assertNull($cliente->zonaEfectiva());
    }

    public function test_vendedor_sin_zona_deja_la_efectiva_en_null(): void
    {
        $vendedor = User::factory()->create(['zona_id' => null]);
        $cliente = Cliente::factory()->create(['vendedor_id' => $vendedor->id, 'zona_id' => null]);

        $this->assertNull($cliente->zonaEfectiva());
    }

    public function test_seeder_es_idempotente(): void
    {
        (new ZonaSeeder)->run();
        (new ZonaSeeder)->run();

        $this->assertSame(4, Zona::count());
        $this->assertDatabaseHas('zonas', ['nombre' => 'Santiago Norte']);
        $this->assertDatabaseHas('zonas', ['nombre' => '7ª Región']);
    }

    public function test_zona_es_auditable(): void
    {
        $zona = Zona::factory()->create();

        $this->assertContains(Zona::class, array_keys(\App\Http\Controllers\Admin\AuditController::MODELOS));
        $this->assertInstanceOf(\OwenIt\Auditing\Contracts\Auditable::class, $zona);
    }
}
