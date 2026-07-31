# Exploración · Simulador de carga de camión (escritorio) — herramienta de venta

> ⚠️ **ESTO NO ES UN PLAN FINO Y NO SE EJECUTA.** Es una exploración de viabilidad
> pedida el 31-jul-2026, para decidir *si* y *cuándo*.
>
> El `README.md` de `docs/planes/` es explícito: el plan fino se escribe **justo
> antes de arrancar la unidad, nunca antes**, con el código a la vista y su sello
> de vigencia. Existe por la lección de `PLAN-M11-FASE2.md`, que pasó semanas
> describiendo un diseño que nunca se construyó. Por eso este documento vive
> **fuera** de `docs/planes/`. El §8 dice cómo convertirlo en plan.

---

## 1. Qué es, y qué NO es

**Es un simulador suelto — una calculadora de "¿me cabe?".** Alguien de ventas
tiene un cliente lejos que pregunta *"¿alcanza tanta cantidad en tal camión?"*,
escribe las cantidades a mano, elige el camión, y obtiene una respuesta: **sí cabe
/ no cabe / cabe todo menos esto**.

Caso de uso textual del pedido:

> 200 botellones (vacíos) · 20 cajas de tapas · 10 dispensadores · 1 lavadora
> → ¿entra en el camión X?

**No es** un módulo atado a facturas ni a despachos. Es una decisión de diseño que
conviene dejar escrita, porque abarata mucho:

| No lleva | Por qué abarata |
|---|---|
| Integración con `documentos_venta` / `despachos` | Las cantidades las escribe la persona |
| Orden de carga por ruta (LIFO) | Para cotizar no importa el orden de descarga, solo si cabe |
| Estado, permisos finos, auditoría | Es una calculadora. **No escribe nada operativo** |
| Medir el catálogo completo | Solo los tipos de bulto que se cotizan |

**Consecuencia buena:** es el módulo **más seguro posible** de agregar a DaliGo. No
toca facturación ni despachos, no migra datos existentes. Si sale mal, se apaga la
pantalla y no pasó nada.

---

## 2. ⚠️ Corrección: el problema es VOLUMEN, no peso

**Una versión anterior de este documento concluía que el camión se llena por peso.
Es falso para Dali, y el dato que lo corrige es que los botellones se venden
SIEMPRE VACÍOS** (aclaración de Marcos, 31-jul-2026). Un botellón lleno pesa ~21 kg;
vacío pesa cerca de 1 kg. Eso invierte el resultado.

La cuenta corregida, con valores de referencia míos (**no son las medidas reales de
Dali** — es exactamente lo que hay que medir en la fase 0):

| Bulto | Cantidad | Peso total aprox. | Volumen aprox. |
|---|---|---|---|
| Botellón 20 L **vacío** | 200 | ~200 kg | **~7,8 m³** |
| Cajas de tapas | 20 | ~200 kg | ~1 m³ |
| Dispensadores | 10 | ~180 kg | ~1,5 m³ |
| Lavadora | 1 | ~100 kg | ~1 m³ |
| | **≈ 680 kg** | **≈ 11 m³** | |

Contra un camión chico (caja ~4,2 × 2,0 × 2,0 m = **16,8 m³**, carga útil ~3.500 kg):

- **Peso usado: ~19%**
- **Volumen usado: ~65%**

### El hallazgo corregido

> **Los botellones vacíos son aire con forma.** Casi no pesan y ocupan
> muchísimo. El camión de Dali se llena **por volumen**, y el peso casi nunca es
> el límite.

Y eso **refuerza** el caso del visor 3D en vez de debilitarlo:

1. Si el límite fuera el peso, bastaba una suma — no hacía falta 3D.
2. Como el límite es el **espacio**, la pregunta es literalmente geométrica: cómo
   se acomodan las cosas. Ahí el 3D no es adorno, es el cálculo.
3. Y el caso más difícil es justo el de Dali: **máquinas grandes e irregulares
   mezcladas con muchos bultos chicos y livianos.** Sumar volúmenes miente mucho
   ahí, porque alrededor de una llenadora queda espacio muerto que los botellones
   pueden aprovechar… o no, según si se pueden poner encima.

### Lo que más espacio ocupa (dicho por Marcos)

- **Llenadoras de 100 y 200 botellones** — las más grandes
- **Plantas de osmosis de 500 y de 1 tera**
- **Lavadoras de botellones**

