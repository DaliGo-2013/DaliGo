<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-neutral-900">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.preferencias-notificaciones', ['user' => $user])
                </div>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
