<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen antialiased">
    {{-- Fondo: el mismo paisaje de la cabecera, a pantalla completa (estilos .login-* en resources/css/tema.css) --}}
    <div class="login-fondo" style="background-image: url('{{ asset('img/login-paisaje.jpg') }}')" aria-hidden="true">
    </div>

    <div class="relative z-10 flex min-h-svh items-center justify-center p-6 md:p-10">
        <div class="login-tarjeta w-full max-w-sm">
            {{-- Cabecera con el paisaje (public/images/login-paisaje.jpg) y el texto de bienvenida encima --}}
            <div class="login-cabecera">
                <img src="{{ asset('img/login-paisaje.jpg') }}" alt="" class="login-ilustracion">

                <div class="login-cabecera-texto">
                    <h1>Bienvenido a {{ config('app.name', 'Laravel') }}</h1>
                    <p>Sistema de gestión y monitoreo de licencias</p>
                </div>
            </div>

            <div class="login-cuerpo">
                {{ $slot }}
            </div>
        </div>
    </div>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
