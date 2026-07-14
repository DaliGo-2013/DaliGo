# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-14 (v6 — P-DSP-01 verificado, GO P-DSP-03 confirmado). Manda sobre lo anterior.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño usarlo); si no, Opus 4.8 · high.

## ✅ P-DSP-01 VERIFICADO por el Director (spot-checks propios sobre `a3b5a39`)
Migración con estados INT ✓ · `cliente_id` nullable tolerante a boletas ✓ · guard
whereNotIn-vacío con doble condición (`$lineas !== [] && $vistos !== []`) ✓ · `hourlyAt(30)`
en routes + fijado en ScheduleBsaleTest ✓ · los 4 ajustes del shape aplicados ✓. El watermark
persistente con avance solo-sobre-tramo-completo y el tramo máximo de 30 días son diseño de
primera — mejor que lo que pedía el plan. El hallazgo de los fakes que no honraban
`emissiondaterange` es exactamente el tipo de bug-invisible que la review adversarial debe
cazar: bien usada. P-DSP-00 [x] y P-DSP-01 [x] aceptados.

## 🟢 GO P-DSP-03 confirmado (entidad Despacho + escaneos + panel admin)
Tal como propusiste. Recordatorios del plan + lo nuevo de tu propia review:
1. **Requisito NUEVO (tuyo, ahora obligatorio):** `crearDesdeDocumento` re-verifica el doc
   puntual contra Bsale ANTES de crear el despacho (cancellation_status local puede estar
   stale >1 día — no despachar un DTE anulado). Con test.
2. `$table = 'despachos'` fijado a mano (pluralizador) + código `DSP-` impredecible
   (patrón `OrdenServicio::generarCodigoUnico`).
3. `escaneos_despacho` append-only (sin updated_at); `validarRetiro` bajo `lockForUpdate`
   con re-check (patrón bitácora [2026-06-30]).
4. Superficie compartida (routes/nav/seeder/RoleMatrix/AuditController): bloques SIEMPRE al
   final — M12 (Marcos) sigue empujando a main (hoy: recepción "en ruta" + rebuild de
   bundle, verificado sin interferencia con dashboard). Refresca la rama con main antes de
   tocar compartidos.
5. Permisos nuevos `manage despachos` / `confirmar entrega`: aditivos en los 3 puntos +
   RoleMatrixSeedTest.
6. Panel admin responsive 375/768/1024; si tocas Blade/JS/CSS → npm install (qrcode) +
   build + grep del bundle (incluye las 6 clases del dashboard M16 — ahora también son
   críticas: `lg\:grid-cols-5`, `sm\:grid-cols-3`, `min-w-\[1.5rem\]`, `bg-white\/60` + las
   `lg\:flex`/`lg\:hidden` de siempre).

Pendiente del dueño (no te bloquea): fecha de arranque del espejo (`documentos_sync_desde`).
Default 7 días corre al primer run post-deploy.

CIERRE por paso: parte a docs/fleet/buzon/partes/ + push.
