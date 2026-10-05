<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* dompdf tiene soporte limitado de CSS: nada de flexbox/grid, estilos simples nomás */
        body { font-family: sans-serif; font-size: 12px; color: #27272a; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .subtitulo { color: #71717a; margin-bottom: 20px; }
        .kpis { width: 100%; margin-bottom: 20px; }
        .kpis td { width: 20%; padding: 8px; border: 1px solid #e4e4e7; text-align: center; }
        .kpis .valor { font-size: 16px; font-weight: bold; display: block; }
        .kpis .etiqueta { font-size: 10px; color: #71717a; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        table.datos th { background: #f4f4f5; text-align: left; padding: 6px 8px; border-bottom: 2px solid #d4d4d8; }
        table.datos td { padding: 6px 8px; border-bottom: 1px solid #e4e4e7; }
        table.datos td.num { text-align: right; }
        h2 { font-size: 14px; margin-top: 0; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>Reporte de ventas de licencias</h1>
    <div class="subtitulo">
        Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
        — generado el {{ now()->format('d/m/Y H:i') }}
    </div>

    <table class="kpis">
        <tr>
            <td>
                <span class="valor">${{ number_format($kpis['total_facturado'], 2) }}</span>
                <span class="etiqueta">Facturado en el rango</span>
            </td>
            <td>
                <span class="valor">{{ $kpis['cantidad_pagos'] }}</span>
                <span class="etiqueta">Pagos en el rango</span>
            </td>
            <td>
                <span class="valor">{{ $kpis['licencias_activas'] }}</span>
                <span class="etiqueta">Licencias activas hoy</span>
            </td>
            <td>
                <span class="valor">{{ $kpis['por_vencer_15'] }}</span>
                <span class="etiqueta">Por vencer en 15 días</span>
            </td>
            <td>
                <span class="valor">{{ $kpis['empresas_activas'] }}</span>
                <span class="etiqueta">Empresas activas</span>
            </td>
        </tr>
    </table>

    <h2>Facturación por mes</h2>
    <table class="datos">
        <tr><th>Mes</th><th>Cantidad de pagos</th><th>Total</th></tr>
        @forelse ($porMes as $fila)
            <tr>
                <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $fila->mes)->translatedFormat('F Y') }}</td>
                <td>{{ $fila->cantidad }}</td>
                <td class="num">${{ number_format($fila->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Sin pagos en este rango.</td></tr>
        @endforelse
    </table>

    <h2>Top empresas por facturación</h2>
    <table class="datos">
        <tr><th>Empresa</th><th>Cantidad de pagos</th><th>Total</th></tr>
        @forelse ($porEmpresa as $fila)
            <tr>
                <td>{{ $fila->nombre_comercial }}</td>
                <td>{{ $fila->cantidad }}</td>
                <td class="num">${{ number_format($fila->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Sin pagos en este rango.</td></tr>
        @endforelse
    </table>
    <h2>Facturación por plan</h2>
    <table class="datos">
        <tr><th>Plan</th><th>Cantidad de pagos</th><th>Total</th></tr>
        @forelse ($porPlan as $fila)
            <tr>
                <td>{{ $fila->plan }}</td>
                <td>{{ $fila->cantidad }}</td>
                <td class="num">${{ number_format($fila->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Sin pagos en este rango.</td></tr>
        @endforelse
    </table>
</body>
</html>
