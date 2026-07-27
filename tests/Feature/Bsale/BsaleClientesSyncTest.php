<?php

namespace Tests\Feature\Bsale;

use App\Models\Cliente;
use App\Models\User;
use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\ClientSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class BsaleClientesSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array> */
    private array $fakeClients = [];

    private bool $httpFaked = false;

    /** Sobre paginado al estilo Bsale. */
    private function envelope(array $items, int $count, int $limit, int $offset): array
    {
        return ['href' => 'x', 'count' => $count, 'limit' => $limit, 'offset' => $offset, 'items' => $items, 'next' => null];
    }

    /**
     * Define los clientes faqueados de Bsale. Registra el closure de Http::fake una
     * sola vez (lee el estado mutable) para poder "cambiar" los datos entre syncs.
     */
    private function fakeBsale(array $clients): void
    {
        $this->fakeClients = $clients;

        if ($this->httpFaked) {
            return;
        }
        $this->httpFaked = true;

        Http::fake(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) ($query['offset'] ?? 0);
            $limit = (int) ($query['limit'] ?? 50);

            if (str_contains($request->url(), 'clients.json')) {
                return Http::response($this->envelope(array_slice($this->fakeClients, $offset, $limit), count($this->fakeClients), $limit, $offset));
            }

            return Http::response([], 404);
        });
    }

    /**
     * Cliente con el shape REAL de /clients.json (verificado contra la API).
     */
    private function bsaleClient(int $id, string $code, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'firstName' => 'Nombre',
            'lastName' => "Apellido {$id}",
            'email' => '',
            'code' => $code,
            'phone' => '',
            'company' => "Empresa {$id} SpA",
            'note' => null,
            'hasCredit' => 0,
            'maxCredit' => 0,
            'state' => 0,
            'activity' => 'Comercio',
            'city' => 'Santiago',
            'municipality' => 'Cerrillos',
            'address' => 'Camino a Melipilla 9750',
            'companyOrPerson' => 1,
            'sendDte' => 0,
            'isForeigner' => 0,
        ], $overrides);
    }

    private function sync(): array
    {
        return (new ClientSync(new BsaleClient('https://api.bsale.io/v1', 'fake-token')))->run();
    }

    public function test_maps_fields_correctly(): void
    {
        $this->fakeBsale([$this->bsaleClient(7, '18.037.112-5', ['sendDte' => 1])]);

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);
        $c = Cliente::where('bsale_client_id', 7)->firstOrFail();
        $this->assertSame('18037112-5', $c->rut); // normalizado, sin puntos
        $this->assertSame('Empresa 7 SpA', $c->razon_social);
        $this->assertSame('Comercio', $c->giro);
        $this->assertSame('Santiago', $c->ciudad);
        $this->assertSame('Cerrillos', $c->comuna);
        $this->assertSame('Camino a Melipilla 9750', $c->direccion);
        $this->assertTrue($c->es_empresa);
        $this->assertTrue($c->envio_factura_email); // Bsale sendDte
        $this->assertTrue($c->activo);
        $this->assertNull($c->email);               // '' en Bsale => null local
    }

    public function test_persona_uses_first_and_last_name_when_no_company(): void
    {
        $this->fakeBsale([$this->bsaleClient(2, '', [
            'company' => '',
            'firstName' => 'ANDRES',
            'lastName' => 'PEREZ',
            'companyOrPerson' => 0,
        ])]);

        $this->sync();

        $c = Cliente::where('bsale_client_id', 2)->firstOrFail();
        $this->assertSame('ANDRES PEREZ', $c->razon_social);
        $this->assertNull($c->rut); // code vacio => sin RUT
        $this->assertFalse($c->es_empresa);
    }

    public function test_preserves_local_fields_on_resync(): void
    {
        $this->fakeBsale([$this->bsaleClient(1, '12.345.678-5')]);
        $this->sync();

        $vendedor = User::factory()->create();
        Cliente::where('bsale_client_id', 1)->update([
            'segmento' => 'mayorista', 'notas' => 'paga a 30 dias', 'vendedor_id' => $vendedor->id,
        ]);

        // Re-sync con la razon social cambiada en Bsale.
        $this->fakeBsale([$this->bsaleClient(1, '12.345.678-5', ['company' => 'Razon Nueva SpA'])]);
        $stats = $this->sync();

        $c = Cliente::where('bsale_client_id', 1)->firstOrFail();
        $this->assertSame(1, $stats['actualizados']);
        $this->assertSame('Razon Nueva SpA', $c->razon_social); // campo de Bsale actualizado
        $this->assertSame('mayorista', $c->segmento);           // locales preservados
        $this->assertSame('paga a 30 dias', $c->notas);
        $this->assertSame($vendedor->id, $c->vendedor_id);
        $this->assertSame(1, Cliente::count());
    }

    public function test_adopts_manual_row_by_rut(): void
    {
        // Ficha creada a mano en DaliGo, sin enlace a Bsale.
        $manual = Cliente::factory()->create([
            'rut' => '12345678-5', 'bsale_client_id' => null,
            'segmento' => 'recurrente', 'notas' => 'cliente antiguo',
        ]);

        $this->fakeBsale([$this->bsaleClient(9, '12.345.678-5')]);
        $stats = $this->sync();

        $this->assertSame(1, $stats['adoptados']);
        $this->assertSame(1, Cliente::count()); // sin duplicar
        $fresh = $manual->fresh();
        $this->assertSame(9, (int) $fresh->bsale_client_id);     // enlace lleno
        $this->assertSame('recurrente', $fresh->segmento);       // local preservado
        $this->assertSame('Empresa 9 SpA', $fresh->razon_social); // identidad de Bsale
    }

    public function test_multiple_clients_without_rut_are_all_created(): void
    {
        $this->fakeBsale([
            $this->bsaleClient(1, '', ['company' => 'Uno']),
            $this->bsaleClient(2, '', ['company' => 'Dos']),
        ]);

        $stats = $this->sync();

        // Varios NULL caben en el indice unique de rut.
        $this->assertSame(2, $stats['creados']);
        $this->assertSame(2, Cliente::whereNull('rut')->count());
    }

    public function test_rut_collision_is_counted_as_duplicate_not_error(): void
    {
        // Dos clientes Bsale distintos con el mismo RUT (duplicados historicos).
        // Es una condicion ESPERADA del origen: se clasifica como `duplicados`,
        // NO como `errores`, para que el sync no parezca roto cada corrida.
        $this->fakeBsale([
            $this->bsaleClient(1, '12.345.678-5'),
            $this->bsaleClient(2, '12345678-5'),
        ]);

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);
        $this->assertSame(1, $stats['duplicados']);
        $this->assertSame(1, $stats['omitidos']);   // duplicado sigue contando como omitido
        $this->assertEmpty($stats['errores']);       // pero NO es un error
        $this->assertSame(1, Cliente::where('rut', '12345678-5')->count());
    }

    public function test_foreigner_code_is_kept_raw_not_mutilated(): void
    {
        // normalizarRut borraria las letras de un code extranjero y fabricaria
        // un RUT falso (P-123456 -> 12345-6) que ademas puede colisionar.
        $this->fakeBsale([
            $this->bsaleClient(1, 'P-123456', ['isForeigner' => 1, 'company' => 'Extranjero Uno']),
            $this->bsaleClient(2, 'X-123456', ['isForeigner' => 1, 'company' => 'Extranjero Dos']),
        ]);

        $stats = $this->sync();

        $this->assertSame(2, $stats['creados']);
        $this->assertSame('P-123456', Cliente::where('bsale_client_id', 1)->value('rut'));
        $this->assertSame('X-123456', Cliente::where('bsale_client_id', 2)->value('rut'));
        $this->assertEmpty($stats['errores']); // sin colision entre codes distintos
    }

    public function test_consumidor_final_rut_is_stored_null(): void
    {
        // RUTs genericos de "consumidor final"/mostrador (66666666-6 y, el mas
        // frecuente en el barrido real, 55555555-5): Bsale trae varios y el unique
        // los volveria ruido recurrente de errores -> se guardan null.
        $this->fakeBsale([
            $this->bsaleClient(1, '66.666.666-6', ['company' => 'Consumidor Uno']),
            $this->bsaleClient(2, '66666666-6', ['company' => 'Consumidor Dos']),
            $this->bsaleClient(3, '55.555.555-5', ['company' => 'Mostrador']),
        ]);

        $stats = $this->sync();

        $this->assertSame(3, $stats['creados']);
        $this->assertEmpty($stats['errores']);
        $this->assertNull(Cliente::where('bsale_client_id', 1)->value('rut'));
        $this->assertNull(Cliente::where('bsale_client_id', 2)->value('rut'));
        $this->assertNull(Cliente::where('bsale_client_id', 3)->value('rut'));
    }

    public function test_paginates_across_two_pages(): void
    {
        $clients = [];
        for ($i = 1; $i <= 75; $i++) {
            $clients[] = $this->bsaleClient($i, '', ['company' => "Cliente {$i}"]);
        }
        $this->fakeBsale($clients);

        $stats = $this->sync();

        $this->assertSame(75, $stats['creados']);
        $this->assertSame(75, Cliente::count());
    }

    public function test_razon_social_falls_back_to_client_id(): void
    {
        $this->fakeBsale([$this->bsaleClient(33, '', [
            'company' => '', 'firstName' => '', 'lastName' => '',
        ])]);

        $this->sync();

        $this->assertDatabaseHas('clientes', ['bsale_client_id' => 33, 'razon_social' => 'Cliente 33']);
    }

    public function test_no_per_row_audits(): void
    {
        $this->fakeBsale([$this->bsaleClient(1, '11.111.111-1'), $this->bsaleClient(2, '')]);
        $this->sync();

        $this->assertSame(0, Audit::where('auditable_type', Cliente::class)->count());
    }
}
