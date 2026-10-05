<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased">
        {{-- Franja de color; en el login es más alta para que la tarjeta quede apoyada sobre ella --}}
        <div class="fondo-franja" style="height: 55vh" aria-hidden="true"></div>

        <div class="relative z-10 flex min-h-svh items-center justify-center p-6 md:p-10">
            <div class="tarjeta w-full max-w-md">
                <div class="flex flex-col gap-6 p-3 sm:p-5">
                    <a href="{{ route('home') }}" class="flex flex-col items-center gap-1 font-medium" wire:navigate>
                        <span class="mb-2 flex h-12 w-12 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                            <x-app-logo-icon class="size-7 fill-current" />
                        </span>
                        <span class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
                        <span class="text-center text-sm text-zinc-500 dark:text-zinc-400">Sistema de gestión y monitoreo de licencias</span>
                    </a>

                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
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
