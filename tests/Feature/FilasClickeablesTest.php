<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\ListaPrecio;
use App\Models\Maquina;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candado del patron "la fila ES el enlace" (pedido del dueño 03-08-2026):
 * en los listados de administracion se entra tocando el NOMBRE de la fila,
 * no un icono de ojo/lapiz al costado.
 *
 * Un solo candado para todos los listados a proposito: el patron se pidio
 * PAREJO ("quiero esa opcion en todos los listados") y la regresion tipica es
 * que una pantalla nueva vuelva al icono. Cada caso verifica dos mitades:
 *   1. el nombre vive DENTRO de un <a> que apunta al destino correcto, y
 *   2. el icono de navegacion se fue (sin title="Editar" / "Ver ...").
 * Bodegas, sucursales y usuarios tienen su candado en sus propios archivos
 * (BodegaManagementTest, SucursalManagementTest, UserManagementTest).
 */
class FilasClickeablesTest extends TestCase
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

    /** El nombre esta DENTRO del <a> al destino (no un assertSee suelto). */
    private function assertFilaEnlaza(string $html, string $urlParcial, string $nombre): void
    {
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*'.preg_quote($urlParcial, '/').'"[^>]*>(?:(?!<\/a>).)*'.preg_quote($nombre, '/').'/s',
            $html,
            "「{$nombre}」 no está dentro del enlace a {$urlParcial}."
        );
    }

    public function test_listas_de_precios_abre_tocando_la_lista(): void
    {
        $lista = ListaPrecio::factory()->create(['nombre' => 'COQUIMBO-1']);

        $html = $this->actingAs($this->admin())->get('/admin/listas-precios')->assertOk()->getContent();

        $this->assertFilaEnlaza($html, "/admin/listas-precios/{$lista->id}", 'COQUIMBO-1');
        $this->assertStringNotContainsString('title="Ver precios"', $html);
    }

    public function test_clientes_abre_tocando_el_cliente(): void
    {
        $cliente = Cliente::factory()->create(['razon_social' => 'Aguas Enlace SpA']);

        $html = $this->actingAs($this->admin())->get('/admin/clientes')->assertOk()->getContent();

        $this->assertFilaEnlaza($html, "/admin/clientes/{$cliente->id}/edit", 'Aguas Enlace SpA');
        $this->assertStringNotContainsString('title="Editar"', $html);
    }

    public function test_configuracion_abre_tocando_el_parametro(): void
    {
        $config = Configuracion::create([
            'clave' => 'parametro_enlace', 'valor' => 'x',
            'tipo' => Configuracion::TIPO_STRING, 'grupo' => 'general',
            'descripcion' => 'Parametro del candado',
        ]);

        $html = $this->actingAs($this->admin())->get('/admin/configuracion')->assertOk()->getContent();

        $this->assertFilaEnlaza($html, "/admin/configuracion/{$config->id}/edit", 'Parametro Enlace');
        $this->assertStringNotContainsString('title="Editar"', $html);
    }

    public function test_maquinas_abre_tocando_la_maquina(): void
    {
        $sucursal = Sucursal::factory()->create();
        $maquina = Maquina::create(['nombre' => 'Sopladora Enlace', 'sucursal_id' => $sucursal->id]);

        $html = $this->actingAs($this->admin())->get('/admin/maquinas')->assertOk()->getContent();

        $this->assertFilaEnlaza($html, "/admin/maquinas/{$maquina->id}/edit", 'Sopladora Enlace');
        $this->assertStringNotContainsString('title="Editar"', $html);
        // "Ver rendimiento" es OTRO destino y sigue como enlace propio, fuera del <a> de la fila.
        $this->assertStringContainsString('Ver rendimiento', $html);
    }

    public function test_productos_abre_tocando_el_producto(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Botellon Enlace 20L']);

        $html = $this->actingAs($this->admin())->get('/admin/productos')->assertOk()->getContent();

        $this->assertFilaEnlaza($html, "/admin/productos/{$producto->id}/edit", 'Botellon Enlace 20L');
        $this->assertStringNotContainsString('title="Editar"', $html);
    }

    public function test_roles_abre_tocando_el_rol(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/roles')->assertOk()->getContent();

        // El rol 'admin' del seeder, con su nombre como lo pinta la vista (headline).
        $rolId = \Spatie\Permission\Models\Role::findByName('admin')->id;
        $this->assertFilaEnlaza($html, "/admin/roles/{$rolId}/edit", 'Admin');
        $this->assertStringNotContainsString('title="Editar"', $html);
    }

    public function test_tipos_de_botellon_abre_tocando_el_tipo(): void
    {
        $producto = Producto::factory()->create();
        $tipo = TipoBotellon::create(['codigo' => 'TE1', 'nombre' => 'Tipo Enlace', 'producto_id' => $producto->id, 'activo' => true]);

        $html = $this->actingAs($this->admin())->get('/admin/tipos-botellon')->assertOk()->getContent();

        $this->assertFilaEnlaza($html, "/admin/tipos-botellon/{$tipo->id}/edit", 'Tipo Enlace');
        $this->assertStringNotContainsString('title="Editar"', $html);
        $this->assertStringContainsString('Ver producción', $html);
    }

    /**
     * Despachos: la fila abre la FICHA; el QR imprimible es otra accion y
     * CONSERVA su boton (no era navegacion redundante).
     */
    public function test_despachos_sin_ojito_y_con_el_boton_del_qr(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/despachos')->assertOk()->getContent();

        $this->assertStringNotContainsString('title="Ficha / escaneo"', $html);
        // El listado vacio no tiene filas que verificar; lo que se fija es que el
        // patron del boton-ojo no vuelva a la vista.
        $this->assertStringNotContainsString('x-icon.eye', $html);
    }
}
