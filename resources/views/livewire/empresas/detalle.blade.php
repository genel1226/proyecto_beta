{{--
    Detalle desplegable de cada fila de la tabla de empresas (PowerGrid::detail()).
    PowerGrid le pasa $row = la empresa de esa fila. Mini tabla campo/valor, de dos pares por fila; los estilos
    son las clases .detalle-* de tema.css (las mismas del detalle de Licencias).
--}}
@php
    // Último pago registrado de esta empresa (de cualquiera de sus licencias). Solo se consulta al abrir la fila.
    $ultimoPago = \Illuminate\Support\Facades\DB::table('licencia_pagos as p')
        ->join('licencias as l', 'l.id', '=', 'p.licencia_id')
        ->where('l.empresa_id', $row->id)
        ->max('p.fecha_pago');

    $web = filled($row->pagina_web) ? $row->pagina_web : '—';

    $campos = [
        'Razón social' => $row->razon_social ?: '—',
        'NIT' => $row->nit ?: '—',
        'Correo' => $row->email ?: '—',
        'Teléfono' => $row->telefono ?: '—',
        'País' => $row->pais ?: '—',
        'Página web' => $web,
        'Último pago' => $ultimoPago ? \Illuminate\Support\Carbon::parse($ultimoPago)->format('d/m/Y') : '—',
        'Registrada el' => $row->created_at
            ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i')
            : '—',
    ];

    if ($row->trial_ends_at) {
        $campos['Fin de la demo'] = \Illuminate\Support\Carbon::parse($row->trial_ends_at)->format('d/m/Y');
    }

    // Usuarios contratados por tipo, sumando todas sus licencias activas (vigentes o por vencer).
    $porTipo = \App\Models\Licencias\Licencias::query()
        ->where('empresa_id', $row->id)
        ->whereIn('estado', ['V', 'X'])
        ->with('detalleUsuarios')
        ->get()
        ->flatMap(fn($licencia) => $licencia->detalleUsuarios)
        ->groupBy('tipo_usuario_id')
        ->map(fn($grupo) => (int) $grupo->sum('cantidad'))
        ->filter(fn($cantidad) => $cantidad > 0);

    $nombresTipo = \App\Models\TipoUsuario\TipoUsuario::whereIn('id', $porTipo->keys()->all())->pluck('nombre', 'id');

    $usuarios = $porTipo->isEmpty()
        ? 'Sin licencias activas'
        : $porTipo->sum() .
            ' en total: ' .
            $porTipo
                ->map(fn($cantidad, $tipoId) => ($nombresTipo[$tipoId] ?? 'Tipo ' . $tipoId) . ' ' . $cantidad)
                ->implode(' · ');

    $filas = array_chunk($campos, 2, true);
    $direccion = filled($row->direccion) ? $row->direccion : '—';
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

            <tr>
                <th>Usuarios contratados</th>
                <td colspan="3">{{ $usuarios }}</td>
            </tr>

            <tr>
                <th>Dirección</th>
                <td colspan="3" class="whitespace-pre-line">{{ $direccion }}</td>
            </tr>
        </tbody>
    </table>
</div>
