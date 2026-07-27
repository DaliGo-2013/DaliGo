# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-27 (v10 — refresh VERIFICADO; GO P-DSP-04 en firme). Manda sobre lo anterior.

MODELO: Fable 5 si el dueño lo fija (P-DSP-04 es diseño de seguridad y lo justifica); si no, Opus 4.8 · high.

## ✅ Refresh VERIFICADO por el Director (`11f95fe` / parte `017032c`)
Medido de forma independiente: tu rama **automergea LIMPIO contra main** (cero conflictos,
`git merge-tree` sobre `2d88a8e`). Trabajo de calidad en los 9 conflictos — en particular el
**rescate del ítem de menú**: main borró el nav legacy en el paso 4/4 del menú V4 y tú lo
trasladaste a `MenuPrincipal` con su permiso; sin ese traslado Despachos desaparecía de la
navegación y nadie lo habría notado hasta el QA. Bien visto.

Tu recomendación de **refrescar por paso, no por unidad**: ADOPTADA como regla del stream 2.
Es la misma lección que el stream 1 pagó dos veces esta semana (el lote NOTIF-1 quedó 5 días
sin merge y necesitó rework completo; el fix de TZ murió dos veces contra el churn de Marcos).

## 🟢 GO P-DSP-04 — QR anti-fraude de retiro (M07, el corazón de la unidad)
Los 6 puntos del dictado v9 siguen vigentes SIN CAMBIOS (lock + re-check con la fila
bloqueada, `URL::signedRoute` sobre el `codigo DSP-`, superficie de escaneo documentada,
cola «McDonald's» por polling, entrega parcial, y apoyarse en el guard de re-verificación
contra Bsale que ya existe — no duplicarlo). Tus 2 notas del refresh quedan incorporadas:
reusar `dibujarQrsMostrador` y que toda pantalla nueva nazca con los contratos nuevos.

**Contratos nuevos de main — valen para todo lo que escribas de ahora en más:**
1. Ancho por layout (`<x-app-layout ancho="...">`), nunca contenedor propio. Candado:
   `AnchoDePaginaTest` (regex sobre `mx-auto max-w-* px-`).
2. Salida única por el `:back` del `page-header`; nada de `:cancel` en `x-form-footer`.
   Candado: `VolverTest`.
3. Errores amables: **GET** sin permiso → redirect al Inicio con aviso; **POST** conserva el
   403. Asertar en consecuencia (importa para la cola offline del conductor).

## 🎉 CAMBIO DE BASE CONFIRMADO — P-DSP-00..03 EN PRODUCCIÓN (merge `7320bee`, 27-07)
El dueño dio la doble llave y el Director mergeó tu trabajo a main: **espejo de documentos
Bsale + zonas + entidad Despacho con escaneos + panel admin están VIVOS**. Decisión explícita
de romper el plan original (que mergeaba en P-DSP-07): la ventana de merge limpio era hoy y la
semana demostró dos veces el costo de arrastrar ramas.

Verificación del Director antes del push: merge-tree limpio ×2, **suite completa ejecutada
sobre el árbol mergeado — 958 verdes / 4.576 aserciones, exacto a tu parte**, y bundle
`app-DcH-lDk3.css` probado superset del de main (0 selectores perdidos de 474, +1 tuyo).

**→ Arranca P-DSP-04 en rama NUEVA desde main fresco** (`feature/despachos-qr` o el nombre que
prefieras). NO sigas en `feature/despachos-v1`: cumplió su ciclo, ciérrala cuando confirmes
que no te queda nada suelto ahí. Deuda cero, empiezas limpio.

### ⚠️ El merge rompió el deploy dos veces (I-07) — ya resuelto, pero léelo
Tu seeder tumbó el deploy con `SQLSTATE[22001] Data too long for column 'descripcion'`: la
descripción de `documentos_sync_desde` tenía 267 chars. **No es descuido tuyo** — es un gotcha
de paridad: **SQLite no valida el largo de un VARCHAR y MySQL sí**, así que tus 958 verdes
eran legítimamente verdes. Las 5 migraciones sí aplicaron; el deploy abortó en el seeder y la
app siguió respondiendo.

Mi primer arreglo también falló, por asumir `varchar(255)`: **el proyecto fija
`Schema::defaultStringLength(191)`** (AppServiceProvider, por el límite de índice de InnoDB en
MySQL 5.7 con utf8mb4). Todo `$table->string()` de este repo son **191**, no 255. Anótalo:
vale para cualquier columna que crees de aquí en adelante.

Candado nuevo en main: `tests/Feature/ConfiguracionSeedLongitudTest.php` recorre lo que el
seeder deja en la tabla y falla si algún string no cabe, leyendo el límite de
`Builder::$defaultStringLength` en vez de hardcodearlo. Mutación verificada roja. Barrido de
todos los seeders al límite real: 0 violaciones. Deploy `22efc74` **success**, seeders
completos y `/admin/despachos` responde.

**Tu cron ya corre en producción:** `bsale:sync-documents` (`hourlyAt(30)`, ventana de 7 días).
El espejo se está poblando de verdad — a partir de ahora P-DSP-04 puede asumir documentos
reales. Si ves algo raro en `storage/logs/bsale-sync.log` durante tus primeras corridas,
repórtalo al buzón: es su estreno en producción y nadie lo ha visto operar todavía.

## Verificación (reglas de la casa)
Suite COMPLETA por commit. **La baseline de main hoy es 920 verdes / 4.418 aserciones**
(medida por el Director en worktree limpio); tu rama declara 958 — no compares contra números
viejos. Blade/JS → build + grep superset. Worktree propio con `vendor` COPIADO (tu método
funcionó: copiar sí, junction no — el junction hace que PSR-4 resuelva al otro clon).
Parte al buzón → doble llave.

## No es tuyo
- Stream 1 (Max-1): rescate del lote NOTIF-1, dictado v25.
- QA de borde TZ, #6 chips, decisión del ciclo de la factura: dueño.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
