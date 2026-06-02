<x-guest-layout>
    <header class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">{{ __('Forgot your password?') }}</h1>
        <p class="mt-1 text-sm text-neutral-500">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </p>
    </header>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email" :value="old('email')" required autofocus placeholder="tu@correo.cl" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">{{ __('Email Password Reset Link') }}</x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-600">
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Back to log in') }}</a>
    </p>
</x-guest-layout>
