# Parte de Max-1 — 2026-08-21 · MIPROD-1 HECHO: los motivos del soplador se parten por calidad (PEDIDO DIRECTO del dueño — espera doble llave)

> Forjador A, stream 1 · rama `feature/param-miprod-1-motivos` (commit
> `ecdabc79`, nace de `30c147db`) pusheada. **NO mergeo**: doble llave. Lote
> FUERA de la cola dictada — origen declarado abajo; LOG-3 sigue intacto y
> NO lo arranco.

## Origen: pedido directo del dueño (21-08, pantalla en mano)

El dueño mandó captura de la pantalla de tandas del soplador con dos pedidos:
(1) al marcar SEGUNDAS «solo debería estar la opción de detalles estéticos»;
(2) los campos de motivos «deberían ser paramétricos». Le hice DOS preguntas
de precisión y una de ACOPLE: eligió un solo chip para segundas, podar
«Scrap de arranque» de las malas, y — avisado de que ese motivo alimenta el
desglose de scrap del informe OEE — **decidió quitarlo igual** (decisión
informada, registrada acá y en el código).

## Qué quedó forjado

**Dos claves TIPO_JSON** (LISTAS_SIMPLES, grupo `produccion`):
`produccion_motivos_segunda` (default `['Detalles estéticos']`) y
`produccion_motivos_malas` (default = los 9 de hoy sin «Scrap de arranque»),
con métodos vivos `motivosSegunda()`/`motivosMalas()`. La antigua
`MOTIVOS_DEFECTO` compartida se retira con sus 4 consumidores migrados
(chips ×2 + Rule::in ×2 — censo de consumidores a lo bitácora 26-08).

**OJO declarado — acá NO rige la regla de oro**: el dueño pidió conducta
NUEVA, así que los defaults nacen con ella (no con la histórica). Lo que sí
es invariante y quedó con candado: las tandas históricas conservan su motivo
(persistido por fila — la pantalla muestra «2ª: Material quemado» legado tal
cual) y el desglose de scrap del OEE sigue leyendo el string legado
(comentario en `Oee.php` lo declara; reponer el motivo en la clave lo
reactiva hacia adelante).

## Candados y amoldes

- **4 candados nuevos** (`ParametrosMiProduccionTest`, archivo espejo del
  apartado): la conducta nueva con BD virgen por la **forma contigua de los
  chips** (los motivos tienen gemelos de texto en otros forms de la misma
  pantalla) + validación por las dos calidades · mover una lista mueve chips
  y validación y NO la hermana · legado histórico + scrap del OEE · UI
  una-por-línea.
- **Amoldes DECLARADOS** en `ProduccionTest` (la conducta la cambió el
  dueño): 2 líneas del helper `agregarTanda` (el motivo válido por defecto
  ahora sale de la lista de su calidad) + 5 payloads con `motivo_segunda`
  que dejó de ser de segunda. Cero cifras cambiadas.
- **Mutación post-commit** (el error tentador: `motivosSegunda()` leyendo la
  lista de malas — volver a compartir): **2 rojos EXACTOS predichos** (la
  conducta BD-virgen y el mover-la-lista) → restaurada + grep del marcador.

## Verificación (invariante, delta 100 % atribuido)

- **Baseline propio** (worktree + robocopy + diagnóstico autoloader, sobre
  `30c147db`): **2404 / 17.121** — main creció +60 tests desde LOG-2 (matriz
  de audiencias, selector de soplador…), absorbidos.
- **Rama**: **2408 / 17.166** = baseline **+4 tests / +29 aserciones** (míos)
  **+16** (candados-iteradores del seeder: 2 claves × 8 — 4ª confirmación del
  patrón). Cero tests existentes movidos más allá de los amoldes declarados.
- Batería dirigida (Produccion* completo + Parametros* + Configuracion*):
  **230 / 1.646 verdes**.
- **Bundle byte-idéntico** (status real vacío).

## Para el radar del Director

- Rama lista: **va la doble llave** (el dueño ya dio la suya en el chat —
  literalmente dictó la conducta — pero el protocolo es el protocolo).
- El anexo del plan: este lote abre de facto el apartado «Mi producción»
  (que el F0-OPERACIÓN dejó anotado como cross). Quedan de ese cross:
  `MOTIVOS_DIFERENCIA`/`NOTAS_COMUNES` (candidatas LISTAS_SIMPLES), los 45
  días del historial y sus `max:100000` — para cuando se dicte su F0/lote.
- Vi el aviso de baseline a LOG-3 en `56186dc7` (selector de sopladores,
  clave `produccion_roles_soplador`) — tomado. Espero dictado para LOG-3.

## Fuera de alcance (declarado)

LOG-3/4 (radar) · merge (doble llave) · el resto del cross de Mi producción ·
territorio de Max-2 y Marcos · RUTA-MAESTRA/Trello (Director).
