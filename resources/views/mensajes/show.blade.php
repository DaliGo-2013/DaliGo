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

        <div class="dg-enter space-y-3">
            @forelse ($mensajes->reverse() as $mensaje)
                @php $esMio = $mensaje->emisor_id === $usuario->id; @endphp
                <div class="flex {{ $esMio ? 'justify-end' : 'justify-start' }}">
                    {{-- Burbuja paleta-4: mía = brand suave, del otro = neutro. El
                         texto SIEMPRE por {{ }} (jamás {!! !!}): lo escribe un usuario. --}}
                    <div class="max-w-[85%] rounded-2xl px-4 py-2.5 {{ $esMio ? 'bg-brand-50 text-brand-900 ring-1 ring-inset ring-brand-100' : 'bg-neutral-100 text-neutral-900' }}">
                        <p class="whitespace-pre-line break-words text-sm">{{ $mensaje->texto }}</p>
                        <p class="mt-1 text-xs {{ $esMio ? 'text-brand-700/70' : 'text-neutral-400' }}">
                            @unless ($esMio){{ $mensaje->emisor?->name ?? '—' }} · @endunless{{ $mensaje->created_at?->enChile()->format('d-m H:i') }}
                        </p>
                    </div>
                </div>
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

    {{-- Refresco automático (MSG-3): la firma es GLOBAL del chat — un cambio
         en OTRO hilo también recarga (espurio aceptado: marcarLeida del GET es
         idempotente y el contrato es «cambió algo → recarga»). --}}
    <x-poll-recarga :url="route('mensajes.conteo')" :firma="$firmaChat" />
</x-app-layout>
