# ADR-005 · Emisión de DTE: puerto `EmisorDte`, reserva previa, POST sin reintentos

- **Estado:** vigente · **documentos reales emitidos: 0** (esperando autorización escrita de Gerencia, biblia §10.6)
- **Fecha:** 2026-07-28 · **Decide:** equipo con Contabilidad y soporte de Bsale por escrito

## Contexto
DaliGo debe emitir boletas y facturas, pero construir el timbre electrónico propio cuesta 45–70 días-persona más mantención normativa permanente; integrar un emisor, 5–12 días. Bsale es el emisor y la API tiene tres trampas verificadas: el **ambiente lo define solo el token** (misma URL para prueba y producción), el sandbox **no timbra**, y un reintento de `POST /documents.json` que sí se procesó **emite un segundo folio real** — que solo se corrige con nota de crédito ante el SII.

## Decisión
**DaliGo no emite, traduce; la app depende de un puerto; la fila se reserva antes de llamar; el POST no reintenta.**
- `App\Services\Dte\EmisorDte` es la interfaz; `BsaleEmisor` la implementación (traductor puro, no toca la BD).
- `EmisionDte::reservar()` inserta la fila en `dte_emitidos` **antes** del POST; el índice único de `sales_id` es la barrera física contra el doble clic — el `catch (QueryException)` es el camino normal de la segunda petición.
- `BsaleClient::post()` **no** comparte el `retry` de los GET; solo reintenta el **429** (rechazo por velocidad = no creó nada).
- Los tres mapas de `config/dte.php` arrancan **vacíos**; una clave faltante hace fallar la emisión con su nombre. Sin defaults.
- `CandadoDeEmision`: la emisión está apagada por entorno hasta la autorización.

## Alternativas consideradas
- **Timbre propio** — cerrado con números (biblia §10.1).
- **Retry con backoff** — rechazado: no hay idempotencia del lado de Bsale sin `salesId`, y un timeout es indistinguible de «se procesó y perdí la respuesta».
- **Guardar la fila después de emitir** — dos clics rápidos mandan dos POST antes de que ninguno escriba.

## Consecuencias
Cambiar de emisor = otra implementación del puerto. El XML es el documento legal (6 años): `dte_emitidos.url_xml`. Prohibido versionar token, `.p12` o CAF. Nadie emite el primer documento real por iniciativa propia.

## Evidencia
Biblia §10 · `docs/FACTURACION-ELECTRONICA.md` · `CLAUDE.md` bitácora [2026-07-28] · `tests/Feature/Dte/*`, `tests/Unit/Dte/*`.
