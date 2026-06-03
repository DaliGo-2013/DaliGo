<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-neutral-900">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="p-6 text-neutral-600">
                    {{ __("You're logged in!") }} Bienvenido {{ explode(' ', auth()->user()->name)[0] }}
                </div>
            </div>

            <div class="dg-enter mt-6 overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50 shadow-sm">
                <div class="flex items-start gap-3 p-6">
                    <span class="mt-0.5 inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-emerald-500 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.5 7.55a1 1 0 0 1-1.42 0l-3.5-3.525a1 1 0 1 1 1.42-1.408l2.79 2.81 6.79-6.836a1 1 0 0 1 1.414-.005Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <div class="text-sm leading-relaxed text-emerald-900">
                        <p class="font-semibold">Despliegue confirmado</p>
                        <p class="text-emerald-800">Los cambios se están publicando correctamente desde el repositorio DaliGo-2013/DaliGo.</p>
                        <p class="mt-1 text-xs text-emerald-700">Marca de despliegue: <span class="font-mono">deploy-check-001</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
