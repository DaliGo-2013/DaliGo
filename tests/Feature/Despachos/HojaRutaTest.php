<?php

namespace Tests\Feature\Despachos;

use App\Http\Controllers\Admin\AuditController;
use App\Models\Despacho;
use App\Models\HojaDeRuta;
use App\Models\HojaRutaParada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Contracts\Auditable;
use Tests\TestCase;

/**
 * Hoja de ruta digital (P-DSP-08, PLAN-DESPACHOS-V2): la entidad y sus
 * candados estructurales. La lógica de negocio (folio correlativo, cadena de
 * llaves, generación de paradas) se prueba sobre HojaRutaService en
 * HojaRutaServiceTest.
 */
class HojaRutaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * El folio es unique EN LA BASE: la regla no vive solo en el cálculo del
     * service — una carrera que se cuele igual choca aquí (patrón del unique
     * de documento_venta_id en DespachoTest).
     */
    public function test_el_folio_es_unico_a_nivel_de_base(): void
    {
        HojaDeRuta::factory()->create(['folio' => 1000]);

        $this->expectException(QueryException::class);

        HojaDeRuta::factory()->create(['folio' => 1000]);
    }

    /**
     * Un despacho NO puede vivir en dos hojas a la vez: el unique de
     * despacho_id es GLOBAL, no por hoja. Si un rechazado se re-despacha
     * (R15), su parada vieja se resuelve primero — decisión del parte.
     */
    public function test_un_despacho_no_puede_estar_en_dos_hojas(): void
    {
        $despacho = Despacho::factory()->create();
        $hojaA = HojaDeRuta::factory()->create();
        $hojaB = HojaDeRuta::factory()->create();

        HojaRutaParada::create([
            'hoja_de_ruta_id' => $hojaA->id,
            'despacho_id' => $despacho->id,
            'orden' => 1,
        ]);

        $this->expectException(QueryException::class);

        HojaRutaParada::create([
            'hoja_de_ruta_id' => $hojaB->id,
            'despacho_id' => $despacho->id,
            'orden' => 1,
        ]);
    }

    /**
     * La máquina es SECUENCIAL ESTRICTA (R11): desde cada estado hay UN solo
     * destino, la cadena recorre los 6 estados en orden y no hay atajos.
     * Este candado fija la FORMA del mapa; que el service lo aplique (y no
     * se pueda saltar) se prueba con mutación en HojaRutaServiceTest.
     */
    public function test_la_maquina_de_estados_es_una_cadena_sin_saltos(): void
    {
        $cadena = [HojaDeRuta::BORRADOR];
        while ($siguiente = HojaDeRuta::TRANSICIONES[end($cadena)] ?? null) {
            $this->assertNotContains($siguiente, $cadena, 'La máquina tiene un ciclo.');
            $cadena[] = $siguiente;
        }

        $this->assertSame(HojaDeRuta::ESTADOS, $cadena,
            'La cadena de TRANSICIONES debe recorrer TODOS los estados en orden.');

        // Y puedeTransicionarA solo acepta el paso siguiente, nunca un salto.
        $hoja = HojaDeRuta::factory()->create();  // borrador
        $this->assertTrue($hoja->puedeTransicionarA(HojaDeRuta::PAGOS_OK));
        $this->assertFalse($hoja->puedeTransicionarA(HojaDeRuta::RUTA_AUTORIZADA));
        $this->assertFalse($hoja->puedeTransicionarA(HojaDeRuta::EN_RUTA));
        $this->assertFalse($hoja->puedeTransicionarA(HojaDeRuta::BORRADOR));

        // Cerrada es terminal: no hay transición DESDE ella.
        $this->assertArrayNotHasKey(HojaDeRuta::CERRADA, HojaDeRuta::TRANSICIONES);
    }

    /**
     * Auditable de verdad (patrón ZonaTest): implementa el contrato Y está
     * registrada en el filtro del historial — sin lo segundo, la auditoría
     * existe pero nadie puede consultarla.
     */
    public function test_hoja_y_parada_son_auditables(): void
    {
        $this->assertInstanceOf(Auditable::class, new HojaDeRuta);
        $this->assertInstanceOf(Auditable::class, new HojaRutaParada);
        $this->assertContains(HojaDeRuta::class, array_keys(AuditController::MODELOS));
        $this->assertContains(HojaRutaParada::class, array_keys(AuditController::MODELOS));
    }

    /** Las columnas del receptor (R13) existen y se escriben — las exige P-DSP-09. */
    public function test_despacho_persiste_al_receptor_de_la_entrega(): void
    {
        $despacho = Despacho::factory()->create([
            'receptor_nombre' => 'Conserje Edificio Sintético',
            'receptor_rut' => '11111111-1',
            'receptor_relacion' => 'conserje',
        ]);

        $fresco = $despacho->fresh();
        $this->assertSame('Conserje Edificio Sintético', $fresco->receptor_nombre);
        $this->assertSame('11111111-1', $fresco->receptor_rut);
        $this->assertSame('conserje', $fresco->receptor_relacion);
    }

    /** La relación parada() del despacho encuentra su hoja (base del scoping). */
    public function test_despacho_conoce_su_parada_y_su_hoja(): void
    {
        $despacho = Despacho::factory()->create();
        $hoja = HojaDeRuta::factory()->create();
        HojaRutaParada::create([
            'hoja_de_ruta_id' => $hoja->id,
            'despacho_id' => $despacho->id,
            'orden' => 1,
        ]);

        $this->assertSame($hoja->id, $despacho->fresh()->parada->hoja->id);
        $this->assertSame(HojaRutaParada::COBRO_EN_ENTREGA, $despacho->fresh()->parada->estado_cobro,
            'El default de cobro es fail-safe: si nadie dijo pagado, se cobra en la entrega.');
    }
}
