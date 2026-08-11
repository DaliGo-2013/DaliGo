{{--
    TIPOS DE DOCUMENTO DE LA FLOTA (pedido del dueño 11-08-2026: «otra opción para
    crear uno nuevo si a futuro pidieran»).

    Los cinco de la ley NO están acá y no se pueden tocar: son columnas del vehículo,
    viven en el Excel, en el comando de avisos y en el semáforo. Esta pantalla es para
    los que aparezcan después.

    Se dice en la pantalla —y no solo en el código— que crear un documento enciende el
    semáforo de toda la flota a la que le aplique: quien lo crea tiene que poder
    anticiparlo antes de apretar el botón, no descubrirlo en el listado.
--}}
<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Tipos de documento"
                       subtitle="Los papeles con vencimiento que lleva la flota."
                       :back="route('admin.vehiculos.index')" backTitle="Volver a vehículos" />
    </x-slot>

    <div class="space-y-5 py-8">
        <x-status-alert :status="session('status')" />

        <x-seccion titulo="Los que exige la ley">
            <ul role="list" class="divide-y divide-neutral-100">
                @foreach (\App\Models\Vehiculo::DOCUMENTOS as $label)
                    <li class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                        <span class="text-sm text-neutral-900">{{ $label }}</span>
                        <span class="shrink-0 text-xs text-neutral-400">Fijo</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs leading-relaxed text-neutral-500">
                Estos cinco vienen con el sistema y no se pueden borrar: alimentan el semáforo, los
                avisos automáticos y la planilla de la flota.
            </p>
        </x-seccion>

        <x-seccion titulo="Los que agregaron ustedes">
            @if ($tipos->isEmpty())
                <p class="text-sm text-neutral-500">
                    Todavía no hay ninguno. Se agregan abajo, y aparecen en la ficha de cada
                    vehículo al que les toque.
                </p>
            @else
                <ul role="list" class="divide-y divide-neutral-100">
                    @foreach ($tipos as $tipo)
                        <li class="py-3 first:pt-0 last:pb-0"
                            x-data="{ abierto: false }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-neutral-900">
                                        {{ $tipo->nombre }}
                                        @unless ($tipo->activo)
                                            <span class="ml-1 text-xs font-normal text-neutral-400">· desactivado</span>
                                        @endunless
                                    </p>
                                    <p class="text-xs text-neutral-500">
                                        {{ blank($tipo->aplica_a)
                                            ? 'Aplica a todos los vehículos'
                                            : 'Solo: '.collect($tipo->aplica_a)->map(fn ($t) => \App\Models\Vehiculo::TIPOS[$t] ?? $t)->join(', ') }}
                                        @php $uso = $usos[$tipo->id] ?? 0; @endphp
                                        @if ($uso > 0)
                                            · {{ $uso }} {{ $uso === 1 ? 'registro cargado' : 'registros cargados' }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3 text-xs">
                                    <button type="button" @click="abierto = ! abierto"
                                            class="min-h-8 font-medium text-brand-700 hover:text-brand-600"
                                            x-text="abierto ? 'Cerrar' : 'Editar'"></button>
                                    {{-- Un tipo YA USADO se desactiva en vez de borrarse, y el
                                         texto de la confirmación lo dice: borrar se llevaría las
                                         fechas y las fotos que ya cargó la flota. --}}
                                    <form method="POST" action="{{ route('admin.vehiculos.tipos-documento.destroy', $tipo) }}"
                                          onsubmit="return confirm(@js(($usos[$tipo->id] ?? 0) > 0
                                              ? '«'.$tipo->nombre.'» tiene datos cargados, así que se va a DESACTIVAR: sale del semáforo y lo cargado queda guardado. ¿Seguir?'
                                              : '¿Eliminar «'.$tipo->nombre.'»? No tiene nada cargado.'));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="min-h-8 font-medium text-neutral-500 underline-offset-2 transition hover:text-red-700 hover:underline">
                                            {{ ($usos[$tipo->id] ?? 0) > 0 ? 'Desactivar' : 'Eliminar' }}
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <form x-show="abierto" x-cloak method="POST" class="mt-3 space-y-3"
                                  action="{{ route('admin.vehiculos.tipos-documento.update', $tipo) }}">
                                @csrf
                                @method('PUT')
                                @include('admin.vehiculos.tipos-documento._campos', ['tipo' => $tipo])
                                <x-primary-button>Guardar</x-primary-button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-seccion>

        <form method="POST" action="{{ route('admin.vehiculos.tipos-documento.store') }}">
            @csrf
            <x-seccion titulo="Agregar un documento">
                @include('admin.vehiculos.tipos-documento._campos', ['tipo' => null])
                <p class="text-xs leading-relaxed text-neutral-500">
                    Apenas se crea, aparece en la ficha de cada vehículo al que le aplique — con su
                    fecha y su foto, como los demás. Ojo: hasta que se carguen las fechas, esos
                    vehículos van a figurar <span class="font-medium text-neutral-700">sin fecha</span>
                    en el semáforo. Por eso conviene marcar a qué tipos les toca de verdad.
                </p>
                <x-primary-button>Crear el documento</x-primary-button>
            </x-seccion>
        </form>
    </div>
</x-app-layout>
