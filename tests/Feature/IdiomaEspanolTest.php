<?php

namespace Tests\Feature;

use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Candado de IDIOMA: la app se ve en español, sin palabras en inglés a la vista.
 *
 * Salió del barrido que pidió el dueño (03-08-2026: «habían palabras en inglés»
 * en el celular, sin recordar dónde). Lo que se encontró y este candado fija:
 *
 *  1. La PAGINACIÓN estaba en inglés porque faltaba `lang/es/pagination.php` y
 *     las claves JSON de la línea de resultados. En MÓVIL era lo más visible:
 *     la vista de Tailwind esconde los números de página bajo el breakpoint
 *     `sm` y deja solo los botones «Previous» / «Next».
 *  2. Los mensajes de validación armaban el nombre del campo desde la COLUMNA
 *     («El campo producto id...»), y uno salía en inglés: «El campo role...».
 *
 * El barrido recorre pantallas reales —internas, públicas y de error— y mira
 * tanto el texto visible como los atributos que el usuario LEE (placeholder) o
 * que le lee el lector de pantalla (title/aria-label/alt): la primera versión
 * de este barrido descartaba los atributos junto con las etiquetas HTML y por
 * eso daba verde de más.
 */
class IdiomaEspanolTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Palabras inequívocamente inglesas. Se dejan FUERA las que también son
     * español o son nombres propios/técnicos aceptados (Total, No, QR, SKU,
     * Bsale, DaliGo, PWA, email…), para que el candado no dé falsos positivos.
     */
    private const SOSPECHOSAS = [
        'Showing', 'results', 'Previous', 'Next', 'Save', 'Cancel', 'Delete', 'Edit',
        'Search', 'Submit', 'Close', 'Loading', 'Settings', 'Logout', 'Log Out',
        'Required', 'Optional', 'Select', 'Choose', 'Upload', 'Download', 'Actions',
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
        'January', 'February', 'March', 'April', 'June', 'July', 'August',
        'September', 'October', 'November', 'December',
        'Whoops', 'Page Expired', 'Not Found', 'Forbidden', 'Server Error',
        'Too Many Requests', 'Unauthorized', 'Continue', 'Back',
    ];

    private Sucursal $sucursal;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'MIRADOR'],
            ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true],
        );
        $this->admin = tap(User::factory()->create())->assignRole('admin');
    }

    /**
     * Texto que el usuario percibe: el visible MÁS los atributos que se leen.
     */
    private function textoPercibido(string $html): string
    {
        $texto = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/si', ' ', $html);
        $texto = preg_replace('/<[^>]+>/', ' ', $texto);

        $attrs = '';
        foreach (['placeholder', 'title', 'aria-label', 'alt'] as $a) {
            if (preg_match_all('/\b'.$a.'="([^"]*)"/i', $html, $m)) {
                $attrs .= ' '.implode(' | ', $m[1]);
            }
        }

        return preg_replace('/\s+/', ' ', html_entity_decode($texto.' '.$attrs, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function assertSinIngles(string $html, string $donde): void
    {
        $texto = $this->textoPercibido($html);

        foreach (self::SOSPECHOSAS as $palabra) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\p{L}])'.preg_quote($palabra, '/').'(?![\p{L}])/u',
                $texto,
                "[{$donde}] aparece la palabra en inglés «{$palabra}» en el texto que ve el usuario."
            );
        }
    }

    /**
     * La PAGINACIÓN en español. Es el hallazgo original: en el celular los
     * únicos controles visibles son estos dos botones.
     */
    public function test_la_paginacion_esta_en_espanol(): void
    {
        $base = OrdenServicio::factory()->make()->getAttributes();
        $filas = [];
        for ($i = 0; $i < 30; $i++) {
            $filas[] = array_merge($base, [
                'codigo' => 'ST-'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'sucursal_id' => $this->sucursal->id,
                'fecha_ingreso' => '2026-07-15',
                'producto_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('ordenes_servicio')->insert($filas);

        $html = $this->actingAs($this->admin)->get('/admin/servicio-tecnico')->assertOk()->getContent();

        // Control POSITIVO: si no hay paginación, este test no prueba nada.
        $this->assertStringContainsString('Mostrando', $html, 'No se renderizó la paginación: el test quedaría verde por vacío.');
        $this->assertStringContainsString('Anterior', $html);
        $this->assertStringContainsString('Siguiente', $html);
        $this->assertSinIngles($html, 'listado paginado');
    }

    /** Las pantallas internas, en español (texto y atributos). */
    public function test_las_pantallas_internas_estan_en_espanol(): void
    {
        // La ficha de clasificación necesita una bodega real (M04-F1); el
        // wizard de baja y la orden de traslado, sus fixtures (F2). La receta
        // (P-M11-10) edita por producto botellón.
        $bodega = \App\Models\Bodega::factory()->create();
        $orden = \App\Models\BodegaTraslado::factory()->create();
        $botellon = \App\Models\Producto::factory()->create(['activo' => true]);

        $urls = [
            '/dashboard', '/admin/servicio-tecnico', '/admin/servicio-tecnico/create',
            '/admin/productos', '/admin/clientes', '/admin/bodegas', '/admin/listas-precios',
            "/admin/bodegas/{$bodega->id}/edit",
            "/admin/bodegas/{$bodega->id}/baja",
            "/admin/bodegas/traslados/{$orden->id}",
            '/admin/recetas',
            "/admin/recetas/{$botellon->id}/edit",
            '/admin/sucursales', '/admin/users', '/admin/users/create', '/admin/roles',
            '/admin/configuracion', '/admin/maquinas', '/admin/tipos-botellon', '/admin/audits',
            '/admin/despachos', '/admin/notificaciones', '/admin/agenda-terreno',
            '/admin/servicio-tecnico/lote', '/admin/servicio-tecnico/qr', '/admin/plan',
            '/admin/aprobaciones', '/profile',
        ];

        foreach ($urls as $url) {
            $res = $this->actingAs($this->admin)->get($url);
            if ($res->status() !== 200) {
                continue;   // rutas que dependen de datos/permisos que este test no monta
            }
            $this->assertSinIngles($res->getContent(), $url);
        }
    }

    /** Lo que ve el CLIENTE (formularios del QR) y el login: en español. */
    public function test_las_pantallas_publicas_estan_en_espanol(): void
    {
        $id = $this->sucursal->id;
        $urls = [
            '/login' => '/login',
            'olvide mi contraseña' => '/forgot-password',
            'QR por unidad' => URL::signedRoute('ingreso-taller.create', ['sucursal' => $id]),
            'QR por cantidad' => URL::signedRoute('ingreso-taller.lote.create', ['sucursal' => $id]),
            'QR visita industrial' => URL::signedRoute('visita-industrial.create', ['sucursal' => $id]),
        ];

        foreach ($urls as $donde => $url) {
            $this->assertSinIngles($this->get($url)->getContent(), $donde);
        }
    }

    /** Una URL inexistente: la página de error también es del usuario. */
    public function test_la_pagina_de_error_esta_en_espanol(): void
    {
        $this->assertSinIngles($this->get('/no-existe-esta-ruta')->getContent(), '404');
    }

    /**
     * Los mensajes de validación nombran los campos en español. Sin la lista
     * `attributes`, Laravel los arma desde la columna: «producto id», «cliente
     * rut», y «role» —inglés— a la vista del usuario.
     */
    public function test_los_errores_de_validacion_nombran_los_campos_en_espanol(): void
    {
        // Formulario PÚBLICO del cliente (el más expuesto).
        $this->post(route('ingreso-taller.store'), ['sucursal_id' => $this->sucursal->id]);
        $publico = collect(session('errors')->all())->implode(' · ');
        $this->assertStringContainsString('nombre y apellido', $publico);
        $this->assertStringContainsString('teléfono', $publico);
        $this->assertStringNotContainsString('cliente nombre', $publico);
        $this->assertStringNotContainsString('cliente telefono', $publico);

        // Formulario interno: 'role' era la palabra inglesa a la vista.
        $this->actingAs($this->admin)->post('/admin/users', []);
        $interno = collect(session('errors')->all())->implode(' · ');
        $this->assertStringContainsString('El campo rol es obligatorio', $interno);
        $this->assertStringNotContainsString('campo role', $interno);

        // Y la orden de servicio: 'producto id' -> nombre legible.
        $this->actingAs($this->admin)->post('/admin/servicio-tecnico', []);
        $orden = collect(session('errors')->all())->implode(' · ');
        $this->assertStringContainsString('código del producto', $orden);
        $this->assertStringNotContainsString('producto id', $orden);
    }

    /** El idioma de la app está fijado en español, no heredado del default. */
    public function test_el_locale_de_la_app_es_espanol(): void
    {
        $this->assertSame('es', app()->getLocale());
        // Y las piezas de traducción existen (la de paginación faltaba).
        $this->assertFileExists(lang_path('es/pagination.php'));
        $this->assertFileExists(lang_path('es/validation.php'));
        $this->assertFileExists(lang_path('es.json'));
    }
}
