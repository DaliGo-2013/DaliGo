# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-19 (v26 — diseño F0-MENSAJES APROBADO ENTERO por el dueño: las 5 recomendaciones ratificadas. GO MSG-1: backend puro). Manda sobre lo anterior.

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño).

## ✅ Tu diseño F0 fue aprobado ENTERO (dueño, 19-ago)

Las 5 recomendaciones, ratificadas sin ajuste: **ítem de primer nivel «Mensajes»
(menú 32→33)** · **aviso por RÁFAGA** · **permiso `usar mensajes` en TODOS los roles** ·
**online-only v1** · **retención para siempre v1**. Veredictos escritos al pie del plan.
Diseño de primera — la observación de que el anti-spam correcto «ya estaba pagado» por
los contadores es exactamente el tipo de hallazgo que vale un F0.

## 🔨 GO — Lote MSG-1: backend puro testeable (M)

Tu propio mapa §5.7, primera entrega. Sin UI, sin rutas de pantalla — el corazón:

1. **Migraciones**: `conversaciones` + `mensajes` EXACTO como el anexo §5.1 (par
   canónico con unique, contadores por lado, índices declarados, cascade con el
   porqué comentado).
2. **Modelos**: `Conversacion` (`entre($a, $b)` canonicaliza + firstOrCreate;
   `paraUsuario()` scope indexado; rechazo de conversación conmigo mismo) y
   `Mensaje` (append-only, `max:1000`).
3. **Enviar** (servicio o método de dominio, sin controller aún): transacción con
   `lockForUpdate` sobre la conversación → crea mensaje + `ultimo_mensaje_at` + `+1`
   al contador del receptor → **RÁFAGA**: despacha `mensaje.recibido` por el
   dispatcher SOLO si el contador del receptor estaba en 0 antes de este mensaje.
4. **Leer**: `marcarLeida(User)` → MI contador a 0 (idempotente).
5. **M15**: evento 37º `mensaje.recibido` en `EVENTOS` + plantilla
   `notif_plantilla_mensaje_recibido` en el seeder (clave nueva, sin one-shot,
   placeholders `{emisor}`/`{extracto}`) + `urlDestino()` **y** `urlDestinoPara()`
   (los DOS match — y el candado del `default => false` que tú mismo cazaste: evento
   navegable solo para participantes).
6. **Permiso `usar mensajes`** en `RolesAndPermissionsSeeder`: TODOS los roles
   (aditivo, precedente simular carga). Sin rutas todavía — el permiso nace aquí para
   que MSG-2 solo lo consuma.

### Candados (tu molde + los de la casa)
- Par canónico: `entre(7,3)` y `entre(3,7)` = la MISMA conversación; unique aguanta la
  carrera (test de constraint).
- Conversación conmigo mismo: rechazada.
- Enviar: mensaje + contador del OTRO +1 + `ultimo_mensaje_at` movido; MI contador
  intacto.
- RÁFAGA con cifra: 3 mensajes seguidos = UNA notificación (campanita+mail del motor);
  leer → contador 0 → el 4º mensaje SÍ despacha de nuevo.
- Leer idempotente (dos veces = 0, sin efectos).
- `urlDestinoPara`: participante navega, tercero NO (el candado del default false).
- Emisor eliminado: mensaje sobrevive con emisor null («—» queda para MSG-2);
  conversación eliminada por cascade si se va un participante — declarado.
- **Mutación**: rompe la ráfaga (despachar siempre) → el candado de la cifra se pone
  rojo exacto → restaurar → verde. Declárala.

### Verificación (invariante)
Rama `feature/msg-1-backend` desde main FRESCO (baseline del Director: **2208 verdes**
en `75cce08`; el drift corre — re-fetch). Suite COMPLETA de main fresco ANTES. Batería
dirigida: tus tests nuevos + Notificaciones completo + PreferenciaCanal +
ConfiguracionSeedLongitud (la plantilla nueva respeta el tope 191). **Regla de oro
adaptada**: cero tests existentes con cifra cambiada — el evento 37º solo puede sumar.
Parte al buzón; espera doble llave. NO arranques MSG-2.

## ⚠️ Coordinación de territorio (único cruce con Max-1)
MSG-1 toca `Notificacion::EVENTOS` y el `ConfiguracionSeeder` — Max-1 está EN PAUSA
(espera QA del módulo Dashboard) así que tienes la ventana limpia AHORA. Si su
auditoría de Comercial (v72) arranca antes de tu merge, es solo-docs — sin colisión.
El Director secuencia igual.

## Estado
Max-1 en pausa (QA Dashboard pendiente). Marcos activo. Baseline: 2208 en `75cce08`.

CIERRE: GO MSG-1. El corazón primero, la cara después. Fierro.
