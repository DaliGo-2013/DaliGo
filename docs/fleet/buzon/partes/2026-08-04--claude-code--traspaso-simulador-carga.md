# Parte / TRASPASO — sesión de Claude Code del 04-ago-2026 (dueño: Marcos)

> **Para la IA que recibe esto.** Es un traspaso de contexto para seguir en otra
> cuenta. Antes de tocar nada leé, en este orden: `CLAUDE.md` (reglas vivas +
> bitácora de errores), `docs/RUTA-MAESTRA.md` §0 (el ESTADO vive ahí, no acá) y
> `docs/planes/README.md` (regla del sello de vigencia).
>
> Trabajé en **directo sobre `main`** con el dueño al lado, no en rama. El repo es
> **público** (D-012): nunca escribir patentes, credenciales ni rutas sensibles.

---

## 1. Estado del repo al cerrar

- Rama `main`, **7 commits adelante de `origin/main`, SIN PUSHEAR** (los 6 de la
  tabla + este parte).
- ⚠️ **Pushear a `main` DESPLIEGA A PRODUCCIÓN** (`.github/workflows/deploy.yml`:
  SSH a HostGator + `db:seed --force`). No es solo publicar código.
- El repo tiene **otra sesión trabajando en paralelo** que empuja seguido: aparecí
  «behind 36» dos veces en una tarde. **Mergear `origin/main` antes de cualquier
  cosa**, y volver a correr la suite después del merge, no antes.

### Los commits sin pushear

| Commit | Qué |
|---|---|
| `1d4e560` | `docs(dte)` — justificación de los plazos de facturación |
| `a15dd2a` | `feat(roles)` — permisos por **tarjetas** en vez de pestañas con scroll lateral |
| `827a16b` | `docs(tests)` — corrige el comentario del candado del POST |
| `856dc62` | `fix(listados)` — un listado vacío dice POR QUÉ está vacío |
| `7461c2c` | `style(menu)` — «Mi producción» y «Aprobaciones» en imprenta |
| `a7c4e9b` | `feat(logistica)` — **simulador de carga** (lo grande de la sesión) |

*(Los dos primeros de `docs/` sobre el simulador de carga —`cc6ae55`, `e976fba`—
ya están pusheados.)*

### Suite

**1.441 verdes / 10.838 aserciones, CERO ROJOS** — corrida completa con todo lo de
esta sesión adentro, incluido el simulador. Ésa es la cifra de referencia: si quien
retome ve menos, es porque mergeó `origin/main` (la otra sesión suma tests seguido),
no porque algo se rompió.

---

## 2. DOS DECISIONES ABIERTAS DEL DUEÑO (no avanzar sin respuesta)

1. **Las tarjetas de permisos (`a15dd2a`) YA ESTÁN EN PRODUCCIÓN** y él nunca dijo
   si las deja. Llegaron ahí porque la otra sesión pusheó `main` y se llevó mis
   commits. Fue mi error de secuencia: debí dejarlas en rama. Revertir es
   `git revert --no-edit 827a16b a15dd2a && npm run build && git push` (dispara un
   segundo deploy).
2. **Dashboard quedó como el único ítem del menú en minúscula.** Él pidió imprenta
   solo para «Mi producción» y «Aprobaciones». Hay un candado en `SidebarTest` que
   se pone ROJO si alguien lo "uniforma" — es a propósito, obliga a preguntar.

---

## 3. Lo grande: SIMULADOR DE CARGA (Logística)

### Para qué es

El dueño quiere que **ventas pueda armar una carga virtual antes de prometerle a un
cliente lejano**, en vez de adivinar. La flota va de **18,9 a 67,7 m³ — 3,6 veces**,
así que nadie puede tenerlo en la cabeza. No es para el día a día del despacho: es
para **cotizar y planificar rutas**.

**NO es operativo:** no escribe despachos, no toca facturación, no registra nada.
Si se apaga la pantalla, no se pierde un dato.

### Dónde está

- Menú: **LOGÍSTICA → Simulador de carga** (`admin.carga.index`), entre Vehículos y
  Conductores.
