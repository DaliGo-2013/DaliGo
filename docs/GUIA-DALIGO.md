# Guía DaliGo — tutorial de las 4 capas

> Documento para entender **cómo está construida la app** sin haber visto el chat
> donde se escribió. Sigue un recorrido visual y con analogías, y apunta a los
> archivos exactos con números de línea para que puedas abrirlos en VS Code y
> leerlos. Pensado para colaboradores nuevos (humanos o IAs) y para releer en un
> mes cuando ya no recuerdes los detalles.
>
> **Cómo leerlo:** los recorridos están pensados para hacerse **con VS Code
> abierto** en la carpeta del proyecto. Cuando veas `Ctrl+P → archivo`, ábrelo;
> cuando veas una línea (ej. `L55`), salta con `Ctrl+G → 55`.

---

## 0. Panorama general

### ¿Qué es DaliGo?
Una aplicación web hecha con **Laravel 12** (un "kit de construcción" en PHP). Le
da a Importadora DALI un panel interno para administrar usuarios, sucursales,
catálogo, configuración y auditoría. No reemplaza a Bsale (su facturador
electrónico): lo **complementa** trayendo los datos y agregando lo que falta
(peso/dimensiones de productos, multi-sucursal, trazabilidad, etc.).

### Las carpetas que importan
Pensá la app como un edificio con capas. Cada cosa vive en su lugar:

| Capa | Qué hace | Carpeta |
|---|---|---|
| **Vistas** | Lo que ves en el navegador (botones, tablas, formularios) | `resources/views/` |
| **Rutas** | El "directorio": qué URL lleva a qué pantalla | `routes/web.php` |
| **Controladores** | La lógica: qué pasa cuando haces clic | `app/Http/Controllers/` |
| **Modelos** | Cómo se representan los datos (un Usuario, un Producto…) | `app/Models/` |
| **Migraciones** | Las "instrucciones" para crear las tablas de la base de datos | `database/migrations/` |
| **Seeders** | Datos iniciales (roles, permisos, sucursales base) | `database/seeders/` |
| **Servicios** | Lógica especial reutilizable (ej. cliente de Bsale) | `app/Services/` |
| **Comandos** | Tareas que se corren con `php artisan …` | `app/Console/Commands/` |
| **Configuración** | Parámetros leídos del `.env` | `config/` |
| **Tests** | Pruebas automatizadas | `tests/` |

### El ciclo completo del código
```
Tu PC                    GitHub                    HostGator
─────                    ──────                    ─────────
escribes código ─push──> repo (main) ─Actions──> servidor: deploy.sh
   ↑                                                  │
   │                                                  ↓
   └────────── visible en ──── staging.impdali.cl ───┘
```
> Tú nunca tocas el servidor a mano: pusheas a `main` y todo se actualiza solo.
> Excepción: el archivo `.env` (donde van los secretos) vive solo en el servidor.

---

## 1. Cómo está organizada la app

Esta sección explica el patrón que se repite en **todas** las pantallas. Si lo
entiendes una vez, entiendes la app completa.

### La analogía del restaurante
- La **ruta** es el mesero que te sienta en la mesa correcta.
- El **controlador** es la cocina que prepara el pedido.
- El **modelo** es la receta + la despensa (los datos).
- La **vista** es el plato servido que tú ves.

### Recorrido: qué pasa cuando entras a `/admin/sucursales`

#### 1.1. La ruta — "el mesero"
**Archivo:** `routes/web.php` · **Línea ~52**

```php
Route::resource('sucursales', SucursalController::class)
    ->parameters(['sucursales' => 'sucursal'])
    ->middleware('permission:manage sucursales')
    ->except(['show']);
```

- `Route::resource(...)` es un atajo: crea de un golpe **todas** las URLs del
  CRUD (listar, crear, editar, borrar).
- `->middleware('permission:manage sucursales')` es el **guardia de seguridad**:
  antes de dejar pasar, revisa que tu usuario tenga ese permiso. Si no → 403.
  *(Ver sección 2 para el detalle de roles/permisos.)*
