# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-05 (v16 — HOJA DE RUTA EN PRODUCCIÓN; GO P-DSP-09, la PWA sobre la hoja). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## ✅ La hoja de ruta digital está EN PRODUCCIÓN (merge `b9d89a3`, doble llave 05-ago)

Verificación del Director: dos corridas de suite (la primera la ganó un refactor de Marcos
que entró en plena carrera — el escritor Excel compartido; se absorbió y se re-corrió
entera): **1495 verdes / 11.069 aserciones, cero rojos** sobre el árbol final. Deploy y
Tests de CI verdes. Rama borrada tras ancestría.

Tu colisión fina del keyword `'carga'` era exactamente el tipo de defecto que un merge verde
esconde — cazarla ANTES del parte es lo que hace confiable tu doble llave. El hallazgo del
`manifest 3.json` ya está reportado al dueño para el canal directo de Marcos.

Después de tu parte entró también el **informe de devoluciones de Max-1** (`6c91f94`, suite
1501/11.101): E6 quedó COMPLETA. Baseline del día para ti: **1501 / 11.101**.

## 🟢 GO P-DSP-09 — la PWA del conductor SOBRE la hoja (F2 de PLAN-DESPACHOS-V2)

Rama **nueva desde main FRESCO** (main ya tiene tu hoja + el informe + el refactor Excel).
El corazón — es cerrar los huecos que el papel todavía le gana a la app:

- **Dirección + comuna + teléfono visibles por parada** (el hueco más grande: hoy el
  conductor no los ve; vienen de `clientes` vía el documento de la parada).
- **Receptor OBLIGATORIO al confirmar**: nombre + RUT + relación (`empresa|conserje|otro`)
  — las columnas ya existen en `despachos` desde tu F1; ahora se exigen (validador + UI).
- **Cobro en entrega**: parada `cobrar_en_entrega` → método (`efectivo|cheque|transbank`) +
  monto OBLIGATORIOS; parada `pagado`/`credito` → ni se muestran. El monto queda registrado,
  la rendición NO es de este lote (ronda 2 con Luis).
- **Rechazo con motivo** → `resultado=rechazada` + notificación (M15) a jefe de despacho;
  el gancho a M13 Devoluciones ya existe en BD — NO automatices la devolución (es decisión
  del dueño si el rechazo en puerta crea la devolución sola; anótalo como pregunta).
- **Orden pactado** ya lo respetas; **offline**: los campos nuevos viajan en el MISMO
  payload de la cola IndexedDB (uuid + capturado_at intactos) — un solo camino, como tu
  P-DSP-05.
- Candados mínimos: receptor/cobro exigidos según estado de la parada (mutados), el 403 de
  hoja ajena sigue antes de validar, offline con campos nuevos drena idéntico.

## Territorio
- **Max-1** cerró E6 y queda en pausa — sin cruce.
- **Marcos**: M05 + M18 + Excel compartido, MUY activo (ganó una carrera al push hoy).
  Rama corta, re-refresh por-paso, push temprano.

## Recordatorios
Suite COMPLETA antes de cualquier push (baseline HOY: **1501 / 11.101** en main `6c91f94`).
Blade tocado → build + grep superset. Conflictos con `git checkout origin/main -- <archivo>`,
nunca con `>` (BOM de PS 5.1). Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
