<?php

namespace Tests\Feature\Despachos;

use App\Models\Despacho;
use App\Models\Devolucion;
use App\Models\HojaDeRuta;
use App\Models\HojaRutaParada;
use App\Models\Notificacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rechazo en puerta (P-DSP-09, R15): el conductor no pudo entregar — la
 * parada queda rechazada CON su motivo, el equipo de despacho recibe el
 * aviso M15, y NADA más se automatiza (si el rechazo crea la devolución
 * M13 es decisión del dueño, pregunta abierta del parte).
 */
class RechazoParadaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    private function conductor(): User
    {
        return tap(User::factory()->create())->assignRole('conductor');
    }

    /** @return array{despacho: Despacho, hoja: HojaDeRuta} */
    private function paradaEnRuta(User $conductor): array
    {
        $hoja = HojaDeRuta::factory()->enRuta()->create(['conductor_id' => $conductor->id]);
        $despacho = Despacho::factory()->retirado()->create();
        HojaRutaParada::create([
            'hoja_de_ruta_id' => $hoja->id,
            'despacho_id' => $despacho->id,
            'orden' => 1,
        ]);

        return ['despacho' => $despacho, 'hoja' => $hoja];
    }

    public function test_el_rechazo_queda_en_la_parada_con_su_motivo(): void
    {
        $conductor = $this->conductor();
        ['despacho' => $despacho] = $this->paradaEnRuta($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $despacho),
                ['motivo' => 'Local cerrado, nadie recibe'],
                ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('duplicado', false);

        $parada = $despacho->fresh()->parada;
        $this->assertSame(HojaRutaParada::RESULTADO_RECHAZADA, $parada->resultado);
        $this->assertSame('Local cerrado, nadie recibe', $parada->rechazo_motivo);
        // El despacho NO cambia de estado: la carga física vuelve a bodega y
        // su reingreso es territorio M13/bodega, no de este endpoint.
        $this->assertSame(Despacho::RETIRADO, $despacho->fresh()->estado);
    }

    public function test_el_rechazo_es_idempotente_y_no_pisa_el_motivo(): void
    {
        $conductor = $this->conductor();
        ['despacho' => $despacho] = $this->paradaEnRuta($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $despacho), ['motivo' => 'Motivo original'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('duplicado', false);

        // El reintento de la cola: duplicado, sin pisar, sin segundo aviso.
        $avisosAntes = Notificacion::where('evento', 'despacho.parada_rechazada')->count();

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $despacho), ['motivo' => 'Otro texto'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('duplicado', true);

        $this->assertSame('Motivo original', $despacho->fresh()->parada->rechazo_motivo);
        $this->assertSame($avisosAntes, Notificacion::where('evento', 'despacho.parada_rechazada')->count(),
            'Un duplicado no dispara un segundo aviso.');
    }

    public function test_sin_motivo_no_hay_rechazo(): void
    {
        $conductor = $this->conductor();
        ['despacho' => $despacho] = $this->paradaEnRuta($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $despacho), [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->assertNull($despacho->fresh()->parada->resultado);
    }

    public function test_un_despacho_suelto_no_se_puede_rechazar(): void
    {
        $conductor = $this->conductor();
        $suelto = Despacho::factory()->retirado()->create(['conductor_id' => $conductor->id]);

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $suelto), ['motivo' => 'X'], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_el_scoping_por_hoja_tambien_gatea_el_rechazo(): void
    {
        $ajeno = $this->conductor();
        ['despacho' => $despacho] = $this->paradaEnRuta($this->conductor());

        $this->actingAs($ajeno)
            ->post(route('entregas.rechazar', $despacho), ['motivo' => 'X'], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_una_parada_ya_entregada_no_se_rechaza(): void
    {
        $conductor = $this->conductor();
        ['despacho' => $despacho] = $this->paradaEnRuta($conductor);
        $despacho->parada->update(['resultado' => HojaRutaParada::RESULTADO_ENTREGADA]);
        $despacho->update(['estado' => Despacho::ENTREGADO]);

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $despacho), ['motivo' => 'X'], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_el_aviso_llega_al_jefe_de_despacho_con_placeholders_resueltos(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_despacho');
        $conductor = $this->conductor();
        ['despacho' => $despacho, 'hoja' => $hoja] = $this->paradaEnRuta($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $despacho),
                ['motivo' => 'Cliente sin efectivo'], ['Accept' => 'application/json'])
            ->assertOk();

        $fila = Notificacion::where('evento', 'despacho.parada_rechazada')
            ->where('user_id', $jefe->id)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->firstOrFail();

        // Sin placeholders huérfanos (patrón DevolucionNotificacionesTest).
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $fila->titulo.$fila->cuerpo);
        $this->assertStringContainsString('Cliente sin efectivo', $fila->cuerpo);
        $this->assertStringContainsString((string) $hoja->folio, $fila->titulo);

        // La campanita navega a la HOJA para quien porta el gate...
        $this->assertSame(route('admin.hojas-ruta.show', $hoja), $fila->urlDestinoPara($jefe));
        // ...y para quien no, no navega.
        $this->assertNull($fila->urlDestinoPara($conductor));
    }

    public function test_el_rechazo_no_crea_una_devolucion_m13(): void
    {
        // Decisión del dueño PENDIENTE (pregunta del parte): hoy el rechazo
        // solo registra y avisa — automatizar la devolución sería inventar
        // una regla de negocio que nadie pidió.
        $conductor = $this->conductor();
        ['despacho' => $despacho] = $this->paradaEnRuta($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $despacho), ['motivo' => 'X'], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame(0, Devolucion::count());
    }

    public function test_la_parada_rechazada_sale_de_la_hoja_del_conductor(): void
    {
        $conductor = $this->conductor();
        ['despacho' => $despacho] = $this->paradaEnRuta($conductor);

        $this->actingAs($conductor)
            ->post(route('entregas.rechazar', $despacho), ['motivo' => 'X'], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertNotContains(
            $despacho->id,
            $this->actingAs($conductor)->get(route('entregas.index'))->viewData('despachos')->pluck('id'),
            'Una parada rechazada no se vuelve a mostrar aunque el despacho siga en reparto.',
        );
    }
}
