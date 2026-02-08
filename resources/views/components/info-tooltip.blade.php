@props(['text'])

<span
    {{ $attributes->merge(['class' => 'help-tooltip']) }}
    data-tooltip="{{ $text }}"
    role="img"
    aria-label="{{ $text }}"
    tabindex="0"
>
    i
</span>
