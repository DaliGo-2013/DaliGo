<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CREAR UN VEHICULO DEJANDO CAMPOS EN BLANCO (15-09-2026).
 *
 * El incidente: el alta devolvia el 500 generico. El log de produccion lo dijo
 * sin ambiguedad — «SQLSTATE[23000]: Column 'pasillo_cm' cannot be null».
 *
 * El mecanismo, que es el reusable: `pasillo_cm` es NOT NULL con default 0 (la
 * migracion dice `->default(0)` SIN `->nullable()`), pero su validacion dice
 * `nullable`. El formulario manda el campo SIEMPRE, asi que dejarlo en blanco
 * llega como '' , `ConvertEmptyStringsToNull` lo vuelve null, la validacion lo
 * deja pasar y el insert manda un NULL EXPLICITO contra una columna que no lo
 * admite.
 *
 * POR QUE LA SUITE ESTABA VERDE: los tests arman el payload a mano y NUNCA
 * mandaban esa clave. Una clave ausente no se valida y la columna toma su
 * default; una clave presente y vacia es otra cosa completamente. Es la misma
 * leccion de la bitacora [2026-07-06]: en un test de formulario hay que simular
 * al NAVEGADOR, que manda todos los campos, y no al programador, que manda los
 * que le interesan.
 */
class VehiculoCamposVaciosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_logistica');
    }

    /**
     * El payload como lo manda el NAVEGADOR: todos los campos del formulario
     * presentes, y los que el usuario no lleno viajan vacios.
     */
    private function comoElNavegador(array $extra = []): array
    {
        return array_merge([
            'ppu' => 'PFBS22',
            'alias' => '',
            'marca' => '',
            'modelo' => '',
            'anio' => '',
            'tipo' => 'camion',
            'combustible' => '',
            'vin' => '',
            'numero_motor' => '',
            'cilindrada' => '',
            'pbv_kg' => '',
            'capacidad_carga_kg' => '',
            'largo_util_cm' => '',
            'ancho_util_cm' => '',
            'alto_util_cm' => '',
            'pasillo_cm' => '',          // <- el que reventaba
            'presion_psi' => '',
            'base' => '',
            'conductor_nombre' => '',
            'estado' => Vehiculo::ESTADO_ACTIVO,
            'baja_motivo' => '',
            'baja_at' => '',
            'rt_vence' => '',
            'emisiones_vence' => '',
            'permiso_circulacion_vence' => '',
            'soap_vence' => '',
            'extintor_vence' => '',
            'extintor_capacidad_kg' => '',
            'observaciones' => '',
        ], $extra);
    }

    public function test_se_crea_dejando_todos_los_opcionales_en_blanco(): void
    {
        $this->actingAs($this->jefe())
            ->post(route('admin.vehiculos.store'), $this->comoElNavegador())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $vehiculo = Vehiculo::where('ppu', 'PFBS22')->first();

        $this->assertNotNull($vehiculo, 'El vehiculo no se creo: es el 500 del alta volviendo.');

        // `pasillo_cm` es NOT NULL: dejarlo en blanco tiene que quedar en su
        // default y no en null, o el insert se cae contra MySQL.
        $this->assertNotNull($vehiculo->pasillo_cm, 'Se guardo null en una columna NOT NULL: vuelve el 500.');
        $this->assertSame(0, (int) $vehiculo->pasillo_cm);
    }

    /**
     * Control de que lo que SI se escribe se respeta: sin esto, un arreglo que
     * pisara el pasillo con 0 siempre pasaria el test de arriba y romperia el
     * simulador de carga, que usa ese numero para reservar el paso.
     */
    public function test_el_pasillo_escrito_se_respeta(): void
    {
        $this->actingAs($this->jefe())
            ->post(route('admin.vehiculos.store'), $this->comoElNavegador(['pasillo_cm' => '45']))
            ->assertSessionHasNoErrors();

        $this->assertSame(45, (int) Vehiculo::where('ppu', 'PFBS22')->first()->pasillo_cm);
    }

    /**
     * Editar tiene el mismo formulario y el mismo riesgo: pasa por el mismo
     * `datosValidados()`, asi que si el arreglo viviera solo en `store()` esto
     * quedaria roto.
     */
    public function test_editar_dejando_el_pasillo_en_blanco_tampoco_revienta(): void
    {
        $vehiculo = Vehiculo::factory()->create(['ppu' => 'PFBS22', 'pasillo_cm' => 30]);

        $this->actingAs($this->jefe())
            ->put(route('admin.vehiculos.update', $vehiculo), $this->comoElNavegador())
            ->assertSessionHasNoErrors();

        $this->assertNotNull($vehiculo->fresh()->pasillo_cm, 'Editar dejo null en una columna NOT NULL.');
    }
}
