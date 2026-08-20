# Parte de cierre — Max-2 · MSG-1 · Backend del chat interno (sin UI)

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **MSG-1** — GO del dictado v26 (PLAN-MENSAJES §5.7, primer lote)
ESTADO: **HECHA** — pide doble llave
/usage INICIO → FIN: n/d (lo captura el dueño)
SIGUIENTE: doble llave de `feature/msg-1-backend` → GO MSG-2 (pantallas) cuando se dicte.

## EVIDENCIA

Rama **`feature/msg-1-backend`** desde main fresco (`1edbc8e`), pusheada — 1 commit
de código (el lote ES una unidad backend), suite COMPLETA verde:

| Commit | Qué | Suite |
|---|---|---|
| `38b58da` | Migración conversaciones+mensajes · modelos · Mensajeria (envío bajo lock + RÁFAGA) · evento 37º `mensaje.recibido` + plantilla + los dos match · permiso `usar mensajes` en los 12 roles · 13 tests | **2221 / 15.470** (baseline 2208 / 15.430 — delta EXACTO +13 tests) |

**Los candados del dictado, verificados:**
1. Par canónico: `entre(A,B)` ≡ `entre(B,A)` misma fila; el unique aguanta el insert
   directo (QueryException). **MUTADO**: canonicalización cegada (sin min/max) →
   rojo exacto → `git checkout --` → verde.
2. Conmigo-mismo rechazado (InvalidArgumentException en `entre()`).
3. Enviar: mensaje + contador del OTRO +1 + `ultimo_mensaje_at` movido + MI contador
   intacto — todo bajo lockForUpdate de la fila de la conversación.
4. **RÁFAGA con cifra: 3 mensajes = UNA notificación** (database + mail del motor);
   marcarLeida → contador 0 → el 4º SÍ despacha (2 en total). **MUTADO**: despachar
   siempre → rojo exacto en el assert de la cifra → restaurado → verde. Extra no
   dictado: test de que la ráfaga es POR LADO (responder sin leer también avisa al
   otro — cada dirección tiene la suya).
5. marcarLeida idempotente (2× = 0, sin efectos).
6. `urlDestinoPara`: participante con permiso navega · tercero null · usuario sin
   `usar mensajes` null (el candado del `default => false` que el dictado pidió).
7. Eliminar un participante se lleva SU hilo por cascade y no los ajenos
   (comportamiento declarado del anexo §5.1; en 1-a-1 el emisor siempre es
   participante, así que el nullOnDelete de emisor_id queda de cinturón).
8. Texto vacío / >1000 rechazado; la plantilla renderiza `{emisor}`/`{extracto}`.

## Desviaciones declaradas

- **Guard `Route::has('mensajes.show')` en `urlDestino()`**: el dictado pide los
  DOS match Y cero rutas de pantalla — pero `route()` sin la ruta registrada LANZA
  RouteNotFoundException y una campanita de mensaje habría reventado la bandeja
  con 500. Con el guard la rama nace apagada (destino null hoy) y se enciende sola
  cuando MSG-2 registre la ruta. El test la prueba VIVA registrando la ruta en
  runtime — gotcha cazado de paso: una ruta nombrada en runtime no entra sola al
  lookup de `Route::has`, exige `Route::getRoutes()->refreshNameLookups()`.
- **`RoleMatrixSeedTest::matrix()` actualizado en las 12 filas**: el test es
  exhaustivo por diseño (assertEqualsCanonicalizing) — es el contrato moviéndose
  CON su seeder, no una cifra ajustada. **`member` deja de ser rol-vacío**
  (`['usar mensajes']`): «todos con todos» incluye al rol mínimo.
- **HigienePermisosTest SIN excepción**: `usar mensajes` ya se usa en `app/` (el
  gate de `urlDestinoPara`), así que NO se declaró en
  PERMISOS_ANTES_DE_SU_FUNCIONALIDAD — declararlo ahí habría puesto rojo al
  guardián-del-guardián. Categoría nueva `'Mensajes' => ['mensajes']` en
  config/permissions (sin keyword propio el permiso caía en «Generales» → rojo).
- Cero build (sin Blade/CSS/JS); package.json sin cambios entre ramas.

## Verificación adicional (gate propio)

- Batería dirigida **97/97**: Mensajes (13) + Notificaciones completo +
  PreferenciasCanal + RoleMatrix + HigienePermisos + ConfiguracionSeedLongitud
  (la descripción de la plantilla nueva ≤191).
- Primera corrida cazó 2 rojos propios ANTES del commit: el test de plantilla sin
  sembrar ConfiguracionSeeder (el fallback nunca-mudo pintaba la etiqueta
  genérica) y el lookup de la ruta runtime — ambos eran del test, no del código.
- Regla de oro adaptada (v26): cero tests existentes con cifra cambiada — los
  conteos derivados (`count(EVENTOS)*2` de preferencias, conteo del seeder de
  plantillas) se movieron solos; los dos contratos (CLAVES_M15 +1, matrix ×12)
  van declarados arriba.
