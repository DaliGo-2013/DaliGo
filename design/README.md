# design/ — Bundle de contexto para claude.ai/design

Previews HTML **autocontenidos** del design system de DaliGo + variantes de menú
estilo Talana, para subirlos al proyecto design-system **"DaliGo"** en claude.ai/design
(herramienta DesignSync). Ahí cada archivo aparece como tarjeta y sirve de contexto
para iterar diseños **solo de vista** (sin lógica).

## Estructura

- `src/` — fuentes editables (commiteadas). Cada HTML lleva `<!-- @dsCard group="…" -->`
  como PRIMERA línea y el placeholder `/*__DALIGO_CSS__*/` en un `<style>` del head.
  - `00-brief-daligo.md` — brief con paleta, componentes, inventario del nav y mapeo Talana.
  - `10/11` — fundaciones y componentes (grupo "Fundaciones").
  - `20-22` — pantallas actuales capturadas del server (grupo "Pantallas actuales").
  - `30-35` — variantes de menú V1/V2/V3, desktop + móvil (grupo "Menu Talana").
- `tools/` — scripts Node del pipeline.
- `.capture/` (gitignored) — HTML crudo del harness + CSS compilado.
- `dist/` (gitignored) — bundle final con CSS inline; es el `localDir` de DesignSync.

## Regenerar el bundle

```bash
# 1. Capturar las pantallas reales (SQLite :memory:, datos 100% sintéticos)
php artisan test tests/Design/DesignCaptureTest.php

# 2. Limpiar capturas -> src/2X-*.html
node design/tools/clean-capture.mjs

# 3. Compilar el CSS dedicado (incluye clases de las variantes; requiere red la 1a vez)
npx --yes @tailwindcss/cli@latest -i design/design.css -o design/.capture/design-tw.css --minify

# 4. Ensamblar dist/ (CSS inline + validación @dsCard y <256KiB)
node design/tools/build.mjs
```

Verificar localmente antes de subir: `php -S 127.0.0.1:8890 -t design/dist` y revisar
cada preview a 1280 y 375 px (sin scroll horizontal, paleta de 4, interacciones CSS).

## Subir (DesignSync, desde una sesión de Claude)

1. `list_projects` → proyecto "DaliGo" (crear con `create_project` si no existe).
2. `finalize_plan` con `localDir = design/dist`, writes `["*.html", "*.md"]`.
3. `write_files` por lotes (localPath relativo a dist).

## Reglas

- Datos SIEMPRE sintéticos (factories) — el repo es público.
- Paleta estricta de 4 (ver CLAUDE.md §Reglas de diseño); rojo solo destructivo.
- Interacción de los previews: CSS-only (`<details>`, radios `:checked`, checkbox
  drawer) — nada de JS; el pane puede sanitizar scripts.
- `design/` NO afecta el deploy (docroot es `public/`).