- `app/Services/Carga/CalculoDeCarga.php` — el motor
- `app/Http/Controllers/Admin/SimuladorCargaController.php`
- `resources/views/admin/carga/index.blade.php`
- `resources/js/carga3d.js` — visor, **import dinámico** desde `app.js`
- `app/Models/TipoBulto.php` + `database/seeders/TiposBultoSeeder.php`
- Migraciones `2026_08_04_180000` (tabla `tipos_bulto`) y `_180100` (4 columnas a
  `vehiculos`)
- `tests/Feature/Carga/CalculoDeCargaTest.php` — 10 candados

### Las tres reglas que NO se pueden romper

1. **CENTÍMETROS ENTEROS, nunca metros con coma flotante.** Un prototipo en float
   daba 900 botellones de 10 L en el HD35; el real es 675. Causa: `2.00 // 0.40`
   devuelve 4 en vez de 5 (0,40×5 no es exactamente 2,0 en binario).
2. **Nunca dividir volúmenes.** Para el HD35 esa cuenta da 109 bolsas y la realidad
   son 84 — 125 botellones prometidos que no entran. Hay candado.
3. **Un simulador que exagera es peor que no tenerlo.** Todo redondeo va hacia
   abajo, y lo que no está medido NO se siembra con números inventados.

### Datos del negocio que costó sacar (no volver a preguntarlos)

- Los botellones se venden **SIEMPRE VACÍOS** (~1 kg, no ~21). **El límite es el
  VOLUMEN, no el peso** — con 420 botellones en el HD35 el peso va al 30% y el
  espacio al 100%.
- La unidad de carga es la **BOLSA DE 5**, no el botellón. `unidades`=5 convierte
  «28 bolsas» en «140 botellones», que es el número con el que habla el vendedor.
- Los botellones van **acostados, pico hacia la puerta** → `orientacion_fija`.
  Hallazgo: esa posición **resulta ser la óptima de las 6**. La bodega venía
  haciéndolo bien.
- El botellón de **10 L rinde ~1,6×** el de 20 L. Dato de venta.
- Patrón de estiba real (visto en fotos): **muro de bolsas al fondo contra la
  cabina · máquinas en jaula a lo largo de un costado · cajas hacia la puerta ·
  pasillo de paso** que NO es capacidad.
- Las **máquinas viajan en jaula de madera SOBRE PALLET**. Se mide la **jaula con
  el pallet**, no la máquina. Traen impreso *"keep off / box lid may collapse"* →
  `soporta_peso_encima = false`.
- ⚠️ **Aparecieron cajones rotulados `UN3480` (baterías de litio, clase 9)** en una
  foto de carga habitual. Para el simulador: fuera de alcance y con aviso en
  pantalla. **Aparte del simulador: alguien debería verificar que esos despachos
  cumplan la normativa de mercancías peligrosas.** No hay dato de que no la
  cumplan — solo que el rotulado amerita preguntar.

### Flota (medidas del dueño, ya verificadas)

| Vehículo | Largo × Ancho × Alto (m) | m³ | Botellones 20 L |
|---|---|---|---|
| Contenedor 40' (va en el Mercedes) | 12,03 × 2,35 × 2,39 | 67,7 | 1.620 |
| HINO 500 (FC 1118) | 7,97 × 2,60 × 2,66 | 55,1 | 1.500 |
| Chevy 3 | 8,00 × 2,30 × 2,45 | 45,1 | 960 |
| H1 | 7,00 × 2,30 × 2,30 | 37,0 | 800 |
| Hyundai HD35 | 4,30 × 2,00 × 2,20 | 18,9 | 420 |

El contenedor sale de su propia placa (`42G1`, CU.CAP. 67,7 m³, NET 28.800 kg).
**Duda que quedó sin cerrar del todo:** la fila **H1** no cuadra — un Hyundai H1 es
una furgoneta de ~5,15 m totales, no 7,00 m con 37 m³. Lo planteé y el dueño no lo
corrigió. **Vale confirmarlo antes de que alguien cotice con ese número.**

### Bultos medidos (cm) — ya sembrados