- `->except(['show'])` significa "no necesitamos la pantalla de ver una sucursal
  sola; solo listar, crear, editar, borrar".

#### 1.2. El controlador — "la cocina"
**Archivo:** `app/Http/Controllers/Admin/SucursalController.php`

```php
// L17 — listado
public function index(): View
{
    $sucursales = Sucursal::withCount('users')->orderBy('nombre')->get();
    return view('admin.sucursales.index', ['sucursales' => $sucursales]);
}
```
- `index()` se ejecuta al **listar**. Pide a la BD las sucursales ordenadas por
  nombre, contando los usuarios de cada una, y le pasa el resultado a la vista.

```php
// L67 — validación
private function validateData(Request $request, ?Sucursal $sucursal = null): array
{
    return $request->validate([
        'nombre' => ['required', 'string', 'max:191'],
        'codigo' => ['required', 'string', 'max:191',
                     Rule::unique('sucursales', 'codigo')->ignore($sucursal)],
        ...
    ]);
}
```
- `validateData()` es el **control de calidad** del formulario. Verifica que el
  nombre venga, que el código no se repita, etc. Si algo falla, Laravel **vuelve
  automáticamente** al formulario con los errores en rojo.

#### 1.3. El modelo — "la receta + despensa"
**Archivo:** `app/Models/Sucursal.php`

```php
// L11 — el modelo es Auditable (cada cambio se registra; ver sección 2/auditoría)
class Sucursal extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait;

    // L17 — el nombre de la tabla en la BD
    protected $table = 'sucursales';

    // L19-26 — qué campos se pueden llenar con datos del usuario
    protected $fillable = ['nombre', 'codigo', 'ciudad', 'direccion',
                            'es_central', 'activa'];

    // L41 — la RELACIÓN: una sucursal tiene muchos usuarios
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
```
- El modelo es la **representación PHP** de una fila de la tabla `sucursales`.
- Las **relaciones** (`hasMany`, `belongsTo`) son lo que permite hacer cosas
  como `$sucursal->users` y obtener los usuarios de esa sucursal.

#### 1.4. La migración — "el plano de la tabla"
**Archivo:** `database/migrations/2026_06_05_120000_create_sucursales_table.php`

```php
Schema::create('sucursales', function (Blueprint $table) {
    $table->id();
    $table->string('nombre');
    $table->string('codigo')->unique();   // código único
    $table->string('ciudad')->nullable();
    $table->boolean('es_central')->default(false);
    $table->timestamps();
});
```
- Es la "receta" para **crear la tabla** en la BD. Se ejecuta automáticamente
  con `php artisan migrate`.
- Por eso **nunca creamos tablas a mano**: quedan versionadas en código y se
  aplican igual en local, staging y producción.

#### 1.5. La vista — "el plato servido"
**Archivo:** `resources/views/admin/sucursales/index.blade.php`

La vista toma la lista `$sucursales` que mandó el controlador y la dibuja. Usa
**piezas reutilizables** llamadas componentes Blade:

```blade
<x-list-card title="Sucursales" :count="$sucursales->count()">
    @foreach ($sucursales as $sucursal)
        <x-list-row>...</x-list-row>
    @endforeach
</x-list-card>
```

- Las piezas (`<x-list-card>`, `<x-list-row>`, `<x-primary-button>`, etc.) viven
  en `resources/views/components/`. Por eso todas las pantallas se ven iguales.

### 🔁 Resumen del viaje
```
Tú entras a /admin/sucursales
   → routes/web.php (la ruta) revisa permiso y manda a…
   → SucursalController@index (la cocina) pide datos a…
   → Modelo Sucursal (la receta) que lee la tabla creada por…
   → la migración, y entrega todo a…
   → la vista index.blade.php que dibuja la pantalla.
```
Este mismo viaje se repite **idéntico** en Usuarios, Roles, Configuración,
Catálogo, Auditoría. Solo cambian los nombres.

