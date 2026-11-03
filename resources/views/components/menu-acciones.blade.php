{{--
    Menú de acciones de una fila: botón de tres puntos verticales que abre una barrita con los íconos de colores
    de siempre (btn-accion + btn-celeste / btn-ambar / ...), cada uno con su tooltip (atributo title).
    Lo dibuja LicenciasTabla (campo 'acciones_menu').
    Detalle: texto de la llamada a toggleDetail, p. ej. "toggleDetail('5')".
    Cada item: ['wire' => 'edit(5)', 'etiqueta' => 'Editar licencia', 'icono' => 'pencil-square', 'clase' => 'btn-ambar'].

    Alpine puro: el panel se "teletransporta" al <body> y se ancla al botón, así la tabla no lo recorta.
    $wire.metodo(id) llama directo al método de la tabla (edit, renovar, activar, baja, verPagos).
--}}
@props(['items' => [], 'detalle' => null])

<div x-data="{ abierto: false }" class="inline-flex items-center gap-1">
    {{-- Ver detalles: a la izquierda del menú, abre/cierra la mini tabla de la fila (toggleDetail de PowerGrid) --}}
    @if ($detalle)
        <button type="button" class="btn-accion btn-gris" title="Ver detalles" aria-label="Ver detalles"
            x-on:click="$wire.{{ $detalle }}">
            <flux:icon name="list-bullet" class="size-4" />
        </button>
    @endif

    @if (count($items) > 0)
        <button type="button" x-ref="btn" x-on:click="abierto = !abierto" class="menu-acc-btn" title="Opciones"
            aria-label="Opciones" aria-haspopup="menu" x-bind:aria-expanded="abierto">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="12" cy="5" r="1.8" />
                <circle cx="12" cy="12" r="1.8" />
                <circle cx="12" cy="19" r="1.8" />
            </svg>
        </button>

        <template x-teleport="body">
            <div x-show="abierto" x-anchor.bottom-end.offset.4="$refs.btn" x-on:click.outside="abierto = false"
                x-on:keydown.escape.window="abierto = false" class="menu-acc-panel menu-acc-iconos" role="menu"
                style="display: none">
                @foreach ($items as $item)
                    <button type="button" role="menuitem" class="btn-accion {{ $item['clase'] }}"
                        title="{{ $item['etiqueta'] }}" aria-label="{{ $item['etiqueta'] }}"
                        x-on:click="abierto = false; $wire.{{ $item['wire'] }}">
                        <flux:icon :name="$item['icono']" class="size-4" />
                    </button>
                @endforeach
            </div>
        </template>
    @endif
</div>
