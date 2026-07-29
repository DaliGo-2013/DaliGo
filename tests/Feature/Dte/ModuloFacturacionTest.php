<?php

namespace Tests\Feature\Dte;

use App\Models\DteEmitido;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\EstadoSii;
use App\Services\Dte\FormaPago;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados del módulo Facturación (M05).
 *
 * La regla que ordena el módulo, y lo que estos tests protegen: **nada que
 * aparezca en pantalla finge funcionar**. Cada origen de documento hace algo real o
 * dice qué le falta.
 */
class ModuloFacturacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function ordenCobrable(): OrdenServicio
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'mano_obra' => 25000]);
        $orden->repuestos()->create(['nombre' => 'Termostato', 'sku' => '1070154', 'cantidad' => 1, 'precio_unitario' => 5000]);

        return $orden->fresh();
    }

    // --- Acceso ---

    public function test_sin_el_permiso_no_se_entra(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');

        $this->actingAs($tecnico)->get(route('admin.dte.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($tecnico)->get(route('admin.dte.estado'))->assertRedirect(route('dashboard'));
    }

    public function test_el_invitado_va_al_login(): void
    {
        $this->get(route('admin.dte.index'))->assertRedirect('/login');
    }

    public function test_el_modulo_aparece_en_el_menu_del_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Facturación')
            ->assertSee(route('admin.dte.index'), false);
    }

    // --- Documentos ---

    public function test_el_listado_vacio_explica_que_va_a_aparecer_ahi(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dte.index'))
            ->assertOk()
            ->assertSee('Acá van a aparecer los documentos emitidos')
            // El XML es el documento legal: la pantalla lo dice.
            ->assertSee('6 años');
    }

    public function test_el_aviso_de_arriba_habla_de_avance_y_no_de_carencia(): void
    {
        // Lo mira Gerencia: un módulo que enumera lo que le falta parece roto, y el
        // avance real es grande. Mismo dato, lectura correcta.
        $this->actingAs($this->admin())
            ->get(route('admin.dte.index'))
            ->assertOk()
            ->assertSee('Módulo en marcha')
            // Y la tranquilidad que importa: no se le quita nada a nadie.
            ->assertSee('sigue funcionando en Bsale igual que siempre')
            ->assertDontSee('Todavía no se emite');
    }

    public function test_los_origenes_no_disponibles_dicen_que_les_falta(): void
    {
        $respuesta = $this->actingAs($this->admin())
            ->get(route('admin.dte.index'))
            ->assertOk()
            ->assertSee('Desde una orden de servicio')
            ->assertSee('Boleta de mostrador')
            ->assertSee('Guía de despacho')
            ->assertSee('Nota de crédito')
            // Se rotulan «Próximamente», no «no disponible»: lo que viene no está roto.
            ->assertSee('Próximamente')
            // Pero SIEMPRE con el motivo. Un "próximamente" sin motivo es una
            // promesa vacía, y el plazo del 1-nov tiene que seguir leyéndose.
            ->assertSee('punto de venta')
            ->assertSee('1-nov-2026')
            ->assertSee('para anular hace falta un documento emitido');

        // Y ninguno ofrece un botón que no funcione.
        $origenes = $respuesta->viewData('origenes');
        foreach ($origenes as $origen) {
            if (! $origen['disponible']) {
                $this->assertNotNull($origen['motivo'], "El origen «{$origen['titulo']}» no explica qué le falta.");
                $this->assertNull($origen['url'], "El origen «{$origen['titulo']}» ofrece un enlace y no está disponible.");
            }
        }
    }

    public function test_lista_las_ordenes_cobrables_sin_documento(): void
    {
        $cobrable = $this->ordenCobrable();
        // En garantía vigente: no se factura.
        OrdenServicio::factory()->create([
            'facturacion' => 'garantia',
            'garantia_doc_fecha' => now()->subMonth()->toDateString(),
            'mano_obra' => 20000,
        ]);
        // Sin monto: nada que cobrar.
        OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'mano_obra' => 0]);

        $ordenes = $this->actingAs($this->admin())
            ->get(route('admin.dte.index'))
            ->assertOk()
            ->viewData('ordenesListas');

        $this->assertCount(1, $ordenes);
        $this->assertSame($cobrable->id, $ordenes->first()->id);
    }

    public function test_una_orden_ya_facturada_sale_de_la_lista(): void
    {
        $orden = $this->ordenCobrable();
        DteEmitido::create([
            'tipo_dte' => DocumentoTributario::BOLETA,
            'sales_id' => $orden->codigo,
            'orden_servicio_id' => $orden->id,
            'estado_sii' => EstadoSii::ACEPTADO,
        ]);

        $ordenes = $this->actingAs($this->admin())
            ->get(route('admin.dte.index'))
            ->viewData('ordenesListas');

        $this->assertCount(0, $ordenes, 'Ya tiene documento: no vuelve a ofrecerse para facturar.');
    }

    public function test_lista_y_filtra_los_documentos_emitidos(): void
    {
        $sucursal = Sucursal::factory()->create(['nombre' => 'Mirador']);

        DteEmitido::create([
            'tipo_dte' => DocumentoTributario::BOLETA, 'sales_id' => 'ST-1', 'folio' => 1050,
            'receptor_nombre' => 'Cliente de mostrador', 'total' => 54000,
            'estado_sii' => EstadoSii::ACEPTADO, 'sucursal_id' => $sucursal->id,
            'emitido_at' => now(),
        ]);
        DteEmitido::create([
            'tipo_dte' => DocumentoTributario::FACTURA_AFECTA, 'sales_id' => 'ST-2', 'folio' => 77,
            'receptor_nombre' => 'Aguas del Norte SpA', 'total' => 119000,
            'estado_sii' => EstadoSii::RECHAZADO, 'emitido_at' => now()->subDay(),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dte.index'))->assertOk()
            ->assertSee('Cliente de mostrador')
            ->assertSee('Aguas del Norte SpA')
            ->assertSee('Mirador');

        // Filtro por tipo.
        $soloFacturas = $this->actingAs($admin)
            ->get(route('admin.dte.index', ['tipo_dte' => DocumentoTributario::FACTURA_AFECTA]))
            ->assertOk()->viewData('documentos');
        $this->assertSame(1, $soloFacturas->total());

        // Filtro por estado.
        $rechazados = $this->actingAs($admin)
            ->get(route('admin.dte.index', ['estado_sii' => EstadoSii::RECHAZADO]))
            ->assertOk()->viewData('documentos');
        $this->assertSame(1, $rechazados->total());
        $this->assertSame('Aguas del Norte SpA', $rechazados->first()->receptor_nombre);
    }

    public function test_un_filtro_invalido_se_rechaza(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dte.index', ['estado_sii' => 'inventado']))
            ->assertSessionHasErrors('estado_sii');
    }

    // --- Estado ---

    public function test_el_estado_muestra_el_checklist_de_lo_que_falta(): void
    {
        Sucursal::factory()->create(['codigo' => 'MIR', 'activa' => true]);

        $vista = $this->actingAs($this->admin())
            ->get(route('admin.dte.estado'))
            ->assertOk()
            ->assertSee('Preparación para emitir')
            ->assertSee('Tipos de documento de Bsale')
            ->assertSee('Oficinas por sucursal')
            ->assertSee('Medios de pago')
            ->assertSee('Autorización para emitir');

        // Con la config vacía, nada está listo.
        foreach ($vista->viewData('faltantes') as $item) {
            $this->assertFalse($item['listo'], "«{$item['titulo']}» no debería estar listo con la config vacía.");
        }

        // Y nombra la sucursal que falta mapear.
        $this->assertStringContainsString('MIR', $vista->viewData('faltantes')[1]['detalle']);
    }

    public function test_el_checklist_marca_lo_que_ya_esta_configurado(): void
    {
        Sucursal::factory()->create(['codigo' => 'MIR', 'activa' => true]);
        config([
            'dte.bsale.tipos_documento' => [DocumentoTributario::BOLETA => 1],
            'dte.bsale.oficinas' => ['MIR' => 9],
            'dte.bsale.medios_pago' => [FormaPago::EFECTIVO => 3],
        ]);

        $faltantes = $this->actingAs($this->admin())
            ->get(route('admin.dte.estado'))
            ->assertOk()
            ->viewData('faltantes');

        $this->assertTrue($faltantes[0]['listo'], 'Tipos de documento configurados.');
        $this->assertTrue($faltantes[1]['listo'], 'Todas las sucursales activas mapeadas.');
        $this->assertTrue($faltantes[2]['listo'], 'Medios de pago configurados.');
        // La autorización sigue apagada: es lo correcto.
        $this->assertFalse($faltantes[3]['listo']);
    }

    public function test_el_estado_avisa_cuando_la_credencial_es_de_produccion(): void
    {
        config(['dte.ambiente' => 'produccion']);

        $this->actingAs($this->admin())
            ->get(route('admin.dte.estado'))
            ->assertOk()
            ->assertSee('Producción')
            // OJO: el aserto no puede cruzar un salto de línea de la plantilla.
            ->assertSee('dirección de la API es la misma');
    }

    public function test_el_estado_tambien_dice_lo_que_no_depende_del_sistema(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dte.estado'))
            ->assertOk()
            ->assertSee('Autorización de Gerencia por escrito')
            ->assertSee('Dos respuestas de Bsale')
            // Y lo ya resuelto, para que no parezca que falta todo.
            ->assertSee('Reglas contables, ya definidas')
            ->assertSee('8 reglas');
    }

    public function test_el_estado_muestra_primero_el_avance_construido(): void
    {
        $vista = $this->actingAs($this->admin())
            ->get(route('admin.dte.estado'))
            ->assertOk()
            ->assertSee('Avance del módulo')
            ->assertSee('0 de 4 pasos de configuración listos')
            ->assertSee('va a seguir sumando funciones');

        $this->assertNotEmpty($vista->viewData('construido'), 'La parte hecha tiene que ser visible.');
        $this->assertSame(4, $vista->viewData('totalPasos'));
        $this->assertSame(0, $vista->viewData('listos'));
    }
}
