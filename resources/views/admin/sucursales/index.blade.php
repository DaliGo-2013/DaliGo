<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Sucursales" subtitle="Bodegas y sucursales de DALI.">
            <x-slot name="action">
                <x-button-link :href="route('admin.sucursales.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Crear sucursal
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <x-status-alert :status="session('status')" class="mb-6" />

        <x-list-card title="Sucursales" :count="$sucursales->count()" :countLabel="\Illuminate\Support\Str::plural('sucursal', $sucursales->count())">
            @forelse ($sucursales as $sucursal)
                {{-- La fila entera abre la edicion (pedido del dueño 03-08: fuera el
                     lapiz, la sucursal ES el boton). Mismo patron que bodegas y ST.
                     Sin @can: el resource completo esta detras de 'manage sucursales',
                     asi que quien puede VER este listado puede editar. --}}
                <x-list-row>
                    <a href="{{ route('admin.sucursales.edit', $sucursal) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $sucursal->nombre }}</p>
                            @if ($sucursal->es_central)
                                <x-badge variant="neutral">central</x-badge>
                            @endif
                            @unless ($sucursal->activa)
                                <x-badge variant="neutral">inactiva</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">
                            {{ $sucursal->codigo }}@if ($sucursal->ciudad) · {{ $sucursal->ciudad }}@endif
                        </p>
                    </a>

                    <x-slot name="meta">
                        {{-- En móvil las dos cifras se apilan (dos textos en una línea de 375px
                             desbordan); desde sm: van una al lado de la otra. --}}
                        <div class="flex w-full flex-col gap-0.5 sm:w-auto sm:flex-row sm:items-center sm:gap-4">
                            {{-- EL PLAZO QUE SE LE PROMETE AL CLIENTE, a la vista (dueño, 14-08-2026:
                                 «cerremos ese agujero»). Sale del CÓDIGO de la sucursal, y hasta hoy
                                 esta pantalla mostraba el código sin su consecuencia: con «Mirador»
                                 en minúsculas el plazo caía al default y el correo prometía 15 días
                                 donde la regla dice 10, siete semanas, a la vista de todos. Solo se
                                 muestra en las que RECIBEN taller: en las demás sería un número que
                                 no se usa. --}}
                            @if ($sucursal->recibe_servicio_tecnico)
                                <div class="flex items-center gap-1 text-sm text-neutral-500 sm:shrink-0 sm:justify-end">
                                    {{-- El @if va en su propia línea: pegado a una palabra
                                         (…hábiles@if) Blade NO lo compila y el @endif revienta. --}}
                                    <span>
                                        Taller: hasta {{ $sucursal->dias_reparacion }} días hábiles
                                        @if ($sucursal->plazo_es_por_defecto)
                                            <span class="text-neutral-400">(por defecto)</span>
                                        @endif
                                    </span>
                                    <x-info-tip>
                                        Es el plazo que se le promete al cliente —en el correo de ingreso y en la pantalla
                                        del QR— cuando deja un equipo en esta sucursal. Lo decide su <strong>código</strong>
                                        (<code>{{ $sucursal->codigo }}</code>): Mirador repara ahí mismo, y las que mandan el
                                        equipo a Mirador tardan más.
                                        @if ($sucursal->plazo_es_por_defecto)
                                            Esta sucursal <strong>no tiene un plazo propio</strong>, así que usa el valor por
                                            defecto. Si su plazo es otro, hay que configurarlo para su código.
                                        @endif
                                    </x-info-tip>
                                </div>
                            @endif

                            <div class="text-sm text-neutral-500 sm:w-28 sm:shrink-0 sm:text-right">
                                {{ $sucursal->users_count }} {{ \Illuminate\Support\Str::plural('usuario', $sucursal->users_count) }}
                            </div>
                        </div>
                    </x-slot>

                    <x-slot name="actions">
                        {{-- Js::from y no {{ }}: un nombre con apostrofo ("O'Higgins")
                             rompia el confirm() y el form se enviaba SIN preguntar
                             (bitacora 2026-07-28, mismo fix que agenda-terreno). --}}
                        <form method="POST" action="{{ route('admin.sucursales.destroy', $sucursal) }}" onsubmit="return confirm({{ Illuminate\Support\Js::from('¿Eliminar la sucursal '.$sucursal->nombre.'?') }});">
                            @csrf
                            @method('DELETE')
                            <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                <x-icon.trash class="h-5 w-5" />
                            </x-icon-button>
                        </form>
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">Aún no hay sucursales.</li>
            @endforelse
        </x-list-card>
    </div>
</x-app-layout>
