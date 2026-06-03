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
        </div>
    </div>
</x-app-layout>