---

## 2. Roles y permisos

Esta sección explica **quién puede hacer qué**. Es lo que está detrás del
"guardia" que vimos en la sección anterior.

### La analogía de las tarjetas de acceso
- Un **permiso** es una puerta concreta ("entrar a Sucursales").
- Un **rol** es una tarjeta que abre cierto grupo de puertas ("jefe_ventas").
- Un **usuario** lleva una tarjeta → puede abrir las puertas que esa tarjeta
  permite.
- El `admin` tiene la tarjeta maestra (todas las puertas).

Esto lo maneja un paquete instalado en el proyecto: **spatie/laravel-permission**.

### 2.1. Dónde se definen — la fuente de verdad
**Archivo:** `database/seeders/RolesAndPermissionsSeeder.php`

```php
// L23-36 — la lista de PERMISOS (las puertas que existen)
$permissions = [
    'view users', 'create users', 'edit users', 'delete users',
    'manage roles', 'manage sucursales', 'manage settings',
    'view audit', 'manage productos',
    'report production', 'manage production',
];

foreach ($permissions as $name) {
    Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
}

// L42-43 — el rol admin recibe TODOS los permisos
$admin = Role::firstOrCreate(['name' => 'admin', ...]);
$admin->givePermissionTo($permissions);

// L59-65 — roles del negocio con sus permisos de partida
Role::firstOrCreate(['name' => 'vendedor', ...]);  // sin permisos aún
Role::firstOrCreate(['name' => 'jefe_ventas', ...])->givePermissionTo('view users');
Role::firstOrCreate(['name' => 'jefe_bodega', ...])->givePermissionTo('view users');
Role::firstOrCreate(['name' => 'conductor', ...]);
Role::firstOrCreate(['name' => 'tecnico', ...]);
```

#### ¿Qué es `firstOrCreate`? — la palabra mágica "idempotente"
Significa "créalo solo si no existe". Por eso este archivo **se corre en cada
despliegue sin miedo**: no duplica ni borra; solo garantiza que los roles y
permisos base existan. Esa propiedad se llama **idempotencia**: correrlo una vez
o cien veces da el mismo resultado.

### 2.2. Cómo se hace cumplir — el guardia en 3 niveles

#### Nivel A: la puerta de la URL
**Archivo:** `routes/web.php`

```php
->middleware('permission:manage sucursales')
```
Si tu rol no tiene ese permiso → la app responde **403 (prohibido)**.

#### Nivel B: el menú
**Archivo:** `resources/views/layouts/navigation.blade.php`

Busca con `Ctrl+F` → `@can`. Verás bloques así:
```blade
@can('manage sucursales')
    <x-nav-link ...>Sucursales</x-nav-link>
@endcan
```
Si no tienes el permiso, **ni siquiera ves el enlace** en el menú. *(Doble
candado: aunque adivinaras la URL, el Nivel A te frena.)*

#### Nivel C: reglas de negocio
En los controladores hay protecciones de sentido común. Ejemplo en
`UserController`: una guarda llamada `wouldRemoveLastAdmin()` impide que el
sistema se quede sin ningún admin (te bloquea si intentas quitar el rol al
último).

### 2.3. Cómo un usuario obtiene su rol
**Archivo:** `app/Models/User.php` · **Línea ~16**

```php
use HasFactory, Notifiable, HasRoles, AuditableTrait;
```

Ese `HasRoles` (de spatie) es lo que le da al usuario los superpoderes
`assignRole()`, `hasRole()`, `can()`. Asignar un rol se hace:
- Desde la **pantalla de Usuarios** (admin → editar usuario → elegir rol).
- Con el comando `php artisan app:assign-role correo@impdali.cl admin`.

### 2.4. Roles base intocables
**Archivo:** `app/Http/Controllers/Admin/RoleController.php` · **L18**

