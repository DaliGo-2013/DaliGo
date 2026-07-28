# Parte — Max-1: dictado v27 COMPLETO — P-NAV-06 + #6 chips, 2 ramas esperan doble llave · 2026-07-28

> De Max-1 (Forjador A) al Director. Ambas tareas en una sesión, ramas separadas como se
> ordenó. Ninguna toca `public/build` (los DOS bundles salieron byte-idénticos a main).

## Tarea 1 — P-NAV-06 · `fix/nav-huerfanas` @ `bc01f53`

- **4 ítems nuevos en `MenuPrincipal`**: Kardex + Máquinas + Tipos de botellón bajo
  Operación (`manage production`, el permiso de sus rutas) y Conductores bajo ST
  (`manage servicio tecnico`, el de su grupo en routes). Modelo `despachos`, como dictaste.
- **4 Volver quitados, no 3**: el dictado listaba Máquinas/Tipos/Conductores, pero el
  **Kardex también tenía `:back`** y `test_ningun_item_del_menu_lleva_volver` lo habría
  cazado al entrar al menú. Mismo tratamiento.
- **El candado avisó como predijiste** y se actualizó sin silenciarlo:
  `test_los_listados_huerfanos_conservan_su_volver` → `test_las_ex_huerfanas_estan_en_el_menu`
  (vigila el sentido inverso: si salen del menú, necesitan su Volver de vuelta). El Kardex
  salió además de la lista de «pantallas hijas» del candado central.
- **Detalle documentado**: el patrón del Kardex es exacto y ADEMÁS lo cubre el
  `admin.produccion.*` del ítem Producción → en esa página quedan resaltados ambos ítems.
  Aceptado (el kardex ES parte de Producción); comentario en la fuente.
- Suite completa **989 verdes / 5.404 aserciones**.
- 🔴 **Sub-paso BLOQUEADO, no ejecutado: el bundle de diseño.** `DesignCaptureTest` **no
  existe en main** (cero hits fuera de docs; tampoco hay carpeta de bundle ni skill). El
  mecanismo vive en la sesión de rediseño (`design/menu-talana`, el clon del dueño). No lo
  inventé desde cero — sería infraestructura nueva no dictada y colisión probable con esa
  sesión. Opciones: (a) esa sesión regenera su bundle post-merge, o (b) me pasas el
  mecanismo por dictado y lo corro.

## Tarea 2 — #6 chips del motivo del ajuste · `feature/chips-motivo-ajuste` @ `ea88fe6`

Tu dimensionamiento fue exacto: el componente ya hacía todo; el trabajo real era la fuente.

- **`motivos_ajuste_produccion`** (TIPO_JSON, grupo `produccion`) con 4 motivos iniciales
  del dominio; descripción **152/191** (candado I-07 verificado en la batería).
- **Textarea → `<x-reason-chips :allowOther="true">`** leyendo la clave con saneo
  `is_string`: una edición manual rota degrada a solo-«Otro» sin reventar el form (test).
- **Centinela `__otro__` resuelto ANTES de validar** (patrón bitácora): texto libre viaja,
  vacío cae al `required`.
- **Editable por UI sin trabajo extra** (TIPO_JSON ya tenía textarea+validación en
  Configuración) — test de integración por el endpoint real: edita la clave y el chip
  nuevo aparece / el viejo desaparece.
- **Aguas abajo (lo que pediste confirmar), con hallazgo y fix**: el `{cambio}` de las
  plantillas NOTIF-1 **sí duplicaba el motivo** (`Motivo_ajuste: — → X`, redundante con la
  línea `{motivo}`) — excluido del diff en `describirCambio()` con comentario; test
  positivo+negativo (trae `Asignadas: 100 → 150`, no trae `Motivo_ajuste`). Las plantillas
  se leen bien: el motivo estandarizado llega vía `{motivo}` tal cual.
- **Alcance respetado**: SOLO el motivo del ajuste; los otros 4 usos de `reason-chips`
  siguen en constantes de modelo.
- `MotivoAjusteChipsTest` (7) + los 12 tests vecinos verdes. Suite completa
  **996 verdes / 5.425 aserciones**.

## Para la doble llave

| Rama | Commit | Suite | Artefactos |
|---|---|---|---|
| `fix/nav-huerfanas` | `bc01f53` | 989/5.404 ✔ | bundle byte-idéntico a main |
| `feature/chips-motivo-ajuste` | `ea88fe6` | 996/5.425 ✔ | bundle byte-idéntico a main |

Son independientes entre sí (cero archivos compartidos); cualquier orden de merge sirve.
**Sellos de RUTA-MAESTRA (P-NAV-06 [x] y el hallazgo #6 del backlog QA) van post-merge**,
como el housekeeping de P-TZ (dictado v23) — no antes, la regla de oro exige evidencia.

## Consumo

Sesión v27: talla **M** (2 lotes S + 3 suites completas + sin workflow — el
dimensionamiento del dictado hizo innecesaria la exploración). `/usage`: Mauricio, cuando
puedas.
