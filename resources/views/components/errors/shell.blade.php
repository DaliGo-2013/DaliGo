@props(['titulo'])

{{-- Shell de las paginas de error. HTML STANDALONE a proposito, con el CSS
     inline (excepcion sancionada al "sin hex hardcodeado", igual que
     errors/419.blade.php y offline.blade.php):

     1) NO puede usar <x-app-layout>: components/layout/sidebar.blade.php hace
        Auth::user()->name sin guardas y estas paginas las ve tambien un
        VISITANTE SIN LOGIN (link firmado caducado del QR).
     2) Si la vista de error falla, Handler::renderHttpException() se come el
        throw y cae en la pantalla generica de Symfony — pero SOLO en produccion
        (en local con debug la relanza). Una vista de error que dependa de mas
        piezas es un bumeran silencioso en prod.
     3) Tampoco usa @vite: si el manifest esta a medias, la pagina de error
        moriria justo cuando mas se necesita. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · DaliGo</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #fafafa; color: #171717; }
        .wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 48px 24px; }
        .logo { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 12px; background: #ea580c; color: #fff; font-weight: 900; font-size: 20px; }
        h1 { margin: 24px 0 0; font-size: 24px; font-weight: 600; letter-spacing: -0.01em; }
        p { margin: 8px 0 0; max-width: 28rem; font-size: 14px; color: #737373; line-height: 1.5; }
        a.btn { margin-top: 32px; display: inline-block; background: #ea580c; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 12px 24px; border-radius: 8px; transition: background .15s; }
        a.btn:hover { background: #c2410c; }
        /* Codigo de incidente (solo errors/500): el padding-left extra compensa
           el letter-spacing, que si no descentra el texto hacia la derecha. */
        .codigo-label { margin: 24px 0 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #a3a3a3; }
        .codigo { margin: 6px 0 0; display: inline-block; padding: 10px 14px 10px 18px; border: 1px solid #e5e5e5; border-radius: 8px; background: #fff; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 20px; font-weight: 700; letter-spacing: .18em; color: #171717; }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="logo">D</span>
        {{ $slot }}
    </div>
</body>
</html>
