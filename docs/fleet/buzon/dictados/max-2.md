# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-14 (v7 — P-DSP-03 verificado, GO P-DSP-04). Manda sobre lo anterior.

MODELO: Fable 5 disponible hasta el 19-07; para P-DSP-04 (talla M/L con diseño de seguridad)
Fable se justifica si el dueño lo fija; si no, Opus 4.8 · high.

## ✅ P-DSP-03 VERIFICADO por el Director (spot-checks sobre `d99ed88`)
Diff acotado ✓ · bundle `BHh2X-_E` grep 6/6 confirmado independiente ✓ · FAIL-CLOSED en la
re-verificación (indeterminado ≠ vigente — correcto) ✓ · el unique en BD sobre
`documento_venta_id` como defensa estructural contra la carrera doble-submit es la decisión
correcta (SQLite no exhibía la carrera; el constraint sí la mata en MySQL) ✓ · permisos en
los 3 puntos + RoleMatrix ✓ · responsive verificado en preview ✓. Tu review adversarial
volvió a pagar (fail-open reproducido y cerrado). P-DSP-03 [x] aceptado.

## 🟢 GO P-DSP-04 confirmado (QR anti-fraude M07 — el corazón de la unidad)
Según plan §2, con estos énfasis:
1. `validarRetiro` bajo `lockForUpdate` + re-check del estado con la fila bloqueada; TODO
   escaneo deja fila en `escaneos_despacho` (válido, doble_retiro Y estado_invalido). El
   2º escaneo dispara ALERTA visible y NO cambia estado. Tests del lock y de la carrera
   (doble-tap del operador de bodega — patrón bitácora [2026-06-30]).
2. QR firmado `URL::signedRoute` sobre el `codigo DSP-` (no el id — no enumerable), reusa
   el patrón M12 (`dibujarQrsMostrador`, chunk qrcode ya en bundle) + página imprimible.
3. Si la ruta de escaneo es pública o semi-pública: middleware `signed` + throttle; si es
   de bodega autenticada: `manage despachos`. Decide por el flujo real (operador de bodega
   logueado en un puesto fijo) y documenta la decisión en el plan.
4. Cola "McDonald's": polling JSON liviano patrón `porConfirmarConteo()` de ST. Pantalla
   apta para un monitor en bodega (texto grande, estados por relleno — paleta de 4).
5. Entrega total/parcial: parcial marca `entrega_parcial` y el saldo queda visible.
6. Blade/JS → npm install + build + grep del bundle (recuerda tu propio gotcha del escape
   CSS: grepear `min-w-` crudo o la forma `min-w-\[1\.5rem\]`).
Suite verde por commit. Parte al buzón.

NOTA: tu rama acumula P-DSP-00..03 sin mergear. El merge sigue siendo P-DSP-07 (unidad
completa, doble llave) según plan — pero si la rama se vuelve pesada de refrescar contra
main, propone un merge intermedio post-04 con gate R-31 y el Director lo evalúa.

Pendiente del dueño (no bloquea): fecha de arranque del espejo (`documentos_sync_desde`).

CIERRE por paso: parte a docs/fleet/buzon/partes/ + push.
