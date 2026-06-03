<x-guest-layout>
    <header class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">{{ __('Register') }}</h1>
        <p class="mt-1 text-sm text-neutral-500">Crea tu cuenta para comenzar.</p>
    </header>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="mt-1.5" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Nombre y apellido" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="tu@correo.cl" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1.5" type="password" name="password" required autocomplete="new-password" placeholder="••••••••" />
            <x-input-hint>Usa al menos 8 caracteres.</x-input-hint>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="mt-1.5" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">{{ __('Register') }}</x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-600">
        {{ __('Already registered?') }}
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Log in') }}</a>
    </p>
</x-guest-layout>
