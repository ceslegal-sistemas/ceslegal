@props([
    'hint' => null
])

@if($hint)
    <span class="ms-auto">{{ $hint }}</span>
@endif
