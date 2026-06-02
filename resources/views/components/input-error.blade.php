@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'dg-shake space-y-1 text-sm text-rose-400']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
