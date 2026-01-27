@props(['class' => ''])

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 512 512"
    fill="none"
    stroke="currentColor"
    {{ $attributes->merge(['class' => 'text-[var(--accent)] ' . $class]) }}
>
    <rect x="96" y="180" width="320" height="180" rx="28" ry="28" stroke-width="20" stroke-linecap="round" stroke-linejoin="round" />
    <path d="M128,180h256l-32-40h-192l-32,40Z" stroke-width="20" stroke-linecap="round" stroke-linejoin="round" />
    <circle cx="380" cy="270" r="12" fill="currentColor" stroke="none" />
    <path d="M200,300v-70" stroke-width="18" stroke-linecap="round" />
    <path d="M180,250l20-20,20,20" stroke-width="18" stroke-linecap="round" stroke-linejoin="round" />
    <path d="M280,230v70" stroke-width="18" stroke-linecap="round" />
    <path d="M260,280l20,20,20-20" stroke-width="18" stroke-linecap="round" stroke-linejoin="round" />
</svg>