```php
private const BASE_ROLES = ['admin', 'member'];
```
Estos dos no se pueden renombrar ni borrar desde la UI (para no romper la app).

### 2.5. Etiquetas en español
**Archivo:** `config/permissions.php`

Los nombres técnicos (`manage sucursales`) se muestran al admin como
"Gestionar sucursales" usando este mapa. Cuando agregas un permiso nuevo,
agrégalo aquí también.

### 🔁 Resumen: "¿puedo entrar a esta pantalla?"
```
Entras a /admin/sucursales
   → ¿tu rol tiene el permiso 'manage sucursales'? (lo definió el Seeder)
       · NO → 403, y además ni viste el enlace en el menú (@can)
       · SÍ → pasas, y el controlador hace su trabajo
```

---

## 3. Catálogo + sincronización con Bsale

Esta sección explica el comando `bsale:sync-catalog` que trae los productos
desde Bsale, y la idea más importante del diseño: **preservar peso/dimensiones
locales**.

### El "para qué"
Bsale ya tiene el catálogo: 2.846 productos con nombre, SKU, categoría, código
de barras. Pero **a Bsale le faltan dos cosas críticas** para DaliGo: **peso** y
**dimensiones** (alto, ancho, largo). Sin esos datos no se puede cotizar
despacho con Chilexpress/Starken/Cruz del Sur.

### La analogía del carnet y la ficha del gimnasio
- Bsale es tu **carnet de identidad** (nombre, RUT, etc.).
- DaliGo es tu **ficha del gimnasio** que además guarda tu peso y estatura.
- Necesitas ambas, pero **no quieres copiar la ficha del gimnasio cada vez que
  actualizan el carnet**.
- Solución: sincronizamos los datos del carnet (Bsale → DaliGo) pero
  conservamos siempre el peso/estatura del gimnasio.

### El viaje del comando
Cuando en cPanel escribiste `php artisan bsale:sync-catalog`, esto es lo que
pasó:

```
Tu comando
   → BsaleSyncCatalog (la orden, app/Console/Commands/)
       → CatalogSync (el cerebro, app/Services/Bsale/)
           → BsaleClient (el mensajero, habla con Bsale por HTTPS)
               → API de Bsale (le pide los productos)
           ← Bsale responde con páginas de 50 productos
       ← CatalogSync recorre cada producto y lo guarda
   ← Devuelve la tabla "2846 creados, 0 errores"
```

### 3.1. El comando — "el botón rojo"
**Archivo:** `app/Console/Commands/BsaleSyncCatalog.php`

```php
// L17 — la "firma" del comando
protected $signature = 'bsale:sync-catalog';

// L23-27 — verificación de token (si falta, falla limpio)
if (! $client->hasToken()) {
    $this->error('Falta BSALE_ACCESS_TOKEN en .env...');
    return self::FAILURE;
}

// L32 — llama al cerebro
$stats = (new CatalogSync($client))->run();
```

El comando es solo el **wrapper** que imprime la tabla bonita. El trabajo real
lo hace `CatalogSync`. Esto permite reutilizar el cerebro desde otros lugares
(un webhook, un job, un test).

### 3.2. El mensajero — el cliente HTTP
**Archivo:** `app/Services/Bsale/BsaleClient.php`

```php
// L84-88 — configuración del cliente HTTP
return Http::withHeaders(['access_token' => $this->token])  // el sello identificador
    ->acceptJson()           // "respóndeme en JSON"
    ->timeout(30)            // si no contesta en 30s, abandona
    ->retry(2, 500, throw: false);  // reintenta 2 veces si falla la red
```

- **L43** — si Bsale responde **429 (demasiadas peticiones)**, esperamos y
  reintentamos (límite de Bsale: 3.000 peticiones por 5 minutos).
- **L65** — `each(...)` es la **paginación**. Bsale entrega los productos de 50
  en 50:

