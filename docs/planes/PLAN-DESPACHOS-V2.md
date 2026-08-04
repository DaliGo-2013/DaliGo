# PLAN-DESPACHOS-V2 — la hoja de ruta digital (M08)
> **VIGENTE** — sellado por el Director el 2026-08-03 con aprobación del dueño, sobre las
> respuestas de Luis (operaciones) a la ronda 1 de preguntas (xlsx 31-jul, 25/26 contestadas).
> No sustituye a PLAN-DESPACHOS-V1 (P-DSP-00..07): lo extiende con P-DSP-08..10.

## 0. Evidencia (citas verificadas una por una el 03-ago)

| # | Afirmación | Evidencia |
|---|---|---|
| 1 | `despachos` es 1:1 con el documento de venta (unique) | `database/migrations/2026_07_14_210000_create_despachos_y_escaneos.php:23` |
| 2 | `despachos.zona_id` ya existe, nullable | misma migración, `:24` |
| 3 | `despachos.conductor_id` → `users`, nullable | misma migración, `:27` |
| 4 | `Zona` es auditable — el patrón para las entidades nuevas | `app/Models/Zona.php:16` |
| 5 | La tabla `audits` opera desde junio | `database/migrations/2026_06_05_190426_create_audits_table.php` |
| 6 | `umbral_aprobacion_clp` sembrado y reservado por PLAN-M14 para reglas monetarias | `database/seeders/ConfiguracionSeeder.php:19` |
| 7 | Todo `string()` es varchar(191) | `app/Providers/AppServiceProvider.php:49` |
| 8 | La PWA del conductor está codificada pero NO en main (54 atrás / 7 adelante al 03-ago) | `origin/feature/entregas-conductor` + parte `2026-07-28--max-2--p-dsp-05-pwa-conductor.md` |
| 9 | `registrarEntrega(..., array $extra = [])` — punto único de entrega, dentro del lock | `origin/feature/entregas-conductor:app/Services/Despachos/DespachoService.php:179` |
| 10 | Los pasos madre de E8 | `docs/RUTA-MAESTRA.md:251-261` |
| 11 | D-006 RESUELTA hoy con la respuesta de Luis (era 🔴) | `docs/DECISIONES.md` §D-006, enmienda 03-ago |

## 1. La fuente: qué definió Luis (respuesta → regla)

- **R1+R24**: hoy Ricardo (jefe de logística) tipea folios en un Excel que autocompleta el
  resto → la hoja digital se genera **eligiendo documentos del espejo Bsale**; el tipeo muere.
- **R2**: un vehículo sale N veces al día → **una hoja por SALIDA**, no por día.
- **R3**: el orden lo pone hoy el chofer «por experiencia»; lo pactado = jefe de despacho +
  chofer, y el chofer lo respeta → **campo `orden` editable en planificación**.
- **R4+R7**: «OK» = pagado; **sin OK el chofer COBRA en la puerta** (cheque, Transbank);
  clientes con crédito pagan cuando quieran → tres estados de cobro por parada.
- **R5**: horas manuales fuera (pedido explícito de Luis) → **timestamps automáticos** de
  transición (la guía electrónica del 1-nov-2026 exigirá hora de salida; el sistema la sabrá sola).
- **R6**: agregar destino con el camión en la calle = **edición de la hoja en curso, solo
  jefe de despacho, todo auditado** (quién y qué).
- **R11**: cadena real de 3 llaves — jefe ventas autoriza PAGOS → jefe despacho autoriza
  RUTA → jefe bodega autoriza CARGA.
- **R12**: peoneta opcional; si va, **el bono se parte a medias** y su nombre queda por seguridad.
- **R13**: receptor en la puerta = **nombre + RUT + relación** (empresa compradora, conserje, otro).
- **R15**: rechazo en puerta → vuelve a bodega, nota de crédito, se anula la guía, se
  re-despacha otro día → la parada registra `rechazada` y el resto es gancho a M13/M05.
- **R17**: los camiones siempre vuelven a su sucursal; sin rutas mixtas.
- **R21 (cierra D-006)**: la hoja se arma **por zona**; el vendedor **NO es fijo** (hay rutas
  con ventas de varios vendedores).
- **R22 (cierra el hallazgo «carga ajena» del gate M07)**: **Ricardo autoriza a UN conductor
  para UNA ruta** → scoping conductor↔hoja.
- **R25**: folio de la hoja **autogenerado, parte en 1000** (pedido de Luis).
- **R8+R23**: guía de despacho solo como respaldo (>$1.000.000, destinos especiales); la
  factura siempre viaja con la carga; **ya registran a mano conductor/RUT/patente** — la hoja
  digital captura justo lo que la ley exigirá.

## 2. Modelo de datos

### `hojas_de_ruta`
- `folio` unsignedInteger **unique, autogenerado, parte en 1000** — `max(folio)+1` bajo
  `lockForUpdate` sobre la propia tabla (semilla 999). *Alternativa descartada:*
  AUTO_INCREMENT con offset — se rompe en restores/re-imports y ata el folio al id.
- `sucursal_id` FK (R17) · `zona_id` FK → `zonas` (R21) · `vehiculo` string(191) ·
  `patente` string(8) (R8: hoy ya se registran a mano).
