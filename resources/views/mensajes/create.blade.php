<x-app-layout ancho="formulario">
    <div class="py-8">
        <x-volver :href="route('mensajes.index')" titulo="Volver a Mensajes" class="mb-3" />

        <h2 class="mb-4 text-lg font-semibold leading-tight text-neutral-900">Nuevo mensaje</h2>

        <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
            <form method="POST" action="{{ route('mensajes.store') }}" class="space-y-4">
                @csrf

                <div>
                    <x-input-label for="destinatario_id" value="Para" />
                    <x-select id="destinatario_id" name="destinatario_id" class="mt-1.5" required>
                        <option value="" disabled @selected(! old('destinatario_id'))>Elige a quién escribirle…</option>
                        @foreach ($destinatarios as $destinatario)
                            <option value="{{ $destinatario->id }}" @selected((int) old('destinatario_id') === $destinatario->id)>
                                {{ $destinatario->name }}
                            </option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('destinatario_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="texto" value="Mensaje" />
                    <x-textarea id="texto" name="texto" rows="4" maxlength="1000" required
                                class="mt-1.5" placeholder="Escribe tu mensaje…">{{ old('texto') }}</x-textarea>
                    <x-input-hint>El destinatario recibirá un aviso en su campanita (y correo, según su preferencia).</x-input-hint>
                    <x-input-error :messages="$errors->get('texto')" class="mt-2" />
                </div>

                <x-primary-button type="submit" class="h-12 w-full justify-center">
                    Enviar mensaje
                </x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