```php
public function each(string $path, array $query = [], int $limit = 50): Generator
{
    $offset = 0;
    do {
        $page = $this->get($path, array_merge($query, ['limit' => $limit, 'offset' => $offset]));
        foreach ($page['items'] ?? [] as $item) {
            yield $item;  // ← lo entrega UNO POR UNO, no todos juntos
        }
        $offset += $limit;
    } while (...);
}
```

`yield` es magia de PHP: en vez de cargar **los 4.184 productos en memoria de
golpe**, los va entregando uno a uno. Si tuvieras 100.000 productos no se te
llenaría la RAM.

### 3.3. El cerebro — la lógica de preservación
**Archivo:** `app/Services/Bsale/CatalogSync.php` · **ESTE es el archivo
más importante.**

#### La preservación estructural (L87–98)
```php
// Solo campos que MANDA Bsale. Los locales (peso/dims/marca/atributos/descripcion)
// se omiten a proposito => no se tocan en update y quedan null en create.
$bsaleFields = [
    'sku' => $sku,
    'barcode' => $barcode,
    'nombre' => ...,
    'categoria' => ...,
    'activo' => ...,
    'bsale_variant_id' => $variantId,
    'bsale_product_id' => $productId,
    'bsale_product_type_id' => $ptId,
];
```

**Mira lo que NO está en este arreglo:**
- ❌ `peso_kg`
- ❌ `alto_cm`, `ancho_cm`, `largo_cm`
- ❌ `marca`
- ❌ `descripcion`
- ❌ `atributos`

**Por qué es bonito:** Laravel solo actualiza los campos que están en este
arreglo. Si `peso_kg` no aparece, **literalmente nunca se toca**. No hay un
`if peso existe → no sobrescribir`. Es **estructural**: no se puede sobrescribir
lo que ni siquiera mencionamos.

#### Las tres formas de "guardar" un producto (L100–136)
Cuando llega un producto de Bsale, el cerebro intenta tres caminos en orden:

```
1) ¿Ya existe un producto local con este bsale_variant_id?
   → SÍ: lo actualizo (preservando los locales).
        Esto cubre el caso RENOMBRE: si en Bsale cambian el SKU,
        encontramos la fila por el id de Bsale, no por el SKU.

2) Si no, ¿hay un producto local con este SKU pero SIN enlace a Bsale?
   → SÍ: lo "adopto" (Llena los campos de Bsale, preserva el peso/marca).
        Esto cubre el caso: "primero lo subiste por CSV con peso, ahora
        la sync llega y le conecta con su variante de Bsale".

3) Si no, lo creo nuevo (el peso/dimensiones quedan vacíos hasta que
   alguien los cargue por CSV o a mano).
```

Esta **escalera de 3 pasos** es lo que hace al comando **idempotente** y
robusto. Por eso puedes correrlo dos veces sin duplicar: la segunda vez todos
caen en el paso 1 (actualizar).

#### Sin auditoría por fila (L31)
```php
Producto::withoutAuditing(function () use (...) { ... });
```

Si auditáramos cada upsert, tendríamos 2.846 filas nuevas en `audits` cada vez
que corremos la sync. En vez de eso, registramos **un solo resumen en el log**.
Los cambios manuales que haga un humano en el panel sí siguen auditados normal.

### 3.4. El puente PC ↔ servidor: el `.env`
**Archivo:** `.env` (en tu PC) y `/home4/impdali/daligo/.env` (en HostGator)

```
BSALE_BASE_URL=https://api.bsale.io/v1
BSALE_ACCESS_TOKEN=<el token de tu cuenta de Bsale>
```

Y en **`config/services.php`**, Laravel lee esos valores:
```php
'bsale' => [
    'base_url' => env('BSALE_BASE_URL', 'https://api.bsale.io/v1'),
    'token' => env('BSALE_ACCESS_TOKEN'),
],
```

**El `.env` jamás se sube a GitHub** (está en `.gitignore`). Cada entorno tiene
el suyo. Por eso tuviste que poner el token **dos veces**: una en tu PC y otra
en el servidor.

### 🎯 Resumen del catálogo

