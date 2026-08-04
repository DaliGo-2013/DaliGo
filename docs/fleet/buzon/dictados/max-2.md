# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-04 (v15 — P-DSP-08 RECIBIDO, las 4 micro-decisiones aceptadas; re-refresh contra el main con M13 y doble llave). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## ✅ P-DSP-08: parte RECIBIDO — y tus 4 micro-decisiones quedan ACEPTADAS

El lote es exactamente lo que el plan pedía y las llamadas a criterio están bien tomadas:

1. **FK blanda + snapshot de vehículo**: correcta — tenías razón contra el dictado; el
   `destroy()` físico de M18 rompería una FK dura, y congelar ppu/nombre es el patrón de la
   casa. `patente` a 12 por el ancho real de `vehiculos.ppu`: bien visto.
2. **Receptor nace en F1, se exige en P-DSP-09**: mismo criterio que `estado_cobro`. ✓
3. **La salida la registra `autorizar carga`**: ✓. Tu pregunta («¿se bloquea la salida con
   paradas sin escanear?») **va a la ronda 2 con Luis** — el Director la suma a esa lista.
4. **`despacho_id` unique global**: la regla dura correcta hoy; si la operación real pide
   otra cosa con los rechazados re-despachados, será enmienda declarada de P-DSP-09/10.

El mapa declarativo `TRANSICIONES` con su candado de forma, la matriz cruzada de llaves, y
`salirARuta` escribiendo el `EN_RUTA` que todos leían y nadie escribía — nivel de gate.

## 🔴 Paso 1 — re-refresh: M13 ENTRÓ A MAIN DESPUÉS de tu push (estás ~15 atrás)

El lote 1 de Devoluciones de Max-1 se mergeó hoy (`7750951`) y **choca con lo tuyo justo
donde esperas**: la matriz de roles (M13 sumó `manage devoluciones` a 2 jefaturas + 2
permisos; tú traes el rol 12º `jefe_despacho` + 3 permisos), `config/permissions.php`,
`RolesAndPermissionsSeeder`, `MenuPrincipal`, `ConfiguracionSeeder`, `aprobaciones/index` y
el bundle de siempre. Nada de eso te asusta a estas alturas:

1. Merge de main en `feature/hoja-de-ruta`; resuelve la matriz DECLARANDO la unión (el
   candado `RoleMatrixSeedTest` exige exactamente eso); manifest con
   `git checkout origin/main --` + rebuild + superset.
2. **Suite COMPLETA sobre el árbol mergeado** (baseline de main hoy: **1425 / 10.765**;
   la tuya subirá con tus 37).
3. Parte CORTO (cifra + qué chocó + cómo). Verifico y pido la llave del dueño el mismo día.

## 🟢 Después de la llave: P-DSP-09 — la PWA sobre la hoja (GO condicionado al merge)

Rama nueva desde main fresco con tu hoja adentro. El corazón (PLAN-DESPACHOS-V2 §3):
- El conductor ve por parada **dirección + comuna + teléfono** (hoy no los ve — el hueco
  más grande vs el papel).
- **Receptor obligatorio** al confirmar: nombre + RUT + relación (empresa|conserje|otro).
- **Cobro en entrega**: si la parada es `cobrar_en_entrega`, método (efectivo|cheque|
  transbank) + monto obligatorios.
- **Rechazo con motivo** → `resultado=rechazada` (el gancho a M13 que ya existe en la BD).
- Orden pactado ya lo respetas desde P-DSP-08; offline igual que tu PWA actual.

## Territorio
- **Max-1** arranca P-M13-04 (reportes de devoluciones) — comparte matriz de roles contigo
  SOLO vía main; el que llega segundo re-declara la unión.
- **Marcos**: M05 + M18. La FK blanda a `vehiculos` es tu única frontera con él — si M18
  cambia su modelo, lo tuyo sobrevive por el snapshot (por diseño).

## Recordatorios
Suite COMPLETA antes de cualquier push. Blade tocado → build + grep superset. Conflictos con
`git checkout origin/main -- <archivo>`, nunca con `>` (BOM de PS 5.1). Parte al buzón →
doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
