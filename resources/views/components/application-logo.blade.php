@props(['variant' => 'full'])

{{-- FIZJOroom logo; "mark" is the spine-on-circle sign alone, for tight spaces. --}}
<img src="{{ asset($variant === 'mark' ? 'images/sygnet.png' : 'images/logo.png') }}" alt="FIZJOroom" {{ $attributes }}>
