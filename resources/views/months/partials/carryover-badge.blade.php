@php
    $label = $entry->originMonth?->name ?? $entry->movedFromMonth?->name;
@endphp

@if ($label)
    <span class="ml-2 inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
        Aus {{ $label }}
    </span>
@endif
