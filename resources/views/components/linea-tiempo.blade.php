{{--
    Línea de tiempo horizontal: "hoy" a la izquierda y cada elemento como un punto según los días que faltan.
    Los nombres se alternan arriba y abajo del eje para que no se pisen.

    Uso: <x-linea-tiempo :items="$vencimientos" :span="$spanVencimientos" />
    Cada item: ['dias' => int, 'nombre' => string, 'texto' => string, 'color' => string, 'detalle' => string]
    'color' puede ser green, amber, red, rose, violet o blue.
--}}
@props(['items' => [], 'span' => 12])

@php
    $hex = [
        'green' => '#22c55e',
        'amber' => '#f5b301',
        'red' => '#f43f5e',
        'rose' => '#be185d',
        'violet' => '#3b82f6',
        'blue' => '#3b82f6',
    ];
    $span = max(1, (int) $span);

    // Posición según los días, pero separando los puntos que caen muy cerca (varias vencidas, o dos el mismo día)
    // para que los nombres no se encimen. Separación mínima: 9 % del ancho; si no caben, se reparte parejo.
    $separacion = 9;
    $posiciones = [];
    $anterior = -$separacion + 2;
    foreach ($items as $i => $item) {
        $natural = 2 + (max(0, min($item['dias'], $span)) / $span) * 94;
        $anterior = max($natural, $anterior + $separacion);
        $posiciones[$i] = $anterior;
    }
    $ultimo = end($posiciones) ?: 0;
    if ($ultimo > 96) {
        $factor = 94 / ($ultimo - 2);
        $posiciones = array_map(fn($x) => 2 + ($x - 2) * $factor, $posiciones);
    }
@endphp

{{-- En pantallas angostas la línea se desplaza horizontalmente en vez de apretarse --}}
<div class="overflow-x-auto">
    <div class="linea-tiempo">
        <div class="linea-eje"></div>
        <div class="linea-hoy"></div>

        @foreach ($items as $i => $item)
            @php
                $x = $posiciones[$i];
                $arriba = $i % 2 === 0;
            @endphp

            <span class="linea-punto"
                style="left: {{ $x }}%; background: {{ $hex[$item['color']] ?? '#a1a1aa' }}"
                title="{{ $item['nombre'] }} · {{ $item['detalle'] }}"></span>
            <span class="linea-nombre"
                style="left: {{ $x }}%; top: {{ $arriba ? '6px' : '66px' }}">{{ $item['nombre'] }}</span>
            <span class="linea-dato"
                style="left: {{ $x }}%; top: {{ $arriba ? '22px' : '82px' }}">{{ $item['texto'] }}</span>
        @endforeach
    </div>
</div>
