{{-- Poll de firma con recarga (extraído al 4º uso — MSG-3): pide un JSON
     liviano cada `intervalo` ms y, SOLO si la firma del contenido cambió,
     recarga la página completa (sin websockets, sin estados a medias). El
     guard de visibilityState evita gastar red con la pestaña oculta. La
     firma horneada al render y la del endpoint deben salir de la MISMA
     función del controller — si divergieran, el monitor recargaría en loop
     (o nunca). OJO: el poll de Servicio Técnico (banner con delta, sin
     recarga) es OTRA conducta a propósito y NO usa este componente —
     misma coexistencia declarada que el _tabs de ST con x-tab-nav.
     Script inline: el layout no expone un @stack('scripts'). --}}
@props(['url', 'firma', 'intervalo' => 20000])

<script>
    (function () {
        var base = @js($firma);
        var url = @js($url);
        function comprobar() {
            if (document.visibilityState !== 'visible') return;
            fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (d && d.firma !== base) window.location.reload();
                })
                .catch(function () {});
        }
        setInterval(comprobar, {{ (int) $intervalo }});
        // MSG-5: tick INMEDIATO al volver a la pestaña/app — cero cambio de
        // conducta (misma comprobacion; nadie recarga si la firma no cambio),
        // solo se adelanta el proximo tick. Los 3 consumidores lo heredan.
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') comprobar();
        });
    })();
</script>
