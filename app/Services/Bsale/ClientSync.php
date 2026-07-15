<?php

namespace App\Services\Bsale;

use App\Models\Cliente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza los clientes de Bsale hacia la tabla local `clientes`.
 *
 * Bsale manda la identidad (rut, razon social, giro, contacto, direccion,
 * es_empresa, envio de DTE, activo, id); DaliGo conserva el enriquecimiento
 * (segmento, notas, vendedor asignado): esos campos NO entran en el upsert,
 * asi que jamas se pisan. Shape verificado contra la API real (email/phone
 * vienen planos en el objeto; code trae puntos y puede venir vacio).
 */
class ClientSync
{
    /**
     * RUTs genericos/placeholder que Bsale repite (consumidor final, mostrador):
     * se guardan null para no volverse ruido recurrente de errores en el unique.
     * Confirmados en el barrido real de produccion (55555555-5 es el mas frecuente).
     */
    private const RUTS_GENERICOS = ['66666666-6', '55555555-5'];

    public function __construct(private BsaleClient $client) {}

    /**
     * @return array{creados:int,actualizados:int,adoptados:int,duplicados:int,omitidos:int,errores:array<int,array>}
     */
    public function run(): array
    {
        // `duplicados`: RUT ya tomado por otra ficha (esperado, benigno — Bsale
        // trae varios registros por RUT). `errores`: fallos reales e inesperados
        // (deberían ser 0). Se separan para que el sync no parezca roto cada corrida.
        $stats = ['creados' => 0, 'actualizados' => 0, 'adoptados' => 0, 'duplicados' => 0, 'omitidos' => 0, 'errores' => []];

        // Carga masiva: sin audit por fila (igual que el catalogo); resumen al log.
        Cliente::withoutAuditing(function () use (&$stats) {
            foreach ($this->client->each('clients.json', ['state' => 0]) as $bsaleClient) {
                try {
                    $this->upsertOne($bsaleClient, $stats);
                } catch (ClienteDuplicadoException $e) {
                    // Duplicado de RUT en el origen: se omite a propósito, no es error.
                    $stats['duplicados']++;
                    $stats['omitidos']++;
                } catch (Throwable $e) {
                    $stats['omitidos']++;
                    $stats['errores'][] = [
                        'client_id' => $bsaleClient['id'] ?? null,
                        'rut' => $bsaleClient['code'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        Log::info(sprintf(
            'bsale:sync-clients → %d creados, %d actualizados, %d adoptados, %d duplicados por RUT (esperado), %d errores.',
            $stats['creados'], $stats['actualizados'], $stats['adoptados'], $stats['duplicados'], count($stats['errores']),
        ));

        return $stats;
    }

    private function upsertOne(array $c, array &$stats): void
    {
        $clientId = isset($c['id']) ? (int) $c['id'] : null;
        if ($clientId === null) {
            throw new \RuntimeException('Cliente sin id.');
        }

        $rut = $this->rutDesdeBsale($c);
        $esEmpresa = (int) ($c['companyOrPerson'] ?? 0) === 1;

        // Empresa: company; persona: preferir nombre y apellido (company puede
        // traer basura para personas). Fallback defensivo al id.
        $nombrePersona = trim(($this->limpiar($c['firstName'] ?? null) ?? '').' '.($this->limpiar($c['lastName'] ?? null) ?? ''));
        $razon = $esEmpresa
            ? ($this->limpiar($c['company'] ?? null) ?? ($nombrePersona !== '' ? $nombrePersona : null))
            : ($nombrePersona !== '' ? $nombrePersona : $this->limpiar($c['company'] ?? null));
        if ($razon === null || $razon === '') {
            $razon = "Cliente {$clientId}";
        }

        // Solo campos que MANDA Bsale. Los locales (segmento/notas/vendedor_id)
        // se omiten a proposito => no se tocan en update y quedan null en create.
        $bsaleFields = [
            'rut' => $rut,
            'razon_social' => $razon,
            'giro' => $this->limpiar($c['activity'] ?? null),
            'email' => $this->limpiar($c['email'] ?? null),
            'telefono' => $this->limpiar($c['phone'] ?? null),
            'direccion' => $this->limpiar($c['address'] ?? null),
            'ciudad' => $this->limpiar($c['city'] ?? null),
            'comuna' => $this->limpiar($c['municipality'] ?? null),
            'es_empresa' => $esEmpresa,
            'envio_factura_email' => (int) ($c['sendDte'] ?? 0) === 1,
            'activo' => (int) ($c['state'] ?? 0) === 0,
            'bsale_client_id' => $clientId,
        ];

        // 1) Match por bsale_client_id (maneja cambio de RUT en Bsale).
        $row = Cliente::where('bsale_client_id', $clientId)->first();

        if ($row !== null) {
            try {
                $row->fill($bsaleFields)->save();
                $stats['actualizados']++;
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    throw new ClienteDuplicadoException("Cliente {$clientId}: el RUT '{$rut}' ya existe en otra fila; omitido.");
                }
                throw $e;
            }

            return;
        }

        // 2) Adopcion: ficha local sin enlazar (creada a mano) con el mismo RUT.
        if ($rut !== null) {
            $unlinked = Cliente::whereNull('bsale_client_id')->where('rut', $rut)->first();

            if ($unlinked !== null) {
                $unlinked->fill($bsaleFields)->save();
                $stats['adoptados']++;

                return;
            }
        }

        // 3) Cliente nuevo.
        try {
            Cliente::create($bsaleFields);
            $stats['creados']++;
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new ClienteDuplicadoException("RUT '{$rut}' ya existe en otra fila; cliente {$clientId} omitido.");
            }
            throw $e;
        }
    }

    /**
     * Code de Bsale -> rut local. Extranjeros (isForeigner) usan codigos que NO
     * son RUT: se guardan crudos (capados al ancho de la columna, 20) sin
     * normalizar — normalizarRut les borraria las letras y fabricaria un RUT
     * falso que ademas puede colisionar en el unique. Los RUTs genericos de
     * consumidor final (ver RUTS_GENERICOS) se guardan null: Bsale trae varios y
     * el unique los volveria ruido recurrente de errores.
     */
    private function rutDesdeBsale(array $c): ?string
    {
        $code = trim((string) ($c['code'] ?? ''));
        if ($code === '') {
            return null;
        }

        if ((int) ($c['isForeigner'] ?? 0) === 1) {
            return mb_substr($code, 0, 20);
        }

        $rut = Cliente::normalizarRut($code);

        return in_array($rut, self::RUTS_GENERICOS, true) ? null : $rut;
    }

    /**
     * Trim + ''→null + tope defensivo de 191 (largo de columna) para no perder
     * la fila completa por un dato basura demasiado largo.
     */
    private function limpiar(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, 191);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');

        return $code === '1062' || $code === '19'
            || str_contains($e->getMessage(), 'UNIQUE')
            || str_contains($e->getMessage(), 'Duplicate');
    }
}
