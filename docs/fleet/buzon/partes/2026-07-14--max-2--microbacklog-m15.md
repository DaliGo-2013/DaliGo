# Parte — Max-2 · §3 micro-backlog M15 hecho en rama · 2026-07-14

CUENTA: Max-2 · Forjador B (stream 2) · Opus 4.8 · high
TAREA: dictado v2 §3 — micro-backlog M15 (las 3 observaciones del QA de P-M15-09). Tomado porque despachos sigue bloqueado en la delegación P-DSP-00 (shape de documents.json sin respuesta del server) y era el único ítem ejecutable de mi cola.
ESTADO: HECHA en rama propia `feature/m15-microbacklog` (desde main fresco `c86e341` — no mezclé M15 en la rama de despachos; provenance limpia). Suite 571 verde. Lista para merge trivial cuando dictes.

QUÉ HICE (los 3 ítems):
- **(a) Correo de destino visible en el panel:** la fila de `/admin/notificaciones` mostraba solo el nombre del usuario interno; ahora muestra `nombre · correo` (y si no hay destinatario guardado, cae al email del user). Sin usuario: el destino solo, como antes.
- **(b) Error SMTP sin truncar:** el `ultimo_error` pasaba por `Str::limit(80)` + `truncate`. Ahora es un `<details>` nativo (sin JS, táctil): resumen corto como `<summary>` y el texto ÍNTEGRO expandible (`whitespace-pre-wrap break-words`, fondo `red-50`). Exactamente lo que faltó durante el debugging de P-M15-10.
- **(c) Test de humo endurecido:** `test_campanita_muestra_el_conteo_en_el_nav` ahora assertea también el CONTENIDO del dropdown (título de la notificación) + acciones ("Marcar todas", "Ver todas"), no solo el badge del conteo.

VERIFICACIÓN:
- Test nuevo `test_panel_muestra_el_correo_de_destino_y_el_error_completo` (fixture de error >80 chars; assertea correo + nombre + la cola del error más allá del truncado viejo).
- Suite completa **571 verde** (+1). `view:clear` + `npm run build` + grep del bundle 6/6 (`lg\:flex`, `lg\:hidden`, `.w-80` + las nuevas `whitespace-pre-wrap`/`break-words`/`.bg-red-50`).
- Cero colisión: solo vista del panel M15 + 2 tests M15 (nadie más los toca).

TESTS: 571 verde. /usage: ← Mauricio completa. Sesión en Opus 4.8 ✓.

SIGUIENTE: mi cola queda 100% bloqueada en el shape de P-DSP-00 (delegación al server pendiente de despacho). Con el shape → P-DSP-01 (espejo) → 03 → 04 → 05. El merge de esta rama de micro-backlog es trivial (docs+vista+tests) — cuando dictes la ventana.