| Pregunta | Respuesta |
|---|---|
| ¿Qué hace el comando? | Trae todos los productos activos de Bsale y los guarda/actualiza en DaliGo. |
| ¿Por qué no pisa el peso/dimensiones? | Porque el arreglo `$bsaleFields` simplemente no incluye esos campos. Es estructural. |
| ¿Qué pasa si lo corro dos veces? | La segunda vez todos los productos se "actualizan" (no se duplican). Idempotente. |
| ¿Necesita internet en el servidor? | Sí, el servidor habla con `api.bsale.io` por HTTPS. |
| ¿Y si el token se vence? | El comando falla con un mensaje claro. Actualizas el `.env`, corres `config:cache`, y listo. |

---

## 4. Despliegue automático

Esta sección explica el **viaje invisible** que pasa cada vez que pusheas: tu
código llega solo a producción sin que toques el servidor.

### La analogía Uber
- Tú pides el viaje (`git push`).
- Un sistema invisible (Uber/GitHub Actions) encuentra un conductor (un
  "runner" temporal).
- El conductor recoge el paquete (tu código) y lo lleva a la dirección
  (HostGator).
- Tú nunca hablas con el conductor; solo ves "tu pedido llegó".

### El viaje paso a paso
```
1. Tú escribes en tu PC:  git push origin main
       ↓
2. GitHub recibe el código nuevo
       ↓
3. GitHub Actions detecta el push y arranca un "runner" (mini servidor temporal)
       ↓
4. El runner se conecta por SSH a HostGator (con una llave secreta)
       ↓
5. El runner ejecuta UN solo comando: bash deploy.sh
       ↓
6. deploy.sh actualiza la app (pull, dependencias, migraciones, caché)
       ↓
7. Listo. staging.impdali.cl tiene la versión nueva.
```

Hay **dos archivos clave** en este viaje.

### 4.1. La receta del runner — GitHub Actions
**Archivo:** `.github/workflows/deploy.yml`

```yaml
# L5-8 — cuándo se dispara
on:
  push:
    branches: [main]      # cada push a main
  workflow_dispatch:      # o cuando le doy "Run workflow" en Actions
```

```yaml
# L12-14 — concurrency: nunca dos deploys en paralelo
concurrency:
  group: deploy-prod
  cancel-in-progress: false   # si llega un push mientras hay otro deploy, espera
```

```yaml
# L29-32 — los secretos (cifrados en GitHub: ni tú los puedes ver)
env:
  SSH_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
  SSH_HOST: ${{ secrets.SSH_HOST }}
  SSH_PORT: ${{ secrets.SSH_PORT }}
  SSH_USER: ${{ secrets.SSH_USER }}
```

```yaml
# L37-43 — el corazón: SSH al servidor y bash deploy.sh
ssh -i ~/.ssh/deploy_key -p "$SSH_PORT" ... \
  "$SSH_USER@$SSH_HOST" \
  "cd /home4/impdali/daligo && bash deploy.sh"
```

#### Cómo verlo en vivo
1. Ve a `https://github.com/DaliGo-2013/DaliGo`.
2. Pestaña **Actions** (arriba).
3. Lista de todos los despliegues con 🟢 verde / 🔴 rojo.
4. Clic en uno → ves los pasos detallados. Si falló, te dice en qué línea.

### 4.2. El script de actualización del servidor
**Archivo:** `deploy.sh` — vive en el repo pero **se ejecuta en el servidor**.

