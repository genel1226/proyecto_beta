{{--
    Tarjeta flotante con el título de cada página: ícono + título + (opcional) botones a la derecha.
    Uso:
        <x-pagina-titulo icono="building-office" titulo="Empresas">
            <flux:button ...>Nueva empresa</flux:button>   ← lo que vaya aquí sale a la derecha
        </x-pagina-titulo>
--}}
@props(['icono' => null, 'titulo' => ''])

<section {{ $attributes->class('tarjeta-titulo mb-5 flex flex-wrap items-center justify-between gap-4') }}>
    <div class="flex min-w-0 items-center gap-4">
        @if ($icono)
            <div class="titulo-icono">
                <flux:icon :icon="$icono" class="size-7" />
            </div>
        @endif

        <h1 class="m-0 truncate text-2xl font-bold tracking-tight text-zinc-800 sm:text-3xl dark:text-zinc-100">
            {{ $titulo }}
        </h1>
    </div>

    @if ($slot->hasActualContent())
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</section>
