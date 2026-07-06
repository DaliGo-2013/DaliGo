<section>
    <header>
        <h2 class="text-base font-semibold text-neutral-900">
            Notificaciones
        </h2>
        <p class="mt-1 text-sm text-neutral-500">
            Elige por qué canal quieres recibir cada aviso. La campanita del sistema siempre te llega; puedes desactivar el correo o WhatsApp.
        </p>
    </header>

    <form method="post" action="{{ route('perfil.notificaciones.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        @foreach (\App\Models\Notificacion::EVENTOS as $evento => $etiqueta)
            <div>
                <x-input-label :value="$etiqueta" />
                <div class="mt-1.5 grid gap-2 sm:grid-cols-3">
                    <x-checkbox-item name="_campanita_fija" value="1" :checked="true" :disabled="true">
                        Campanita
                        <x-slot name="note">siempre</x-slot>
                    </x-checkbox-item>
                    <x-checkbox-item name="prefs[{{ $evento }}][{{ \App\Models\Notificacion::CANAL_MAIL }}]" value="1"
                                     :checked="\App\Models\PreferenciaCanal::habilitadoPara($user, $evento, \App\Models\Notificacion::CANAL_MAIL)">
                        Correo
                    </x-checkbox-item>
                    <x-checkbox-item name="prefs[{{ $evento }}][{{ \App\Models\Notificacion::CANAL_WHATSAPP }}]" value="1"
                                     :checked="\App\Models\PreferenciaCanal::habilitadoPara($user, $evento, \App\Models\Notificacion::CANAL_WHATSAPP)">
                        WhatsApp
                        <x-slot name="note">pronto</x-slot>
                    </x-checkbox-item>
                </div>
            </div>
        @endforeach

        <div class="flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>

            @if (session('status') === 'preferencias-notificaciones-actualizadas')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm text-neutral-500">Guardado.</p>
            @endif
        </div>
    </form>
</section>