```bash
# L22 — anti-cPanel
git checkout -- public/.htaccess
# cPanel reescribe este archivo. Lo descartamos antes del pull para que no haya
# conflicto.

# L23 — baja el código nuevo
git pull --ff-only origin main

# L27 — instala dependencias de PHP (paquetes externos)
composer install --no-dev --optimize-autoloader --no-interaction
# --no-dev: no instala herramientas de desarrollo (PHPUnit, etc.).
# --optimize-autoloader: hace la app más rápida en producción.

# L30 — actualiza la estructura de la BD
php artisan migrate --force
# El --force es porque no hay nadie para responder "¿estás seguro?".
# Esto es lo que añadió las columnas barcode y bsale_product_type_id la
# última vez.

# L33 — siembra roles/permisos (idempotente)
php artisan db:seed --force

# L36 — enlace al storage público
php artisan storage:link --force

# L39-41 — cachea config/rutas/vistas (la app es más rápida así)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# L44 — borra el caché de permisos (spatie lo cachea por 24h)
php artisan permission:cache-reset
# Esto es lo que hace que un permiso nuevo (ej. manage productos) tome
# efecto inmediatamente tras el deploy.
```

### 4.3. Lo que `deploy.sh` NO hace (y por qué)

| Cosa | Por qué no |
|---|---|
| Compilar CSS/JS | El servidor no tiene Node.js. Tú corres `npm run build` **en tu PC** y commiteas la carpeta `public/build/`. |
| Correr tests | Los tests corren en tu PC antes de pushear. Si pusheas con tests rotos, el deploy se hace igual (podríamos agregar la verificación en el futuro). |
| Correr `bsale:sync-catalog` | A propósito: la sync se ejecuta a mano por ahora (como hiciste tú en la terminal). El día que activemos cron en cPanel, se podría agendar. |

### 4.4. Dónde "vive" cada cosa en HostGator (cPanel)

| Carpeta en el servidor | Qué hay ahí |
|---|---|
| `/home4/impdali/daligo/` | La app completa (lo que git pulleó) |
| `/home4/impdali/daligo/public/` | Lo único que ve internet (carpeta pública) |
| `/home4/impdali/daligo/.env` | El archivo de configuración con secretos (no en git) |
| `/home4/impdali/daligo/storage/logs/laravel.log` | Los logs de Laravel (errores, warnings, info) |

#### Acceso en cPanel
- **File Manager** → navega a `/home4/impdali/daligo/`.
- **Terminal** (en la sección "Advanced") → para correr `php artisan ...`.

### 🎯 Resumen del despliegue

| Pregunta | Respuesta |
|---|---|
| ¿Qué dispara un deploy? | Un `git push` a `main`, o un clic en "Run workflow" en GitHub Actions. |
| ¿Dónde veo si falló? | Pestaña **Actions** del repo en GitHub. |
| ¿Qué hace exactamente? | Baja el código, instala dependencias, migra la BD, siembra roles, cachea. |
| ¿Cuánto tarda? | Típicamente 1–3 minutos. |
| ¿Y si edito un archivo en cPanel a mano? | El siguiente `git pull` lo **sobrescribe**. Nunca edites código directo en el servidor; edita en tu PC y haz push. *(Excepción: `.env`.)* |

---

## 5. Glosario rápido

| Término | Qué significa |
|---|---|
| **Artisan** | El comando de Laravel (`php artisan ...`) para tareas administrativas. |
| **Blade** | El lenguaje de plantillas de Laravel (los archivos `.blade.php`). |
| **CI/CD** | Continuous Integration / Continuous Deployment: automatización del despliegue. |
| **Componente Blade** | Pieza reutilizable de UI (`<x-list-card>`, `<x-primary-button>`). Vive en `resources/views/components/`. |
| **Composer** | Gestor de paquetes de PHP. Equivalente a `npm` en JavaScript. |
| **Controlador** | Clase PHP en `app/Http/Controllers/` con la lógica de una pantalla. |
| **Eloquent** | El ORM de Laravel: la forma de hablar con la BD a través de modelos. |
| **Fillable** | Lista de campos que un modelo permite asignar en masa (anti-injection). |
| **Idempotente** | Que correrlo una vez o muchas veces da el mismo resultado (sin duplicar). |
| **Middleware** | "Filtro" que corre antes de un controlador (ej. el guardia `permission:`). |
| **Migración** | Archivo PHP en `database/migrations/` que crea/altera tablas. |
| **Modelo** | Clase PHP en `app/Models/` que representa una tabla. |
| **Ruta** | Mapeo URL → controlador, definido en `routes/web.php`. |
| **Seeder** | Archivo PHP en `database/seeders/` que inserta datos iniciales. |
| **Servicio** | Clase de lógica reutilizable, en `app/Services/`. |
| **Vista** | Archivo Blade en `resources/views/` que define la pantalla. |

