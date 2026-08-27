# Parte de Max-1 — 2026-08-21 · Dictado v83, LOG-2 HECHO: la flota avisa cuando el dueño quiera (espera doble llave)

> Forjador A, stream 1 · rama `feature/param-log-2-flota` (commit `15284f0e`,
> nace de `6df3eeae` — la Cabina de Marcos adentro) pusheada. **NO mergeo**:
> doble llave. NO arranco LOG-3.

## Qué quedó forjado (molde DASH-1/OPE-1, 5º uso)

**Clave `vehiculos_dias_aviso`** (default 30 = `Vehiculo::DIAS_AVISO`), leída
por el método vivo **`Vehiculo::diasAviso()`** (clamp ≥1). RANGOS **7-90**
(una semana de aviso mínima con sentido; un trimestre de techo — más que eso
el badge naranjo permanente pierde su urgencia). **Grupo `vehiculos`, no
`logistica`** (letra del dictado pisada con evidencia, declarado): el idioma
del seeder agrupa por apartado/pantalla y el hermano `despachos` ya sentó el
precedente aunque ambos cuelguen del menú Logística.

## Derivaron CINCO rótulos, no tres

Los 3 del mapa (tarjeta del listado, resumen del Excel, comando) **+ 2 que el
mapa no tenía**: `_form.blade.php:167` y `show.blade.php:137` ya derivaban…
**de la CONSTANTE** — con la clave movida habrían mentido igual. Ahora todos
leen `diasAviso()`. Dos matices declarados:

- La **descripción estática del comando pierde su cifra a propósito**: una
  property no puede leer la BD al registrarse el comando — un número ahí
  volvería a mentir. Su mensaje runtime («ningún documento entró en los N
  días») sí deriva.
- El hito del **Plan del proyecto** (`PlanProyecto::MODULOS`) es una `const`
  de PHP — no puede llamar métodos: queda `Vehiculo::DIAS_AVISO` + «(franja
  configurable)», lo honesto posible ahí.

## La hermana `DIAS_VENTANA_VENCIDO = 30`: NIVEL 3, se queda

Contra-evidencia con el código a la vista (doctrina OPE-1): su porqué está
escrito de manual en el propio comando («es deliberado y no una limitación…
la deuda histórica se ve en el listado, que es su lugar») — es un tope de
RUIDO del re-aviso, no una franja que el negocio mire, y no cambia nada
visible en pantalla. Hoy coincide en 30 por accidente. Quedó **candado de
independencia explícito**: con la franja movida a 60, un documento vencido
hace 40 días sigue sin inundar la campanita.

## Candados (+5 en `ParametrosLogisticaTest`, molde pleno + mitad estructural)

BD virgen idéntica (doc a 45 días AL DÍA, a 20 POR VENCER, rótulo «30 días») ·
mover la franja a 60 mueve el badge, el rótulo y **el hito del aviso diario
CON CIFRA** (el doc a 45 días: nada con 30, aviso real con 60) · la ventana de
re-aviso NO se mueve · bordes 7/90 · estructural: los 5 fuentes usan
`diasAviso()` y no queda franja a mano.

## Verificación (invariante, delta 100 % atribuido)

- **Baseline propio** (worktree + robocopy + diagnóstico autoloader, sobre
  `6df3eeae` — recuenta con la Cabina absorbida): **2339 / 16.275**.
- **Rama**: **2344 / 16.321** = baseline **+5 tests / +38 aserciones** (míos)
  **+8** (candados-iteradores del seeder: 566→574, el patrón +8-por-clave se
  sostiene — 3er lote que lo confirma). Cero tests existentes movidos.
- Batería dirigida (Vehiculo* ×5 + FlotaExcel + ParametrosLogistica +
  Configuracion*): **113 / 958 verdes, cero amoldes**.
- **Mutación post-commit** (default 30→45): **1 rojo EXACTO** — solo el
  candado de BD virgen. El dato fino: los candados VIEJOS de la flota
  (VehiculoTest del borde 30/31, FlotaExcel, avisos) quedaron VERDES porque
  siembran el seeder y ya vigilan LA CLAVE — la arquitectura buscada quedó
  demostrada por la propia mutación.
- **Bundle byte-idéntico** (status real vacío).

## Para el radar del Director

- Rama lista: **va la doble llave**. El deploy siembra la clave solo; default
  = histórico.
- Espero dictado para LOG-3 (las listas del conductor + el TV de bodega).
  NO lo arranco solo. Para LOG-4 tomo nota del OJO: baseline fresco y
  re-lectura de líneas post-Cabina (zona compartida-de-hecho con Marcos).

## Fuera de alcance (declarado)

LOG-3/4 (radar) · merge (doble llave) · la cola de funciones · el simulador
(Cabina de Marcos) · territorio de Max-2 · RUTA-MAESTRA/Trello (Director).
