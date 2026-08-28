<x-app-layout ancho="formulario">
    <div class="py-8">
        <x-volver :href="route('mensajes.index')" titulo="Volver a Mensajes" class="mb-3" />

        <div class="mb-4 flex items-center gap-3">
            <x-avatar>{{ mb_strtoupper(mb_substr($otro?->name ?? '—', 0, 1)) }}</x-avatar>
            <h2 class="text-lg font-semibold leading-tight text-neutral-900">{{ $otro?->name ?? '—' }}</h2>
        </div>

        <x-status-alert :status="session('status')" class="mb-4" />

        {{-- «Mensajes anteriores»: la paginación estándar de la casa. Página 1 =
             lo más reciente (query desc); dentro de la página se lee cronológico. --}}
        @if ($mensajes->hasPages())
            <div class="mb-4">{{ $mensajes->links() }}</div>
        @endif

        {{-- Burbujas por el partial compartido con el endpoint `nuevos` (MSG-5):
             la misma vista pinta el render inicial y el append — cero
             divergencia. El texto SIEMPRE por {{ }}: lo escribe un usuario. --}}
        <div id="hilo-mensajes" class="dg-enter space-y-3">
            @forelse ($mensajes->reverse() as $mensaje)
                @include('mensajes._burbuja', ['mensaje' => $mensaje, 'usuario' => $usuario])
            @empty
                <p class="py-6 text-center text-sm text-neutral-500">Todavía no hay mensajes en esta conversación.</p>
            @endforelse
        </div>

        {{-- Composición (molde kaizen): guarda de texto vacío + online-only —
             sin señal NO se encola (veredicto del dueño, anexo §5.1): se avisa
             y se conserva lo escrito. OJO: cero comillas dobles en el x-data. --}}
        <div class="mt-6"
             x-data="{
                 texto: '',
                 sinSenal: false,
                 enviar(e) {
                     if (! this.texto.trim()) { e.preventDefault(); this.$destacar(this.$refs.grupoTexto); return; }
                     if (this.$store.red && ! this.$store.red.online) {
                         e.preventDefault();
                         this.sinSenal = true;
                         return;
                     }
                     this.sinSenal = false;
                 },
             }">
            <form method="POST" action="{{ route('mensajes.responder', $conversacion) }}"
                  class="space-y-3" x-on:submit="enviar($event)">
                @csrf
                <div x-ref="grupoTexto">
                    <label for="mensaje_texto" class="sr-only">Mensaje</label>
                    <x-textarea id="mensaje_texto" name="texto" rows="2" maxlength="1000"
                                class="mt-1" x-model="texto"
                                placeholder="Escribe un mensaje…"></x-textarea>
                    <x-input-error :messages="$errors->get('texto')" class="mt-2" />
                </div>
                <p x-show="sinSenal" x-cloak class="text-sm text-red-600" data-error-message>
                    Necesitas señal para enviar el mensaje. Lo escrito se conserva: reintenta cuando vuelva la conexión.
                </p>
                <x-primary-button type="submit" class="h-12 w-full justify-center">
                    Enviar
                </x-primary-button>
            </form>
        </div>
    </div>

    {{-- Chat vivo (MSG-5): el hilo TRAE y APPENDEA sin reload — el composer
         conserva lo escrito (el hallazgo del QA del dueño). Solo en la página
         1 ($mensajes->onFirstPage()): en las históricas se lee, no se
         conversa. Guard de reentrada + visibilitychange = tick inmediato al
         volver a la app. Script inline: el layout no expone @stack. --}}
    @if ($mensajes->onFirstPage())
        <script>
            (function () {
                var desde = @js((int) ($mensajes->max('id') ?? 0));
                var url = @js(route('mensajes.nuevos', $conversacion));
                var contenedor = document.getElementById('hilo-mensajes');
                var pidiendo = false;
                function tick() {
                    if (document.visibilityState !== 'visible' || pidiendo || !contenedor) return;
                    pidiendo = true;
                    fetch(url + '?desde=' + desde, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (d) {
                            if (!d || !d.html) return;
                            contenedor.insertAdjacentHTML('beforeend', d.html);
                            desde = d.ultimo;
                            var burbujas = contenedor.querySelectorAll('[data-mensaje-id]');
                            if (burbujas.length) burbujas[burbujas.length - 1].scrollIntoView({ behavior: 'smooth', block: 'end' });
                        })
                        .catch(function () {})
                        .finally(function () { pidiendo = false; });
                }
                setInterval(tick, 4000);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') tick();
                });
            })();
        </script>
    @endif
</x-app-layout>