| Bulto | Largo | Ancho | Alto | Unidades |
|---|---|---|---|---|
| Bolsa 5× botellón 20 L | 130 | 26 | 51 | 5 |
| Bolsa 5× botellón 10 L | 110 | 21 | 40 | 5 |
| Caja de soportes | 79 | 24 | 43 | 1 |
| Caja de tapas | 46 | 37 | 42 | 1 |
| Dispensador LB-07B | 33 | 29 | 87 | 1 (11 kg) |
| Dispensador LB-93 | 38 | 33 | 98 | 1 (15,5 kg) |

El dueño avisó que varían **1-2 cm**. No importa salvo al filo; para eso está el
factor de calibración.

### Lo que falta, en orden

1. **Cargar las medidas útiles de los 5 camiones** en la ficha de cada vehículo
   (sección «Caja de carga»). Hasta entonces la pantalla muestra el aviso de
   "falta medir" — es correcto, no es un bug.
2. **Medir las jaulas**: llenadora 100 y 200, osmosis 500 y 1 tera, lavadora,
   sopladora. Están anotadas como pendientes en el seeder.
3. **CARGAS MIXTAS** — hoy simula un tipo de bulto a la vez. Es lo que más falta
   para que sirva de verdad: lo real es botellones + cajas + máquina junta, por
   zonas (ver el patrón de estiba de arriba).
4. **Calibrar**: contar UNA carga real y ajustar el factor hasta que el cálculo la
   reproduzca. Es lo que hace que bodega le crea. Cuesta una visita al patio.
5. **Cálculo inverso** («¿cuánto puedo vender en este camión?»): el dueño lo pidió
   explícitamente y es más valioso que el «¿cabe?». Mismo motor, al revés.

### Documentación de contexto

`docs/EXPLORACION-CARGA-3D.md` (pusheado) tiene el análisis largo: fases, plazos,
peso técnico, los hallazgos de las fotos. **Está desactualizado en dos puntos:**
dice que la tabla `vehiculos` no existe (ya existe) y no refleja el recorte a
"solo envíos grandes" ni el cálculo inverso.

---

## 4. Lo otro que se hizo en la sesión

- **Permisos por tarjetas** (`a15dd2a`): la franja de pestañas necesitaba scroll
  horizontal y **escondía áreas enteras sin avisar**. Ahora rejilla que envuelve,
  con contador y barra por área. Candado clave: las áreas cerradas se ocultan solo
  con `x-show`, así que sus casillas **siguen viajando en el POST** — si alguien lo
  cambia por un `@if` de Blade, guardar borra permisos en silencio.
- **Listados vacíos** (`856dc62`): 23 de 26 ya tenían mensaje, pero era
  **incondicional**. Las tarjetas del Inicio enlazan a listados FILTRADOS, así que
  «Reparadas 0» decía *"No hay órdenes registradas"* con 2.732 órdenes en el
  sistema. Nuevo `<x-lista-vacia>` con dos mensajes. **No usa `array_filter()`**
  para detectar filtros: descarta `'0'`, y `activo=0` es un filtro legítimo.
- **Menú en imprenta** (`7461c2c`): bandera `imprenta` en `MenuPrincipal`; la
  mayúscula la pone el CSS, el label sigue en minúscula (regla del propio archivo).

---

## 5. Cómo verificar UI sin sesión (patrón útil de este repo)

No se ingresan contraseñas. Para ver una pantalla real: un test temporal con
`actingAs()` que vuelca el HTML a `public/`, reemplazando `http://daligo.test/` por
`/` (si no, los assets no cargan), servirlo con `php -S` y abrirlo. **Borrar el
volcado y el test antes de commitear.** Está en la bitácora [2026-07-26].

## 6. Reglas del proyecto que me mordieron

- Tras tocar Blade/CSS/JS: `php artisan view:clear && npm run build` y
  **commitear `public/build/`** (el servidor no tiene Node). Grepear el bundle por
  `lg:flex`/`lg:hidden` antes de commitear — es una regresión histórica del purge.
- Mutar un test para probar que muerde: **commitear ANTES** de mutar, o
  `git checkout --` se lleva el trabajo (bitácora 2026-07-28).
- `docs/planes/` solo acepta planes con **sello de vigencia**, escritos justo antes
  de arrancar la unidad. El simulador se construyó por pedido directo **sin plan
  fino** y **sin entrada en `RUTA-MAESTRA`** — las dos cosas quedan pendientes.
