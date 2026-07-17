<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar trabajo" :subtitle="$trabajo->cliente_nombre.' · '.$trabajo->fecha->format('d-m-Y')">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.agenda-terreno.index', ['anio' => $trabajo->fecha->year, 'mes' => $trabajo->fecha->month])" size="lg" variant="secondary" label="Volver" title="Volver a la agenda">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-icon-button type="submit" form="agenda-form" size="lg" variant="primary" label="Guardar" title="Guardar cambios">
                        <x-icon.check class="h-5 w-5" />
                    </x-icon-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
                 x-data="agendaTerrenoForm({
                    endpointCliente: '{{ route('admin.agenda-terreno.buscar-cliente') }}',
                    servicios: @js($servicios->keyBy('id')->map(fn ($s) => ['valor_uf' => $s->valor_uf !== null ? rtrim(rtrim(number_format($s->valor_uf, 2, ',', '.'), '0'), ',') : null, 'duracion' => $s->duracion, 'incluye' => $s->incluye, 'observaciones' => $s->observaciones])),
                    clienteId: {{ (int) old('cliente_id', $trabajo->cliente_id ?? 0) }},
                    servicioId: @js((string) old('servicio_terreno_id', $trabajo->servicio_terreno_id ?? '')),
                 })">
                <form id="agenda-form" method="POST" action="{{ route('admin.agenda-terreno.update', $trabajo) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.agenda-terreno._form', ['trabajo' => $trabajo])
                    <div class="mt-6">
                        <x-primary-button class="w-full justify-center py-3 sm:w-auto">Guardar cambios</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Eliminar (solo desde editar, con confirmación) --}}
            <form method="POST" action="{{ route('admin.agenda-terreno.destroy', $trabajo) }}"
                  onsubmit="return confirm('¿Eliminar este trabajo de la agenda? Esta acción no se puede deshacer.');"
                  class="text-right">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">Eliminar de la agenda</button>
            </form>
        </div>
    </div>
</x-app-layout>
