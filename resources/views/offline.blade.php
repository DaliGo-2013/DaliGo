{{--
    Pagina de fallback offline (la sirve el service worker cuando una navegacion
    falla por red). Es deliberadamente AUTOSUFICIENTE: estilos inline y cero
    assets externos, porque debe renderizar desde el cache del SW sin CSS ni
    fuentes disponibles — excepcion documentada a la regla "sin hex hardcodeado"
    de CLAUDE.md (el naranjo #EA580C inline es el de la marca).

    REGLA DE INVALIDACION: si tocas este archivo, sube la version de CACHE en
    public/sw.js (el SW solo se actualiza cuando cambia sw.js; este Blade queda
    precacheado hasta ese bump). Comentario espejo en public/sw.js.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sin conexión · DaliGo</title>
</head>
<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fafafa;font-family:ui-sans-serif,system-ui,sans-serif;color:#171717;text-align:center;padding:1rem">
    <div>
        <div style="width:64px;height:64px;margin:0 auto 1rem;border-radius:16px;background:#EA580C;color:#fff;font-size:2rem;font-weight:700;line-height:64px">D</div>
        <h1 style="font-size:1.25rem;margin:0 0 .5rem;font-weight:600">Sin conexión</h1>
        <p style="font-size:.875rem;color:#525252;margin:0 0 1rem;max-width:24rem">
            No hay señal en este momento. Nada de lo que ya enviaste se pierde.
        </p>

        {{-- Qué SÍ se puede seguir haciendo. Se nombra SOLO lo que la app cumple
             de verdad: la única pantalla con cola offline es "Mi producción"
             (resources/js/offline-queue.js, enganchada en produccion/mi-reporte),
             las tandas viven en IndexedDB —sobreviven cerrar la app— y se drenan
             solas con el evento `online`.

             A propósito NO hay un enlace a Mi producción: el service worker sirve
             las navegaciones desde la red y solo precachea ESTA página, así que
             tocar ese enlace sin señal volvería a caer aquí. Lo que sí funciona
             es volver a la pantalla que ya estaba cargada (history.back()). --}}
        <div style="text-align:left;max-width:24rem;margin:0 auto 1.25rem;border:1px solid #e5e5e5;border-radius:12px;background:#fff;padding:.875rem 1rem">
            <p style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#737373;margin:0 0 .5rem">
                Lo que sí sigue funcionando
            </p>
            <p style="font-size:.875rem;color:#404040;margin:0 0 .625rem">
                <strong>Las tandas que registraste en Mi producción están guardadas</strong>
                en el teléfono y se envían solas cuando vuelva la señal. No las
                escribas de nuevo, y no pasa nada si cierras la app.
            </p>
            <p style="font-size:.875rem;color:#404040;margin:0">
                Si estabas registrando tandas, vuelve atrás sin recargar y sigue:
                esa pantalla ya está cargada y funciona sin señal.
            </p>
        </div>

        <p style="font-size:.8125rem;color:#737373;margin:0 0 1.25rem;max-width:24rem">
            El resto de las pantallas necesita señal.
        </p>

        <div style="display:flex;flex-direction:column;gap:.5rem;max-width:24rem;margin:0 auto">
            <button onclick="history.back()" style="min-height:48px;padding:0 1.5rem;border:0;border-radius:8px;background:#EA580C;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer">
                Volver a lo que estaba
            </button>
            <button onclick="window.location.reload()" style="min-height:48px;padding:0 1.5rem;border:1px solid #d4d4d4;border-radius:8px;background:#fff;color:#404040;font-size:.875rem;font-weight:600;cursor:pointer">
                Reintentar
            </button>
        </div>
    </div>
</body>
</html>
