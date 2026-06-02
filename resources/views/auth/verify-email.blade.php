<x-guest-layout>
    <h1 class="mb-2 text-xl font-semibold text-neutral-900">{{ __('Verify Email') }}</h1>
    <p class="mb-6 text-sm text-neutral-600">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-lg bg-neutral-100 px-4 py-3 text-sm font-medium text-neutral-700">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>{{ __('Resend Verification Email') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-neutral-500 underline-offset-2 hover:text-neutral-800 hover:underline">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
