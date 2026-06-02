<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-150 ease-in-out hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/40 focus:ring-offset-2 focus:ring-offset-white active:scale-[0.98] disabled:opacity-50']) }}>
    {{ $slot }}
</button>
