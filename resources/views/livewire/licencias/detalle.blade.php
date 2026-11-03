{{--
    Detalle desplegable de cada fila de la tabla de licencias (PowerGrid::detail()).
    PowerGrid le pasa $row = la licencia de esa fila (con empresa, plan y renovaciones_count ya cargados).
    Se dibuja como una mini tabla campo/valor, de dos pares por fila. Los datos delicados respetan los mismos
    permisos que las columnas de la tabla. Los colores salen de las clases .detalle-* de tema.css, así que
    siguen el modo claro/oscuro del tema sin depender de las variantes dark: de Tailwind.
--}}
@php
    $inicio = \Illuminate\Support\Carbon::parse($row->fecha_inicio);
    $vence = \Illuminate\Support\Carbon::parse($row->fecha_vencimiento);
    $renovaciones = (int) ($row->renovaciones_count ?? 0);

    $campos = [
        'Fecha de inicio' => $inicio->format('d/m/Y'),
        'Periodicidad' => match ($row->periodicidad) {
            'M' => 'Mensual',
            'A' => 'Anual',
            'P' => 'Personalizada',
            default => $row->periodicidad ?: '—',
        },
        'Duración' => $inicio->diffInDays($vence) . ' días',
        'Renovaciones' => $renovaciones === 0 ? 'Ninguna' : 'Renovada ×' . $renovaciones,
    ];

    if (Gate::allows('licenses.monto.ver')) {
        $campos['Moneda'] = $row->moneda ?: '—';
    }

    if (Gate::allows('licenses.descuento.ver')) {
        $campos['Descuento'] = $row->descuento !== null ? number_format((float) $row->descuento, 2) : '—';
    }

    $campos['Registrada el'] = \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i');

    $filas = array_chunk($campos, 2, true);
    $observaciones = Gate::allows('licenses.observaciones.ver') ? ($row->observaciones ?: '—') : null;
@endphp

<div class="detalle-caja">
    <table class="detalle-tabla">
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($fila as $etiqueta => $valor)
                        <th>{{ $etiqueta }}</th>
                        <td>{{ $valor }}</td>
                    @endforeach
                    @if (count($fila) === 1)
                        <th></th>
                        <td></td>
                    @endif
                </tr>
            @endforeach

            @if ($observaciones !== null)
                <tr>
                    <th>Observaciones</th>
                    <td colspan="3" class="whitespace-pre-line">{{ $observaciones }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