Estas son máquinas: voluminosas, irregulares y **no apilables**. Un botellón
encima de una llenadora no va.

### Consecuencia para el plan

**La regla de apilado pasa de la fase 3 a la fase 1.** No es un refinamiento: con
este mix es *la* pregunta. "¿Puedo poner botellones arriba de la máquina?" decide
si el camión rinde el 60% o el 95%.

---

## 2bis. Cómo se cargan de verdad los botellones — y por qué simplifica todo

Aclaración operativa de Marcos (31-jul-2026), y es el dato más útil de toda la
exploración:

- Los botellones van **acostados de costado**, no parados.
- Con el **pico mirando hacia la puerta** del camión.
- Van **envueltos en bolsas de 5**.
- **No se deforman.**
- Entra distinta cantidad según el camión, porque hay camiones de varios tamaños.

### Tres consecuencias fuertes

**1. La unidad de carga no es el botellón: es la bolsa de 5.**
Eso es lo que se mide, lo que se apila y lo que se cuenta. 200 botellones son **40
bolsas**. El modelo se simplifica por cinco, y —más importante— se vuelve fiel a
cómo la bodega ya trabaja.

**2. La orientación es fija y conocida.**
Acostado, pico a la puerta. No hay que buscar la mejor rotación: el bulto tiene un
solo alto, un solo ancho y un solo largo posibles. Eso **elimina la parte más
costosa** de un empaque 3D genérico, que es probar orientaciones.

**3. Para el producto principal, la respuesta es división entera — no empaque.**

```
bolsas que entran =  floor(ancho útil  / ancho de la bolsa)
                   × floor(alto útil   / alto de la bolsa)
                   × floor(largo útil  / largo de la bolsa)
```

Y acá está el detalle que hace la diferencia: **dividir volúmenes sobreestima; la
división entera da el número real.** Si sobran 15 cm de ancho, ahí no entra nada —
es espacio muerto. Un cálculo por volumen se los comería como si fueran
aprovechables y prometería carga que no cabe.

O sea que **para el caso dominante la herramienta puede ser exacta, no estimativa**,
porque reproduce exactamente el patrón con el que se carga hoy. Eso es raro y hay
que aprovecharlo: la mayoría de estos simuladores solo pueden aproximar.

### Qué se le pide entonces al 3D

Con esto, el número de botellones sale de aritmética y **no necesita 3D**. El visor
se justifica para lo otro:

- **Las máquinas** (llenadoras, plantas, lavadoras): grandes, irregulares, no
  apilables. Ahí sí hay que ver dónde caen.
- **El espacio que sobra alrededor de ellas**: cuántas bolsas entran en los huecos.
- **Mostrárselo al cliente** en la cotización.

### Lo que falta preguntar en bodega

| Pregunta | Por qué importa |
|---|---|
| ¿Cómo van los 5 dentro de la bolsa — en fila, en cruz, apilados? | Define las medidas del bulto. Basta medir **la bolsa armada**, no calcularla |
| ¿Cuántas bolsas de alto se apilan? | Es el tope vertical del cálculo |
| ¿Se pueden poner bolsas **sobre** las máquinas? | Es lo que decide si el camión rinde 60% u 90% |
| ¿Hay más de una capacidad de botellón? | Cada una es un tipo de bulto distinto |

---

## 2ter. Lo que muestran las fotos de carga real (31-jul-2026)

Marcos pasó 4 fotos de cargas reales, en tres camiones distintos. Lo que se ve
cambia el modelo, y para mejor.

### El patrón de carga es por ZONAS, no por acomodo libre

En las cuatro fotos se repite lo mismo:

1. **Las bolsas de botellones van al FONDO, contra la cabina, formando un muro** de
   piso a casi el techo.
2. **Las cajas de cartón van hacia la puerta y a los costados**, apiladas mucho más
   alto que las bolsas.
3. Las bolsas van **acostadas con el pico hacia la puerta**, como estaba dicho — se
   ve el fondo redondo de los botellones de frente.

**Esto simplifica el cálculo todavía más de lo previsto.** No hay que resolver un
empaque libre: hay que llenar **dos zonas con reglas distintas**.

```
[ MURO DE BOLSAS ]  [ zona de cajas / máquinas ]  → puerta
     contra
    la cabina
```

Y el muro se describe con tres números contables a ojo: **bolsas de ancho × de alto
× de profundidad**. Eso es exactamente lo que la fórmula de división entera del
§2bis calcula.

