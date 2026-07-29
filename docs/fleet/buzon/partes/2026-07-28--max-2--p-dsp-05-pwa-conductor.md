# Parte de cierre — Max-2 · P-DSP-05 · PWA del conductor (M08-MVP)

CUENTA: Max-2 (Forjador B, stream 2 · DESPACHOS) · MODELO: Fable 5 (fijado por el dueño en su sesión)
TAREA: **P-DSP-05** — GO del dictado v12, gatillado por tu merge de P-DSP-04 (`946573a`)
ESTADO: **HECHA** — pide doble llave

## EVIDENCIA

- Rama **`feature/entregas-conductor`** desde main fresco (`d21a4a8`), como ordenaste — NO encadenada.
  4 commits: `a0c5190` backend · `91cab6d` cola v2 · `7bc2fbc` UI · `5903a8d` menú.
- TESTS: **1208 verdes / 7810 aserciones**, suite COMPLETA en cada commit.
  **18 tests nuevos** en `EntregaConductorTest` + los candados barren lo nuevo solos
  (MenuPrincipal/Sidebar/IconoTamano/MarcoHorizontal/AnchoDePagina/Pwa — este último SIN bump:
  no se tocó `offline.blade.php`).
- Bundle verificado: `firmaPad`/`entregaForm`/`rechazadasEntregas`/`dgColaEntregas`/`dgComprimirFoto`
  presentes; tests del soplador y PWA intactos.

## El criterio de "hecho" del plan, verificado de verdad (browser, 375px)

Banco de pruebas estático (patrón bitácora 2026-07-26 — sin login, sin contraseñas, borrado
antes del commit): la vista real renderizada con datos fake + el bundle real, servida local.

1. **Firma manuscrita**: pointer events sobre el canvas → trazo dibujado, `firmaLista` true.
2. **Foto**: File sintético al input → `fotoLista` true.
3. **Modo avión** (`$store.red.online = false`) → Confirmar → **encolada en IndexedDB**: item con
   uuid, `capturado_at` del device, etiqueta con apóstrofo (`O'Higgins` — vía `Js::from`,
   gotcha 28-07) y **los dos Blobs reales** (firma `image/png`, foto comprimida).
   Tarjeta tachada (optimista).
4. **Vuelve la señal** (evento `online`, server mockeado 422) → el drenado reconstruye el
   **FormData** (uuid + `firma.png`, sin Content-Type manual) → clasificada **permanente con su
   status** → aparece en **«No se pudieron enviar»** con su etiqueta → **Descartar** la borra y
   la sección se oculta.
5. Caso éxito (server 200): drena, borra de la cola y **la página se recargó sola** — es
   `iniciarColaOffline` reconciliando con el server, el comportamiento diseñado.
6. Sin scroll horizontal a 375.

La idempotencia servidor (drenar dos veces = una entrega) está cubierta por los tests HTTP, no
por el banco: 2× mismo uuid multipart → 1 entrega, `duplicado:true`, **ni la hora ni la firma del
primer envío se pisan**.

## Decisiones de diseño (las 3 que valen la pena discutir)

1. **`entrega_uuid` ahora es UNIQUE** (era index normal, P-DSP-03). El pre-check es la cara
   amable; la BD es la red — dos drenados en paralelo pasan ambos el pre-check. Sin cambio de
   tipo de columna (riesgo MySQL 5.7); NULL múltiples OK; sin FK (1553 no aplica).
2. **Firma vanilla, cero npm nuevo**: canvas + pointer events + `setPointerCapture` +
   `touch-action:none` (sin eso el gesto de firmar scrollea) + fondo blanco pre-pintado (GD
   re-encoda a JPEG: un PNG transparente quedaría negro). signature_pad solo aportaría suavizado
   bézier, y la regla del hosting cobra caro cada dependencia.
3. **UN camino online/offline** (fetch + FormData + `Accept: json`): online postea directo,
   sin señal (o si la red se cae ENTRE el check y el envío) encola el MISMO payload. Un solo
   código que mantener y la rama `expectsJson()` queda obligatoria por construcción.

**Servicio sin duplicar**: `registrarEntrega` ganó `array $extra = []` que entra al MISMO update
dentro del lock (uuid + hora del device atómicos con el estado); default `[]` = camino del jefe
byte-equivalente (sus tests no se tocaron). `confirmarEntregaConductor` = pre-check + delega +
catch del unique (patrón LoteServicio calcado).

**Scoping duro**: solo `conductor_id = yo`; sin conductor asignado NO se ve ni se confirma; el
403 va ANTES de validar (la cola lo clasifica permanente — reintentar no arregla un ajeno).

## Mutaciones verificadas (árbol commiteado ANTES, lección aprendida)

| Mutación | Resultado |
|---|---|
| Catch del unique ciego (`if (false)`) | **rojo** el test de la carrera |
| `$extra` fuera del update | **2 rojos** (capturado_at + idempotencia) |
| ⚠️ La 1ª mutación cayó en el catch de `crearDesdeDocumento` (misma forma, otra función) y quedó VERDE — mutar la ocurrencia equivocada da falsa confianza; se re-mutó la correcta | anotado para la doctrina |

## Riesgos aceptados (del plan, documentados)

- Recargar sin señal cae a `/offline` del SW (cachear HTML autenticado está prohibido; snapshot
  en IndexedDB sería un paso propio si el uso real lo pide).
- Safari puede purgar IndexedDB tras días sin uso → pérdida ante purga aceptada en MVP.
- `capturado_at` = reloj del device a propósito; `entregado_at` (server) es la verdad de auditoría.
- Doble tap offline: la UI optimista lo previene; si ocurre, el 2º drena como permanente y va a
  rechazadas — la BD no duplica.

/usage INICIO → FIN: sesión heredada del hilo del dueño, no comparable con un asiento limpio.

## SIGUIENTE

**Doble llave de `feature/entregas-conductor`.** Con eso DESPACHOS-v1 queda P-DSP-00..05 en main
y restan **P-DSP-06** (integración M14 — la dependencia ya está en main) y **P-DSP-07** (gate
R-31 + QA staging celular del dueño, que con la regla de refrescar-por-paso ya no es el choque
de bundles que el plan temía). Espero dictado para el que siga.
