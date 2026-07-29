# Parte de cierre — Max-2 · los 4 hallazgos del gate de P-DSP-04

CUENTA: Max-2 (Forjador B, stream 2 · DESPACHOS) · MODELO: Fable 5
> El dictado v11 pedía Opus 4.8 · high. El dueño mantiene Fable 5 fijado en su sesión y no lo
> cambio yo; los fixes son quirúrgicos igual, así que el modelo no cambia el resultado. Lo declaro
> por transparencia del ledger, no como objeción.

TAREA: **dictado v11** — los 4 hallazgos MEDIA + las 3 observaciones menores
ESTADO: **HECHA**

Los 4 son reales. No refuto ninguno, y el nº1 me corrige de raíz.

## EVIDENCIA

- Rama: **`fix/qr-hallazgos-gate`**, commit **`858c06a`**.
  *Nota sobre la base:* pediste "desde main fresco", pero los fixes tocan código de P-DSP-04 que
  **aún no está en main** (espera doble llave). La saqué de `feature/despachos-qr`, que ya está
  refrescada contra main — o sea main fresco **+** el lote. Verificado: `0` commits de main
  fuera de esta rama.
- TESTS: **1021 verdes / 5523 aserciones** (1008 con el lote antes de estos fixes; +13).
- Bundle **`app-bJbInchW.css` sin cambios**: los fixes no agregan clases nuevas (rebuild
  determinista, mismo hash).

## Hallazgo 1 — la afirmación del lock era falsa, y era mía

Confirmé tu demostración en el vendor: `SQLiteGrammar::compileLock()` hace `return '';`
**incondicional**. Bajo SQLite `->lockForUpdate()` emite SQL byte-idéntico a omitirlo, así que lo
que mi "mutación verificada" de P-DSP-04 probó fue la **re-lectura**, no el lock. Y tu advertencia
sobre el arreglo obvio también es correcta: un assert de `DB::listen` buscando `for update`
saldría rojo sobre código correcto.

- Docblocks de `validarRetiro` y `registrarEntrega` corregidos: dicen qué cubre la suite
  (re-lectura) y que **el lock no es asertable en SQLite**, con el porqué.
- Cobertura honesta nueva: **`tests/Unit/LockParaMySqlTest.php`** — compila el mismo builder con
  `MySqlGrammar` y exige el sufijo `for update`; test de unidad puro, sin BD. El segundo caso deja
  el **contrafáctico** documentado (`en_sqlite_el_mismo_lock_no_emite_nada`): si algún día empieza
  a fallar es buena noticia — significaría que ya se puede cubrir en feature.

Que el gate haya cazado una afirmación mía sobre-declarada es exactamente para lo que existe. La
tomo como corrección, no como rasguño: **una doc que promete un control que el código no aplica es
peor que no tener doc**, porque el próximo lee y confía.

## Hallazgo 2 — el grave: parcial grabada como completa (dato de negocio perdido)

Reproducido: el único portador del flag era un hidden con solo `:value` de Alpine y el checkbox no
tenía `name`. Sin JS → llega vacío → `(bool)''` = false → **ENTREGADO con el saldo adentro**.

Arreglado con el patrón checkbox+hidden **y el `name` en el CHECKBOX**: hidden `value="0"` estático
+ checkbox `value="1"` que lo pisa por ir después. El HTML solo ya es correcto.
**Extra que encontré arreglándolo:** el bloque del saldo tenía `x-cloak`, y
`[x-cloak]{display:none!important}` lo habría dejado oculto **para siempre** sin JS — el operador no
podría escribir el saldo que el servidor le exige. Quitado: se prefiere un flash de 50 ms a perder
la función.
3 tests: parcial sin JS, total sin JS, y la **forma del HTML** (para que nadie vuelva al patrón viejo).

## Hallazgo 3 — el user_id sin cobertura HTTP

Confirmado: con `validarRetiro($despacho, null)` la suite quedaba verde. 2 tests HTTP nuevos que
asertan el `user_id` de la fila, en el camino válido y en el `doble_retiro` (también el rechazado
queda a nombre de quien lo intentó — es la mitad útil de la evidencia).

## Hallazgo 4 — el monitor podía mostrar una carga ya retirada

El JSON ahora devuelve una **firma del contenido** (`md5` de los códigos pendientes, en orden) además
del total, y la vista compara contra ella. Las dos salen del **mismo helper privado**: si se
calcularan por separado podrían divergir y el monitor recargaría en loop (o nunca) — hay un test
que exige que coincidan. El test del caso ciego entra una carga y saca otra: **total idéntico,
firma distinta**.

## Las 3 menores, hechas

- `test_la_firma_de_un_despacho_no_sirve_para_otro`: usa el código **real** de otro despacho con la
  firma de este. Antes con `DSP-FALSIFIC` probaba un 404 y sobrevivía a que se quitara `signed`.
- **4 tests** para los bloques de veredicto, asertados por su **texto visible** (el contrato con el
  operador), no por clases de Tailwind: «Retiro autorizado», «NO entregues / doble retiro», «ya
  está cerrado», y el negativo de que sin veredicto no se pinta nada.
- `assertRedirect($despacho->urlFicha())` con destino.

## Mutaciones verificadas (y la lección que aprendí en la sesión anterior)

**Commiteé ANTES de mutar.** La sesión pasada restauré una mutación con `git checkout --` y me
llevé ~110 líneas de trabajo sin commitear; esta vez el árbol estaba limpio, así que cada
restauración fue segura:

| Mutación | Resultado |
|---|---|
| A · hidden sin `value` + checkbox sin `name` | **rojo** (`el_formulario_de_entrega_funciona_sin_alpine`) |
| B · `firma` constante en el JSON | **2 rojos** (caso ciego + coincidencia vista/JSON) |
| C · `validarRetiro($despacho, null)` | **2 rojos** (los dos del operador) |

## Sobre el desacuerdo de fondo

Acepto tu criterio: el `docblock` de `DespachoController` ahora dice lo que el código hace de
verdad — **SÍ** cierra «una carga no sale dos veces»; **NO** cierra «retirar una carga que no te
corresponde», porque el panel reparte la URL firmada de cualquier despacho a quien tenga
`manage despachos` y no hay control por zona ni exigencia de tener el QR físico. Queda escrito como
**alcance aceptado de v1**, no como control existente. Si el dueño quiere cerrarlo, es paso propio.

/usage INICIO → FIN: sesión heredada del hilo del dueño, no comparable con un asiento limpio.

## SIGUIENTE

**P-DSP-04 lista para doble llave**, ahora con los 4 hallazgos cerrados. Recomiendo mergear
`fix/qr-hallazgos-gate` (trae el lote completo: P-DSP-04 + fixes) en un solo movimiento, en vez de
mergear la rama vieja y después esta.

Sin GO a P-DSP-05, no lo arranco. Cuando lo des: rama nueva desde main **después** de este merge.
