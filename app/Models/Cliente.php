<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Cliente (M03). Espejo local de los clientes de Bsale (enlace por
 * bsale_client_id) mas el enriquecimiento que Bsale no guarda: segmento,
 * notas y vendedor asignado (cartera). La sync solo escribe los campos que
 * manda Bsale; los locales jamas se pisan.
 */
class Cliente extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\ClienteFactory> */
    use HasFactory, AuditableTrait;

    public const SEGMENTOS = ['mayorista', 'retail', 'recurrente'];

    protected $table = 'clientes';

    protected $fillable = [
        'rut',
        'razon_social',
        'giro',
        'email',
        'telefono',
        'direccion',
        'ciudad',
        'comuna',
        'es_empresa',
        'envio_factura_email',
        'activo',
        'segmento',
        'notas',
        'vendedor_id',
        'vendedor_nombre',
        'bsale_client_id',
    ];

    protected function casts(): array
    {
        return [
            'es_empresa' => 'boolean',
            'envio_factura_email' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * ¿La ficha viene de Bsale (espejo) o es local (creada en DaliGo)? Importa
     * para decidir si un cambio de contacto se puede guardar: la sync horaria
     * (bsale:sync-clients) pisa email/telefono/direccion/ciudad de las fichas de
     * Bsale, así que solo tiene sentido actualizar las locales desde DaliGo.
     */
    public function getEsDeBsaleAttribute(): bool
    {
        return filled($this->bsale_client_id);
    }

    /**
     * Busca una ficha por RUT normalizándolo primero (idempotente si ya viene
     * canónico). Null si no hay RUT o no existe en el catálogo.
     */
    public static function buscarPorRut(?string $rut): ?self
    {
        $norm = self::normalizarRut($rut);

        return $norm ? self::where('rut', $norm)->first() : null;
    }

    /**
     * Normaliza un RUT a su forma canonica `12345678-9`: quita puntos, espacios
     * y guiones, K mayuscula, y separa el digito verificador. Devuelve null si
     * no quedan al menos cuerpo + DV (Bsale trae clientes con code vacio).
     */
    public static function normalizarRut(?string $rut): ?string
    {
        $limpio = strtoupper((string) preg_replace('/[^0-9kK]/', '', (string) $rut));

        if (strlen($limpio) < 2) {
            return null;
        }

        return substr($limpio, 0, -1).'-'.substr($limpio, -1);
    }

    /**
     * Digito verificador chileno (modulo 11) para un cuerpo de RUT.
     */
    public static function dvRut(int $cuerpo): string
    {
        $suma = 0;
        $factor = 2;

        foreach (array_reverse(str_split((string) $cuerpo)) as $digito) {
            $suma += (int) $digito * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }
}
