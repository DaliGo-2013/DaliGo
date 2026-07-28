# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-27 (v27 — fin del standby: P-NAV-06 + #6 chips paramétricos, ambos dimensionados). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ NOTIF-1 EN PRODUCCIÓN (merge `601ec7a`, Deploy+Tests success)
La directiva del dueño del 22-07 está viva y visible. Verificación del Director sobre el árbol
mergeado: gates 3/3 (ancla 1+1=2 · build sin mover un byte · seeder bajo 191), cero BOM en los
12 archivos, **suite 989/5.394**, QA staging 4 rutas 302. Tu rebuild salió **byte-idéntico** al
de main — lo que main tenía por el accidente del `@source` de `storage/framework/views` ahora
lo produce el `.blade` por derecho propio, cerrando limpio el matiz que yo había dejado abierto.

**Tu hallazgo del BOM corrigió MI anexo** (`git show ... > archivo` mete BOM en PS 5.1 y
revienta Vite vía manifest, con `git status` limpio). Ya está corregido a
`git checkout origin/main -- <archivo>`. Segunda vez esta semana que un forjador corrige al
Director con evidencia; así funciona el sistema.

Housekeeping: borra `feature/notif-especificas-v2` del remoto (ya mergeada) y la vieja
`feature/notif-especificas` si sigue ahí.

## 🟢 TAREA 1 — P-NAV-06: pantallas huérfanas al menú (S, ~30 min)
Cierra uno de los 2 pasos que le faltan a E-NAV (el otro, P-NAV-05, es gate del dueño, no tuyo).
Rama `fix/nav-huerfanas` desde main fresco. Está especificado en `docs/RUTA-MAESTRA.md:167`:

- **4 líneas de datos en `app/Support/MenuPrincipal.php`**: Máquinas, Tipos de botellón y
  Kardex bajo **Operación**; Conductores bajo **ST**. Cada una con su permiso, igual que el
  ítem `despachos` que Max-2 trasladó ahí en su refresh (úsalo de modelo).
- ⚠️ **Y quitarles el `:back`**: hoy Máquinas / Tipos de botellón / Conductores conservan su
  «Volver» porque estando fuera del menú es su única salida (excepción sancionada en P-NAV-08).
  En cuanto entran al menú, un listado del menú NO lleva Volver.
- **El candado te avisa solo**: `VolverTest::test_los_listados_huerfanos_conservan_su_volver`
  se pone rojo en cuanto agregues los ítems. Eso NO es una regresión: es el test pidiéndote que
  actualices su lista de huérfanos. Actualízalo, no lo silencies.
- Regenerar el bundle de diseño (`DesignCaptureTest` → DesignSync) para que el diseño refleje
  la app real.

## 🟢 TAREA 2 — #6 chips paramétricos del motivo del ajuste (S/M) · DIMENSIONADO
Es el hallazgo #6 del QA 15-07, idea de producto del dueño: **lenguaje común entre solicitante
y aprobador**. Rama `feature/chips-motivo-ajuste` desde main fresco.

**Lo que medí, para que no lo re-descubras:**
- Hoy el motivo del ajuste es **texto libre**: `<x-textarea name="motivo_ajuste">` en
  `resources/views/admin/produccion/reporte.blade.php:271`, validado
  `['required','string','max:255']` en `ProduccionController.php:681`.
- **El componente ya existe y hace todo lo que hace falta**: `<x-reason-chips>` (grilla táctil
  sobre `<x-chip-radio>`, con chip «Otro» que revela un campo de texto como salida de escape).
  Ya se usa en 4 sitios: rechazo de aprobación y los 3 motivos del soplador.
- **Lo único que NO existe es la parametrización**: hoy las opciones vienen de constantes de
  modelo (`Aprobacion::MOTIVOS_RECHAZO`, `ProduccionRegistro::MOTIVOS_DEFECTO`), no de
  Configuración. Ahí está el trabajo real.

**El lote:**
1. Clave nueva en `ConfiguracionSeeder` (tipo JSON, grupo `produccion`), p. ej.
   `motivos_ajuste_produccion`, con una lista inicial sensata. **Ojo con el límite de 191 en
   `descripcion`** — es lo que tumbó el deploy hoy (I-07) y hay candado
   (`ConfiguracionSeedLongitudTest`).
2. Cambiar el textarea por `<x-reason-chips name="motivo_ajuste" :allowOther="true">` leyendo
   esa clave. La validación actual sigue sirviendo (el chip entrega string), pero **verifica
   el camino de «Otro»**: el texto libre viaja en `{name}_otro` según el componente.
3. Editable desde la UI de Configuración, como el resto de las claves.
4. **Alcance: SOLO el motivo del ajuste.** No migres los otros 4 usos de `reason-chips` a
   Configuración en este lote — si el patrón queda bien, se propaga después.

**Verifica el efecto aguas abajo:** `motivo_ajuste` viaja a las notificaciones como `{motivo}`
en las plantillas del lote NOTIF-1 que acabas de poner en producción. Con chips el texto queda
estandarizado, que es exactamente el objetivo del hallazgo — pero confirma que las plantillas
siguen leyéndose bien y que el `{cambio}` no se ensucia.

Orden sugerido: Tarea 1 primero (cierra E-NAV y es corta), Tarea 2 después. **Ramas separadas**,
parte por lote o uno solo si cierras ambas.

## Recordatorios duros (sin cambios)
Suite COMPLETA por commit (**baseline actual: 989 verdes / 5.394 aserciones**). Blade tocado →
main fresco + `npm run build` + grep superset. Asertar por ruta/marcador. Resolver conflictos
con `git checkout origin/main -- <archivo>`, nunca con `>`. Parte al buzón → doble llave.

## No es tuyo
- P-DSP-04 QR anti-fraude: Max-2, ya arrancó en `feature/despachos-qr`.
- P-NAV-05 (gate R-31 formal + QA del dueño en celular), P-TZ-03, decisión del ciclo de la
  factura: dueño.
- Sublote C de notificaciones (payload de cotización/terreno): territorio de Marcos.
- Anotado sin fix, decisión del dueño: la campanita aterriza en la tarjeta puntual pero el
  botón del correo apunta a la lista pelada (`payload['url']` sin ancla, `Aprobaciones.php:303`).

CIERRE: parte a docs/fleet/buzon/partes/ + push.
