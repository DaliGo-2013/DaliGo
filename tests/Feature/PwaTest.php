<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spike PWA (P-SPK-01): manifest instalable + service worker + fallback offline.
 * Los archivos public/manifest.json y public/sw.js son estaticos: aqui se
 * valida su CONTRATO (claves/estrategias criticas), no su comportamiento en
 * el navegador (eso se verifica en preview/celular real).
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_responde_sin_auth_y_es_autosuficiente(): void
    {
        // Sin login (el SW la precachea en install) y sin assets externos:
        // debe renderizar aunque no haya CSS/fuentes disponibles.
        $response = $this->get('/offline');

        $response->assertOk()
            ->assertSee('Sin conexión')
            ->assertDontSee('fonts.bunny.net')
            ->assertDontSee('build/assets');
    }

    public function test_manifest_es_valido_e_instalable(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertIsArray($manifest, 'manifest.json debe ser JSON valido');
        // scope "/" explicito: sin el, el scope por defecto seria /produccion/
        // y login/logout quedarian "fuera de la app" instalada.
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/produccion/mi-reporte', $manifest['start_url']);

        $sizes = collect($manifest['icons'])->pluck('sizes');
        $this->assertTrue($sizes->contains('192x192'));
        $this->assertTrue($sizes->contains('512x512'));
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_service_worker_respeta_las_reglas_criticas(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Version de cache presente (regla de invalidacion del Blade offline).
        $this->assertMatchesRegularExpression("/CACHE = 'daligo-v\\d+'/", $sw);
        // Passthrough temprano de non-GET/cross-origin (jamas respondWith ahi).
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
        $this->assertStringContainsString('url.origin !== self.location.origin', $sw);
        // Fallback offline SOLO en el catch (nunca por status/ok: el 302 de
        // auth llega como opaqueredirect y mostraria offline en cada login).
        $this->assertStringContainsString('.catch(() => paginaOffline())', $sw);
    }

    public function test_layouts_declaran_el_manifest(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Guest (login) y app (autenticado) comparten el head PWA.
        $this->get('/login')->assertSee('manifest.json');

        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $this->actingAs($soplador)->get('/produccion/mi-reporte')
            ->assertOk()
            ->assertSee('manifest.json')
            ->assertSee('Sin conexión'); // indicador de red del operario
    }
}
