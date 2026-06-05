# DaliGo — Guía del proyecto y bitácora

> **Documento vivo.** Recopila las *formas correctas de hacer las cosas* en DaliGo y una
> **bitácora de errores y soluciones** que crece con el tiempo. Mientras más lo alimentemos,
> más fácil se vuelve trabajar en el proyecto y más rápido entran los nuevos colaboradores.

---

## ⭐ La regla de oro (obligatoria para todos)

**Antes de trabajar:** lee este documento.

**Cuando tengas una dificultad o un error** —de deploy, build, creación de herramientas/features,
infraestructura, base de datos o **cualquier tema de la app web y relacionados**— **y lo resuelvas:**

1. Agrega una entrada **al inicio** de la [Bitácora](#bitácora-de-errores-y-soluciones) usando la plantilla.
2. Hazlo **ANTES de cerrar / commitear** la tarea. No lo dejes "para después".
3. Sé concreto: el **síntoma** (mensaje de error textual si lo hay), la **causa raíz** y la
   **solución que SÍ funcionó** (comando / archivo / commit).

Esto aplica por igual a **colaboradores humanos** y a **asistentes de IA** (Claude Code lee este
archivo automáticamente al iniciar cada sesión en este repo).

---

## Cómo trabajar en DaliGo (formas correctas)

### Stack y convenciones
- **Laravel 12** + **Blade**, **PHP 8.3.31** (fijado en `composer.json` → `config.platform.php`; local = producción).
- **Tailwind CSS v4** vía `@tailwindcss/vite` (sintaxis `@import 'tailwindcss'`, **no** `@tailwind`).
  No existen `tailwind.config.js`/`postcss.config.js` — la config vive en [resources/css/app.css](resources/css/app.css).
- **Color de marca centralizado** en `resources/css/app.css` → bloque `@theme` (`--color-brand-*`, naranjo `#EA580C`).
  **No hardcodear** naranjos en las vistas; usar las utilidades `brand-*`.
- **UI en español**, tema claro y sobrio, **motion sutil** (`dg-enter` / `dg-shake`, respeta `prefers-reduced-motion`).
- **Permisos** con `spatie/laravel-permission`; el seeder `RolesAndPermissionsSeeder` es **idempotente** (se puede correr siempre).

### Reglas de diseño (UI)
Tema **claro y sobrio**: naranjo de marca + blanco + neutros, **sin degradados**. **Reutiliza los componentes Blade** de `resources/views/components/`; no recrees botones/inputs/iconos inline.

**Colores** — siempre utilidades, nunca hex hardcodeado:
- Marca: `brand-600` (#ea580c, primario), `brand-700` (#c2410c, hover), `brand-50`/`brand-100` (fondos y anillos suaves). Definidos en [resources/css/app.css](resources/css/app.css) (`@theme`); para cambiar el tono, edítalo **solo ahí**.
- Neutros: escala `neutral-*` (texto `neutral-900/700/600/500/400`, fondos `neutral-50/100`, bordes `neutral-200/300`).
- Destructivo (eliminar): `red-600` (texto/fondo), `red-500` (hover), `red-50` (fondo hover).

**Iconos** — Heroicons estilo **outline**, como componentes Blade en `resources/views/components/icon/`, usados con `<x-icon.nombre />` (hoy: `pencil`, `trash`, `plus`):
- Formato exacto del SVG: `fill="none"`, `viewBox="0 0 24 24"`, `stroke-width="1.5"`, `stroke="currentColor"`, `aria-hidden="true"`. Tamaño por defecto `h-5 w-5` (sobrescribible; `h-4 w-4` dentro de botones).
- El color se **hereda** con `currentColor`: no pongas color en el SVG; contrólalo con `text-*` en el contenedor.
- Icono accionable (botón/enlace): agrega `title="..."` y un `<span class="sr-only">Acción</span>` para accesibilidad.
- Icono nuevo: copia uno existente (ej. `pencil.blade.php`), pega el `path` de Heroicons *outline 24* y conserva el mismo formato.

**Botones** — usa los componentes, no los reconstruyas: `<x-primary-button>` (`bg-brand-600 hover:bg-brand-700`), `<x-secondary-button>` (borde `neutral-300`, fondo blanco), `<x-danger-button>` (`bg-red-600 hover:bg-red-500`). Todos: `rounded-lg`, `text-sm font-semibold`, `shadow-sm`, `transition duration-150`, foco con anillo de marca y `active:scale-[0.98]`. Icon-button de fila: `rounded-lg p-2 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700` (destructivo: `hover:bg-red-50 hover:text-red-600`).

**Formularios** — `<x-text-input>`, `<x-input-label>`, `<x-input-error>`, `<x-input-hint>`. Inputs: `rounded-lg border-neutral-300`, foco `focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30`.

**Radios:** `rounded-lg` (botones/inputs/icon-buttons), `rounded-2xl` (tarjetas), `rounded-full` (badges/pills y avatares).

**Tipografía:** fuente **Instrument Sans** (en `@theme`). Títulos `text-xl font-semibold text-neutral-900`; encabezados de sección `text-xs font-medium uppercase tracking-wide text-neutral-500`; cuerpo `text-sm`; badges/ayudas `text-xs`.

**Tarjetas y listas:** contenedor `rounded-2xl border border-neutral-200 bg-white shadow-sm`; cabecera `border-b border-neutral-100 px-6 py-3`; filas con `divide-y divide-neutral-100`, cada una `flex items-center gap-4 px-6 py-4 hover:bg-neutral-50`. Avatares `h-10 w-10 rounded-full bg-neutral-100`. Para columnas alineadas a la derecha (badge + acciones) dar **ancho fijo** a esas columnas (ver commit `0c3f4fa`).

**Badges / pills:** `inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-100`.

**Motion** (sutil, rápido, con propósito — **sin rebotes ni loops**): `transition duration-150` en estados/hover; `active:scale-[0.98]` al presionar; `dg-enter` para materializar contenedores al cargar; `dg-shake` para feedback de error; **siempre** respetar `prefers-reduced-motion` (ya cubierto en `app.css`).

### Responsive (obligatorio en cada cambio)
La app es **mobile-first**. Toda pantalla y componente debe verse bien y **sin scroll horizontal** a **375px (móvil), 768px (tablet) y ≥1024px (desktop)**.
- Las clases base son para móvil; se escala con prefijos `sm:` / `md:` / `lg:`, nunca al revés.
- **Prohibido** un ancho fijo (`w-28`, `w-64`, …) sin fallback responsive; en móvil usar `w-full` o reflow.
- Filas/tablas con varias columnas deben **reflowar o apilar** en móvil (usa `<x-list-row>`), no desbordar.
- **Cada cambio se entrega ya responsive y verificado** a esos 3 anchos.
- Checklist antes de cerrar: ¿se ve bien a 375/768/1024? ¿sin scroll horizontal? ¿los botones/enlaces son cómodos al tocar en móvil?

### Reutilizar antes de crear (DRY)
**Antes de escribir UI nueva**, revisa el catálogo de abajo y **reutiliza**. Si un patrón se repite (o se va a repetir), **extrae un componente** en `resources/views/components/` en vez de copiar markup. **Prohibido** inline-stylear a mano botones, inputs, selects, checkboxes, badges, íconos o filas que ya tienen componente. Los elementos de uso constante SIEMPRE se componentizan.

#### Catálogo de componentes (`<x-...>`)
- **Layout / página:** `app-layout`, `guest-layout`, `page-header` (título + subtítulo + slot `action`), `list-card` (tarjeta + cabecera + `<ul>`), `list-row` (fila **responsive** con slots `leading`/`meta`/`actions`), `avatar`, `modal`, `dropdown` / `dropdown-link`.
- **Navegación:** `nav-link`, `responsive-nav-link`.
- **Botones / enlaces:** `primary-button`, `secondary-button`, `danger-button`, `button-link` (enlace con estilo primario), `icon-button` (`:href`, `variant=default|danger`, `label`), `secondary-link` (cancelar/volver).
- **Form controls:** `input-label`, `text-input`, `select`, `textarea`, `checkbox`, `radio`, `checkbox-item` (checkbox + label en tarjeta; slot `note`), `input-error`, `input-hint`, `form-footer` (`:cancel` + botón submit en el slot).
- **Feedback:** `badge` (`variant=brand|neutral`), `status-alert` (`:status`), `auth-session-status`.
- **Íconos:** `icon.pencil`, `icon.trash`, `icon.plus` (agrega nuevos como Heroicons outline; ver Reglas de diseño).

Si algo no existe y se va a usar seguido, créalo siguiendo el formato de los componentes existentes.

### Entorno local
- Todo junto: `composer dev` (levanta `serve` + `queue` + `pail` logs + `vite` a la vez).
- O por separado: `php artisan serve` y `npm run dev`.
- BD local de desarrollo: **SQLite**.

### Frontend / assets ⚠️ (gotcha importante)
- Tras tocar **Blade, CSS o JS** → corre **`npm run build`** y **commitea `public/build/` junto con el cambio**.
- `public/build` **está versionado** (no está en `.gitignore`) y el **servidor no tiene Node**.
  Si no recompilas y commiteas, en producción el CSS/JS queda **viejo**. Además Tailwind v4 **purga**
  las clases no usadas, así que cualquier **clase nueva** necesita el rebuild para existir en el bundle.

### Tests
- `composer test` (o `php artisan test`). Framework: **PHPUnit** (no Pest). BD de test: SQLite en memoria.
- Tests en `tests/Feature/` y `tests/Unit/`.

### Deploy
- **`git push origin main` = despliegue automático.** GitHub Actions (`.github/workflows/deploy.yml`)
  entra por **SSH** al servidor y corre **`deploy.sh`** en `/home4/impdali/daligo`.
- `deploy.sh` ya hace, en orden: descartar `public/.htaccess` → `git pull --ff-only` →
  `composer install --no-dev` → `migrate --force` → `db:seed RolesAndPermissionsSeeder --force` →
  `storage:link` → `config:cache` + `route:cache` + `view:cache` → `permission:cache-reset`.
  **No corras seeds/cachés a mano en producción**; ya están cubiertos.
- Mirar el avance en la pestaña **Actions** del repo `DaliGo-2013/DaliGo`. Staging: **staging.impdali.cl**.
- Servidor: HostGator compartido (cPanel + LiteSpeed), PHP **ea-php83**, BD **MySQL 5.7** (`impdali_daligo`), **sin Node**.

---

## Cómo registrar un error en la bitácora

Copia esta plantilla y pégala **al inicio** de la sección Bitácora (las entradas más recientes van arriba):

```markdown
### [AAAA-MM-DD] Título corto del problema
- **Síntoma:** qué se vio (mensaje de error textual si aplica).
- **Causa:** la causa raíz.
- **Solución:** qué se hizo (comando / archivo / commit) que SÍ funcionó.
- **Evitar a futuro:** (opcional) cómo no repetirlo.
```

---

## Bitácora de errores y soluciones

> Las entradas más recientes van arriba. Sembrada con los problemas ya resueltos del proyecto.

### [2026-06-05] Deploy de GitHub Actions: "Too many authentication failures" (llave equivocada en el secret)
- **Síntoma:** los deploys #9–#13 fallan en segundos sin actualizar el server (HEAD se queda atrás, árbol limpio). Log del job: `Received disconnect from <host> port 2222:2: Too many authentication failures` → exit 255. El handshake SSH y el host key SÍ funcionan (`Permanently added ... ED25519`); falla la **autenticación**.
- **Causa raíz:** el secret `SSH_PRIVATE_KEY` de GitHub contenía una llave **RSA** (`SHA256:2CW02O...`) que **no está** en el `authorized_keys` del servidor. El server solo autoriza la llave **ed25519 `daligo-deploy`** (`SHA256:m2QhGn...`, la misma que `~/.ssh/daligo_hostgator` local). El server rechaza la RSA → ssh cae a keyboard-interactive/password → "Too many authentication failures". Funcionó hasta el #8 porque el `authorized_keys` del server se cambió/reseteó después (quedó solo la ed25519), pero el secret siguió con la RSA vieja. **No era KEX ni el ssh-agent** (las 3 primeras teorías, descartadas al leer el `ssh -v` del job).
- **Solución:** actualizar el secret `SSH_PRIVATE_KEY` con la llave **ed25519 autorizada** (`daligo_hostgator`). En el workflow se deja `-o IdentitiesOnly=yes -o IdentityAgent=none` (ofrecer solo la llave de deploy) y `-o KexAlgorithms=+diffie-hellman-group-exchange-sha256` (KEX legacy de HostGator). Para desbloquear producción al toque: `deploy.sh` a mano por SSH con la llave buena.
- **Evitar a futuro:** (1) el secret debe ser **la misma** llave que está en el `authorized_keys` del server — verificar con `ssh-keygen -lf` en ambos lados; preferir ed25519. (2) Si un deploy falla en segundos, **leer el log del job** (`GET /repos/{o}/{r}/actions/jobs/{id}/logs`, con `ssh -v`) ANTES de teorizar.

### [2026-06-04] `updateOrCreate` con columna `date` casteada no actualiza
- **Síntoma:** al reasignar producción (mismo soplador/fecha/turno con otra cantidad), la fila no se duplicaba pero la cantidad no se actualizaba (quedaba la primera).
- **Causa:** `updateOrCreate(['fecha' => $fecha, ...], [...])` compara `fecha` (cast a `date`) por igualdad exacta de string; el valor almacenado incluye hora (`Y-m-d 00:00:00`) y no coincide con `Y-m-d`, dejando el match inconsistente.
- **Solución:** buscar con `whereDate('fecha', $fecha)->where(...)->first()` y luego `update()` o `create()` explícito. Ver `app/Http/Controllers/Admin/ProduccionController.php::asignarStore` (módulo Producción).
- **Evitar a futuro:** para columnas `date`/`datetime` casteadas, usar `whereDate()` al buscar coincidencias; no confiar en la igualdad directa de `updateOrCreate`.

### [2026-06-04] Estilos nuevos de Tailwind no aparecen en producción
- **Síntoma:** un cambio de clases Tailwind se ve en local pero en staging/producción sigue igual.
- **Causa:** `public/build` está versionado y el servidor no tiene Node; no se recompiló ni commiteó el bundle, y Tailwind purga las clases no usadas (las nuevas no existían en el CSS desplegado).
- **Solución:** correr `npm run build` y commitear `public/build/` (manifest + assets `app-*.css/js`) **junto** con los Blade modificados. Ej.: commit `0c3f4fa`.
- **Evitar a futuro:** todo cambio de Blade/CSS/JS termina con `npm run build` + commit del build.

### [2026-06-03] El deploy por GitHub Actions no conecta a HostGator (KEX)
- **Síntoma:** el job de deploy falla con `handshake failed: no common algorithm for key exchange`.
- **Causa:** el SSH de HostGator solo ofrece el key-exchange `diffie-hellman-group-exchange-sha256`, que la librería SSH en Go de `appleboy/ssh-action` no negocia.
- **Solución:** usar el **cliente OpenSSH nativo del runner** (escribir la llave a un archivo y `ssh -i ...`). Ver `.github/workflows/deploy.yml` (commit `ad02f82`).
- **Evitar a futuro:** **no** volver a `appleboy/ssh-action` para este servidor.

### [2026-06-03] `git pull --ff-only` falla en el servidor por `public/.htaccess`
- **Síntoma:** `deploy.sh` aborta en el `git pull` porque el árbol de trabajo está sucio.
- **Causa:** cPanel reinyecta un bloque de handler PHP (solo comentarios) en `public/.htaccess`.
- **Solución:** `git checkout -- public/.htaccess` **antes** del pull (ya está en `deploy.sh`, commit `3b292c5`). cPanel lo vuelve a agregar después sin afectar el funcionamiento.

### [2026-06-03] Los `git push` no disparaban el deploy en el servidor
- **Síntoma:** se pusheaba a `main` pero el servidor nunca actualizaba.
- **Causa:** el `origin` del repo en el servidor apuntaba al repo viejo (`Mauricio-Alvarez-T/DaliGo`).
- **Solución:** `git remote set-url origin https://github.com/DaliGo-2013/DaliGo.git` en el servidor.

### [2026-06-02] 404 al fijar la versión de PHP con `AddHandler` en `.htaccess`
- **Síntoma:** la app responde 404 tras agregar un `AddHandler ...` en `.htaccess` para forzar PHP.
- **Causa:** en este hosting la versión de PHP se controla por **MultiPHP Manager (vhost)**, no por `.htaccess`.
- **Solución:** fijar la versión en **MultiPHP Manager**; no usar `AddHandler`.

### [2026-06-02] Estilos rotos tras instalar Laravel Breeze (Tailwind v3 vs v4)
- **Síntoma:** la UI queda sin estilos / rota después de instalar Breeze.
- **Causa:** Breeze trae **Tailwind v3** (`tailwind.config.js` + `postcss.config.js`), pero el proyecto usa **Tailwind v4**.
- **Solución:** borrar `tailwind.config.js` y `postcss.config.js`, volver `resources/css/app.css` a `@import 'tailwindcss'` y `vite.config.js` a `@tailwindcss/vite` (conservando `alpinejs`). Recompilar con `npm run build`.

---

## Nota de mantención

Si la Bitácora supera ~40 entradas y empieza a pesar, mover **solo esa sección** a `docs/PLAYBOOK.md`
y dejar aquí la regla + un enlace. Por ahora, un único archivo es lo más simple.
