<?php

namespace App\Services\Bsale;

use RuntimeException;

/**
 * Un cliente de Bsale cuyo RUT ya está tomado por otra ficha local (Bsale trae
 * varios registros con el mismo RUT: duplicados históricos, consumidor final,
 * persona+empresa). NO es un fallo: es una condición esperada del origen. Se
 * cuenta aparte de los errores reales para que el sync no parezca roto.
 */
class ClienteDuplicadoException extends RuntimeException {}
