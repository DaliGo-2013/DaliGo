<?php

/*
|--------------------------------------------------------------------------
| Documentos tributarios electrónicos (M05)
|--------------------------------------------------------------------------
|
| Traducción entre el vocabulario de DaliGo y el del emisor. Vive en config y
| no en `Configuracion` editable a propósito: un id de oficina o de medio de
| pago equivocado emite un documento tributario mal atribuido, y eso se corrige
| con nota de crédito. Es una decisión de despliegue, no un parámetro de usuario.
|
| Los tres mapas arrancan VACÍOS y eso es deliberado: sin ellos BsaleEmisor
| lanza una excepción con el nombre exacto de la clave que falta, en vez de
| emitir con un valor adivinado. Se llenan con una lectura contra la cuenta
| (paso B6, `dte:emitir-prueba`), que es la única parte del módulo que necesita
| credencial.
|
*/

return [

    /** Emisor activo. Debe coincidir con EmisorDte::nombre() y con `dte_emitidos.emisor`. */
    'emisor' => env('DTE_EMISOR', 'bsale'),

    /*
    |--------------------------------------------------------------------------
    | Candado de emisión (B4) — ver App\Services\Dte\CandadoDeEmision
    |--------------------------------------------------------------------------
    |
    | `ambiente` declara qué ES la credencial que está en el .env: 'prueba' o
    | 'produccion'. Hay que declararlo a mano porque la API de Bsale NO lo dice:
    | la URL es idéntica en los dos casos y el ambiente lo define únicamente el
    | token.
    |
    | El default es 'produccion' A PROPÓSITO, y es lo contrario de lo cómodo: un
    | valor desconocido O AUSENTE se trata como producción, o sea que el sistema
    | asume la credencial más peligrosa mientras nadie declare lo contrario. Con el
    | default en 'prueba', una credencial de producción copiada a un computador sin
    | etiquetar quedaba sin la protección del candado — y el servidor de producción
    | SÍ tiene una credencial real (de ahí se leen catálogo, precios y clientes).
    | Para emitir en pruebas hay que declararlo: BSALE_AMBIENTE=prueba.
    |
    | `emision_habilitada` es el interruptor de la emisión REAL y arranca
    | APAGADO. Representa la autorización de Gerencia: mientras esté en false, el
    | sistema puede leer todo de Bsale pero no puede crear ni un documento.
    |
    | Leer NUNCA está bloqueado: el plan acordado es recabar los datos de la
    | cuenta real (tipos de documento, oficinas, medios de pago, notas de crédito
    | pasadas) antes de emitir, y eso son todas consultas.
    |
    */

    'ambiente' => env('BSALE_AMBIENTE', 'produccion'),

    'emision_habilitada' => env('DTE_EMISION_HABILITADA', false),

    'bsale' => [

        /*
        | documentTypeId de Bsale por código de DTE del SII (33 factura, 39
        | boleta, 52 guía, 61 nota de crédito).
        |
        | Bsale acepta `codeSii` como alternativa, y si acá no hay id se manda
        | ese. Pero el codeSii es AMBIGUO cuando la empresa tiene más de un tipo
        | de documento con el mismo código (dos series de factura, una por
        | sucursal): Bsale elegiría una y no hay forma de saber cuál. Con el id
        | explícito no hay ambigüedad.
        */
        'tipos_documento' => [
            // 33 => 1,   // Factura electrónica
            // 39 => 2,   // Boleta electrónica
            // 52 => 3,   // Guía de despacho
            // 61 => 4,   // Nota de crédito
        ],

        /*
        | officeId de Bsale por CÓDIGO de sucursal de DaliGo.
        |
        | Regla de Contabilidad (28-jul-2026): el documento se emite desde la
        | sucursal donde se REPARA, no donde se recibió el equipo. Se indexa por
        | código y no por id porque los ids no coinciden entre staging y
        | producción.
        */
        'oficinas' => [
            // 'MIR' => 1,
        ],

        /*
        | paymentTypeId de Bsale por forma de pago de DaliGo (ver
        | App\Services\Dte\FormaPago).
        |
        | Regla de Contabilidad: el pago se registra en el MOMENTO de emitir, así
        | que un documento sin pago quedaría descuadrado en el cierre de caja.
        | Por eso una forma de pago sin mapear es un error y no una omisión
        | silenciosa.
        */
        'medios_pago' => [
            // FormaPago::EFECTIVO => 1,
        ],

    ],

];
