<?php

namespace App\Services\Bsale;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP de solo lectura para la API de Bsale. El token y la base se leen
 * de config('services.bsale.*') por defecto, pero son inyectables (tests).
 */
class BsaleClient
{
    private string $base;

    private string $token;

    public function __construct(?string $base = null, ?string $token = null)
    {
        $this->base = rtrim($base ?? (string) config('services.bsale.base_url'), '/');
        $this->token = $token ?? (string) config('services.bsale.token');
    }

    public function hasToken(): bool
    {
        return $this->token !== '';
    }

    /**
     * GET de un recurso (ej. "variants.json"). Devuelve el JSON decodificado.
     *
     * @throws BsaleApiException
     */
    public function get(string $path, array $query = []): array
    {
        $url = $this->base.'/'.ltrim($path, '/');

        for ($attempt = 1; ; $attempt++) {
            $res = $this->request()->get($url, $query);

            // 429: respeta Retry-After (o backoff), con tope de reintentos.
            if ($res->status() === 429 && $attempt <= 3) {
                sleep(min((int) ($res->header('Retry-After') ?: 2 ** $attempt), 30));

                continue;
            }

            if (! $res->successful()) {
                throw new BsaleApiException(
                    "Bsale HTTP {$res->status()} en {$path}: ".mb_substr((string) $res->body(), 0, 200),
                    $res->status(),
                );
            }

            return $res->json() ?? [];
        }
    }

    /**
     * Recorre el sobre paginado {count,limit,offset,items,next} y entrega cada item.
     *
     * @return Generator<int, array>
     */
    public function each(string $path, array $query = [], int $limit = 50): Generator
    {
        $offset = 0;

        do {
            $page = $this->get($path, array_merge($query, ['limit' => $limit, 'offset' => $offset]));
            $items = $page['items'] ?? [];

            foreach ($items as $item) {
                yield $item;
            }

            $count = (int) ($page['count'] ?? 0);
            $offset += $limit;
        } while (count($items) === $limit && $offset < $count);
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders(['access_token' => $this->token])
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }
}
