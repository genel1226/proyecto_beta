@props([
    'sidebar' => false,
])

{{--
    Logo de Software4tech. Qué versión se ve (completa clara / completa oscura / solo el "4")
    lo decide tema.css según el modo claro u oscuro y si el menú está minimizado.
    Los archivos están en public/img/.
--}}
<a {{ $attributes->class('logo-menu') }} aria-label="{{ config('app.name', 'Software4tech') }}">
    <img src="{{ asset('img/logo_s4t.png') }}" alt="{{ config('app.name', 'Software4tech') }}" class="logo-completo-claro" width="468" height="84">
    <img src="{{ asset('img/logo_s4t_oscuro.png') }}" alt="" aria-hidden="true" class="logo-completo-oscuro" width="468" height="84">
    <img src="{{ asset('img/logo_s4t_icono.png') }}" alt="" aria-hidden="true" class="logo-icono" width="132" height="132">
</a>