- `conductor_id` → `users` · `peoneta_nombre` string(191) null (R12).
- `estado` string(32): `borrador → pagos_ok → ruta_autorizada → cargada → en_ruta → cerrada`.
- Por transición: `*_at` + `*_por` (user) automáticos. **Cero campos manuales de hora** (R5).
- Auditable (patrón `Zona`, evidencia #4).

### `hoja_ruta_paradas`
- `hoja_de_ruta_id` FK cascade · `despacho_id` FK (unique por hoja) — reusa la entidad 1:1
  documento↔despacho (evidencia #1).
- `orden` unsignedSmallInteger — editable hasta `en_ruta`; después solo vía edición auditada (R6).
- `estado_cobro` string(32): `pagado | cobrar_en_entrega | credito` (R4+R7).
- `cobro_metodo` string(32) null (`efectivo|cheque|transbank`) + `cobro_monto`
  unsignedInteger null — obligatorios al entregar una parada `cobrar_en_entrega`.
- `resultado` string(32) null: `entregada | rechazada | reprogramada` (R15). `rechazada`
  NO automatiza nota de crédito ni reingreso: registra y notifica (M15); el papeleo es
  territorio M05/M13.

### Receptor de la entrega (columnas en `despachos`, donde vive la entrega)
`receptor_nombre` string(191) · `receptor_rut` string(12) · `receptor_relacion` string(32)
(`empresa|conserje|otro`) — se exigen junto a firma+foto en la confirmación (R13).

### Las 3 llaves (R11) — permisos nuevos
`autorizar pagos ruta` (jefe ventas) · `autorizar ruta` (jefe despacho) · `autorizar carga`
(jefe bodega). Transición **secuencial estricta**, cada una auditada. *Alternativa
descartada:* pasar la cadena por el motor M14 — este flujo es secuencial fijo, no por
umbral; P-DSP-06 (V1) sigue reservado para el umbral monetario (`umbral_aprobacion_clp`,
evidencia #6).

### Scoping conductor↔ruta (R22)
Retiro y entrega válidos solo si el despacho pertenece a una hoja `en_ruta` cuyo
`conductor_id` = usuario autenticado. Cierra el hallazgo del gate de M07 («cualquiera con
permiso entrega cualquier carga») **por diseño del dueño de la operación**, no por parche.

## 3. Pasos (mapeados 1:1 a RUTA-MAESTRA §E8)

- **Previo, ya en vuelo** — doble llave de `feature/entregas-conductor` (P-DSP-05, evidencia
  #8) tras re-refresh contra main. La PWA base entra ANTES que la hoja de ruta.
- **P-DSP-08** ≙ P-M08-02 · Entidad `hojas_de_ruta` + `hoja_ruta_paradas` + 3 llaves + folio
  desde 1000 + generación de paradas desde `documentos_venta` (Ricardo solo ELIGE documentos)
  + scoping conductor↔ruta + candados (máquina de estados no saltable, folio único bajo
  carrera, permiso por llave). **Sin UI de conductor.**
- **P-DSP-09** ≙ P-M08-03 · PWA del conductor SOBRE la hoja: paradas en el orden pactado,
  **dirección + comuna + teléfono visibles** (hoy la vista no los muestra — evidencia en el
  parte de P-DSP-05), receptor nombre+RUT+relación, cobro en entrega (método+monto), rechazo
  con motivo (gancho M13).
- **P-DSP-10** ≙ P-M08-04 · Edición de hoja en curso (solo jefe de despacho, auditada, R6) +
  cierre por jefe de logística (R18: cerrada = todas las paradas resueltas) + bono
  paramétrico conductor/peoneta (BLOQUEADO por el Excel de la fórmula — seguimiento ronda 2).

## 4. Fuera de alcance (dicho explícito para aprobar con los ojos abiertos)

- **No emite guía de despacho electrónica** — territorio M05/Marcos. Sí deja patente, chofer
  y hora capturados para la ley del 1-nov.
- **No optimiza rutas por mapa**: F1-F2 usan orden manual pactado (R3). Ruteo automático
  (R14, «que la app le arme la ruta») = paso futuro; depende de sanear direcciones en Bsale.
- **No maneja rendición de caja** del cobro en ruta: registra método+monto; la cuadratura al
  volver es seguimiento de la ronda 2.
- **No toca vendedor↔cartera** (R21: el vendedor no es atributo de la ruta).

## 5. Seguimientos — ronda 2 con Luis (ninguno bloquea P-DSP-08/09)

1. El **Excel de la fórmula del bono** (R19) — bloquea solo la parte de bono de P-DSP-10.
2. **Rendición del cobro en ruta**: qué hace el chofer con cheques/efectivo al volver, quién cuadra.
3. Aclarar R9: ¿las facturas de verdad no vuelven firmadas hoy (solo la guía)?
4. **Direcciones**: ¿quién completa/corrige las direcciones de clientes en Bsale? (condición del ruteo futuro)
5. **D-003 bodegas**: EN CURSO — Luis ya trabaja la limpieza en Bsale (03-ago). Esperar.

## 6. Verificación

- Cada lote: suite COMPLETA sobre el árbol mergeado (la baseline que manda es la de main del
  día del re-refresh de la PWA), candados nuevos mutados en ambos sentidos, doble llave.
- QA de cierre de F1+F2: **una ruta real de Sucursal Mirador punta a punta** (Ricardo arma la
  hoja, un conductor la ejecuta) con la hoja de papel corriendo en paralelo — mismo folio de
  documentos, comparación al cierre.
