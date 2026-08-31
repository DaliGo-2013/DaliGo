<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\VehiculoDocumentoController;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 'cargar respaldos vehiculos': EL CICLO COMPLETO DE LA FOTO SIN LA FLOTA ENTERA.
 *
 * Pedido del dueño (31-08-2026), configurando la cuenta de Camilo Toro (rol «Vehiculos», creado
 * desde la UI de Roles): que quien carga las fotos de los documentos también pueda VERLAS y
 * VOLVER A EDITARLAS. Antes los permisos eran todo-o-nada: subir/reemplazar/quitar vivían bajo
 * 'manage vehiculos', así que el único camino para que alguien mantuviera los respaldos era
 * regalarle también crear/eliminar vehículos y el catálogo de tipos de documento.
 *
 * La tabla de verdad que este archivo fija (medida ANTES del cambio, para saber qué faltaba):
 *   · solo 'ver vehiculos'              → ve la foto, NO sube, NO reemplaza, NO quita (el conductor)
 *   · solo 'cargar respaldos vehiculos' → ve, sube, reemplaza, quita — y NADA más de la flota
 *   · 'manage vehiculos'                → todo, como siempre
 */
class VehiculoCargarRespaldosPermisoTest extends TestCase
{
    use RefreshDatabase;

    private Vehiculo $camion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(VehiculoDocumentoController::DISCO);

        $this->camion = Vehiculo::factory()->create([
            'soap_vence' => now()->addDays(40)->toDateString(),
        ]);
    }

    /** Un usuario cuyo rol tiene SOLO el permiso granular, como el rol «Vehiculos» de Camilo. */
    private function cargador(): User
    {
        $rol = Role::firstOrCreate(['name' => 'solo_cargador', 'guard_name' => 'web']);
        $rol->syncPermissions(['cargar respaldos vehiculos']);

        return tap(User::factory()->create())->assignRole('solo_cargador');
    }

    private function subir(User $u)
    {
        return $this->actingAs($u)->post(
            route('admin.vehiculos.documentos.store', [$this->camion, 'soap_vence']),
            ['archivo' => UploadedFile::fake()->image('soap.jpg', 900, 900)],
        );
    }

    // ─────────────────────────────────────────── el ciclo completo de la foto

    public function test_quien_carga_tambien_ve_reemplaza_y_quita(): void
    {
        $u = $this->cargador();

        // Llega a la flota y a la ficha (sin esto el permiso no se puede ejercer).
        $this->actingAs($u)->get(route('admin.vehiculos.index'))->assertOk();
        $this->actingAs($u)->get(route('admin.vehiculos.show', $this->camion))->assertOk();

        // SUBE.
        $this->subir($u)->assertRedirect();
        $this->assertSame(1, VehiculoDocumento::count());

        // La VE: el enlace en la ficha, la pantalla del documento y los bytes.
        $doc = VehiculoDocumento::sole();
        $this->actingAs($u)->get(route('admin.vehiculos.show', $this->camion))
            ->assertSee('Ver el documento')
            ->assertSee('Reemplazar el documento')
            ->assertSee('Quitar');
        $this->actingAs($u)->get(route('admin.vehiculos.documentos.archivo', $doc))
            ->assertOk();

        // La REEMPLAZA (subir de nuevo conserva la versión anterior, regla del 11-08).
        $this->subir($u)->assertRedirect();
        $this->assertSame(2, VehiculoDocumento::count());

        // Y QUITA la equivocada: vuelve a quedar la anterior.
        $this->actingAs($u)
            ->delete(route('admin.vehiculos.documentos.destroy', VehiculoDocumento::orderByDesc('id')->first()))
            ->assertRedirect();
        $this->assertSame(1, VehiculoDocumento::count());
    }

    /** Y el ítem «Vehículos» del menú se le muestra: un permiso sin puerta no existe. */
    public function test_el_menu_le_muestra_vehiculos(): void
    {
        $this->actingAs($this->cargador())
            ->get(route('dashboard'))
            ->assertSee('Vehículos');
    }

    // ─────────────────────────────────────────── y NADA más de la flota

    /**
     * El permiso es de FOTOS, no de flota: no edita los datos del vehículo, no lo crea, no lo
     * elimina, no toca el catálogo de tipos. Sin estos asserts, el permiso granular podría
     * crecer en silencio hasta ser un 'manage' con otro nombre.
     */
    public function test_no_puede_tocar_nada_mas_de_la_flota(): void
    {
        $u = $this->cargador();

        // Pantalla de editar: GET sin permiso = redirect amable al Inicio (D-014).
        $this->actingAs($u)->get(route('admin.vehiculos.edit', $this->camion))->assertRedirect();
        // Mutaciones: 403 pelado.
        $this->actingAs($u)->put(route('admin.vehiculos.update', $this->camion), [])->assertForbidden();
        $this->actingAs($u)->post(route('admin.vehiculos.store'), [])->assertForbidden();
        $this->actingAs($u)->delete(route('admin.vehiculos.destroy', $this->camion))->assertForbidden();
        $this->actingAs($u)->post(route('admin.vehiculos.tipos-documento.store'), [])->assertForbidden();
    }

    /** Regresión: el que SOLO ve (el conductor) sigue sin poder subir ni ver los controles. */
    public function test_quien_solo_ve_sigue_sin_los_controles_de_carga(): void
    {
        $conductor = tap(User::factory()->create())->assignRole('conductor');

        // Con un respaldo ya cargado por otro…
        $gestor = tap(User::factory()->create())->assignRole('jefe_logistica');
        $this->subir($gestor);

        // …lo VE pero no lo administra.
        $this->actingAs($conductor)->get(route('admin.vehiculos.show', $this->camion))
            ->assertOk()
            ->assertSee('Ver el documento')
            ->assertDontSee('Reemplazar el documento')
            ->assertDontSee('>Quitar<');
        $this->subir($conductor)->assertForbidden();
    }

    // ─────────────────────────────────────────── el «Ver la foto actual» de Editar

    /**
     * La otra mitad del pedido («nos olvidamos la opción de ver»): la pantalla de EDITAR decía
     * «Hay una foto cargada · N KB» sin forma de mirarla — reemplazar a ciegas. Ahora, si el
     * documento tiene respaldo, hay un enlace a la foto actual.
     */
    public function test_editar_ofrece_ver_la_foto_actual_cuando_existe(): void
    {
        $gestor = tap(User::factory()->create())->assignRole('jefe_logistica');

        // Sin respaldo: el enlace no se dibuja (no hay nada que ver).
        $this->actingAs($gestor)->get(route('admin.vehiculos.edit', $this->camion))
            ->assertOk()
            ->assertDontSee('Ver la foto actual');

        $this->subir($gestor);

        $this->actingAs($gestor)->get(route('admin.vehiculos.edit', $this->camion))
            ->assertOk()
            ->assertSee('Ver la foto actual')
            ->assertSee(route('admin.vehiculos.documentos.show', [$this->camion, 'soap_vence']));
    }
}
