# Parte — Max-1 · Verificación post-deploy de M14 en producción (14-07)

> De: Max-1 (Forjador A) · Para: Director / Mauricio
> Contexto: «revisa tu buzón y ejecuta» con dictado v4 vigente. Al abrir encontré la
> **Fase B ya ejecutada por el Director** (merge `69a93a2` a main, 14-07 09:10, doble llave
> Director+Mauricio; tablero actualizado en `c382c41`). Lo ejecutable que me quedaba del
> paso: **verificación independiente post-deploy** — la vigilancia que protege el QA de la
> demo — y este parte. El QA staging desde el celular sigue pendiente (guion abajo).

## Verificado ✅

1. **Actions verdes (4/4):** Tests #152 y "Desplegar a producción" #244 sobre el merge
   `69a93a2` → ambos `success`; Tests #153 y Deploy #245 sobre `c382c41` → ambos `success`.
2. **Migración + seeders corrieron:** el Deploy #244 en `success` implica exit 0 de
   `migrate --force` (dentro de `deploy.sh` — si el DDL falla, el job queda rojo, como el
   2026-06-30) **y** del `db:seed --force` a nivel workflow (fix del deploy.sh congelado).
   `DatabaseSeeder` de este merge incluye `ConfiguracionSeeder` (umbral 50 / escala 30 min)
   y `ReglasAprobacionSeeder` (regla `produccion.ajuste_reporte` → rol_aprobador `admin`).
3. **El servidor sirve el árbol nuevo (HTTP público a staging.impdali.cl):**
   - Bundle en el HTML del login: `app-BzrcTguX.css` + `app-D4WiVrDO.js` — **el mismo
     bundle verificado en Fase A** (grep 6/6).
   - Rutas M14 registradas: `/aprobaciones` → 302, `/aprobaciones/mias` → 302,
     `/admin/aprobaciones` → 302 (redirect a login; ni 404 ni 500 → route:cache OK).
4. Local de Max-1 sincronizado: `main` en `c382c41`.

## No verificado directamente (transparencia)

- **Lectura directa de la BD de prod** (regla activa + claves `umbral_ajuste_produccion_unidades`
  / `aprobacion_escala_minutos`): mi intento de SSH solo-lectura fue bloqueado por el
  guardrail de permisos de la sesión (lectura remota no autorizada explícitamente en este
  turno). Evidencia indirecta suficiente: seed exit 0 + contenido fijado por
  `AprobacionesSchemaTest` en CI. Cinturón extra si se quiere antes de la reunión: 1 línea
  de tinker por SSH — o la propia demo lo prueba (si la regla faltara, el ajuste se
  aplicaría al tiro **sin** campanita → señal inmediata).

## Lo que falta para cerrar E2·M14

**QA staging desde el celular de Mauricio** → yo marco P-M14-07 [x] en RUTA-MAESTRA
(+ sello de hashes 01→07) → **E2·M14 CERRADA**.

### Guion del QA (3 pasos, ~5 min)

1. **El ajuste lo pide un NO-admin** (clave del guion: la regla aprueba `admin`; si el
   ajuste lo hace un admin, se auto-aprueba al tiro y no hay campanita — correcto, pero no
   demuestra el flujo). Entrar como **jefe de bodega** → Producción → un reporte → Ajustar
   → cambiar cantidades con **Σ|Δ| ≥ 50** (ej. asignadas +60) + motivo → guardar.
   *Esperado:* flash «quedó pendiente de aprobación» y el reporte **sin** cambios aún.
2. **En el celular, como admin:** campanita con la notificación (+ correo al admin).
   Abrir **Aprobaciones** desde el teléfono → tarjeta con el Δ y el motivo → **Aprobar**
   (1 tap). *Esperado:* «Solicitud aprobada y aplicada.»
3. **Verificar aplicado:** el reporte quedó con los valores nuevos; la solicitud figura
   **Aprobada** en `/admin/aprobaciones` (historial con filtros) y en «Mis solicitudes»
   del jefe; la transición visible en `/admin/auditoria`.

— Max-1
