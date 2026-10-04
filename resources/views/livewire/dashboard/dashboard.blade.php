{{-- wire:poll.60s: el panel se refresca solo cada minuto, para que refleje lo que hizo el cron sin recargar --}}
<div wire:poll.60s>
    {{--
        Chart.js (gratis, licencia MIT). @assets lo carga UNA sola vez por página, y Livewire espera a que termine
        antes de evaluar scripts. Usa la copia local si existe (public/vendor/chartjs/chart.umd.min.js, sirve sin
        internet); si no, la baja de jsDelivr.

        dibujarGrafico() es genérico: sirve para 'bar', 'line', 'pie' y 'doughnut'. Para agregar otro gráfico basta
        con armar un arreglo de configuración y pintar un <canvas> como los de más abajo.
    --}}
    @assets
        <script
            src="{{ file_exists(public_path('vendor/chartjs/chart.umd.min.js')) ? asset('vendor/chartjs/chart.umd.min.js') : 'https://cdn.jsdelivr.net/npm/chart.js@4' }}"
            defer></script>
        <script>
            window.dibujarGrafico = function(canvas, cfg, intentos) {
                intentos = intentos || 0;

                // Chart.js puede tardar un instante en estar listo: reintenta cada 50 ms (máx. ~10 s).
                if (typeof window.Chart === 'undefined') {
                    if (intentos > 200) {
                        canvas.parentElement.textContent = 'No se pudo cargar la librería de gráficos.';
                        return;
                    }
                    return setTimeout(function() {
                        window.dibujarGrafico(canvas, cfg, intentos + 1);
                    }, 50);
                }

                // Si Livewire ya reemplazó este nodo, o ya tiene gráfico, no hace nada.
                if (!canvas.isConnected || window.Chart.getChart(canvas)) {
                    return;
                }

                var oscuro = document.documentElement.classList.contains('dark');
                var texto = oscuro ? '#a1a1aa' : '#52525b';
                var rejilla = oscuro ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
                var dinero = function(n) {
                    return '$' + Number(n).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                };

                var circular = cfg.tipo === 'doughnut' || cfg.tipo === 'pie';
                var dataset = {
                    label: cfg.titulo,
                    data: cfg.valores,
                    backgroundColor: cfg.colores
                };

                if (cfg.tipo === 'bar') {
                    dataset.borderRadius = 4;
                    dataset.maxBarThickness = 48;
                } else if (cfg.tipo === 'line') {
                    dataset.borderColor = cfg.colores;
                    dataset.tension = 0.3;
                } else {
                    dataset.borderWidth = 0;
                }

                new window.Chart(canvas, {
                    type: cfg.tipo,
                    data: {
                        labels: cfg.etiquetas,
                        datasets: [dataset]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: cfg.tipo === 'doughnut' ? '62%' : undefined,
                        plugins: {
                            legend: {
                                display: circular,
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
                                        var valor = circular ? c.parsed : c.parsed.y;
                                        if (circular) {
                                            return ' ' + c.label + ': ' + valor;
                                        }
                                        return ' ' + (cfg.moneda ? dinero(valor) : valor);
                                    }
                                }
                            }
                        },
                        scales: circular ? {} : {
                            x: {
                                ticks: {
                                    color: texto
                                },
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: texto,
                                    callback: function(v) {
                                        return cfg.moneda ? '$' + v : v;
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

    <div class="flex flex-wrap items-center justify-between gap-2 py-6 bg-zinc-100 px-3 my-5 dark:bg-zinc-800">
        <div class="flex text-4xl font-bold items-center gap-2 text-zinc-700 dark:text-zinc-200">
            <flux:icon.home class="size-12" />
            Dashboard
        </div>
        <flux:text class="text-xs text-zinc-500">
            Actualizado a las {{ now()->format('H:i') }} · se refresca cada minuto
        </flux:text>
    </div>

    @php
        $tarjeta =
            'block border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60';
        $coloresHex = [
            'green' => '#22c55e',
            'amber' => '#f59e0b',
            'rose' => '#f43f5e',
            'blue' => '#3b82f6',
            'zinc' => '#a1a1aa',
        ];
        $etiquetasEstado = [
            'V' => ['Vigentes', 'green'],
            'X' => ['Por vencer', 'amber'],
            'N' => ['Vencidas', 'rose'],
            'P' => ['En proceso', 'blue'],
            'C' => ['Canceladas', 'zinc'],
        ];
        $totalLicencias = array_sum($estados);
    @endphp

    @if (!$hayAlgo)
        <flux:text class="mx-3 text-zinc-500">
            Todavía no tienes permisos para ver información en este panel. Pídele a un administrador que te los asigne.
        </flux:text>
    @else
        {{-- ===== Indicadores ===== --}}
        <div class="mx-3 mb-6 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
            @if ($verLicencias)
                <a href="{{ route('licencias') }}" wire:navigate class="{{ $tarjeta }}">
                    <div class="text-xs text-zinc-500">Licencias activas</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums text-blue-600 dark:text-blue-400">
                        {{ $estados['V'] + $estados['X'] }}
                    </div>
                </a>

                <a href="{{ route('licencias') }}" wire:navigate class="{{ $tarjeta }}">
                    <div class="text-xs text-zinc-500">Por vencer (15 días)</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums text-amber-600 dark:text-amber-400">
                        {{ $porVencer }}
                    </div>
                </a>

                <a href="{{ route('licencias') }}" wire:navigate class="{{ $tarjeta }}">
                    <div class="text-xs text-zinc-500">Vencidas</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums text-rose-600 dark:text-rose-400">
                        {{ $estados['N'] }}
                    </div>
                </a>

                <a href="{{ route('licencias') }}" wire:navigate class="{{ $tarjeta }}">
                    <div class="text-xs text-zinc-500">En proceso</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums">{{ $estados['P'] }}</div>
                </a>
            @endif

            @if ($verEmpresas)
                <a href="{{ route('empresas') }}" wire:navigate class="{{ $tarjeta }}">
                    <div class="text-xs text-zinc-500">Empresas activas</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums">{{ $empresasActivas }}</div>
                    <div class="text-xs text-zinc-500 mt-0.5">{{ $demosEnCurso }} en demo</div>
                </a>
            @endif

            @if ($verMontos)
                <a href="{{ route('reportes') }}" wire:navigate class="{{ $tarjeta }}">
                    <div class="text-xs text-zinc-500">Facturado este mes</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                        ${{ number_format($facturadoMes, 2) }}
                    </div>
                </a>
            @endif
        </div>

        <div class="mx-3 grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- ===== Próximos vencimientos ===== --}}
            @if ($verLicencias)
                <div class="lg:col-span-2 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <flux:heading size="sm">Próximos vencimientos</flux:heading>
                        <a href="{{ route('licencias') }}" wire:navigate
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Ver todas →</a>
                    </div>

                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($vencimientos as $v)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div class="min-w-0">
                                    <div class="font-medium text-sm truncate">{{ $v['empresa'] }}</div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $v['codigo'] }} · {{ $v['plan'] }} · vence {{ $v['fecha'] }}
                                    </div>
                                </div>
                                <flux:badge :color="$v['color']" size="sm">{{ $v['texto'] }}</flux:badge>
                            </div>
                        @empty
                            <flux:text class="text-zinc-500 py-3">No hay licencias vigentes por vencer.</flux:text>
                        @endforelse
                    </div>
                </div>
            @endif

            {{-- ===== Licencias por estado (dona) + cuentas demo ===== --}}
            @if ($verLicencias || $verEmpresas)
                <div class="space-y-6">
                    @if ($verLicencias)
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                            <flux:heading size="sm" class="mb-3">Licencias por estado</flux:heading>

                            @if ($totalLicencias === 0)
                                <flux:text class="text-zinc-500">Todavía no hay licencias registradas.</flux:text>
                            @else
                                @php
                                    $cfgEstados = [
                                        'tipo' => 'doughnut',
                                        'titulo' => 'Licencias',
                                        'moneda' => false,
                                        'etiquetas' => collect($etiquetasEstado)
                                            ->map(fn($e, $c) => $e[0] . ' (' . $estados[$c] . ')')
                                            ->values()
                                            ->all(),
                                        'valores' => collect($etiquetasEstado)
                                            ->map(fn($e, $c) => $estados[$c])
                                            ->values()
                                            ->all(),
                                        'colores' => collect($etiquetasEstado)
                                            ->map(fn($e) => $coloresHex[$e[1]])
                                            ->values()
                                            ->all(),
                                    ];
                                @endphp

                                {{-- wire:key con hash de los datos: si cambian, Livewire reemplaza el bloque y se dibuja uno nuevo --}}
                                <div wire:key="grafico-estados-{{ md5(json_encode($cfgEstados)) }}"
                                    class="relative h-64">
                                    <div wire:ignore class="h-full">
                                        <canvas x-init="(function r() { window.dibujarGrafico ? window.dibujarGrafico($el, @js($cfgEstados)) : setTimeout(r, 50) })()"></canvas>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($verEmpresas)
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                            <flux:heading size="sm" class="mb-2">Cuentas demo</flux:heading>

                            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($demos as $d)
                                    <div class="flex items-center justify-between gap-3 py-2">
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium truncate">{{ $d['nombre'] }}</div>
                                            <div class="text-xs text-zinc-500">{{ $d['cod'] }} · hasta
                                                {{ $d['fecha'] }}</div>
                                        </div>
                                        <flux:badge :color="$d['color']" size="sm">{{ $d['texto'] }}
                                        </flux:badge>
                                    </div>
                                @empty
                                    <flux:text class="text-zinc-500 py-1 text-sm">No hay cuentas demo.</flux:text>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ===== Facturación de los últimos 6 meses (barras) ===== --}}
            @if ($verMontos)
                <div class="lg:col-span-2 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="sm">Facturación de los últimos 6 meses</flux:heading>
                        <a href="{{ route('reportes') }}" wire:navigate
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Ver reportes →</a>
                    </div>

                    @php
                        $cfgFacturacion = [
                            'tipo' => 'bar',
                            'titulo' => 'Facturado (USD)',
                            'moneda' => true,
                            'etiquetas' => collect($facturacionMeses)
                                ->pluck('etiqueta')
                                ->map(fn($e) => \Illuminate\Support\Str::ucfirst($e))
                                ->all(),
                            'valores' => collect($facturacionMeses)->pluck('total')->all(),
                            'colores' => '#10b981',
                        ];
                    @endphp

                    <div wire:key="grafico-facturacion-{{ md5(json_encode($cfgFacturacion)) }}" class="relative h-56">
                        <div wire:ignore class="h-full">
                            <canvas x-init="(function r() { window.dibujarGrafico ? window.dibujarGrafico($el, @js($cfgFacturacion)) : setTimeout(r, 50) })()"></canvas>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ===== Últimos pagos ===== --}}
            @if ($verPagos)
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <flux:heading size="sm">Últimos pagos</flux:heading>
                        <a href="{{ route('pagos') }}" wire:navigate
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Ver todos →</a>
                    </div>

                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($pagosRecientes as $p)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium truncate">{{ $p['empresa'] }}</div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $p['codigo'] }} · {{ $p['tipo'] }} · {{ $p['fecha'] }}
                                    </div>
                                </div>
                                @if ($p['monto'] !== null)
                                    <div class="text-sm font-semibold tabular-nums">
                                        ${{ number_format($p['monto'], 2) }}</div>
                                @endif
                            </div>
                        @empty
                            <flux:text class="text-zinc-500 py-2 text-sm">Todavía no hay pagos registrados.</flux:text>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
