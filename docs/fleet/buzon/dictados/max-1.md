# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-28 (v84 — LOG-2 EN PRODUCCIÓN: la flota
> avisa cuando el dueño quiera. GO LOG-3: las listas del conductor y el TV de
> bodega). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ LOG-2 está EN PRODUCCIÓN (merge `458916b`, doble llave 28-ago)

Suite del Director **2359 / 16.434 CERO rojos** sobre el trial merge en main
fresco — que traía encima el lote de permisos solo-admin de Marcos (+15/+113,
atribuido entero a sus 5 archivos de test). Tu delta +5/+38 +8-por-clave
clavado. Spot-checks del parte contra el árbol: los cinco, correctos. Rama
borrada.

Lo que quedó fino: la **letra pisada con evidencia** (grupo `vehiculos`, no
`logistica` — el precedente `despachos` citado: exactamente cómo se pisa un
dictado); los **2 rótulos extra** que el mapa no tenía y que habrían mentido
igual porque derivaban de la CONSTANTE; y la mutación 30→45 con los candados
viejos VERDES — la arquitectura demostrada por la propia mutación, directo al
acta. Registro en PLAN-PARAMETRICOS §5.4.

**OJO DE CONTEXTO (importa para TODO lo que sigue):** el 28-ago aterrizó la
matriz de avisos (`f991cd4`, tarea directa del dueño, ejecutada por el
Director — ver `docs/planes/PLAN-AVISOS.md`). Tocó TU territorio recién
entregado: `VehiculosAvisarVencimientos` ya no resuelve destinatarios por
permiso sino por evento vía `App\Support\AudienciasNotificacion` (fuente única
de «quién recibe qué»; un hito silenciado no se reclama), y tu
`test_avisa_a_todos_los_que_pueden_ver_la_flota` se reescribió como «se mudó».
Además `ConfiguracionController::index()` ahora EXCLUYE el grupo
`notif_destinatarios`, `edit()` redirige esas claves a su matriz, y el
`ConfiguracionSeeder` termina con un loop que siembra 25 claves
`notif_roles_*` (el candado-iterador del seeder las recorre también). Baseline
fresco obligatorio.

## 🔨 GO — Lote LOG-3: las listas del conductor + el TV de bodega (M)

Hallazgos #2, #3 y #4 aprobados del mapa §5.4 (molde COM-1/OPE-2,
LISTAS_SIMPLES + RANGOS):

1. **`despachos_metodos_cobro`** (LISTAS_SIMPLES, grupo `despachos`) — default
   `['efectivo', 'cheque', 'transbank']`. Hoy inline **×2** en
   `EntregaConductorController.php:103` y `:121` (el segundo es la validación
   condicional de cobro-en-entrega) — UNA fuente para ambas, más la vista del
   conductor que ofrece las opciones (re-mapea tú: el grep de `cobro_metodo`
   en `resources/views/` te la da). Fallback constante, patrón `getLista`.
2. **`despachos_relaciones_receptor`** (LISTAS_SIMPLES) — default
   `['empresa', 'conserje', 'otro']`, hoy inline en
   `EntregaConductorController.php:102` + su vista. Mismo molde.
3. **`despachos_tarjetas_monitor`** — default **12**
   (`DespachoController.php:178`, el `limit(12)` del monitor de bodega).
   RANGOS chico con sentido, declarado (piensa en el TV real: ¿cuántas
   tarjetas caben legibles?). Si algún rótulo nombra el 12, deriva en este
   lote (doctrina DASH-2).
4. **⚠ La trampa que ESTE lote crea y debe cerrar:** el endpoint del conductor
   es el de la COLA OFFLINE (entrega_uuid). Hoy quitar un método de cobro es
   imposible (lista hardcodeada); con la perilla, una entrega ENCOLADA con un
   método que el dueño quitó llegaría al drenado y el `Rule::in` la
   rechazaría con 422 = rechazo PERMANENTE de una entrega real ya hecha en la
   puerta. Decide y DECLARA el diseño (¿el drenado acepta cualquier valor
   histórico y solo la UI ofrece la lista viva? ¿otra cosa?) —
   contra-evidencia declarada, doctrina OPE-1. Candado obligatorio sobre el
   caso: una entrega encolada con un método recién quitado NO se pierde.
5. **Candados molde**: default idéntico byte a byte con BD virgen · mover la
   clave mueve SU pantalla CON CASO (un método nuevo aparece en el formulario
   del conductor y pasa la validación; el monitor con la perilla en N muestra
   N tarjetas) · bordes del rango por ambos lados · listas: dedup/vacías según
   `parseListaSimple` · mutación con rojo exacto declarado → restaurar →
   verde · estructural si aplica.
6. **Regla de oro**: cero tests existentes con cifra cambiada (los que se
   MUDEN, clasifícalos y decláralo — precedente del test del permiso suelto).

### Verificación (invariante)
Rama `feature/param-log-3-conductor` desde main FRESCO (baseline: main es
`f991cd4` con la matriz de avisos adentro — recuenta tú; mi cifra sobre ese
árbol exacto es **2377 / 17.001**). Suite COMPLETA antes. Batería dirigida:
Entrega* + Despacho* + HojaRuta* + ParametrosLogistica + Configuracion*.
Al agregar tus claves al seeder: van en el array principal, ANTES del loop de
audiencias del final (o donde el idioma del archivo mande — declarado). Parte
al buzón; espera doble llave. NO arranques LOG-4.

## 📡 Radar LOG-4 y cola (NO arranques)
- **LOG-4** (higiene): 188 ×6 → constante · POR_PAGINA ×2 · `yaSalioDeBodega()`
  ×4 · correo por PERMISOS (#13 — ⚠ re-mirar contra el precedente
  `AudienciasNotificacion` antes de forjar: puede que ya no sea «unificar
  permisos» sino «sumarlo al registry»; lo decide el Director en ese dictado) ·
  topes cubicar UI←servidor (re-leer líneas post-Cabina) · 90 % ×2.
- **Cola de funciones** (dictados aparte): buscador de folios · caducidad QR ·
  topes de monto R11.

## Estado
Max-2: MSG-6 forjando (el gesto completo del chat — territorio disjunto).
Marcos: permisos solo-admin en producción (27-ago), muy activo. Director: la
matriz de avisos del dueño entregada el mismo día (PLAN-AVISOS v1); fases 2/3
de la Configuración amable en cola, sin GO.

CIERRE: GO LOG-3. La puerta del cliente ya registra todo; ahora que el dueño
decida con qué se cobra y cuánto muestra el TV. 🔨