### Cinco detalles que no se ven en una descripción

| Observación | Consecuencia para el modelo |
|---|---|
| **Las cajas se apilan MUCHO más alto que las bolsas** — en una foto van 6 o más de alto contra ~5 filas de bolsas | El límite de apilado es **por tipo de bulto**, no global. Ya está previsto en `P-CARGA-01` |
| **La hilera del piso a veces va girada** respecto al resto del muro | La carga real **no es una rejilla perfecta**: hay adaptación manual. El cálculo debe presentarse como *capacidad práctica*, no como verdad exacta al bulto |
| **Queda piso libre hacia la puerta** en varias fotos | ⚠️ Ver la advertencia de abajo |
| **Hay pallets plásticos negros** afuera, con cajas encima | Las cajas llegan paletizadas pero parecen cargarse **a mano, sueltas**. Si se cargaran con pallet adentro, el cálculo cambia bastante: el pallet ocupa y no se apila igual. **Hay que confirmarlo** |
| **Hay eslingas y cuerdas** de amarre | El amarre come unos centímetros y obliga a dejar acceso. Es parte de por qué la capacidad práctica < la teórica |

> ⚠️ **Sobre el piso libre: no concluyo que los camiones vayan a medio cargar.** Las
> fotos pueden ser de una carga **en curso**, o de un viaje limitado por la ruta o
> por lo que había que despachar ese día. Sería un error usar estas fotos como
> evidencia de que se desaprovecha espacio. **Pero es la primera pregunta que la
> herramienta va a poder responder con datos** en vez de con impresiones.

### La forma más barata de validar el simulador

Las fotos sugieren un método de aceptación que no cuesta nada y vale mucho:

> **Calibrar contra una carga real.** Tomar un camión ya cargado, contar lo que
> entró de verdad, y comprobar que el simulador dé ese número. Si el cálculo dice
> 46 bolsas y en la práctica entraron 38, la diferencia es el factor de
> aprovechamiento real — y se ajusta.

Eso convierte la herramienta de "estimación teórica" en "reproduce lo que ya
pasa", que es lo único que hace que bodega le crea. Va como paso nuevo en la
fase 1.

### Otro regalo de las fotos

Las tres patentes son legibles, así que **la tabla `vehiculos` tiene sus primeras
tres filas identificadas** y se ve a simple vista que son de tamaños distintos (el
de la tercera foto es notablemente más largo).

**No escribo las patentes en este documento a propósito:** el repositorio es
**público** (decisión D-012), y la bitácora ya tiene una entrada sobre información
sensible que llegó a la rama pública antes de notarlo. Van por canal interno junto
con las medidas.

### Y una señal de que el 3D va a funcionar

El visor de la fase 2 va a verse **muy parecido a estas fotos**: un muro de bultos
al fondo y cajas hacia la puerta. Eso importa más de lo que parece — si la pantalla
se parece a lo que la gente de bodega ve todos los días, la va a entender sin
capacitación. Si se pareciera a un plano de ingeniería, no.

---

## 3. Catálogo real de Dali (verificado en el sitio oficial, 31-jul-2026)

Consultado `importadoradali.cl`. Líneas publicadas: **Agua, Ferretería, Fitness,
Hogar, Embalaje, Solar**. Según Marcos, lo que importa para cotizar carga es la
**línea Agua más las máquinas**; **solar nunca se vende**.

| Familia | Qué entra | Para el simulador |
|---|---|---|
| **Botellones vacíos** | El bulto de mayor volumen y menor peso | 🔴 Crítico |
| **Máquinas** | Llenadoras 100 y 200 · plantas de osmosis 500 y 1 tera · lavadoras | 🔴 Crítico — no apilables |
| **Dispensadores** | Básicos, de ventilador, de compresor (3 tamaños distintos) | 🟡 Importante |
| **Insumos** | Filtros, membranas, tapas | 🟢 Menor, pero fácil de medir |
| Ferretería / Fitness / Hogar / Embalaje | Se venden, rara vez en carga mixta grande | ⬜ Fuera de la primera tanda |
| Solar | Nunca se vende | ⬜ Fuera de alcance |

**El sitio no publica dimensiones ni pesos de nada.** Confirma que la fase 0 (medir)
es obligatoria y que no hay atajo por catálogo web.

Lista concreta a medir en la fase 0 — **unas 15 a 20 filas**, no 5.000:

