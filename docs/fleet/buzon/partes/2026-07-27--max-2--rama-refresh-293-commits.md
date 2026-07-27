# Parte de cierre — Max-2 · refresh de `feature/despachos-v1` contra main

CUENTA: Max-2 (Forjador B, stream 2 · DESPACHOS-v1) · MODELO: Fable 5 (fijado por el dueño)
TAREA: paso previo exigido por el Director en el tablero del 27-07 — refrescar la rama ANTES de codear P-DSP-04
ESTADO: **HECHA**

## Contexto: por qué existió este paso

El asiento estuvo **congelado 13 días** (última punta `d99ed88`, 14-07 14:49). El dictado v9
(20-07) daba 🟢 GO a P-DSP-04, pero la sesión no se abrió: el dueño **perdió el chat** de este
asiento y el hilo se recuperó hoy desde el buzón (el dictado manda sobre el chat — `buzon/README.md`).
El Director midió la deuda en el tablero: 10 commits propios contra un main que avanzó ~200.
La medición de hoy: **main +293 / rama +10** desde el merge-base `4ef4dd2`.

## EVIDENCIA

- Commit del merge: **`11f95fe`** — `chore(despachos): refrescar rama con main (293 commits) — R-33`
- Aislamiento: worktree propio `.claude/worktrees/max2-despachos` (el clon de `Documents/DaliGo`
  es del Director interino; el tablero advertía "colisión de sesiones en el clon"). Se copió
  `vendor/` y se verificó que **PSR-4 resuelve al worktree** (`App\Models\Despacho` →
  ruta del worktree), no al otro clon: el gotcha del vendor por junction NO aplica al copiar.
- Bundle: **`app-DcH-lDk3.css`**, superset verificado contra el de main (`app-C7eHxgkz.css`):
  583/583 clases de main presentes, +1 propia (`whitespace-pre`). Manifest alineado.
  `package-lock.json` reescribió su `name` al del worktree → descartado (gotcha 2026-07-07).

### Los 9 conflictos, lado por lado

| Archivo | Resolución |
|---|---|
| `config/permissions.php` | AMBOS: labels de main (instalaciones, tiempos reparación) + mis 2 (`manage despachos`, `confirmar entrega`). **Además** grupo `'Despachos' => ['despachos','entrega']` en las categorías nuevas de main: sin él mis permisos caían en "Generales". Verificado que `entrega` no colisiona con ningún permiso de ST/terreno. |
| `app/Models/User.php` | AMBOS: `zona_id` (D-006, mío) + `jefe_id`/`jefe()`/`subordinados()`/`idsCarteraServicioTecnico()` (main). Imports ya traían `BelongsTo` y `HasMany`. |
| `app/Models/Cliente.php` | AMBOS: `zona()`/`zonaEfectiva()` (míos) + `getEsDeBsaleAttribute()`/`buscarPorRut()` (main). |
| `RolesAndPermissionsSeeder.php` | UNIÓN de la matriz: `jefe_bodega` suma `ver todo servicio tecnico` (main) **y** `manage despachos` (mío). |
| `RoleMatrixSeedTest.php` | UNIÓN espejo: admin suma mis 2; `conductor` suma `confirmar entrega`; se conserva `tecnico_industrial` de main. |
| `routes/web.php` | AMBOS imports (`DespachoController` + `InstalacionController`). Rutas de despachos intactas (verificado). |
| `docs/RUTA-MAESTRA.md` | **MAIN manda**: es doc de estado, no de código — la versión de main (26-07) ya lista esta rama entre las abiertas; la mía era del 13-07. |
| `navigation.blade.php` | **Borrado ACEPTADO** (main retiró el nav legacy en `99f9a1b`, menú V4 paso 4/4). Mi aporte se **trasladó** a `app/Support/MenuPrincipal.php` → ítem `despachos` en el módulo Operación con `permiso: manage despachos`. Sin este traslado, Despachos desaparecía del menú. |
| `public/build/manifest.json` | Regenerado con `npm run build`, jamás a mano (R-33). |

### Lo que el refresh compró: 4 tests rojos por contratos nuevos de main

Ninguno era bug de producción; los 4 eran mi código del 14-07 contra reglas que main endureció después:

1. **Errores amables** (`bootstrap/app.php`, familia de errores 500/403): un **GET** autenticado sin
   permiso ya NO devuelve 403 seco — redirige al Inicio con aviso. Mis 2 tests de `DespachoTest`
   esperaban 403 y recibían 302. Alineados a `assertRedirect(dashboard) + assertSessionHas('aviso')`
   **sin bajar la vara** (siguen probando que no entra), y el **POST conserva el 403 real**
   (contrato del caso 6 del handler — importa para la cola offline).
2. **Ancho de página centralizado** (`AnchoDePaginaTest`): las vistas no declaran contenedor propio →
   `create.blade.php` pasó a `<x-app-layout ancho="formulario">`, `index` sin `mx-auto max-w-7xl`.
3. **Doctrina del botón único** (`VolverTest`, 24-07): fuera `:cancel` de `x-form-footer`; la única
   salida del formulario es el `:back` del `page-header` (agregado, "Volver a despachos").

TESTS: **958 verdes** (4576 assertions), suite COMPLETA (`php artisan test`, no subset).
Línea base propia medida ANTES del merge en el worktree: **630 verdes** — igual a lo reportado en P-DSP-03, lo que confirma que el worktree estaba sano.

/usage INICIO → FIN: no comparable — sesión heredada del hilo de diseño del dueño, no una sesión limpia de asiento.

## SIGUIENTE

**P-DSP-04 — QR anti-fraude de retiro** queda desbloqueado y con la rama al día; el choque de
bundles que advertía el dictado v9 ya no está por delante (queda pagado antes de escribir código nuevo).
Los 6 puntos del dictado siguen vigentes sin cambios; dos notas del refresh para cuando se ejecute:

1. El QR debe reusar `dibujarQrsMostrador` (M12) — sigue en el bundle, verificado.
2. La cola "McDonald's" polling y toda pantalla nueva **nacen** con los 3 contratos nuevos
   (ancho por layout, salida por `:back`, y ojo con el handler de errores amables al asertar
   permisos: GET→redirect, POST→403).

Recomendación al Director: mientras la unidad siga sin mergear, refrescar la rama **por paso**, no
por unidad — 13 días costaron 4 tests y 3 migraciones de convención; 30 días habrían costado un rework.
