@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 text-gray-900 dark:text-slate-100 focus:border-[var(--accent)] focus:ring-[var(--accent)] rounded-md shadow-sm']) }}>