```
Botellón vacío (cada capacidad que se venda)
Llenadora 100 · Llenadora 200
Planta osmosis 500 · Planta osmosis 1 tera
Lavadora (cada modelo)
Dispensador básico · de ventilador · de compresor
Caja de tapas · caja de filtros · membranas
```

---

## 4. Lo que ya existe en DaliGo (verificado, commit `b5d9702`)

| Verificado | Resultado |
|---|---|
| Columnas de medidas en `productos` | ✅ `peso_kg`, `alto_cm`, `ancho_cm`, `largo_cm` — `2026_06_08_120000_create_productos_table.php:24-27` |
| Por qué existen | La migración lo dice textual: *"Guarda lo que Bsale NO tiene y necesitamos para cotizar despacho"* |
| ¿Las llena la sincronización? | ❌ **No, y no puede.** Bsale no tiene esos campos (`docs/BSALE_API.md`, confirmado por ausencia) |
| ¿Hay dónde cargarlas? | ✅ Formulario, importador masivo, métrica de completitud en `ProductoController.php:58-61` |
| ¿Hay camión o capacidad? | ❌ **No existe** |
| Patrón para librería pesada | ✅ Import dinámico, ya usado con `qrcode` — `resources/js/app.js:912-929` |
| Ancho de página | `listado` = `max-w-7xl` — `resources/views/layouts/app.blade.php:45` |

---

## 5. Solo escritorio: qué ahorra

Desaparece del trabajo: PWA, `service worker`, cola offline, QA a 375px y en
simulador de iPhone, barra de envío fija, acordeón móvil, anti-zoom de iOS, barrido
táctil de 44px, y el 3D en celular (donde el rendimiento se discute).

Lo único a resolver: la pantalla necesita más ancho que `max-w-7xl` — token nuevo
en `layouts/app.blade.php:45`. Y en pantalla angosta debe **decir** que es de
escritorio, no romperse en silencio.

---

## 6. Paso a paso

Cada fase queda funcionando sola. Unidad: una persona a tiempo parcial (5-6 h/día).

### Fase 0 — Medir bultos y camiones (3 a 5 días)

| Paso | Qué se hace | Capa |
|---|---|---|
| **P-CARGA-01** | Tabla `tipos_bulto`: nombre, medidas, peso, **unidades por bulto** (5 para la bolsa de botellones), **si es apilable y cuántos de alto**, si soporta peso encima, y **orientación fija** cuando la tiene. Las 15-20 filas del §3 | Migración + Modelo |
| **P-CARGA-02** | Medir y pesar. Para los botellones se mide **la bolsa de 5 armada y acostada** (§2bis), no el botellón. **Las máquinas son las que más importan** y las más incómodas: hay que tomar el bulto real, con patas y salientes, no el del folleto | Operación + Seeder |
| **P-CARGA-03** | Tabla `vehiculos`: **medidas útiles interiores** y carga máxima en kg, **una fila por cada camión de la flota** — Marcos confirma que entra distinta cantidad según el camión, y eso es justamente lo que la herramienta viene a resolver. La medida útil no es la del folleto: hay que medir cada camión por dentro | Migración + Modelo + CRUD |

> **Medir la bolsa armada, no calcularla.** Cómo van los 5 botellones dentro (en
> fila, en cruz) no importa si se mide el bulto terminado en la posición en que
> viaja: acostado, pico a la puerta.

### Fase 1 — El simulador que responde (2 a 3 semanas)

