# Parte — Max-1: main movió el terreno (agenda FUSIONADA) → Tarea 1 rehecha (v2) + render refrescado + auditoría TZ del código nuevo · 2026-07-21

> De Max-1 (Forjador A) al Director. Sin dictado nuevo en el buzón (sigue v20); esto es la
> puesta al día de mis dos ramas tras los 5 merges de Marcos de anoche, que cambiaron el
> terreno DESPUÉS de mi parte anterior (`b5377ad`).

## Qué cambió bajo mis pies

Marcos mergeó: franjas 2h, **fusión calendario→index** (borró `calendario.blade.php`;
`calendario()` quedó como redirect), multi-día/viajes, conductores CRUD y seguimiento sin
repuesto. Efectos sobre el dictado v20:
- Mi `fix/tz-calendario-agenda` (`d315a09`) quedó **obsoleta**: editaba un Blade que ya no
  existe. La **eliminé del remoto** para que nadie la mergee por error (habría conflicto
  modify/delete; el registro queda en este parte y en el anterior).
- La vista fusionada **adoptó `esHoy` en las cabeceras de día** (Marcos tomó el patrón ✓)
  pero **la grilla del calendario resucitó el `isToday()` UTC** (línea 91) — el mismo sitio
  que el dueño autorizó, en el archivo nuevo.

## Rama 1 (rehecha) — `fix/tz-calendario-agenda-v2` @ `5dba30d`

- **1 línea**: `index.blade.php:91` `$d->isToday()` → `FechaNegocio::esHoy($d)`. Es el
  ÚNICO sitio sobreviviente del dictado: los defaults año/mes del index ya venían de
  `FechaNegocio` (P-TZ-01) y el `diaSel` desapareció con la fusión.
- Test de frontera adaptado a la vista fusionada: celdas `#dia-{iso}` como marcador estable
  (día chileno en negrita, día UTC no), **mutación verificada ROJA**, + frontera de MES
  fijando el default del index contra regresiones (el controller está en churn activo).
- Suite completa **696 verdes (2.236 aserciones)**. Bundle `app-BORCJ-Y7.css`, superset
  **24/24** — amplié la lista con 6 clases de la vista fusionada de Marcos
  (`lg:col-span-5/7`, `lg:sticky`, `scroll-mt-6`, `target:ring-2`, `lg:grid-cols-12`)
  para que el gate también proteja su layout de ahora en adelante.

## Rama 2 (refrescada) — `fix/tz-render` @ `22560e7`

Merge de `origin/main` (conflicto SOLO en manifest → **regenerado**, jamás a mano; cero
solapamiento de código con lo de Marcos). Suite completa en el árbol mergeado:
**700 verdes (2.243 aserciones)**, superset 24/24. El bundle mergeado es el MISMO
`app-BORCJ-Y7.css` de la rama 1 (byte a byte: ninguno de los dos lotes agrega clases) →
el segundo merge a main entra limpio en `public/build/`.

## Auditoría TZ del código nacido post-inventario (workflow, 2 fases: buscar → refutar)

Los 5 merges de Marcos nacieron DESPUÉS del inventario del PLAN-TIMEZONE, así que corrí el
gate de la familia completa sobre todo el rango `b4f0019..7f8b0fc` (agenda multi-día,
franjas, `bloquearSiOcupado`, conductores, lote, visita, seguimiento) con verificación
adversarial por hallazgo. Resultado: **el código nuevo de Marcos está notablemente limpio**
— 1 solo hallazgo confirmado, severidad BAJA:

- **`AgendaTrabajoController::update():151`** — `$destino = $trabajo->fecha ?? now();`
  alimenta el redirect `['anio' => …, 'mes' => …]`. Alcanzable solo al editar una solicitud
  QR **aún sin fecha** (validateData exige fecha solo si estado ≠ solicitado). Escenario:
  31-07 a las 21:30 de Chile, la vendedora guarda la coordinación de datos → redirect a
  `?anio=2026&mes=8` y la agenda abre AGOSTO (los query params puentean el default ya
  corregido del index). **NO lo toqué**: está fuera de los sitios listados por la excepción
  («NADA más — es de Marcos»). Fix de 1 línea listo para dictar:
  `$trabajo->fecha ?? \App\Support\FechaNegocio::ahora()`.

## Estado para la doble llave

| Rama | Commit | Suite | Espera |
|---|---|---|---|
| `fix/tz-calendario-agenda-v2` | `5dba30d` | 696 ✔ | doble llave |
| `fix/tz-render` | `22560e7` (mergeada con main de hoy) | 700 ✔ | doble llave |

P-TZ-03 (QA de borde del dueño ~21:30) queda aún más completo con ambas: día correcto en
toda la app + calendario resaltando el día real + horas chilenas en filas nuevas. El
hallazgo `update():151` queda a decisión del Director (dictado a Marcos o excepción).
