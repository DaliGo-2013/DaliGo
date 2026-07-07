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
        <p style="font-size:.875rem;color:#525252;margin:0 0 1.5rem;max-width:22rem">
            No hay señal en este momento. Tu trabajo no se pierde:
            cuando vuelva la conexión, recarga e inténtalo de nuevo.
        </p>
        <button onclick="window.location.reload()" style="height:48px;padding:0 1.5rem;border:0;border-radius:8px;background:#EA580C;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer">
            Reintentar
        </button>
    </div>
</body>
</html>
