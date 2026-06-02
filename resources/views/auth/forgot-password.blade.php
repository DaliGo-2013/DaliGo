<x-guest-layout>
    <h1 class="mb-2 text-xl font-semibold text-neutral-900">{{ __('Forgot your password?') }}</h1>
    <p class="mb-6 text-sm text-neutral-600">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">{{ __('Email Password Reset Link') }}</x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-600">
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Back to log in') }}</a>
    </p>
</x-guest-layout>
