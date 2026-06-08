<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Exploracion de SOLO LECTURA de la cuenta Bsale configurada en .env.
 * No escribe nada en Bsale ni en la BD. Sirve para dimensionar el catalogo
 * real antes de planear la sincronizacion. El token se lee de config/.env y
 * nunca se imprime.
 */
class BsaleExplore extends Command
{
    protected $signature = 'bsale:explore';

    protected $description = 'Explora (solo lectura) la cuenta Bsale del .env: conteos, formas y catálogo.';

    public function handle(): int
    {
        $base = rtrim((string) config('services.bsale.base_url'), '/');
        $token = (string) config('services.bsale.token');

        if ($token === '') {
            $this->error('Falta BSALE_ACCESS_TOKEN en .env (config services.bsale.token).');

            return self::FAILURE;
        }

        $this->info("Base: {$base}  ·  token: ".str_repeat('*', 6).'(oculto)');
        $this->newLine();

        // 1. Conteos reales (los listados filtran por estado; /count.json da el total).
        $this->line('== Conteos ==');
        $productos = $this->count("{$base}/products/count.json");
        $variantes = $this->count("{$base}/variants/count.json");
        $this->line('  productos (total): '.($productos ?? '—'));
        $this->line('  variantes/SKUs (total): '.($variantes ?? '—'));
        if ($productos && $variantes && $productos > 0) {
            $this->line('  variantes por producto (prom): '.round($variantes / $productos, 2));
        }
        $this->newLine();

        // 2. Tipos de producto (categorias).
        $this->line('== Categorías (product_types) ==');
        $pt = $this->get("{$base}/product_types.json?limit=50&state=0");
        if ($pt) {
            $this->line('  total: '.($pt['count'] ?? '?'));
            foreach (array_slice($pt['items'] ?? [], 0, 25) as $t) {
                $this->line('   - ['.($t['id'] ?? '?').'] '.trim((string) ($t['name'] ?? '')));
            }
        }
        $this->newLine();

        // 3. Muestra de variantes (donde vive el SKU = code).
        $this->line('== Variantes (muestra; SKU = code) ==');
        $vs = $this->get("{$base}/variants.json?limit=10&expand=[product]");
        foreach (($vs['items'] ?? []) as $v) {
            $prod = is_array($v['product'] ?? null) ? ($v['product']['name'] ?? $v['product']['id'] ?? '') : '';
            $this->line(sprintf(
                '   code=%-18s barCode=%-16s state=%s  %s',
                $this->short($v['code'] ?? '', 18),
                $this->short($v['barCode'] ?? '', 16),
                $v['state'] ?? '?',
                $this->short(is_string($prod) ? $prod : (string) $prod, 30),
            ));
        }
        $this->newLine();

        // 4. Listas de precios.
        $this->line('== Listas de precios ==');
        $pl = $this->get("{$base}/price_lists.json?expand=[coin]&limit=50");
        if ($pl) {
            $this->line('  total: '.($pl['count'] ?? '?'));
            foreach (array_slice($pl['items'] ?? [], 0, 20) as $l) {
                $coin = is_array($l['coin'] ?? null) ? ($l['coin']['name'] ?? '') : '';
                $this->line('   - ['.($l['id'] ?? '?').'] '.$this->short((string) ($l['name'] ?? ''), 35).'  ('.$coin.')  state='.($l['state'] ?? '?'));
            }
        }
        $this->newLine();

        // 5. Oficinas/bodegas (cuantas y cuantas virtuales — las ~25 virtuales de la biblia).
        $this->line('== Oficinas / bodegas ==');
        $of = $this->get("{$base}/offices.json?limit=50");
        if ($of) {
            $items = $of['items'] ?? [];
            $virtuales = collect($items)->where('isVirtual', 1)->count();
            $this->line('  total (envelope): '.($of['count'] ?? '?').'  ·  en esta página: '.count($items).'  ·  virtuales (en página): '.$virtuales);
            foreach (array_slice($items, 0, 30) as $o) {
                $this->line('   - ['.($o['id'] ?? '?').'] '.$this->short((string) ($o['name'] ?? ''), 30).'  isVirtual='.($o['isVirtual'] ?? '?').' state='.($o['state'] ?? '?'));
            }
            if (($of['count'] ?? 0) > count($items)) {
                $this->warn('  (hay más oficinas que las mostradas; total='.$of['count'].')');
            }
        }
        $this->newLine();

        // 6. Stock (forma; combinacion variante x oficina).
        $this->line('== Stock (muestra) ==');
        $st = $this->get("{$base}/stocks.json?limit=3&expand=[variant,office]");
        if ($st) {
            $this->line('  total registros de stock: '.($st['count'] ?? '?'));
            foreach (($st['items'] ?? []) as $s) {
                $vc = is_array($s['variant'] ?? null) ? ($s['variant']['code'] ?? '') : '';
                $on = is_array($s['office'] ?? null) ? ($s['office']['name'] ?? '') : '';
                $this->line(sprintf('   code=%-16s qty=%s reservado=%s disp=%s  @ %s',
                    $this->short((string) $vc, 16), $s['quantity'] ?? '?', $s['quantityReserved'] ?? '?', $s['quantityAvailable'] ?? '?', $this->short((string) $on, 20)));
            }
        }
        $this->newLine();

        $this->info('Listo. Exploración de solo lectura completada.');

        return self::SUCCESS;
    }

    private function get(string $url): ?array
    {
        try {
            $res = Http::withHeaders(['access_token' => (string) config('services.bsale.token')])
                ->acceptJson()
                ->timeout(30)
                ->get($url);
        } catch (\Throwable $e) {
            $this->error('  Error de conexión: '.$e->getMessage());

            return null;
        }

        if (! $res->successful()) {
            $this->error('  HTTP '.$res->status().' en '.parse_url($url, PHP_URL_PATH).' → '.$this->short($res->body(), 120));

            return null;
        }

        return $res->json();
    }

    private function count(string $url): ?int
    {
        $json = $this->get($url);

        return isset($json['count']) ? (int) $json['count'] : null;
    }

    private function short(string $value, int $len): string
    {
        $value = trim($value);

        return mb_strlen($value) > $len ? mb_substr($value, 0, $len - 1).'…' : $value;
    }
}
