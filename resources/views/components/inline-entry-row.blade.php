@props([
    'formId',
    'action',
    'method' => 'POST',
])

<form id="{{ $formId }}" method="POST" action="{{ $action }}" class="hidden">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    {!! $hidden ?? '' !!}
</form>

<tr {{ $attributes->merge(['class' => 'border-t border-gray-400']) }}>
    {{ $slot }}
</tr>
