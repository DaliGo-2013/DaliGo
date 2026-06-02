<x-guest-layout>
    <h1 class="mb-6 text-xl font-semibold text-neutral-900">{{ __('Log in') }}</h1>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center">
            <input id="remember_me" type="checkbox" class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500/30" name="remember">
            <span class="ms-2 text-sm text-neutral-600">{{ __('Remember me') }}</span>
        </label>

        <div class="flex items-center justify-between gap-3">
            @if (Route::has('password.request'))
                <a class="text-sm text-neutral-500 underline-offset-2 hover:text-neutral-800 hover:underline" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button>{{ __('Log in') }}</x-primary-button>
        </div>
    </form>

    @if (Route::has('register'))
        <p class="mt-6 text-center text-sm text-neutral-600">
            {{ __("Don't have an account?") }}
            <a href="{{ route('register') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Register') }}</a>
        </p>
    @endif
</x-guest-layout>
