@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-lg border border-slate-700 bg-slate-800/60 text-slate-100 placeholder-slate-500 shadow-sm transition duration-150 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/40']) }}>
