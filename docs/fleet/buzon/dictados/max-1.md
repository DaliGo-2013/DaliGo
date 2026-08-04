# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-03 (v33 — VISTO BUENO del dueño al PLAN-M13; re-sellado primero, migraciones después). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (construcción de módulo nuevo; xhigh solo si algo se resiste).

## ✅ VISTO BUENO del dueño al PLAN-M13 (03-ago) — puedes construir

El dueño aprobó el plan tal como lo sellaste, **con la condición que tú mismo pediste**: el
re-sellado va ANTES de la primera migración. Tus 3 decisiones de alcance consultadas quedan
firmes: **kardex local propio** (patrón M11), **fotos en los dos momentos** (cliente y
bodega), **transportista + N° de seguimiento dentro**. La enmienda de E6 ya está aplicada
desde tu parte anterior.

Tu parte del 30-07 fue exactamente lo que un plan-sin-código debe ser: las 18 citas
verificadas una por una (y la corregida documentando la colisión de vocabulario vale más que
la original), la confesión de la suite descartada incluida. Esa confesión es la razón por la
que tu cifra vale.

## 🔴 Paso 1 — re-verificar y RE-SELLAR el plan contra el main de HOY

Tenías razón: el sello quedó viejo. Y main corrió MÁS desde tu parte — al 03-ago entraron
además: página `/plan` (P-PLAN-01..03, parsea el tracker §10), «Sin solución» del taller,
tarjetas de permisos, fixes de borde de mes, **idioma-espanol** (paginación y nombres de campo
en español) y **PLAN-DESPACHOS-V2** (hoy, del Director — toca DECISIONES y RUTA-MAESTRA §E8,
no tu territorio, pero re-verifica que ninguna cita tuya apunte a línea movida).

- Re-verifica las 18 citas del §0 (especialmente `publico/taller/create*.blade.php`, el molde
  de P-M13-01 que sabías tocado).
- Re-sella con fecha nueva. Si algo cambió de fondo, dilo en el parte del cierre — no hace
  falta parte separado por el re-sellado.
- **La baseline de suite la fija el main del día** (tu 1273 ya está vieja).

## 🟢 Paso 2 — construir el lote 1 (con el plan re-sellado)

Orden del propio plan: primera migración recién tras el re-sellado.

- **P-M13-01** · formulario público del cliente: ruta sin auth con **token firmado GET y
  POST** (la variante endurecida, como tu plan cita) + fotos obligatorias con compresión en
  navegador + **throttle propio del grupo M13** (tu riesgo declarado del `throttle:6,1`
  compartido: aprobado, resuélvelo).
- **P-M13-02** · categorización transporte/fábrica/otro + reglas por tipo y origen.
- **P-M13-03 según el plan aprobado** · reembolso vía M14 (`monto` nunca null; recuerda que
  `solicitar()` aplica inline bajo el umbral) + **kardex local `devolucion_movimientos`** +
  estado «apta para reingreso» + aviso M15 (claves nuevas por seeder `firstOrCreate`, sin
  one-shot). El empuje a stock real sigue fuera (M04/D-003 — Luis está trabajando las bodegas
  AHORA, no te cruces con eso).
- **P-M13-04** (reportes) sigue fuera de este lote.

### El dato de cPanel — LLEGÓ el mismo 03-ago (evidencia: `docs/qa/INFRA/2026-08-03--INFRA--limites-upload-user-ini-litespeed.md`)
**LiteSpeed SÍ honra el `.user.ini`.** Valores WEB efectivos verificados por phpinfo en el
dominio (Server API LiteSpeed V8.3): `upload_max_filesize` **12M** · `post_max_size` **30M** ·
`memory_limit` **256M** · `max_file_uploads` **20** (default real, no fijado). Los cuatro
idénticos a lo esperado; el contraste CLI (512M/516M) confirma que la diferencia viene del
archivo. **Fija el validador con certeza, ya no como asunción**: 12M por archivo comprimido
(la compresión en navegador sigue obligatoria), el request completo con holgura bajo 30M, y
tope duro de fotos muy por debajo de 20. El punto único de configuración queda igual — ahora
con el techo real documentado al lado.

## Territorio
- **Marcos en M05** — ni de refilón. El MEDIO de «Ver documento» sigue en canal directo del dueño.
- **Max-2 en despachos**: re-refresh de su PWA (doble llave inminente) y luego P-DSP-08 (hoja
  de ruta digital, PLAN-DESPACHOS-V2 que entró hoy). M13 no comparte archivos con despachos;
  ante un cruce raro (p. ej. notificaciones), gana el primero en main y el otro rebasea.
- **Luis está limpiando las bodegas en Bsale (D-003 EN CURSO)** — nada de M13 debe asumir
  estructura de bodegas todavía; el kardex local existe justo para eso.

## Recordatorios
Suite COMPLETA antes de cualquier push (baseline = main del día). Blade tocado → build + grep
superset. Conflictos con `git checkout origin/main -- <archivo>`, nunca con `>` (BOM de PS 5.1
revienta Vite). Fotos: nunca la regla `image` (HEIC), compresión en navegador obligatoria.
Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
