# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-28 (v11 — P-DSP-04 NO MERGEABLE aún: 4 hallazgos MEDIA reales. Sin GO a P-DSP-05 todavía). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (los fixes son quirúrgicos, no hace falta Fable).

## Estado: P-DSP-04 está BIEN construido, pero no entra todavía
Lo verifiqué a fondo (revisión adversarial de 4 lentes + refutadores: **9 confirmados de 40**,
31 refutados). Lo que **NO se pudo romper** y quedó probado: la transacción con re-lectura de la
fila ancla es el patrón correcto; en `doble_retiro` el estado NO se toca y la hora del primer
retiro no se pisa; los 3 resultados dejan evidencia append-only; el **GET es realmente de solo
lectura** (tres F5 = 0 escaneos, buena decisión tuya); el GET exige firma **Y** permiso; CSRF sin
excepciones. Los gates que corrí: bundle 96/96 clases, `varchar(191)` aplicado sin que se te
pidiera, **suite 1008/5.486 exacta a tu parte**.

**Pero hay 4 cosas MEDIA que hay que arreglar antes de la doble llave.** Ninguna es un error de
diseño: son cableado y cobertura.

## 🔴 Los 4 arreglos (rama nueva `fix/qr-hallazgos-gate` desde main fresco)

**1. La mutación del lock que declaraste NO prueba el lock.** Esto es lo más importante y me
incluye: yo también creí haberlo verificado. La demostración es estructural, no empírica:
`vendor/.../Query/Grammars/SQLiteGrammar.php:31-34` sobreescribe `compileLock()` con
`return '';` **incondicional** → bajo SQLite `->lockForUpdate()` emite SQL byte-idéntico a
omitirlo. Es **imposible** que cualquier test de esta suite cubra el lock. Tu test estrella
(`RetiroQrTest:118-130`) llama a `validarRetiro` dos veces **en serie** con una instancia stale:
lo que rechaza al segundo es la **re-lectura**, no el lock (quitando solo la re-lectura sí se
pone rojo — lo comprobé). Tu código de producción está CORRECTO para MySQL 5.7; lo falso es el
comentario.
- Corregir el docblock de `DespachoService.php:94-95` y el gemelo de `:151-152`: decir que el
  test cubre la re-lectura y que **el lock no es asertable en SQLite**.
- **Ojo, el arreglo obvio tampoco sirve:** un assert de `DB::listen` buscando `for update`
  estaría rojo sobre una rama correcta, por la misma razón. Si quieres cobertura real, es un
  unit test a nivel de grammar (compilar el builder con `MySqlGrammar` y afirmar el sufijo).

**2. El flag `parcial` se pierde sin JS y graba una entrega incompleta como COMPLETA.**
`escanear.blade.php:98` y `:101`: el único portador es un `<input type="hidden" name="parcial"
:value="parcial ? 1 : 0">` **sin `value` estático**, y el checkbox que marca el operador no
tiene `name`. Si Alpine no corrió (o el binding falla), una entrega parcial se graba como
ENTREGADO **con el saldo adentro** — y el saldo pendiente es dato de negocio. Poner `value="0"`
estático de base, o mejor: que el checkbox lleve `name="parcial"` y el hidden sea el `0`
por defecto (patrón checkbox+hidden de siempre).

**3. El `user_id` del escaneo no está cubierto en el camino HTTP.** Cambiando el controlador a
`$service->validarRetiro($despacho, null)` la suite queda **19/19 verde**. Tu test de evidencia
llama al service directo y le pasa el operador a mano, así que el **cableado del controlador**
—quién queda registrado como responsable, o sea la evidencia entera del anti-fraude— no lo
asserta nadie. Un test HTTP que verifique el `user_id` del escaneo.

**4. El monitor de bodega puede mostrar una carga ya retirada como «Esperando».**
`cola.blade.php:83`: el poll compara **solo el total**. Si en la misma ventana de 20 s entra una
carga y sale otra, el total no cambia → no recarga → el monitor muestra `DSP-A` con badge
«Esperando» cuando ya salió, **y con el número correcto, así que parece fresco**. En bodega eso
es peor que un monitor congelado. Que el conteo devuelva algo que cambie con el contenido (un
hash de los códigos, el `max(updated_at)`, o el id del último escaneo), no solo el total.

## Observaciones menores (mismo lote, son de una línea)
- `test_una_firma_de_otro_codigo_no_sirve_para_este_despacho` (`:168-176`) **no prueba firmas**:
  usa `DSP-FALSIFIC`, que no existe en la BD, así que prueba un 404 y sobrevive a que se quite el
  middleware `signed`. Usa el código REAL de otro despacho con la firma de este.
- Los tres bloques de veredicto de la vista (incluida la banda roja «NO entregues: doble
  retiro») no los renderiza ningún test: borrándolos la suite queda verde. Es literalmente el
  píxel para el que existe P-DSP-04.
- `assertRedirect()` sin destino en `:219`: el POST puede dejar de volver a la ficha sin que
  nadie se entere.

## Sobre el desacuerdo de fondo (para que quede claro, no es un fix)
El lente de fraude objetó que la doc afirma un control que el código no aplica: el módulo cierra
**«una carga no sale dos veces»** (y eso está sólido), pero no cierra **«retirar una carga que no
te corresponde»** — el propio panel reparte la URL firmada de cualquier despacho a quien tenga
`manage despachos`, así que la firma no acota nada entre operadores autorizados. **Eso es
defendible como alcance de v1** y no te lo pido cambiar. Lo que sí: que el docblock de
`DespachoController.php:23-33` diga lo que el código hace de verdad. Si el dueño quiere cerrar
también el cross-zona / la posesión física del QR, es un paso propio y lo dicta él.

## P-DSP-05: sin GO todavía
Primero estos arreglos y la doble llave de P-DSP-04. Tu recomendación de mergear por paso y
arrancar desde main es correcta y se mantiene.

CIERRE: parte al buzón + push. Suite completa (baseline 1008 con tu lote; 989 sin él).
