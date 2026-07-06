{{--
    Formulario PUBLICO de ingreso a servicio tecnico por QR (P-M12-01, piloto).
    Sin login. La sucursal viene fija desde el link firmado del QR. El cliente
    llena esto en su celular; el encargado confirma la recepcion despues.
--}}
<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold tracking-tight text-neutral-900">Ingreso a servicio técnico</h1>
        <p class="mt-1 text-sm text-neutral-500">
            Sucursal <span class="font-medium text-neutral-700">{{ $sucursal->nombre }}</span>
        </p>
        <p class="mt-2 text-sm text-neutral-500">
            Completa los datos de tu equipo. Cuando termines, muéstrale la pantalla al encargado del mostrador.
        </p>
    </div>

    <form method="POST" action="{{ route('ingreso-taller.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="sucursal_id" value="{{ $sucursal->id }}">

        {{-- Honeypot anti-bot: invisible para personas, tentador para bots. --}}
        <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; height:0; overflow:hidden;">
            <label for="sitio_web">No llenar</label>
            <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
        </div>

        {{-- Datos del cliente --}}
        <div>
            <x-input-label for="cliente_nombre" value="Nombre y apellido *" />
            <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 block w-full"
                          :value="old('cliente_nombre')" required autofocus placeholder="Tu nombre" />
            <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="cliente_email" value="Correo *" />
            <x-text-input id="cliente_email" name="cliente_email" type="email" class="mt-1.5 block w-full"
                          :value="old('cliente_email')" required placeholder="tu@correo.cl" />
            <x-input-hint>Te llegará el detalle con el número de folio de tu ingreso.</x-input-hint>
            <x-input-error :messages="$errors->get('cliente_email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="cliente_telefono" value="Teléfono" />
            <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 block w-full"
                          :value="old('cliente_telefono')" placeholder="Ej. +56 9 1234 5678" />
            <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="cliente_rut" value="RUT" />
            <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 block w-full"
                          :value="old('cliente_rut')" placeholder="Ej. 12.345.678-9" />
            <x-input-hint>Opcional.</x-input-hint>
            <x-input-error :messages="$errors->get('cliente_rut')" class="mt-1.5" />
        </div>

        <hr class="border-neutral-200">

        {{-- Datos del equipo --}}
        <div>
            <x-input-label for="tipo_equipo" value="Tipo de equipo *" />
            <x-select id="tipo_equipo" name="tipo_equipo" class="mt-1.5 block w-full">
                @foreach ($tipos as $t)
                    <option value="{{ $t }}" @selected(old('tipo_equipo', 'dispensador') === $t)>{{ ucfirst($t) }}</option>
                @endforeach
            </x-select>
            <x-input-error :messages="$errors->get('tipo_equipo')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="numero_serie" value="N° de serie *" />
            <x-text-input id="numero_serie" name="numero_serie" type="text" class="mt-1.5 block w-full"
                          :value="old('numero_serie')" required placeholder="El número que trae el equipo" />
            <x-input-error :messages="$errors->get('numero_serie')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="falla_reportada" value="¿Qué le pasa al equipo? *" />
            <x-textarea id="falla_reportada" name="falla_reportada" rows="4" class="mt-1.5 block w-full"
                        required placeholder="Cuéntanos la falla que notaste">{{ old('falla_reportada') }}</x-textarea>
            <x-input-error :messages="$errors->get('falla_reportada')" class="mt-1.5" />
        </div>

        <x-primary-button class="w-full justify-center">
            Enviar ingreso
        </x-primary-button>
    </form>
</x-guest-layout>
