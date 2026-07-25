<?php

namespace Tests\Feature;

use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use App\Support\AvisosError;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manejo amable de 403/404 (render de bootstrap/app.php + errors/{403,404}).
 *
 * Antes de este lote el handler NO tenia ningun candado: un 403 mostraba la
 * pantalla generica de Symfony con el texto de spatie en INGLES.
 *
 * Contrato que se fija aca:
 *  - NAVEGACION (GET) autenticada -> al Inicio con session('aviso').
 *  - JSON/fetch -> conserva su 403 (la cola offline del soplador depende de eso).
 *  - VISITANTE -> pagina de error con marca, sin filtrar mensajes del framework.
 *  - ACCION rechazada por ESTADO -> vuelve atras conservando el copy de negocio.
 */
class ManejoErroresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function soplador(): User
    {
        return tap(User::factory()->create())->assignRole('soplador');
    }

    private function reporteDe(User $soplador, string $estado = ProduccionReporte::BORRADOR): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => \App\Support\FechaNegocio::hoy(),
            'turno' => 'dia',
            'asignadas' => 100,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => \App\Support\FechaNegocio::hoy(),
            'turno' => 'dia',
            'asignadas' => 100,
            'estado' => $estado,
        ]);
    }

    // --- 403 en navegacion ---------------------------------------------------

    public function test_navegar_sin_permiso_lleva_al_inicio_y_el_aviso_SE_VE(): void
    {
        // EL candado central: no basta con que el redirect ocurra — hay que SEGUIRLO
        // y comprobar que el mensaje se pinta. El dashboard no tenia <x-status-alert>,
        // asi que un flash que aterrizara ahi se perdia en silencio; por eso el
        // aviso vive en el layout con su propia clave de sesion.
        $soplador = $this->soplador();

        $this->actingAs($soplador)->get('/admin/produccion')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', AvisosError::SIN_PERMISO);

        $this->actingAs($soplador)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(AvisosError::SIN_PERMISO)
            ->assertSee('data-aviso', false);
    }

    public function test_el_usuario_nunca_ve_el_mensaje_en_ingles_de_spatie(): void
    {
        $this->actingAs($this->soplador())->get('/admin/produccion')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->soplador())->get(route('dashboard'))
            ->assertDontSee('User does not have the right permissions')
            ->assertDontSee('Forbidden');
    }

    public function test_navegar_a_un_recurso_ajeno_no_revela_que_existe(): void
    {
        $duenio = tap(User::factory()->create(['name' => 'Pedro Dueño']))->assignRole('soplador');
        $ajeno = $this->reporteDe($duenio);
        $otro = $this->soplador();

        // Mismo mensaje genérico que por permiso: decir "es de otro soplador"
        // confirmaria que el recurso existe (enumeracion) y el remedio es igual.
        $this->actingAs($otro)->get(route('produccion.mi.show', $ajeno))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', AvisosError::SIN_PERMISO);

        $this->actingAs($otro)->get(route('dashboard'))
            ->assertDontSee('Pedro Dueño');
    }

    // --- 404 ----------------------------------------------------------------

    public function test_una_uri_sin_ruta_muestra_la_pagina_con_marca_con_salida_al_inicio(): void
    {
        // Frontera VERIFICADA empiricamente: una URI que no matchea NINGUNA ruta
        // se resuelve ANTES del middleware `web`, asi que no hay sesion ni usuario
        // y no se puede redirigir con un flash. Igual no se ve la pantalla de
        // Symfony: le toca errors/404.blade.php, con su boton "Ir al Inicio".
        // (Un 404 lanzado DENTRO de una ruta —binding que no resuelve— si redirige;
        // eso lo cubre el test de abajo.)
        $this->actingAs($this->soplador())->get('/esto-no-existe-en-ninguna-parte')
            ->assertNotFound()
            ->assertSee('No encontramos esa página')
            ->assertSee(route('dashboard'), false);
    }

    public function test_el_404_de_un_binding_no_filtra_internals(): void
    {
        $this->actingAs($this->soplador())->get(route('produccion.mi.show', 999999))
            ->assertRedirect(route('dashboard'));

        // ModelNotFoundException produce "No query results for model [App\Models\...]".
        $this->assertSame(AvisosError::NO_ENCONTRADO, session('aviso'));
    }

    // --- Lo que NO debe convertirse en redirect ------------------------------

    public function test_una_peticion_json_conserva_su_403(): void
    {
        // CRITICO para resources/js/offline-queue.js: clasifica el 403 como rechazo
        // PERMANENTE. Con un 302 el fetch seguiria el redirect, veria resp.ok del
        // dashboard y BORRARIA la tanda del soplador sin haberla registrado.
        $soplador = $this->soplador();
        $enviado = $this->reporteDe($soplador, ProduccionReporte::ENVIADO);

        $this->actingAs($soplador)
            ->postJson(route('produccion.mi.registros.store', $enviado), [
                'primera' => 10, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            ])
            ->assertForbidden();
    }

    public function test_un_fetch_con_x_requested_with_conserva_su_403(): void
    {
        $this->actingAs($this->soplador())
            ->get('/admin/produccion', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertForbidden();
    }

    public function test_un_visitante_ve_la_pagina_con_marca_no_la_de_symfony(): void
    {
        // Link firmado sin firma (QR caducado/manipulado): no hay Inicio al que
        // llevarlo ni sesion, asi que le toca la pagina de error.
        $this->get(route('ingreso-taller.create'))
            ->assertForbidden()
            ->assertSee('Este enlace no es válido')
            ->assertDontSee('Invalid signature')
            ->assertDontSee(route('dashboard'), false);
    }

    public function test_una_accion_rechazada_por_permiso_se_queda_en_403_con_marca(): void
    {
        $this->actingAs($this->soplador())
            ->post(route('admin.produccion.asignar.store'), [])
            ->assertForbidden()
            ->assertSee('No tienes permiso para entrar ahí');
    }

    // --- Acción rechazada por ESTADO: conserva el copy de negocio ------------

    public function test_una_accion_rechazada_por_estado_conserva_su_mensaje(): void
    {
        $soplador = $this->soplador();
        $enviado = $this->reporteDe($soplador, ProduccionReporte::ENVIADO);

        $this->assertRechazado(
            $this->actingAs($soplador)->patch(route('produccion.mi.update', $enviado), ['enviar' => 0]),
            'Este reporte ya no se puede editar.',
        );
    }

    // --- Anti-bucle ---------------------------------------------------------

    public function test_el_inicio_nunca_se_redirige_a_si_mismo(): void
    {
        // Si el 403/404 lo lanzara el PROPIO dashboard, redirigir ahi seria un
        // bucle infinito. Hoy /dashboard solo lleva 'auth', asi que se simula.
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth'])
            ->get('/dashboard-bucle', fn () => abort(403))
            ->name('dashboard');
        \Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

        $this->actingAs($this->soplador())->get('/dashboard-bucle')->assertForbidden();
    }
}
