{{--
    Título de "Reportes" con el nombre del reporte abierto ("Reportes - Retención") y, debajo,
    una fila de botones con TODOS los reportes: Ventas y pagos + los de toma de decisiones.
    Uso:  <x-reportes-titulo actual="reportes" />            (pantalla Reportes)
          <x-reportes-titulo :actual="$rep['tab']" />        (pantalla Decisiones: actual = clave de la pestaña)

    'actual' se pasa a mano (y no con request()->routeIs) porque Livewire vuelve a pintar la vista en una
    ruta interna (livewire/update) donde routeIs() ya no sabría en qué pantalla estamos.
--}}
@props(['actual' => 'reportes'])

@php
    $tabsDecision = \App\Livewire\Decisiones\Decisiones::TABS;
    $nombre = $actual === 'reportes' ? 'Ventas y pagos' : $tabsDecision[$actual][0] ?? 'Toma de decisiones';
    $enDecisiones = $actual !== 'reportes';
@endphp

<x-pagina-titulo icono="chart-bar" :titulo="'Reportes - ' . $nombre" />

<div class="tarjeta mb-4 !py-3">
    <div class="flex flex-wrap gap-1">
        @can('reportes.index')
            <flux:button :href="route('reportes')" wire:navigate size="sm" icon="banknotes"
                :variant="$actual === 'reportes' ? 'primary' : 'ghost'">
                Ventas y pagos
            </flux:button>
        @endcan

        @can('decisiones.index')
            @foreach ($tabsDecision as $clave => [$etiqueta, $icono, $soloMontos])
                @if ($soloMontos && !auth()->user()->can('reportes.montos'))
                    @continue
                @endif

                @if ($enDecisiones)
                    {{-- Ya estamos en Decisiones: cambiar de pestaña sin recargar la página --}}
                    <flux:button wire:click="cambiarTab('{{ $clave }}')" size="sm" :icon="$icono"
                        :variant="$actual === $clave ? 'primary' : 'ghost'" wire:key="rep-{{ $clave }}">
                        {{ $etiqueta }}
                    </flux:button>
                @else
                    <flux:button :href="route('decisiones', ['tab' => $clave])" wire:navigate size="sm"
                        :icon="$icono" variant="ghost">
                        {{ $etiqueta }}
                    </flux:button>
                @endif
            @endforeach
        @endcan
    </div>
</div>
