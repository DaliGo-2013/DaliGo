<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sesión expirada · DaliGo</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #fafafa; color: #171717; }
        .wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 48px 24px; }
        .logo { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 12px; background: #ea580c; color: #fff; font-weight: 900; font-size: 20px; }
        h1 { margin: 24px 0 0; font-size: 24px; font-weight: 600; letter-spacing: -0.01em; }
        p { margin: 8px 0 0; max-width: 28rem; font-size: 14px; color: #737373; line-height: 1.5; }
        a.btn { margin-top: 32px; display: inline-block; background: #ea580c; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 12px 24px; border-radius: 8px; transition: background .15s; }
        a.btn:hover { background: #c2410c; }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="logo">D</span>
        <h1>Tu sesión expiró</h1>
        <p>Por seguridad, el formulario caducó (o estuvo abierto demasiado tiempo). Vuelve atrás e inténtalo nuevamente.</p>
        <a class="btn" href="{{ url('/login') }}">Volver a iniciar sesión</a>
    </div>
</body>
</html>
