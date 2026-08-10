# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-10 (v20 — P-M11-20 EN PRODUCCIÓN; GO P-M11-21: alertas SIC + panel vivo del jefe). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## ✅ P-M11-20 está EN PRODUCCIÓN (merge `1c040c3`, doble llave 10-ago) — F1 COMPLETA

Verificación del Director: **suite 1771 verdes / 12.661 aserciones, cero rojos** —
corrida DOS veces porque el primer push perdió la carrera contra el visor de Marcos
(I-08 aplicada: re-merge + suite entera; el drift era solo carga3d.js). Spot-checks 6/6;
tu desviación de los 7 motivos quedó **ACEPTADA** con tu propio razonamiento en el
mensaje del merge: «Preformas defectuosas» habría duplicado la pérdida de calidad como
downtime en el Pareto — exactamente el tipo de lectura que hace confiable una lista
cerrada. Y tu gate E2E cazando el atributo Alpine cortado por comillas y la parada sin
máquina que se perdía EN SILENCIO: eso es cazar ANTES del parte, el estándar de la casa.
Rama borrada tras ancestría.

## 🟢 GO — P-M11-21 · Alertas SIC + panel vivo del jefe (F2, stream B)

PLAN-M11-FINAL §4-F2. La captura ya existe (tus paradas + los reportes); ahora la
información PERSIGUE al jefe en vez de esperarlo:

- **Corte SIC cada 2 horas** (comando programado en el scheduler, horario de turno):
  por máquina con asignación activa hoy, proyección lineal del día (producido hasta
  ahora / horas transcurridas × horas del turno) vs meta asignada. Bajo umbral
  (constante de la casa, p.ej. 85 % proyectado) → **notificación M15 al jefe de
  producción** (evento nuevo `produccion.meta_en_riesgo`, molde de tus eventos de M04):
  máquina, producido/meta, proyección, y paradas ABIERTAS si las hay.
- **Escalamiento simple**: segundo corte consecutivo bajo umbral → el aviso se marca
  urgente (asunto/badge), no spamea — máximo 1 aviso por máquina por corte.
- **Panel «Hoy en vivo»** para el jefe (ruta bajo producción, permiso existente del
  jefe): por máquina — avance/meta con barra, proyección, paradas abiertas con
  duración corriendo, último reporte parcial recibido, semáforo simple
  (verde/amarillo/rojo por proyección). Auto-refresh liviano (poll con el patrón de la
  campanita/cola de bodega — SIN websockets nuevos).
- **WhatsApp queda [B:D-007]** — canal email + campanita por ahora; el evento queda
  listo para cuando el canal exista.
- Zona horaria: TODO en hora de negocio (`FechaNegocio`) — el corte de las 14:00 es de
  Chile, no UTC (lección E-TZ).

### Candados mínimos
1. Corte 2× seguidos bajo umbral → 2º aviso urgente; 3º corte igual NO duplica si nada
   cambió (guard por máquina+corte).
2. Máquina sin asignación hoy → ni corte ni aviso (silencio correcto).
3. Proyección con 0 horas transcurridas no divide por cero (primer corte del turno).
4. MUTADO: quitar el guard anti-spam → segundo aviso idéntico → rojo.
5. El panel respeta permisos (soplador no lo ve); paradas abiertas muestran duración
   corriendo server-side (sin JS que calcule mal la medianoche).
6. Los cortes usan FechaNegocio (test con hora frontera, molde de E-TZ).

## Territorio
- **Max-1** arranca P-M11-11 (OEE + Pareto, informes históricos) EN PARALELO — consume
  los mismos datos en superficie distinta. `ProduccionParada` es TUYO: si él necesita
  un scope, lo pide por parte. Tú no tocas informes históricos ni recetas.
- **Marcos** a toda máquina en el simulador (la carrera del 10-ago la perdió el
  Director — re-fetch religioso).

## Recordatorios
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar (baseline
del Director: **1771/12.661** en `1c040c3`). Suite completa antes del push. Blade →
build + superset. `git checkout origin/main --`. varchar ≤191. Parte al buzón → doble
llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
