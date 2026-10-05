<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen">
    {{-- Franja de color de fondo. Está fuera del flujo (position: absolute) a propósito: la cuadrícula de
         Flux necesita que el encabezado esté JUSTO después del menú lateral, y un elemento normal en
         medio la rompería. --}}
    <div class="fondo-franja" aria-hidden="true"></div>

    {{-- Menú lateral: flotante y minimizable (el aspecto sale de tema.css; el botón de las tres rayitas
         del encabezado lo minimiza) --}}
    <flux:sidebar sticky collapsible>
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            {{-- Solo en móvil (cierra el menú). En escritorio el menú se minimiza con el botón de las tres
                 rayitas del encabezado. --}}
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            {{-- Los ítems van DIRECTO en el nav, sin flux:sidebar.group: Flux oculta por completo cualquier
                 grupo cuando el menú está minimizado, y con él se iban los íconos. Los títulos de sección
                 son propios (clase menu-seccion) y se esconden al minimizar; en su lugar aparece una línea
                 fina (menu-separador). --}}
            <div class="menu-seccion">{{ __('Platform') }}</div>

            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                wire:navigate>
                {{ __('Dashboard') }}
            </flux:sidebar.item>

            @can('licenses.index')
                <flux:sidebar.item icon="clipboard-document-list" :href="route('licencias')"
                    :current="request()->routeIs('licencias')" wire:navigate>
                    {{ __('Licencias') }}
                </flux:sidebar.item>
            @endcan

            @can('empresas.index')
                <flux:sidebar.item icon="building-office" :href="route('empresas')" :current="request()->routeIs('empresas')"
                    wire:navigate>
                    {{ __('Empresas') }}
                </flux:sidebar.item>
            @endcan

            @can('pagos.index')
                <flux:sidebar.item icon="banknotes" :href="route('pagos')" :current="request()->routeIs('pagos')"
                    wire:navigate>
                    {{ __('Pagos') }}
                </flux:sidebar.item>
            @endcan

            @can('reportes.index')
                <flux:sidebar.item icon="chart-bar" :href="route('reportes')" :current="request()->routeIs('reportes')"
                    wire:navigate>
                    {{ __('Reportes') }}
                </flux:sidebar.item>
            @endcan

            {{-- Administración: solo aparece si el usuario puede ver al menos una de sus opciones --}}
            @canany(['tipos_usuario.index', 'usuarios.index', 'roles.index'])
                <div class="menu-seccion menu-seccion-2">Administración</div>
                <div class="menu-separador"></div>

                @can('tipos_usuario.index')
                    <flux:sidebar.item icon="user-group" :href="route('tipos-usuario')"
                        :current="request()->routeIs('tipos-usuario')" wire:navigate>
                        {{ __('Tipos de usuario') }}
                    </flux:sidebar.item>
                @endcan

                @can('usuarios.index')
                    <flux:sidebar.item icon="users" :href="route('usuarios')" :current="request()->routeIs('usuarios')"
                        wire:navigate>
                        {{ __('Usuarios') }}
                    </flux:sidebar.item>
                @endcan

                @can('roles.index')
                    <flux:sidebar.item icon="shield-check" :href="route('roles')" :current="request()->routeIs('roles')"
                        wire:navigate>
                        {{ __('Roles') }}
                    </flux:sidebar.item>
                @endcan
            @endcanany
        </flux:sidebar.nav>

        <flux:spacer />

    </flux:sidebar>

    {{-- Header --}}
    <flux:header class="max-lg:hidden">
        <flux:sidebar.toggle class="mr-2" icon="bars-3" inset="left" />

        <flux:spacer />

        {{-- Alternar modo claro / oscuro --}}
        <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" icon="moon" variant="subtle" square
            aria-label="Cambiar entre modo claro y oscuro" class="mr-2" />

        <x-desktop-user-menu />
    </flux:header>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
