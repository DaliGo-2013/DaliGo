<x-guest-layout>
    <header class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">{{ __('Confirm Password') }}</h1>
        <p class="mt-1 text-sm text-neutral-500">
            {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        </p>
    </header>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-password-input id="password" class="mt-1.5" name="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">{{ __('Confirm') }}</x-primary-button>
    </form>
</x-guest-layout>
