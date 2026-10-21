<div>
    {{--
        Gráficos con Chart.js (el mismo que usa el Dashboard: copia local si existe, si no jsDelivr).
        dibujarDecision() es propio de esta pantalla para no depender de que el Dashboard haya cargado
        antes su script. Acepta una o varias series, barras apiladas y formato dinero / % / número.
    --}}
    @assets
        <script
            src="{{ file_exists(public_path('vendor/chartjs/chart.umd.min.js')) ? asset('vendor/chartjs/chart.umd.min.js') : 'https://cdn.jsdelivr.net/npm/chart.js@4' }}"
            defer></script>
        <script>
            window.dibujarDecision = function(canvas, cfg, intentos) {
                intentos = intentos || 0;

                if (typeof window.Chart === 'undefined') {
                    if (intentos > 200) {
                        canvas.parentElement.textContent = 'No se pudo cargar la librería de gráficos.';
                        return;
                    }
                    return setTimeout(function() {
                        window.dibujarDecision(canvas, cfg, intentos + 1);
                    }, 50);
                }

                if (!canvas.isConnected || window.Chart.getChart(canvas)) {
                    return;
                }

                var oscuro = document.documentElement.classList.contains('dark');
                var texto = oscuro ? '#a1a1aa' : '#52525b';
                var rejilla = oscuro ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
                var circular = cfg.tipo === 'doughnut' || cfg.tipo === 'pie';

                var formato = function(n) {
                    n = Number(n);
                    if (cfg.formato === 'dinero') {
                        return '$' + n.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                    if (cfg.formato === 'pct') {
                        return n.toLocaleString('en-US', {
                            maximumFractionDigits: 1
                        }) + ' %';
                    }
                    return n.toLocaleString('en-US');
                };

                var datasets = cfg.series.map(function(s) {
                    var ds = {
                        label: s.nombre,
                        data: s.valores,
                        backgroundColor: s.color
                    };
                    if (cfg.tipo === 'bar') {
                        ds.borderRadius = 4;
                        ds.maxBarThickness = 56;
                    } else if (cfg.tipo === 'line') {
                        ds.borderColor = s.color;
                        ds.tension = 0.3;
                    } else {
                        ds.borderWidth = 0;
                    }
                    return ds;
                });

                new window.Chart(canvas, {
                    type: cfg.tipo,
                    data: {
                        labels: cfg.etiquetas,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: cfg.tipo === 'doughnut' ? '62%' : undefined,
                        plugins: {
                            legend: {
                                display: circular || datasets.length > 1,
                                position: 'bottom',
                                labels: {
                                    color: texto,
                                    boxWidth: 12,
                                    padding: 14
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(c) {
                                        var v = circular ? c.parsed : c.parsed.y;
                                        var nombre = circular ? c.label : c.dataset.label;
                                        return ' ' + nombre + ': ' + formato(v);
                                    }
                                }
                            }
                        },
                        scales: circular ? {} : {
                            x: {
                                stacked: !!cfg.apilado,
                                ticks: {
                                    color: texto
                                },
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                stacked: !!cfg.apilado,
                                beginAtZero: true,
                                ticks: {
                                    color: texto,
                                    precision: 0,
                                    callback: function(v) {
                                        return cfg.formato === 'dinero' ? '$' + v : (cfg.formato === 'pct' ? v + '%' : v);
                                    }
                                },
                                grid: {
                                    color: rejilla
                                }
                            }
                        }
                    }
                });
            };
        </script>
    @endassets

    <x-reportes-titulo :actual="$rep['tab']" />

    {{-- Pregunta que responde el reporte + filtros --}}
    <div class="tarjeta mb-6">
        <div class="mb-3">
            <flux:heading size="lg">{{ $rep['titulo'] }}</flux:heading>
            <flux:text class="text-zinc-500">{{ $rep['pregunta'] }}</flux:text>
        </div>

        <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-4">
            @if ($rep['tab'] === 'riesgo')
                <flux:select wire:model.live="horizonte" label="Mirar hacia adelante">
                    <flux:select.option value="15">15 días</flux:select.option>
                    <flux:select.option value="30">30 días</flux:select.option>
                    <flux:select.option value="60">60 días</flux:select.option>
                    <flux:select.option value="90">90 días</flux:select.option>
                </flux:select>
            @elseif ($usaRango)
                <flux:input type="date" label="Desde" wire:model.live="desde" />
                <flux:input type="date" label="Hasta" wire:model.live="hasta" />
            @else
                <flux:text class="text-zinc-500 sm:col-span-2">Foto de hoy: no depende de fechas.</flux:text>
            @endif

            @can('reportes.export')
                <div class="flex gap-2 sm:col-start-4">
                    <flux:button wire:click="exportarExcel" icon="table-cells" variant="primary" class="w-full">Excel</flux:button>
                    <flux:button wire:click="exportarPdf" icon="document-arrow-down" variant="filled" class="w-full">PDF</flux:button>
                </div>
            @endcan
        </div>
    </div>

    {{-- Indicadores --}}
    @if (count($rep['kpis']))
        @php
            // Clases completas (Tailwind no ve clases armadas con variables): evita una tarjeta sola en la segunda fila.
            $columnas = match (count($rep['kpis'])) {
                1 => 'xl:grid-cols-1',
                2 => 'xl:grid-cols-2',
                3 => 'xl:grid-cols-3',
                4 => 'xl:grid-cols-4',
                default => 'xl:grid-cols-5',
            };
        @endphp
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $columnas }}">
            @foreach ($rep['kpis'] as $k)
                <div class="kpi" data-tono="{{ $k['tono'] }}" wire:key="kpi-{{ $rep['tab'] }}-{{ $loop->index }}">
                    <span class="kpi-icono"><x-dynamic-component :component="'flux::icon.'.$k['icono']" class="size-6" /></span>
                    <span class="kpi-texto">
                        <span class="kpi-etiqueta">{{ $k['etiqueta'] }}</span>
                        <span class="kpi-valor">{{ $k['valor'] }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Gráfico --}}
    @php($g = $rep['grafico'])
    @if ($g && count($g['etiquetas']))
        <div class="tarjeta mb-6">
            <flux:heading size="sm" class="mb-3">{{ $g['titulo'] }}</flux:heading>
            {{-- La llave cambia cuando cambian los datos, para que Livewire vuelva a pintar el canvas --}}
            <div wire:key="grafico-{{ $rep['tab'] }}-{{ md5(json_encode($g)) }}">
                <div wire:ignore class="h-72">
                    <canvas x-init="(function r() { window.dibujarDecision ? window.dibujarDecision($el, @js($g)) : setTimeout(r, 50) })()"></canvas>
                </div>
            </div>
        </div>
    @endif

    {{-- Tabla --}}
    <div class="tarjeta mb-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 text-left text-zinc-500 dark:border-zinc-700">
                    @foreach ($rep['columnas'] as $c)
                        <th class="px-2 py-2 font-medium {{ in_array($c['tipo'], ['num', 'dinero', 'pct']) ? 'text-right' : '' }}">{{ $c['etiqueta'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($rep['filas'] as $fila)
                    <tr wire:key="fila-{{ $rep['tab'] }}-{{ $loop->index }}">
                        @foreach ($rep['columnas'] as $c)
                            @php($v = $fila[$c['k']] ?? null)
                            <td class="px-2 py-2 {{ in_array($c['tipo'], ['num', 'dinero', 'pct']) ? 'text-right tabular-nums' : '' }}">
                                @if ($c['tipo'] === 'badge' && is_array($v))
                                    <flux:badge :color="$v['color']" size="sm">{{ $v['texto'] }}</flux:badge>
                                @else
                                    {{ \App\Livewire\Decisiones\Decisiones::fmt($v, $c['tipo']) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($rep['columnas']) }}" class="px-2 py-6 text-center text-zinc-500">
                            No hay datos para mostrar con estos filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:text class="mb-6 text-xs text-zinc-500">{{ $rep['nota'] }}</flux:text>
</div>