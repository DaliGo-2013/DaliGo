# Parte de Max-1 — 2026-08-20 · Dictado v79, OPE-3 HECHO: config de preforma + higiene — la fase B de Operación queda COMPLETA (espera doble llave)

> Forjador A, stream 1 · rama `feature/param-ope-3-config` (commit `89008e12`,
> nace de `a2ab859f`) pusheada. **NO mergeo**: doble llave. Con este lote los
> 4 hallazgos aprobados del mapa §5.3 están forjados — queda el QA del dueño
> del módulo completo.

## Qué quedó forjado

**1. `config/produccion.php` (nuevo — hallazgo #3, nivel 2):** los patrones
`%preforma%` / `%dañada%` salen del controller a config, molde
`servicio_tecnico.categorias_equipo`, con el porqué del nivel 2 comentado en el
archivo (deploy, no caliente: el criterio que decide qué inventario ES preforma
no se mueve a mitad de turno). Defaults = los literales de hoy EXACTOS. Dos
detalles del corte:

- **La doble vuelta en MAYÚSCULAS del gotcha de la Ñ ahora se DERIVA**
  (`mb_strtoupper($patron)`): antes eran dos literales gemelos (`%dañada%` /
  `%DAÑADA%`); un patrón nuevo trae su variante solo.
- **Selector y validación siguen compartiendo la closure**: mover la config
  mueve las DOS puertas a la vez — candado con `config()` sobreescrito en
  runtime que lo prueba por ambos lados (la dañada entra cuando el patrón
  apunta a otra, y la nueva excluida se rechaza en el POST).

**2. Higiene (hallazgos #2, #5, #12 — delta CERO de conducta):**

- `max:100000` ×6 → **`TOPE_CANTIDAD`** con su porqué (guardia anti-dedazo, no
  capacidad de negocio).
- El `92` ×2 de `rango()` → **`MAX_DIAS_RANGO`** — y el tope de render ganó su
  candado: pedir 200 días devuelve 93 filas (92 + el día hasta).
- Los 2 `paginate(25)` del módulo (kardex e inventario de bodega) adoptan
  **`Controller::POR_PAGINA`** (molde COM-2) — candado por `perPage()` de los
  dos paginadores contra la constante del padre.

## Verificación (invariante, delta 100 % atribuido)

- **Baseline propio** (worktree aislado + robocopy + diagnóstico autoloader,
  sobre `a2ab859f`): **2299 / 16.029** — esta vez calza EXACTO con el conteo
  del dictado (`1a45026` + docs).
- **Rama**: **2303 / 16.048** = baseline **+4 tests / +19 aserciones**, 100 %
  míos (sección OPE-3 de `ParametrosOperacionTest`, que cierra en 18 tests /
  167 aserciones — el archivo quedó siendo el espejo del módulo: OPE-1 + OPE-2
  + OPE-3). Sin claves de seeder nuevas → sin el +24 de los iteradores, como
  se predijo. Cero tests existentes movidos.
- Batería dirigida (Produccion* + Bodegas/Kardex + Configuracion*):
  **265 / 1.592 verdes, cero amoldes**.
- **Mutación post-commit** (`TOPE_CANTIDAD` ×1000): **1 rojo EXACTO predicho —
  y es un candado VIEJO** (`test_asignar_rechaza_cantidad_absurda`, su dedazo
  de 10 millones pasaba a ser válido): la prueba de que la constante nueva es
  exactamente lo que ese test ya vigilaba. Restaurado con `checkout --` + grep
  de los 2 marcadores.
- **Sin rebuild**: el lote no toca Blade/CSS/JS (config + controllers + tests).

## Para el radar del Director

- Rama lista: **va la doble llave**. Config default = conducta byte-idéntica;
  el deploy no siembra nada nuevo (config de archivo, viaja con el código).
- Con el merge, **la fase B de Operación queda completa**: 3 ventanas (OPE-1) +
  3 listas con el 4º hermano (OPE-2) + config de preforma e higiene (OPE-3).
  Listo para el QA del dueño del módulo y la card a Terminadas con el titular
  (M11 nació parametrizado — D-003).
- Siguiente del orden: **F0-LOGÍSTICA** — espero dictado, no lo arranco solo.

## Fuera de alcance (declarado)

F0-LOGÍSTICA · merge (doble llave) · los `max:100000` de Mi producción (cross,
se auditan con su apartado) · los otros 13 `paginate(25)` de la app (adopción
módulo a módulo, COM-2) · MenuPrincipal/chat (Max-2) · territorio de Marcos ·
RUTA-MAESTRA/Trello (Director).
