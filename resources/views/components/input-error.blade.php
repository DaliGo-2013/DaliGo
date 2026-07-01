@props(['messages'])

@if ($messages)
    <ul data-error-message {{ $attributes->merge(['class' => 'dg-shake space-y-1 text-sm text-red-600']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