| Paso | Qué se hace | Capa |
|---|---|---|
| **P-CARGA-04** | Servicio `App\Services\Carga\CalculoDeCarga`: recibe líneas (tipo + cantidad) y un vehículo. Devuelve **volumen, peso y piso ocupados**, y cuál se agotó primero | Servicio |
| **P-CARGA-05** | **Reglas de apilado** (movido desde la fase 3 por el §2): las máquinas van al piso y no llevan nada encima; los botellones se apilan hasta su límite. Es lo que decide si el camión rinde 60% o 95% | Servicio |
| **P-CARGA-06** | **Rejilla por división entera** para los bultos de orientación fija (§2bis): `floor(ancho/ancho) × floor(alto/alto) × floor(largo/largo)`. Es **exacto**, no estimativo, porque reproduce el patrón real de carga. Y no sobreestima como la división de volúmenes | Servicio |
| **P-CARGA-07** | **Acomodo por ZONAS**, como en las fotos (§2ter): muro de bolsas al fondo contra la cabina, cajas y máquinas hacia la puerta, con límite de apilado propio por tipo. Reproduce el patrón real y evita el empaque 3D genérico | Servicio |
| **P-CARGA-08** | **El máximo alcanzable**: *"con esa llenadora adentro te caben 28 bolsas (140 botellones), no 40"* — el dato con el que el vendedor negocia. Y **por camión**, que es la pregunta que hoy se responde de memoria | Servicio |
| **P-CARGA-09** | Pantalla: elegir camión, agregar líneas, veredicto con **factor limitante** y tres barras (volumen / peso / piso) | Controlador + Vista |
| **P-CARGA-10** | **Escenarios guardados** (*"carga típica Coquimbo"*) para no rearmar cada vez | Migración + Controlador |
| **P-CARGA-11** | Resumen imprimible o PDF para adjuntar a la cotización | Vista |
| **P-CARGA-12** | **Calibrar contra una carga real** (§2ter): contar lo que entró de verdad en un camión ya cargado y ajustar el factor de aprovechamiento hasta que el cálculo lo reproduzca. Es el paso que hace que bodega le crea, y no cuesta desarrollo — cuesta una visita al patio | Operación + Servicio |
| **P-CARGA-13** | Tests: que una máquina no acepte carga encima, que el volumen mande sobre el peso, que un bulto más alto que la caja no entre, que el muro respete su límite de filas, y los bordes (cantidad 0, tipo sin medidas, camión sin carga máxima) | Tests |
| **P-CARGA-14** | Permiso nuevo en `config/permissions.php` — en `labels` **y** en `grupos`, o cae en "Generales" | config + Seeder |

**Al terminar la fase 1 la herramienta ya responde la pregunta del pedido.**

### Fase 2 — El visor 3D (3 a 4 semanas)

Acá el 3D tiene un valor que no tenía en la versión operativa: **se le puede
mostrar al cliente.** Una imagen del camión cargado en una cotización convence más
que un número — y con botellones y máquinas mezclados, es lo que hace entendible
por qué no cabe más.

| Paso | Qué se hace | Capa |
|---|---|---|
| **P-CARGA-15** | Librería 3D por **import dinámico**, copiando `qrcode` en `app.js:912-929`: chunk aparte, solo en esta pantalla. `npm run build` y **commitear `public/build/`** (el servidor no tiene Node) | JS + build |
| **P-CARGA-16** | Escena: caja del camión en alambre, un prisma por bulto, rotar y zoom con el mouse. `InstancedMesh` para los cientos de botellones | JS |
| **P-CARGA-17** | Color por **tipo de bulto** con leyenda. Dentro del lienzo los colores son datos, no decoración — conviene sancionarlo como excepción de la paleta de 4, igual que la D-013 de los squircles del Inicio | JS |
| **P-CARGA-18** | Captura de la escena para pegar en la cotización | JS |
| **P-CARGA-19** | Aviso en pantalla angosta: *"esta vista es de escritorio"* | Vista |

### Fase 3 — Refinamientos (3 a 6 semanas, opcional)

| Paso | Qué se hace |
|---|---|
| **P-CARGA-20** | **Varios camiones / cuántos viajes**: *"entran en 2 viajes"* |
| **P-CARGA-21** | Orientaciones permitidas y "este lado arriba" |
| **P-CARGA-22** | Distribución de peso por eje — solo si se usa para carga real, no para cotizar |
| **P-CARGA-23** | Traer cantidades desde una cotización existente. **Único paso que conecta con el resto de DaliGo**, y a propósito va al final |

**Hasta responder la pregunta del pedido: 3 a 4 semanas.** Con 3D: **6 a 8
semanas.** El resto es opcional.

---

## 7. Peso técnico: ¿engorda la app?

**No.**

| | Comprimido |
|---|---|
| `app.js` global hoy | ~38 KB |
| CSS global hoy | ~11 KB |
| Librería 3D (cajas + cámara), chunk aparte | ~150-170 KB |

Se baja **una vez, en una sola pantalla**, y queda en caché. En las otras ~100
pantallas: **cero bytes extra**. Es lo que el import dinámico de `qrcode` ya
resolvió en este repo.

**Servidor:** el cálculo de la fase 1 es aritmética de capas — no se nota. Si se
pasa a empaque 3D real, conviene calcularlo en el navegador por el hosting
compartido (§9bis del informe de facturación).

**No es AutoCAD.** CAD sirve para dibujar geometría precisa: curvas, tolerancias,
planos. Acá los bultos son prismas de tres números. No hay nada que dibujar.

