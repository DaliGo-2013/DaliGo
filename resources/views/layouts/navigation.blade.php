<nav x-data="{ open: false }" class="border-b border-neutral-200 bg-white">
    {{-- Campanita M15: no-leídas del usuario, calculadas UNA vez y reusadas en
         el dropdown desktop y el bloque móvil (evita repetir la query). --}}
    @php
        $dgNoLeidas = \App\Models\Notificacion::campanitaDe(auth()->id())->latest('id')->take(5)->get();
        $dgConteo = \App\Models\Notificacion::campanitaDe(auth()->id())->count();
    @endphp
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex">
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <x-application-logo class="h-9 w-9 text-base" />
                        <span class="text-lg font-semibold tracking-tight text-neutral-900">DaliGo</span>
                    </a>
                </div>

                {{-- Navegación agrupada por dominio: Comercial / Operación / Administración.
                     "Mi producción" queda de primer nivel a propósito: es LA pantalla del
                     operario (soplador) y no debe esconderse tras un dropdown. --}}
                <div class="hidden space-x-8 lg:-my-px lg:ms-10 lg:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @canany(['manage productos', 'manage clientes'])
                        <x-nav-dropdown label="Comercial"
                            :active="request()->routeIs('admin.productos.*', 'admin.listas-precios.*', 'admin.clientes.*')">
                            @can('manage productos')
                                <x-dropdown-link :href="route('admin.productos.index')">Catálogo</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.listas-precios.index')">Precios</x-dropdown-link>
                            @endcan
                            @can('manage clientes')
                                <x-dropdown-link :href="route('admin.clientes.index')">Clientes</x-dropdown-link>
                            @endcan
                        </x-nav-dropdown>
                    @endcanany

                    @canany(['manage productos', 'manage production'])
                        <x-nav-dropdown label="Operación"
                            :active="request()->routeIs('admin.bodegas.*', 'admin.produccion.*')">
                            @can('manage productos')
                                <x-dropdown-link :href="route('admin.bodegas.index')">Inventario</x-dropdown-link>
                            @endcan
                            @can('manage production')
                                <x-dropdown-link :href="route('admin.produccion.index')">Producción</x-dropdown-link>
                            @endcan
                        </x-nav-dropdown>
                    @endcanany

                    @canany(['view users', 'manage roles', 'manage sucursales', 'manage settings', 'view audit', 'view notificaciones', 'view aprobaciones'])
                        <x-nav-dropdown label="Administración"
                            :active="request()->routeIs('admin.users.*', 'admin.roles.*', 'admin.sucursales.*', 'admin.configuracion.*', 'admin.audits.*', 'admin.notificaciones.*', 'admin.aprobaciones.*')">
                            @can('view users')
                                <x-dropdown-link :href="route('admin.users.index')">Usuarios</x-dropdown-link>
                            @endcan
                            @can('manage roles')
                                <x-dropdown-link :href="route('admin.roles.index')">Roles</x-dropdown-link>
                            @endcan
                            @can('manage sucursales')
                                <x-dropdown-link :href="route('admin.sucursales.index')">Sucursales</x-dropdown-link>
                            @endcan
                            @can('manage settings')
                                <x-dropdown-link :href="route('admin.configuracion.index')">Configuración</x-dropdown-link>
                            @endcan
                            @can('view audit')
                                <x-dropdown-link :href="route('admin.audits.index')">Auditoría</x-dropdown-link>
                            @endcan
                            @can('view notificaciones')
                                <x-dropdown-link :href="route('admin.notificaciones.index')">Notificaciones</x-dropdown-link>
                            @endcan
                            @can('view aprobaciones')
                                <x-dropdown-link :href="route('admin.aprobaciones.index')">Aprobaciones</x-dropdown-link>
                            @endcan
                        </x-nav-dropdown>
                    @endcanany

                    @can('report production')
                        <x-nav-link :href="route('produccion.mi.index')" :active="request()->routeIs('produccion.mi.*')" class="whitespace-nowrap">
                            Mi producción
                        </x-nav-link>
                    @endcan

                    @can('aprobar solicitudes')
                        <x-nav-link :href="route('aprobaciones.index')" :active="request()->routeIs('aprobaciones.*')" class="whitespace-nowrap">
                            Aprobaciones
                        </x-nav-link>
                    @endcan

                    @canany(['view servicio tecnico', 'manage servicio tecnico', 'crear lote servicio', 'ver agenda terreno', 'agendar servicio terreno', 'gestionar instalaciones'])
                        <x-nav-dropdown label="Servicio Técnico"
                            :active="request()->routeIs('admin.servicio-tecnico.*', 'admin.agenda-terreno.*', 'admin.servicios-terreno.*', 'admin.instalaciones.*')">
                            {{-- Contador: equipos activos en el taller (todo salvo entregado / sin solución).
                                 Va en el título del menú (slot badge) para seguir visible en la barra. --}}
                            @if (($pendientesServicioTecnico ?? 0) > 0)
                                <x-slot:badge>
                                    <span class="ms-2 inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white"
                                          title="{{ $pendientesServicioTecnico }} equipo(s) por atender">{{ $pendientesServicioTecnico }}</span>
                                </x-slot:badge>
                            @endif
                            @canany(['view servicio tecnico', 'manage servicio tecnico'])
                                <x-dropdown-link :href="route('admin.servicio-tecnico.index')">Listado</x-dropdown-link>
                            @endcanany
                            @can('manage servicio tecnico')
                                <x-dropdown-link :href="route('admin.servicio-tecnico.create')">Registrar ingreso</x-dropdown-link>
                            @endcan
                            @can('crear lote servicio')
                                <x-dropdown-link :href="route('admin.servicio-tecnico.lote.create')">Ingreso por lote</x-dropdown-link>
                            @endcan
                            @can('manage servicio tecnico')
                                <x-dropdown-link :href="route('admin.servicio-tecnico.qr')">Códigos QR</x-dropdown-link>
                            @endcan
                            @canany(['view servicio tecnico', 'manage servicio tecnico'])
                                <x-dropdown-link :href="route('admin.servicio-tecnico.informe')">Informe</x-dropdown-link>
                            @endcanany
                            @canany(['ver agenda terreno', 'agendar servicio terreno'])
                                <x-dropdown-link :href="route('admin.agenda-terreno.index')">Agenda de terreno</x-dropdown-link>
                            @endcanany
                            @can('agendar servicio terreno')
                                <x-dropdown-link :href="route('admin.servicios-terreno.index')">Servicios de terreno</x-dropdown-link>
                            @endcan
                            @can('gestionar instalaciones')
                                <x-dropdown-link :href="route('admin.instalaciones.index')">Instalaciones</x-dropdown-link>
                            @endcan
                        </x-nav-dropdown>
                    @endcanany
                </div>
            </div>

            <div class="hidden lg:ms-6 lg:flex lg:items-center">
                {{-- Campanita in-app (M15) --}}
                @include('layouts.partials.campanita', ['dgNoLeidas' => $dgNoLeidas, 'dgConteo' => $dgConteo])

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-md border border-transparent px-3 py-2 text-sm font-medium leading-4 text-neutral-600 transition duration-150 hover:text-neutral-900 focus:outline-none">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center gap-1 lg:hidden">
                {{-- Campana SIEMPRE visible en móvil (hallazgo QA 14-07: al fondo
                     del hamburguesa nadie la descubre). Va directo a la bandeja
                     personal — un dropdown en 375px tapa la pantalla y la página
                     ya existe; el detalle vive en notificaciones.index. --}}
                <a href="{{ route('notificaciones.index') }}"
                    aria-label="Notificaciones{{ $dgConteo > 0 ? ' ('.$dgConteo.' sin leer)' : '' }}"
                    class="relative inline-flex items-center justify-center rounded-md p-2 text-neutral-500 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 focus:bg-neutral-100 focus:text-neutral-700 focus:outline-none">
                    <x-icon.bell class="h-6 w-6" />
                    @if ($dgConteo > 0)
                        <span class="absolute right-0 top-0 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-600 px-1 text-xs font-semibold tabular-nums text-white">{{ $dgConteo > 9 ? '9+' : $dgConteo }}</span>
                    @endif
                    <span class="sr-only">Notificaciones</span>
                </a>
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-neutral-500 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 focus:bg-neutral-100 focus:text-neutral-700 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden">
        {{-- Menú móvil: mismas agrupaciones que el desktop, pero como secciones
             planas con encabezado (dropdowns anidados son mala UX en móvil). --}}
        <div class="space-y-1 pb-3 pt-2">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @can('report production')
                <x-responsive-nav-link :href="route('produccion.mi.index')" :active="request()->routeIs('produccion.mi.*')">
                    Mi producción
                </x-responsive-nav-link>
            @endcan

            @can('aprobar solicitudes')
                <x-responsive-nav-link :href="route('aprobaciones.index')" :active="request()->routeIs('aprobaciones.*')">
                    Aprobaciones
                </x-responsive-nav-link>
            @endcan

            @canany(['view servicio tecnico', 'manage servicio tecnico', 'crear lote servicio', 'ver agenda terreno', 'agendar servicio terreno', 'gestionar instalaciones'])
                <x-responsive-nav-heading>
                    Servicio Técnico
                    @if (($pendientesServicioTecnico ?? 0) > 0)
                        <span class="ms-2 inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white">{{ $pendientesServicioTecnico }}</span>
                    @endif
                </x-responsive-nav-heading>

                @canany(['view servicio tecnico', 'manage servicio tecnico'])
                    <x-responsive-nav-link :href="route('admin.servicio-tecnico.index')" :active="request()->routeIs('admin.servicio-tecnico.index')">
                        Listado
                    </x-responsive-nav-link>
                @endcanany
                @can('manage servicio tecnico')
                    <x-responsive-nav-link :href="route('admin.servicio-tecnico.create')" :active="request()->routeIs('admin.servicio-tecnico.create')">
                        Registrar ingreso
                    </x-responsive-nav-link>
                @endcan
                @can('crear lote servicio')
                    <x-responsive-nav-link :href="route('admin.servicio-tecnico.lote.create')" :active="request()->routeIs('admin.servicio-tecnico.lote.*')">
                        Ingreso por lote
                    </x-responsive-nav-link>
                @endcan
                @can('manage servicio tecnico')
                    <x-responsive-nav-link :href="route('admin.servicio-tecnico.qr')" :active="request()->routeIs('admin.servicio-tecnico.qr')">
                        Códigos QR
                    </x-responsive-nav-link>
                @endcan
                @canany(['view servicio tecnico', 'manage servicio tecnico'])
                    <x-responsive-nav-link :href="route('admin.servicio-tecnico.informe')" :active="request()->routeIs('admin.servicio-tecnico.informe')">
                        Informe
                    </x-responsive-nav-link>
                @endcanany
                @canany(['ver agenda terreno', 'agendar servicio terreno'])
                    <x-responsive-nav-link :href="route('admin.agenda-terreno.index')" :active="request()->routeIs('admin.agenda-terreno.*')">
                        Agenda de terreno
                    </x-responsive-nav-link>
                @endcanany
                @can('agendar servicio terreno')
                    <x-responsive-nav-link :href="route('admin.servicios-terreno.index')" :active="request()->routeIs('admin.servicios-terreno.*')">
                        Servicios de terreno
                    </x-responsive-nav-link>
                @endcan
                @can('gestionar instalaciones')
                    <x-responsive-nav-link :href="route('admin.instalaciones.index')" :active="request()->routeIs('admin.instalaciones.*')">
                        Instalaciones
                    </x-responsive-nav-link>
                @endcan
            @endcanany

            @canany(['manage productos', 'manage clientes'])
                <x-responsive-nav-heading>Comercial</x-responsive-nav-heading>

                @can('manage productos')
                    <x-responsive-nav-link :href="route('admin.productos.index')" :active="request()->routeIs('admin.productos.*')">
                        Catálogo
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.listas-precios.index')" :active="request()->routeIs('admin.listas-precios.*')">
                        Precios
                    </x-responsive-nav-link>
                @endcan

                @can('manage clientes')
                    <x-responsive-nav-link :href="route('admin.clientes.index')" :active="request()->routeIs('admin.clientes.*')">
                        Clientes
                    </x-responsive-nav-link>
                @endcan
            @endcanany

            @canany(['manage productos', 'manage production'])
                <x-responsive-nav-heading>Operación</x-responsive-nav-heading>

                @can('manage productos')
                    <x-responsive-nav-link :href="route('admin.bodegas.index')" :active="request()->routeIs('admin.bodegas.*')">
                        Inventario
                    </x-responsive-nav-link>
                @endcan

                @can('manage production')
                    <x-responsive-nav-link :href="route('admin.produccion.index')" :active="request()->routeIs('admin.produccion.*')">
                        Producción
                    </x-responsive-nav-link>
                @endcan
            @endcanany

            @canany(['view users', 'manage roles', 'manage sucursales', 'manage settings', 'view audit', 'view aprobaciones'])
                <x-responsive-nav-heading>Administración</x-responsive-nav-heading>

                @can('view users')
                    <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                        Usuarios
                    </x-responsive-nav-link>
                @endcan

                @can('manage roles')
                    <x-responsive-nav-link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')">
                        Roles
                    </x-responsive-nav-link>
                @endcan

                @can('manage sucursales')
                    <x-responsive-nav-link :href="route('admin.sucursales.index')" :active="request()->routeIs('admin.sucursales.*')">
                        Sucursales
                    </x-responsive-nav-link>
                @endcan

                @can('manage settings')
                    <x-responsive-nav-link :href="route('admin.configuracion.index')" :active="request()->routeIs('admin.configuracion.*')">
                        Configuración
                    </x-responsive-nav-link>
                @endcan

                @can('view audit')
                    <x-responsive-nav-link :href="route('admin.audits.index')" :active="request()->routeIs('admin.audits.*')">
                        Auditoría
                    </x-responsive-nav-link>
                @endcan

                @can('view aprobaciones')
                    <x-responsive-nav-link :href="route('admin.aprobaciones.index')" :active="request()->routeIs('admin.aprobaciones.*')">
                        Aprobaciones
                    </x-responsive-nav-link>
                @endcan
            @endcanany
        </div>

        {{-- Campanita in-app (M15) — móvil. Reusa $dgConteo del tope del nav. --}}
        <div class="border-t border-neutral-200 pb-1 pt-4">
            <div class="flex items-center justify-between px-4">
                <span class="inline-flex items-center gap-2 text-base font-medium text-neutral-800">
                    Notificaciones
                    @if ($dgConteo > 0)
                        <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-600 px-1.5 text-xs font-semibold text-white">{{ $dgConteo }}</span>
                    @endif
                </span>
                @if ($dgConteo > 0)
                    <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-brand-600">Marcar todas</button>
                    </form>
                @endif
            </div>
            <div class="mt-1">
                <x-responsive-nav-link :href="route('notificaciones.index')">Ver mis notificaciones</x-responsive-nav-link>
            </div>
        </div>

        <div class="border-t border-neutral-200 pb-1 pt-4">
            <div class="px-4">
                <div class="text-base font-medium text-neutral-800">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-neutral-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
