<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-1 font-medium" wire:navigate>
                    <span class="flex h-12 w-12 mb-2 items-center justify-center rounded-xl bg-accent-content text-accent-foreground">
                        <x-app-logo-icon class="size-7 fill-current text-white dark:text-black" />
                    </span>
                    <span class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
                    <span class="text-center text-sm text-zinc-500 dark:text-zinc-400">Sistema de gestión y monitoreo de licencias</span>
                </a>
                <div class="flex flex-col gap-6">
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
