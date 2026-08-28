<x-app-layout ancho="formulario">
    <x-slot name="header">
        {{-- Una SOLICITUD del QR aún no tiene fecha: el título/subtítulo y el link
             "Volver" deben ser nulo-seguros (antes crasheaban con fecha null). --}}
        @php $volver = $trabajo->fecha ?? \App\Support\FechaNegocio::ahora(); @endphp
        <x-page-header
            :title="$trabajo->estado === 'solicitado' ? 'Coordinar solicitud' : 'Editar trabajo'"
            :subtitle="$trabajo->cliente_nombre.($trabajo->fecha ? ' · '.$trabajo->fecha->format('d-m-Y') : ' · por coordinar')"
            :back="route('admin.agenda-terreno.index', ['anio' => $volver->year, 'mes' => $volver->month])"
            backTitle="Volver a la agenda">
            <x-slot name="action">
                <x-icon-button type="submit" form="agenda-form" size="lg" variant="primary" label="Guardar" title="Guardar cambios">
                    <x-icon.check class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5 py-8 sm:py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8"
             x-data="agendaTerrenoForm({
                endpointCliente: '{{ route('admin.agenda-terreno.buscar-cliente') }}',
                servicios: @js($serviciosJs),
                clienteId: {{ (int) old('cliente_id', $trabajo->cliente_id ?? 0) }},
                servicioId: @js((string) old('servicio_terreno_id', $trabajo->servicio_terreno_id ?? '')),
             })">
            <form id="agenda-form" method="POST" action="{{ route('admin.agenda-terreno.update', $trabajo) }}">
                @csrf
                @method('PUT')
                @include('admin.agenda-terreno._form', ['trabajo' => $trabajo])
                {{-- EL BOTÓN QUE CIERRA LA CONFIRMACIÓN (dueño 21-08-2026). Mientras la cita no
                     está agendada, la acción principal no es «guardar»: es confirmarle al
                     cliente. Antes eso era «cambiá el estado a Agendado y guardá» explicado en
                     un cartel —el aviso salía igual, pero nadie sabía que eso lo mandaba— y la
                     cara NO del mismo flujo («Rechazar y avisar») sí tenía su botón.

                     `confirmar` fija el estado en el servidor: el select sigue estando para los
                     demás estados, pero confirmar no depende de que alguien lo acierte. --}}
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($trabajo->estado !== 'agendado')
                        <x-primary-button name="confirmar" value="1" class="w-full justify-center py-3 sm:w-auto">
                            <x-icon.check class="h-4 w-4" /> Confirmar y avisar al cliente
                        </x-primary-button>
                        <x-secondary-button class="w-full justify-center py-3 sm:w-auto">Guardar sin avisar</x-secondary-button>
                    @else
                        <x-primary-button class="w-full justify-center py-3 sm:w-auto">Guardar cambios</x-primary-button>
                    @endif
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
</x-app-layout>
