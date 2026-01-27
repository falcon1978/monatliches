@props([
    'label',
    'value',
    'valueClass' => '',
    'labelClass' => '',
])

<div class="grid grid-cols-[auto,1fr] items-baseline gap-3">
    <div class="text-lg font-semibold tabular-nums text-gray-900 {{ $valueClass }}">{{ $value }}</div>
    <div class="text-sm uppercase tracking-wide text-gray-700 {{ $labelClass }}">{{ $label }}</div>
</div>
