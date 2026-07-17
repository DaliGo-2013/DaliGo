{{--
    Solicitud PÚBLICA de visita/revisión industrial (QR, sin login): para
    lavadoras, llenadoras y plantas de osmosis EN EL CLIENTE. Elige el tipo de
    trabajo (Visita técnica primero: diagnóstico + cotización), opcionalmente
    el servicio del tarifario, deja sus datos y una fecha preferida opcional.
    Entra a la Agenda de terreno como 'solicitado'; el staff llama y coordina.
--}}
<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold tracking-tight text-neutral-900">Visita / revisión industrial</h1>
        <p class="mt-1 text-sm text-neutral-500">
            Sucursal <span class="font-medium text-neutral-700">{{ $sucursal->nombre }}</span>
        </p>
        <p class="mt-3 text-sm text-neutral-500">
            El técnico va a tu planta. Deja tus datos y te llamamos para coordinar el día.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            Revisa los datos: hay {{ $errors->count() }} campo(s) con problemas más abajo.
        </div>
    @endif

    <form method="POST" action="{{ route('visita-industrial.store') }}" class="space-y-5"
          x-data="{ servicioId: @js(old('servicio_terreno_id', '')) }">
        @csrf
        <input type="hidden" name="sucursal_id" value="{{ $sucursal->id }}">
        {{-- Honeypot anti-bot --}}
        <div class="hidden" aria-hidden="true">
            <label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
        </div>

        {{-- Qué necesitas --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm space-y-4">
            <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">¿Qué necesitas?</h2>
            <div>
                <x-input-label for="tipo">Tipo de trabajo <span class="text-red-500">*</span></x-input-label>
                <x-select id="tipo" name="tipo" class="mt-1.5" required>
                    <option value="">— Selecciona —</option>
                    @foreach ($tipos as $tp)
                        <option value="{{ $tp }}" @selected(old('tipo') === $tp)>{{ \App\Models\AgendaTrabajo::TIPO_ETIQUETAS[$tp] }}</option>
                    @endforeach
                </x-select>
                <x-input-hint>Visita técnica: el técnico diagnostica en tu planta y te cotiza lo que haya que hacer.</x-input-hint>
                <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="servicio_terreno_id" value="Servicio (si ya sabes cuál)" />
                <x-select id="servicio_terreno_id" name="servicio_terreno_id" class="mt-1.5" x-model="servicioId">
                    <option value="">— No estoy seguro / que me orienten —</option>
                    @foreach ($servicios as $s)
                        <option value="{{ $s->id }}" @selected((string) old('servicio_terreno_id') === (string) $s->id)>
                            {{ $s->nombre }}@if ($s->valor_uf_fmt) · {{ $s->valor_uf_fmt }} UF @endif
                        </option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('servicio_terreno_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="descripcion">Cuéntanos qué pasa <span class="text-red-500">*</span></x-input-label>
                <x-textarea id="descripcion" name="descripcion" rows="3" class="mt-1.5" required
                    placeholder="Ej. La planta de osmosis 1T pierde presión; la llenadora traba la cadena.">{{ old('descripcion') }}</x-textarea>
                <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="fecha_preferida" value="¿Cuándo te acomoda? (opcional)" />
                <x-text-input id="fecha_preferida" name="fecha_preferida" type="date" class="mt-1.5 w-full"
                    min="{{ now()->toDateString() }}" :value="old('fecha_preferida')" />
                <x-input-hint>Es una referencia: el día definitivo se coordina contigo.</x-input-hint>
                <x-input-error :messages="$errors->get('fecha_preferida')" class="mt-2" />
            </div>
        </div>

        {{-- Tus datos --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm space-y-4">
            <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Tus datos</h2>
            <div>
                <x-input-label for="cliente_nombre">Nombre / empresa <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 w-full" required
                    maxlength="191" :value="old('cliente_nombre')" />
                <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="cliente_rut">RUT <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 w-full" required
                    maxlength="20" placeholder="Ej. 76.123.456-7" :value="old('cliente_rut')" />
                <x-input-error :messages="$errors->get('cliente_rut')" class="mt-2" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="cliente_telefono">Teléfono <span class="text-red-500">*</span></x-input-label>
                    <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 w-full" required
                        maxlength="30" placeholder="+56 9 1234 5678" :value="old('cliente_telefono')" />
                    <x-input-hint>Te llamamos a este número para coordinar.</x-input-hint>
                    <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="cliente_email">Correo <span class="text-red-500">*</span></x-input-label>
                    <x-text-input id="cliente_email" name="cliente_email" type="email" class="mt-1.5 w-full" required
                        maxlength="191" :value="old('cliente_email')" />
                    <x-input-error :messages="$errors->get('cliente_email')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="direccion">Dirección de la planta <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="direccion" name="direccion" type="text" class="mt-1.5 w-full" required
                    maxlength="191" placeholder="Donde se hará la visita" :value="old('direccion')" />
                <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="ciudad">Ciudad <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="ciudad" name="ciudad" type="text" class="mt-1.5 w-full" required
                    maxlength="191" :value="old('ciudad')" />
                <x-input-error :messages="$errors->get('ciudad')" class="mt-2" />
            </div>
        </div>

        <x-primary-button class="w-full justify-center py-3 text-base">Enviar solicitud</x-primary-button>
        <p class="text-center text-xs text-neutral-400">Te contactaremos para coordinar el día y la hora de la visita.</p>
    </form>
</x-guest-layout>
