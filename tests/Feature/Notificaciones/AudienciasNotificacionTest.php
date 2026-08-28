<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Configuracion;
use App\Models\Devolucion;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\Devoluciones\Devoluciones;
use App\Support\AudienciasNotificacion;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Candados del registry de audiencias (matriz «Avisos y destinatarios»,
 * pedido del dueño 28-08-2026). Molde PARAMETRICOS:
 *  1. BD virgen = destinatarios byte-idénticos al histórico (regla de oro).
 *  2. Mover la clave mueve el aviso, con caso concreto.
 *  3. El vacío deliberado silencia; el vacío por descomposición cae al default.
 *  4. Estructural: los emisores derivan del registry y no queda lista a mano
 *     — el único que discrimina «derivado» de «literal que hoy coincide».
 *  5. Anti-hueco: todo evento del catálogo está clasificado.
 */
class AudienciasNotificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * El HISTÓRICO literal, escrito acá y no derivado de DEFAULTS a propósito:
     * si alguien cambia un default en el registry, este test se pone rojo y
     * obliga a declararlo (las 9 constantes ROLES_AVISO_* + 4 listas inline
     * que el registry reemplazó, y los roles que tenían el permiso de los 6
     * eventos ex-por-permiso según RolesAndPermissionsSeeder al 28-08-2026).
     *
     * @return array<string, list<string>>
     */
    private function historico(): array
    {
        return [
            'taller.ingresado' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
            'taller.reparado' => ['jefe_ventas', 'vendedor', 'admin'],
            'taller.sin_solucion' => ['jefe_ventas', 'vendedor', 'admin'],
            'taller.listo_para_retiro' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
            'cotizacion.enviada' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
            'cotizacion.respondida' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
            'cotizacion.retiro_avisado' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
            'cotizacion.autorizada' => ['jefe_ventas', 'vendedor', 'admin'],
            'garantia.detalle_enviado' => ['tecnico', 'jefe_ventas', 'vendedor', 'admin'],
            'terreno.solicitada' => ['jefe_ventas', 'vendedor', 'admin'],
            'terreno.confirmada' => ['jefe_ventas', 'vendedor', 'admin'],
            'terreno.rechazada' => ['jefe_ventas', 'vendedor', 'admin'],
            'terreno.realizado' => ['jefe_ventas', 'admin'],
            'terreno.no_realizado' => ['jefe_ventas', 'admin'],
            'traslado.despachado' => ['tecnico', 'jefe_bodega', 'jefe_ventas', 'admin'],
            'traslado.recibido' => ['jefe_sucursal', 'admin', 'jefe_ventas'],
            'traslado.diferencias' => ['admin', 'jefe_ventas', 'jefe_bodega', 'jefe_sucursal', 'tecnico'],
            'devolucion.solicitada' => ['jefe_bodega', 'jefe_ventas', 'admin'],
            'despacho.parada_rechazada' => ['jefe_despacho', 'jefe_logistica', 'admin'],
            // Ex-por-permiso: 'ver/manage vehiculos', 'manage production' y
            // 'manage sucursales' según la matriz del seeder al 28-08-2026.
            'vehiculo.documento_por_vencer' => ['jefe_logistica', 'conductor', 'admin'],
            'vehiculo.documento_vencido' => ['jefe_logistica', 'conductor', 'admin'],
            'produccion.meta_en_riesgo' => ['jefe_bodega', 'admin'],
            'molde.umbral_mantencion' => ['jefe_bodega', 'admin'],
            'molde.correctiva_pendiente' => ['jefe_bodega', 'admin'],
            'bodega.nueva' => ['admin'],
        ];
    }

    public function test_sin_clave_en_bd_los_destinatarios_son_identicos_al_historico(): void
    {
        // BD virgen (sin ConfiguracionSeeder): cada evento rinde SU lista
        // histórica, en el mismo orden. La regla de oro de parametrizar.
        foreach ($this->historico() as $evento => $roles) {
            $this->assertSame($roles, AudienciasNotificacion::rolesPara($evento),
                "El default de «{$evento}» dejó de ser el histórico.");
        }
    }

    public function test_el_despacho_real_llega_a_los_roles_historicos_y_no_a_otros(): void
    {
        // End-to-end con BD virgen: una devolución declarada avisa a
        // jefe_bodega/jefe_ventas/admin y NO a un soplador.
        Queue::fake();
        $bodega = tap(User::factory()->create())->assignRole('jefe_bodega');
        $ventas = tap(User::factory()->create())->assignRole('jefe_ventas');
        $admin = tap(User::factory()->create())->assignRole('admin');
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        app(Devoluciones::class)->avisarSolicitada(Devolucion::factory()->create());

        // Conteo POR CANAL, nunca pelado: el dispatcher crea una fila por
        // canal según PreferenciaCanal (bitácora 14-08).
        foreach ([$bodega, $ventas, $admin] as $u) {
            $this->assertSame(1, Notificacion::where('user_id', $u->id)
                ->where('evento', 'devolucion.solicitada')
                ->where('canal', Notificacion::CANAL_DATABASE)->count());
        }
        $this->assertSame(0, Notificacion::where('user_id', $soplador->id)->count());
    }

    public function test_mover_la_clave_mueve_el_aviso(): void
    {
        // La mitad que prueba que la perilla EXISTE: con la audiencia movida a
        // «soplador», el aviso le llega al soplador y DEJA de llegar a ventas.
        Queue::fake();
        $this->seed(ConfiguracionSeeder::class);
        $ventas = tap(User::factory()->create())->assignRole('jefe_ventas');
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        Configuracion::set('notif_roles_devolucion_solicitada', ['soplador']);

        app(Devoluciones::class)->avisarSolicitada(Devolucion::factory()->create());

        $this->assertSame(1, Notificacion::where('user_id', $soplador->id)
            ->where('evento', 'devolucion.solicitada')
            ->where('canal', Notificacion::CANAL_DATABASE)->count());
        $this->assertSame(0, Notificacion::where('user_id', $ventas->id)->count());
    }

    public function test_desmarcar_todo_silencia_el_aviso_sin_reventar(): void
    {
        // Decisión del dueño 28-08: se PUEDE dejar un aviso sin nadie. El
        // vacío deliberado se respeta: cero notificaciones y cero errores.
        Queue::fake();
        $this->seed(ConfiguracionSeeder::class);
        tap(User::factory()->create())->assignRole('jefe_bodega');

        Configuracion::set('notif_roles_devolucion_solicitada', []);

        $this->assertSame([], AudienciasNotificacion::rolesPara('devolucion.solicitada'));

        app(Devoluciones::class)->avisarSolicitada(Devolucion::factory()->create());

        $this->assertSame(0, Notificacion::where('evento', 'devolucion.solicitada')->count());
    }

    public function test_lista_rota_cae_al_default(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $default = $this->historico()['devolucion.solicitada'];

        // Roles que ya no existen (descomposición): default, no silencio.
        Configuracion::set('notif_roles_devolucion_solicitada', ['rol_fantasma']);
        $this->assertSame($default, AudienciasNotificacion::rolesPara('devolucion.solicitada'));

        // Basura que no es array (clave corrompida a mano): default.
        Configuracion::query()->where('clave', 'notif_roles_devolucion_solicitada')
            ->update(['valor' => '"basura"']);
        \Illuminate\Support\Facades\Cache::forget('config.notif_roles_devolucion_solicitada');
        $this->assertSame($default, AudienciasNotificacion::rolesPara('devolucion.solicitada'));

        // Y un evento NO editable revienta con nombre y apellido, no en silencio.
        $this->expectException(\InvalidArgumentException::class);
        AudienciasNotificacion::rolesPara('mensaje.recibido');
    }

    public function test_estructural_los_emisores_derivan_y_no_queda_lista_a_mano(): void
    {
        // La mitad que DISCRIMINA (molde LOG-1): un literal repuesto en un
        // emisor deja la pantalla igual HOY (coincide con el default) pero la
        // clave dejaría de mover ese aviso. Se barren los fuentes.
        $emisores = [
            'app/Models/AgendaTrabajo.php',
            'app/Models/OrdenServicio.php',
            'app/Models/LoteServicio.php',
            'app/Models/OrdenServicioCotizacion.php',
            'app/Http/Controllers/Admin/ServicioTecnicoController.php',
            'app/Http/Controllers/Admin/TrasladoServicioController.php',
            'app/Services/Devoluciones/Devoluciones.php',
            'app/Services/Despachos/DespachoService.php',
            'app/Console/Commands/VehiculosAvisarVencimientos.php',
            'app/Services/Produccion/CorteSic.php',
            'app/Services/Produccion/Moldes.php',
            'app/Services/Bsale/StockSync.php',
        ];
        // BajaDeBodegas.php queda FUERA a propósito: su destinatario es el
        // solicitante de la orden con fallback por permiso (fila informativa).

        foreach ($emisores as $ruta) {
            $fuente = file_get_contents(base_path($ruta));

            $this->assertStringContainsString('AudienciasNotificacion::destinatarios(', $fuente,
                "{$ruta} no deriva del registry.");
            $this->assertStringNotContainsString('ROLES_AVISO', $fuente,
                "{$ruta} volvió a una constante de roles a mano.");
            $this->assertDoesNotMatchRegularExpression('/User::role\(\s*\[/', $fuente,
                "{$ruta} volvió a una lista inline de roles.");
        }

        // El único User::role( sobreviviente de AgendaTrabajo es el fallback
        // intencional del técnico industrial (trabajo sin asignar).
        $agenda = file_get_contents(base_path('app/Models/AgendaTrabajo.php'));
        $this->assertStringContainsString("User::role('tecnico_industrial')", $agenda);

        // Los 4 ex-por-permiso ya no despachan por permiso.
        foreach ([
            'app/Console/Commands/VehiculosAvisarVencimientos.php',
            'app/Services/Produccion/CorteSic.php',
            'app/Services/Produccion/Moldes.php',
            'app/Services/Bsale/StockSync.php',
        ] as $ruta) {
            $this->assertStringNotContainsString('User::permission(', file_get_contents(base_path($ruta)),
                "{$ruta} volvió a despachar por permiso.");
        }
    }

    public function test_todo_evento_esta_clasificado(): void
    {
        // Anti-hueco estructural: un evento nuevo del catálogo tiene que
        // declararse editable (DEFAULTS) o fijo (NO_EDITABLES) — sin doblarse.
        $editables = array_keys(AudienciasNotificacion::DEFAULTS);
        $fijos = array_keys(AudienciasNotificacion::NO_EDITABLES);

        $this->assertSame([], array_values(array_intersect($editables, $fijos)),
            'Un evento no puede ser editable y fijo a la vez.');

        $clasificados = array_merge($editables, $fijos);
        sort($clasificados);
        $catalogo = array_keys(Notificacion::EVENTOS);
        sort($catalogo);

        $this->assertSame($catalogo, $clasificados,
            'Hay eventos del catálogo sin clasificar en AudienciasNotificacion (o clasificados que ya no existen).');

        // Y toda familia de evento tiene su título para la pantalla.
        foreach ($catalogo as $evento) {
            $prefijo = explode('.', $evento)[0];
            $this->assertArrayHasKey($prefijo, AudienciasNotificacion::FAMILIAS,
                "La familia «{$prefijo}» no tiene título en FAMILIAS.");
        }
    }

    public function test_el_seeder_siembra_cada_clave_con_su_default(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        foreach (AudienciasNotificacion::DEFAULTS as $evento => $roles) {
            $fila = Configuracion::query()
                ->where('clave', AudienciasNotificacion::clave($evento))
                ->first();

            $this->assertNotNull($fila, "El seeder no siembra la clave de «{$evento}».");
            $this->assertSame(Configuracion::TIPO_JSON, $fila->tipo);
            $this->assertSame(AudienciasNotificacion::GRUPO, $fila->grupo);
            $this->assertSame(array_values($roles), json_decode($fila->valor, true),
                "La clave sembrada de «{$evento}» no calza con el default del registry.");
            $this->assertLessThanOrEqual(191, mb_strlen((string) $fila->descripcion));
        }
    }
}
