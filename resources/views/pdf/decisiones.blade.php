<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* dompdf: sin flexbox ni grid, solo tablas y estilos simples */
        body { font-family: sans-serif; font-size: 11px; color: #27272a; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .pregunta { font-size: 12px; margin-bottom: 2px; }
        .subtitulo { color: #71717a; margin-bottom: 16px; }
        .kpis { width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 4px; }
        .kpis td { padding: 8px; border: 1px solid #e4e4e7; text-align: center; }
        .kpis .valor { font-size: 15px; font-weight: bold; display: block; }
        .kpis .etiqueta { font-size: 9px; color: #71717a; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.datos th { background: #f4f4f5; text-align: left; padding: 5px 6px; border-bottom: 2px solid #d4d4d8; font-size: 10px; }
        table.datos td { padding: 5px 6px; border-bottom: 1px solid #e4e4e7; }
        .num { text-align: right; }
        .nota { color: #71717a; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ $rep['titulo'] }}</h1>
    <div class="pregunta">{{ $rep['pregunta'] }}</div>
    <div class="subtitulo">
        @if ($rep['tab'] === 'riesgo')
            Licencias que vencen en los próximos {{ $horizonte }} días
        @elseif ($usaRango)
            Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
        @else
            Foto al {{ now()->format('d/m/Y') }}
        @endif
        — generado el {{ now()->format('d/m/Y H:i') }}
    </div>

    @if (count($rep['kpis']))
        <table class="kpis">
            <tr>
                @foreach ($rep['kpis'] as $k)
                    <td>
                        <span class="valor">{{ $k['valor'] }}</span>
                        <span class="etiqueta">{{ $k['etiqueta'] }}</span>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="datos">
        <thead>
            <tr>
                @foreach ($rep['columnas'] as $c)
                    <th class="{{ in_array($c['tipo'], ['num', 'dinero', 'pct']) ? 'num' : '' }}">{{ $c['etiqueta'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rep['filas'] as $fila)
                <tr>
                    @foreach ($rep['columnas'] as $c)
                        <td class="{{ in_array($c['tipo'], ['num', 'dinero', 'pct']) ? 'num' : '' }}">
                            {{ \App\Livewire\Decisiones\Decisiones::fmt($fila[$c['k']] ?? null, $c['tipo']) }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($rep['columnas']) }}">Sin datos para este reporte.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="nota">{{ $rep['nota'] }}</div>
</body>
</html>
