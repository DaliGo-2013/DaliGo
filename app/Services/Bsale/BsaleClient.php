<?php

namespace App\Services\Bsale;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP para la API de Bsale. El token y la base se leen de
 * config('services.bsale.*') por defecto, pero son inyectables (tests).
 *
 * Era de solo lectura hasta M05; `post()` habilita la escritura, que hoy usa
 * únicamente BsaleEmisor para emitir documentos tributarios. Los dos verbos NO
 * comparten política de reintentos, y la diferencia es importante — ver el
 * comentario de `post()`.
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
     * POST de un recurso (ej. "documents.json"). Devuelve el JSON decodificado.
     *
     * SIN REINTENTOS AUTOMÁTICOS, a diferencia de `get()`. Un POST que crea un
     * documento tributario no es idempotente desde el punto de vista de la red:
     * si Bsale procesó la solicitud y la respuesta se perdió en el camino, el
     * reintento emite un SEGUNDO documento con folio propio, y eso ya no se
     * borra — se corrige con nota de crédito. La protección real contra
     * duplicados es el `salesId` (lado Bsale) y el índice único de
     * `dte_emitidos.sales_id` (lado nuestro), no un reintento a ciegas.
     *
     * El 429 sí se reintenta: significa que la solicitud fue RECHAZADA por
     * exceso de velocidad, así que no llegó a crear nada.
     *
     * @throws BsaleApiException
     */
    public function post(string $path, array $body): array
    {
        $url = $this->base.'/'.ltrim($path, '/');

        for ($attempt = 1; ; $attempt++) {
            $res = Http::withHeaders(['access_token' => $this->token])
                ->acceptJson()
                ->timeout(30)
                ->post($url, $body);

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