---

## 6. Cómo abrir cada cosa

### VS Code
- **Abrir un archivo por nombre:** `Ctrl + P` → escribes el nombre → Enter.
- **Ir a una línea exacta:** `Ctrl + G` → escribes el número → Enter.
- **Buscar texto:** `Ctrl + F` (en el archivo abierto) o `Ctrl + Shift + F`
  (en todo el proyecto).
- **Navegación rápida:** clic derecho sobre un nombre de clase → "Go to
  definition" (te lleva al archivo donde está definida).

### GitHub
- **Pestaña Actions** → ver los despliegues, sus logs, si fallaron.
- **Pestaña Issues** → reportar bugs / tareas (no se usa mucho aún en DaliGo).
- **Settings → Secrets** → ver/agregar secretos (llaves SSH, etc.).

### cPanel (hosting)
- **File Manager** → navegar archivos del servidor (`/home4/impdali/daligo/`).
- **Terminal** (sección "Advanced") → consola SSH desde el navegador.
- **MySQL Databases** → ver las BDs (`impdali_daligo`).
- **MultiPHP Manager** → ver/cambiar la versión de PHP del sitio.

---

## 7. Cheat sheet — comandos que usas más seguido

### En tu PC (la carpeta del proyecto)
```bash
# Subir cambios a producción (dispara deploy automático)
git push origin main

# Crear/recrear la BD local con datos iniciales
php artisan migrate:fresh --seed

# Correr todos los tests
php artisan test

# Compilar CSS/JS (después de tocar Blade/CSS/JS)
npm run build

# Probar la sync de Bsale local
php artisan bsale:sync-catalog

# Si cambiaste .env y los cambios no se reflejan
php artisan config:clear
```

### En el servidor (cPanel Terminal)
```bash
# Posicionarse en la app
cd /home4/impdali/daligo

# Re-cachear la config (tras editar .env)
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache

# Correr la sync de Bsale en producción
/opt/cpanel/ea-php83/root/usr/bin/php artisan bsale:sync-catalog

# Ver los logs de errores recientes
tail -50 storage/logs/laravel.log

# Estado de las migraciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status

# Ver qué tareas programadas hay y cuándo corren
/opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:list

# Historial de las syncs automáticas de Bsale
tail -50 storage/logs/bsale-sync.log
```

### Tareas programadas (cron)
Las syncs de Bsale (catálogo, clientes, precios) corren **solas cada hora** gracias a un cron en
cPanel que ejecuta `php artisan schedule:run` cada minuto (Laravel decide qué toca). Se definen en
`routes/console.php`. Para cambiar la frecuencia, edita ahí (`->hourly()` → `->dailyAt('03:00')`,
etc.) y haz push. El cron de cPanel se crea **una sola vez**:
`* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1`

---

## 8. Para seguir aprendiendo

- **Laravel oficial:** https://laravel.com/docs/12.x
- **Spatie Permission (roles/permisos):** https://spatie.be/docs/laravel-permission/v8/introduction
- **Owen-it Auditing (auditoría):** https://laravel-auditing.com/
- **Bsale API:** https://docs.bsale.dev/ + `docs/BSALE_API.md` en este repo.
- **HANDOFF.md** (en este repo) — el estado de ingeniería actual.
- **CLAUDE.md** (en este repo) — bitácora de errores resueltos y reglas vivas.

---

*Documento de referencia para el aprendizaje de DaliGo. Mantenlo actualizado a
medida que la app crezca: cuando un patrón cambie aquí, también debería
actualizarse esta guía.*
