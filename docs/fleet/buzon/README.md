# Buzón de la flota — dictados y partes SIN copy/paste

> Operativo desde 2026-07-13. Reemplaza el traslado manual de dictados y partes que hacía
> Mauricio entre computadores. El bus ahora es git; Mauricio solo abre sesiones y decide.

## Cómo funciona

**Al ABRIR sesión (todo asiento Code):**
1. `git fetch origin && git pull --rebase`
2. Lee TU dictado vigente: `docs/fleet/buzon/dictados/<tu-asiento>.md`
3. Ejecuta. Ante duda de asignación: parte corto al buzón preguntando, no improvises.

**Al CERRAR sesión (todo asiento Code):**
1. Escribe tu parte de cierre (formato FLOTA.md §5) en
   `docs/fleet/buzon/partes/AAAA-MM-DD--<asiento>--<tarea-corta>.md`
2. Commit (`fleet-parte: ...`) + push. Es el ÚNICO lugar de docs/fleet donde los
   Forjadores pueden escribir (enmienda de territorio en FLOTA.md §1).

**El Director:** lee `partes/` al abrir, verifica con evidencia, actualiza tablero/ledger,
y reescribe `dictados/<asiento>.md` con la siguiente orden. Un dictado por archivo, siempre
el VIGENTE (la historia queda en git).

**Mauricio:** abre la sesión de cada asiento y dice la frase única:
**«revisa tu buzón y ejecuta»**. Nada más que copiar. Los asientos de NAVEGADOR no tienen
git: sus paquetes viven en `dictados/navegador.md` y Mauricio los copia desde ahí (fuente
única), y pega la respuesta del navegador él mismo en el buzón de partes O al Director.

## Reglas
- El dictado del buzón MANDA sobre cualquier instrucción vieja de chat.
- Nada [x] sin evidencia — el parte cita commits/archivos como siempre.
- /usage: Mauricio lo corre al abrir y cerrar; el asiento lo anota en su parte si lo recibe.
