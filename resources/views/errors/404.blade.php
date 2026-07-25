{{-- 404. Igual que el 403: al autenticado que navega lo atiende el render() de
     bootstrap/app.php (Inicio + aviso); esta pagina cubre al visitante y las
     acciones no-GET. NUNCA se imprime $exception->getMessage(): ModelNotFound
     produce "No query results for model [App\Models\...] 5". --}}
<x-errors.shell titulo="No encontrado">
    @auth
        <h1>No encontramos esa página</h1>
        <p>El enlace puede estar roto o el registro ya no existe.</p>
        <a class="btn" href="{{ route('dashboard') }}">Ir al Inicio</a>
    @else
        <h1>No encontramos esa página</h1>
        <p>Revisa el enlace e inténtalo de nuevo.</p>
    @endauth
</x-errors.shell>