---

## 8. Decisiones antes de abrir la unidad

| # | Decisión | De quién |
|---|---|---|
| 1 | ¿Se autoriza **medir y pesar** los 15-20 tipos de bulto, máquinas incluidas? Sin eso la herramienta miente | Logística |
| 2 | ¿Los botellones vacíos viajan sueltos, en jaula o en pallet? ¿Cuántos de alto? | Logística / Bodega |
| 3 | ¿Qué camiones entran a la tabla? Hacen falta medidas interiores y carga máxima del permiso de circulación | Logística |
| 4 | ¿Se corta en la fase 1 (sin 3D) y se evalúa, o se compromete hasta la 2? | Gerencia |
| 5 | ¿Colores por tipo en el lienzo 3D como excepción sancionada de la paleta? | Dueño del diseño |
| 6 | Número de módulo y lugar en `RUTA-MAESTRA.md` | Director |

### Riesgo de alcance, dicho derecho

**El ciclo de la factura (M04 → M05 → M07 → M08) está en 0%** y es el objetivo
central: 35 de los 105 puntos del tracker. Este módulo no lo toca técnicamente,
pero **compite por las mismas horas**.

Ya pasó una vez: el pivote de julio construyó M17 Servicio en terreno, que no
estaba en el plan, y quedó anotado como retroactiva **R-002** en `RUTA-MAESTRA.md`
§11 para que no volviera a pasar sin rastro.

No es un argumento contra la idea, sino para que **la decisión sea explícita de
Gerencia**, y para presentarla como lo que es: 3 a 4 semanas evaluables antes de
comprometer el resto.

### Fuera de alcance declarado

- Celular y tablet (pedido explícito: solo escritorio).
- Línea Solar (nunca se vende) y, en la primera tanda, Ferretería / Fitness /
  Hogar / Embalaje.
- Empaque "óptimo": es NP-duro. Se usan reglas de apilado y heurísticas, no la
  solución perfecta. **Hay que decirlo en pantalla** o alguien va a discutir con
  el resultado.
- Uso como documento legal de carga: es estimación para cotizar.

---

## 9. Cómo convertirlo en plan fino, cuando se decida

1. Re-verificar el §4 contra el código del día (se sella el 31-jul-2026 y caduca a
   los 7 días por la regla del `README.md` de `docs/planes/`).
2. Copiar la estructura de `docs/planes/PLAN-DESPACHOS-V1.md`.
3. Guardarlo como `docs/planes/PLAN-CARGA-V1.md` **con sello de vigencia**.
4. Agregar la unidad a `RUTA-MAESTRA.md` §5 y al tracker §10 — un paso hecho sin
   marcar ahí es trabajo que no existe.
5. Archivar este documento con el banner de superado, sin borrarlo.

---

## 10. Para presentarlo en una reunión

> «Es una calculadora de carga para cotizar ventas lejos: el vendedor escribe 200
> botellones, 20 cajas de tapas, 10 dispensadores y una lavadora, elige el camión y
> el sistema dice si cabe y qué queda afuera. **No toca facturación ni despachos**,
> así que no puede romper nada de lo que hoy funciona.
>
> Haciendo la cuenta apareció el dato que ordena el proyecto: **como los botellones
> se venden vacíos, el camión se llena por espacio, no por peso.** Ese ejemplo usa
> el 19% de la carga útil y el 65% del volumen. Por eso lo que hay que resolver es
> geométrico, y por eso el visor 3D no es adorno.
>
> Y el caso difícil es justo el nuestro: **llenadoras, plantas de osmosis y
> lavadoras son grandes y no se les puede poner nada encima**, mezcladas con
> cientos de botellones que sí se apilan. Ahí sumar volúmenes miente: la pregunta
> real es si los botellones aprovechan el espacio que sobra alrededor de la
> máquina.
>
> Fase 1, que ya responde: **3 a 4 semanas**, y lo primero son 3 a 5 días de medir
> unos 15 bultos y los camiones — trabajo de bodega. Con visor 3D para adjuntar a
> la cotización: 6 a 8 semanas. No engorda la app: la librería 3D se descarga solo
> en esa pantalla, igual que ya hacemos con los códigos QR.
>
> Lo que hay que decidir es si estas semanas salen del ciclo de la factura, que hoy
> está en cero. Se puede cortar en la fase 1 y evaluar antes de seguir.»
