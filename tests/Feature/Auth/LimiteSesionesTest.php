<?php

namespace Tests\Feature\Auth;

use App\Models\Configuracion;
use App\Models\User;
use App\Support\LimiteSesiones;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Límite de sesiones paralelas (dueño 01-09): default 3, paramétrico por rol
 * y usuario; al topar, el login nuevo SIEMPRE entra y se expulsa la sesión
 * más antigua. El recorte corre en el listener del evento Login (form login
 * Y recaller de «recordarme») — el POST /login real de estos candados fija
 * de paso que el auto-descubrimiento de app/Listeners registró el listener.
 */
class LimiteSesionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(string $rol = 'soplador'): User
    {
        // Correo del dominio corporativo: el login lo exige (ImpdaliEmail).
        return tap(User::factory()->create([
            'email' => uniqid('u').'@impdali.cl',
        ]))->assignRole($rol);
    }

    /** Filas de sesión con antigüedad escalonada: 's1' la más vieja. */
    private function sesiones(User $user, int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            DB::table('sessions')->insert([
                'id' => str_pad("s{$i}", 40, 'x'),
                'user_id' => $user->id,
                'payload' => 'x',
                'last_activity' => 1000 + $i,
            ]);
        }
    }

    private function clave(string $clave, string $valor, string $tipo): void
    {
        Configuracion::create([
            'clave' => $clave, 'valor' => $valor, 'tipo' => $tipo,
            'grupo' => LimiteSesiones::GRUPO, 'descripcion' => 'test',
        ]);
    }

    private function login(User $user): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_al_topar_el_login_expulsa_la_sesion_mas_antigua(): void
    {
        $user = $this->usuario();
        $this->sesiones($user, 3);

        $this->login($user);

        // Default 3 → quedan las (3−1) más nuevas de las previas; la fila del
        // login que nace se escribe aparte al final del request.
        $this->assertDatabaseMissing('sessions', ['id' => str_pad('s1', 40, 'x')]);
        $this->assertDatabaseHas('sessions', ['id' => str_pad('s2', 40, 'x')]);
        $this->assertDatabaseHas('sessions', ['id' => str_pad('s3', 40, 'x')]);
    }

    public function test_bajo_el_limite_no_borra_nada(): void
    {
        $user = $this->usuario();
        $this->sesiones($user, 1);

        $this->login($user);

        $this->assertDatabaseHas('sessions', ['id' => str_pad('s1', 40, 'x')]);
    }

    public function test_el_recaller_de_recordarme_tambien_recorta(): void
    {
        // El re-ingreso automático por cookie no pasa por el controller de
        // login: dispara el MISMO evento, y el listener lo cubre igual.
        $user = $this->usuario();
        $this->sesiones($user, 3);

        event(new Login('web', $user, true));

        $this->assertDatabaseMissing('sessions', ['id' => str_pad('s1', 40, 'x')]);
        $this->assertDatabaseHas('sessions', ['id' => str_pad('s2', 40, 'x')]);
        $this->assertDatabaseHas('sessions', ['id' => str_pad('s3', 40, 'x')]);
    }

    public function test_limite_uno_deja_solo_la_sesion_nueva(): void
    {
        // El borde del `whereNotIn('id', [])` (compila 1=1 y borra todas las
        // demás — acá es el comportamiento deseado, ver LimiteSesiones).
        $user = $this->usuario();
        $this->clave(LimiteSesiones::CLAVE_USUARIOS, json_encode([$user->id => 1]), Configuracion::TIPO_JSON);
        $this->sesiones($user, 2);

        $this->login($user);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_cero_es_sin_limite_y_no_borra(): void
    {
        $user = $this->usuario();
        $this->clave(LimiteSesiones::CLAVE_DEFAULT, '0', Configuracion::TIPO_INTEGER);
        $this->sesiones($user, 5);

        $this->login($user);

        $this->assertSame(5, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_un_guard_distinto_de_web_no_recorta(): void
    {
        $user = $this->usuario();
        $this->sesiones($user, 5);

        event(new Login('otro-guard', $user, false));

        $this->assertSame(5, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_precedencia_usuario_gana_a_rol_y_rol_a_default(): void
    {
        $user = $this->usuario('soplador');
        $this->clave(LimiteSesiones::CLAVE_DEFAULT, '3', Configuracion::TIPO_INTEGER);
        $this->clave(LimiteSesiones::CLAVE_ROLES, json_encode(['soplador' => 2]), Configuracion::TIPO_JSON);
        $this->clave(LimiteSesiones::CLAVE_USUARIOS, json_encode([$user->id => 5]), Configuracion::TIPO_JSON);

        $this->assertSame(5, LimiteSesiones::de($user));

        Configuracion::set(LimiteSesiones::CLAVE_USUARIOS, '{}');
        $this->assertSame(2, LimiteSesiones::de($user));

        Configuracion::set(LimiteSesiones::CLAVE_ROLES, '{}');
        $this->assertSame(3, LimiteSesiones::de($user));
    }

    public function test_con_varios_roles_gana_el_mas_permisivo_y_cero_es_infinito(): void
    {
        // Un rol por usuario es la regla de la UI; esto fija qué pasa con
        // datos legados multi-rol: los overrides son CONCESIONES (max), y el
        // 0 gana como infinito.
        $user = $this->usuario('soplador');
        $user->assignRole('conductor');

        $this->clave(LimiteSesiones::CLAVE_ROLES, json_encode(['soplador' => 2, 'conductor' => 5]), Configuracion::TIPO_JSON);
        $this->assertSame(5, LimiteSesiones::de($user));

        Configuracion::set(LimiteSesiones::CLAVE_ROLES, json_encode(['soplador' => 2, 'conductor' => 0]));
        $this->assertNull(LimiteSesiones::de($user));
    }

    public function test_un_valor_podrido_cae_al_siguiente_nivel_y_el_exceso_clampa(): void
    {
        $user = $this->usuario('soplador');
        $this->clave(LimiteSesiones::CLAVE_DEFAULT, '3', Configuracion::TIPO_INTEGER);
        $this->clave(LimiteSesiones::CLAVE_ROLES, json_encode(['soplador' => -1]), Configuracion::TIPO_JSON);
        $this->clave(LimiteSesiones::CLAVE_USUARIOS, json_encode([$user->id => 'abc']), Configuracion::TIPO_JSON);

        // Usuario 'abc' podrido → se ignora; rol -1 podrido → se ignora;
        // decide el default. Un dato roto no puede dejar a nadie sin entrar.
        $this->assertSame(3, LimiteSesiones::de($user));

        // Un 999 metido por fuera intenta «muchas», no «default»: clamp a MAX.
        Configuracion::set(LimiteSesiones::CLAVE_USUARIOS, json_encode([$user->id => 999]));
        $this->assertSame(LimiteSesiones::MAX, LimiteSesiones::de($user));
    }

    public function test_bd_virgen_usa_el_default_tres_del_codigo(): void
    {
        $this->assertSame(3, LimiteSesiones::de($this->usuario()));
    }

    public function test_actingas_no_dispara_el_recorte(): void
    {
        // La masa de tests existentes usa actingAs (evento Authenticated, no
        // Login): navegar así con sesiones al tope no borra nada — la suite
        // no cambió de conducta con este lote.
        $user = $this->usuario();
        $this->sesiones($user, 5);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame(5, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
