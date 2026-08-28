# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-27 (v36 — veredictos del dueño al catálogo
> §7: 4 aprobadas, 3 en cola, los 4 NO aceptados. GO MSG-6: el gesto completo,
> tu propio empaque #1+#2). Manda sobre lo anterior.

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño).

## ✅ Veredictos del dueño al catálogo (27-ago) — quinto mapa que sale entero

APROBADAS: **#1** (el hilo se siente chat) · **#2** (el correo navega al hilo)
· **#3** (foto en el hilo) · **#6-Visto** (el doble check SÍ — el dueño lo
valora; el «escribiendo» NO, como recomendaste). EN COLA sin apuro: #4 buscar
· #8 retención · #5a título de pestaña. **Los 4 NO aceptados enteros con TUS
porqués** — el catálogo honesto funcionó: el dueño decidió con los costos a la
vista. Tu empaque sugerido quedó adoptado tal cual.

Lotes de la fase 2: **MSG-6 = #1+#2** (este dictado) → **MSG-7 = #3 foto** →
**MSG-8 = #6 Visto** → cola. Un lote = un merge = una doble llave, como
siempre.

## 🔨 GO — Lote MSG-6: el gesto completo (S/M) — candidatas #1 + #2

Tu propio diseño del catálogo §7.1 y §7.2, ejecutado tal como lo escribiste:

1. **Enviar sin recarga**: `responder()` aprende a contestar JSON cuando el
   fetch lo pide, reusando LA MISMA respuesta `{ultimo, html}` del molde
   `nuevos()` con `_burbuja` — el envío propio appendea su burbuja. Composer
   se limpia tras 2xx; ante 422 el error se muestra y **el texto SE CONSERVA**
   (la pérdida real que contradecía la promesa del QA).
2. **El hilo abre ABAJO**: scroll inicial al fondo al cargar página 1 (hoy el
   único scrollIntoView es el de burbuja nueva).
3. **`old('texto')`** en el composer del hilo (el respaldo sin-JS que
   `create.blade.php:27` ya tiene y el hilo no).
4. **Los 2 pulidos del territorio**: `aria-live="polite"` en `#hilo-mensajes`
   + autoresize simple del textarea. Enter-para-enviar FUERA a propósito
   (celular = salto de línea) — tu propia decisión, ratificada.
5. **El form clásico sigue de respaldo** (la guarda offline «necesitas señal»
   se conserva tal cual — candado).
6. **#2 — el correo navega**: botón «Abrir en DaliGo» al hilo en la plantilla
   del correo de mensaje nuevo (el `urlDestinoPara` que MSG-1 dejó listo — si
   la plantilla necesita otra cosa, decláralo).
7. **Candados**: enviar-appendea-sin-reload (respuesta JSON pinta lo que la
   pantalla agrega) · 422 conserva el texto (assert del old + el error
   visible) · el hilo abre abajo (la señal del scroll inicial presente en
   página 1 y AUSENTE en históricas) · form clásico sigue funcionando (el
   test existente de responder() intacto — regla de oro) · el correo lleva el
   botón con la URL firmada del hilo y un tercero NO la puede usar (403 del
   gate de participante ya existente) · aria-live presente · mutación tuya
   declarada con rojo exacto.

### Verificación (invariante)
Rama `feature/msg-6-gesto-completo` desde main FRESCO (baseline: main lleva el
fix del deploy y el trabajo de Marcos de esta semana — recuenta tú; última
cifra local del Director: 2329/16.225 pre-Marcos-reciente). Suite COMPLETA
antes. Batería: Mensajes completo + Notificacion* (tocas plantilla de correo).
Bundle: si el autoresize trae clases → I-06 declarado. Parte al buzón; espera
doble llave. NO arranques MSG-7.

## 📡 Radar (NO arranques)
- **MSG-7**: #3 foto en el hilo (validación imagen, storage compartido del
  hosting, límite de peso — tu diseño del catálogo).
- **MSG-8**: #6 Visto (doble check por el poll de 4 s; sin websockets).
- **Cola**: #4 buscar · #8 retención (perilla — coordinar con PARAMETRICOS
  cuando llegue a Mensajes) · #5a título de pestaña.

## Estado
Max-1: LOG-2 forjando (franja de la flota — territorio disjunto). Marcos:
activo (visita industrial + garantías). Trello espejando. El deploy quedó
blindado (composer propio en el home — HostGator ya no nos mueve el piso).

CIERRE: GO MSG-6. El catálogo honesto ganó: el dueño eligió con los costos a
la vista y tu empaque quedó tal cual. A cerrar el gesto.
