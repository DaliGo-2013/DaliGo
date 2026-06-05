<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Parametro global tipado clave/valor.
 *
 * El `valor` se guarda siempre como texto y se castea segun `tipo`. Lectura
 * cacheada para siempre (Cache::rememberForever) hasta que set() la invalide.
 * IMPORTANTE: escribir siempre via Configuracion::set(); editar la tabla a mano
 * en la BD deja la cache obsoleta.
 */
class Configuracion extends Model
{
    // El pluralizador de Laravel haria 'configuracions'; fijamos la tabla correcta.
    protected $table = 'configuraciones';

    // Tipos soportados (fuente de verdad para controlador y vistas).
    public const TIPO_STRING = 'string';
    public const TIPO_INTEGER = 'integer';
    public const TIPO_DECIMAL = 'decimal';
    public const TIPO_BOOLEAN = 'boolean';
    public const TIPO_JSON = 'json';

    public const TIPOS = [
        self::TIPO_STRING,
        self::TIPO_INTEGER,
        self::TIPO_DECIMAL,
        self::TIPO_BOOLEAN,
        self::TIPO_JSON,
    ];

    protected $fillable = ['clave', 'valor', 'tipo', 'grupo', 'descripcion'];

    private static function cacheKey(string $clave): string
    {
        return 'config.'.$clave;
    }

    /**
     * Lee un ajuste ya casteado a su tipo PHP, cacheado para siempre.
     * Devuelve $default solo si la clave NO existe en la BD.
     */
    public static function get(string $clave, mixed $default = null): mixed
    {
        // Cacheamos el payload crudo (siempre un array => truthy => rememberForever
        // lo guarda aunque el valor sea null/false/0). 'missing' marca clave ausente.
        $payload = Cache::rememberForever(self::cacheKey($clave), function () use ($clave) {
            $row = static::query()->where('clave', $clave)->first(['valor', 'tipo']);

            return $row
                ? ['v' => $row->valor, 't' => $row->tipo]
                : ['missing' => true];
        });

        if (isset($payload['missing'])) {
            return $default;
        }

        return static::castValor($payload['v'], $payload['t']);
    }

    /**
     * Persiste un valor serializandolo segun el tipo de la fila existente y
     * luego invalida su cache. La clave debe existir (los ajustes los define
     * el codigo via seeder, no se crean por UI).
     */
    public static function set(string $clave, mixed $valor): void
    {
        $config = static::query()->where('clave', $clave)->firstOrFail();

        $config->valor = static::serializeValor($valor, $config->tipo);
        $config->save();

        Cache::forget(self::cacheKey($clave));
    }

    /**
     * Castea el string almacenado al tipo PHP segun $tipo.
     */
    public static function castValor(?string $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }

        return match ($tipo) {
            self::TIPO_INTEGER => (int) $valor,
            self::TIPO_DECIMAL => (float) $valor,
            // OJO: '0' es un string no vacio => (bool)'0' === true. Comparar explicito.
            self::TIPO_BOOLEAN => $valor === '1' || $valor === 'true',
            self::TIPO_JSON => json_decode($valor, true) ?? [],
            default => (string) $valor,
        };
    }

    /**
     * Serializa un valor PHP a string para almacenarlo segun $tipo.
     */
    public static function serializeValor(mixed $valor, string $tipo): ?string
    {
        if ($valor === null) {
            return null;
        }

        return match ($tipo) {
            self::TIPO_INTEGER => (string) (int) $valor,
            self::TIPO_DECIMAL => (string) (float) $valor,
            self::TIPO_BOOLEAN => $valor ? '1' : '0',
            self::TIPO_JSON => is_string($valor) ? $valor : json_encode($valor),
            default => (string) $valor,
        };
    }

    /**
     * Valor ya casteado, para mostrarlo en las vistas (ej. boolean robusto a '0').
     */
    public function getValorTipadoAttribute(): mixed
    {
        return static::castValor($this->valor, $this->tipo);
    }

    /**
     * JSON con formato legible para el textarea de edicion.
     */
    public function jsonPretty(): string
    {
        $decoded = json_decode($this->valor ?? '', true);

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: ($this->valor ?? '');
    }
}
