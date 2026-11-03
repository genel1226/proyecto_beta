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

                // Barras de facturación: los meses anteriores en azul suave y el último (el actual) resaltado.
                if (cfg.resaltarUltimo) {
                    var suave = oscuro ? 'rgba(96,165,250,0.40)' : 'rgba(59,130,246,0.45)';
                    var fuerte = oscuro ? '#93b4f5' : '#1d3260';
                    dataset.backgroundColor = cfg.valores.map(function(v, i) {
                        return i === cfg.valores.length - 1 ? fuerte : suave;
                    });
                }

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

    <x-pagina-titulo icono="home" titulo="Dashboard">
        <flux:text class="text-xs text-zinc-500">
            Actualizado a las {{ now()->format('H:i') }} · se refresca cada minuto
        </flux:text>
    </x-pagina-titulo>

    @php
        $coloresHex = [
            'green' => '#22c55e',
            'amber' => '#f5b301',
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
        <flux:text class="tarjeta text-zinc-500">
            Todavía no tienes permisos para ver información en este panel. Pídele a un administrador que te los asigne.
        </flux:text>
    @else
        {{-- ===== Indicadores: la tarjeta entera en el tono suave, ícono en el color fuerte ===== --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @if ($verLicencias)
                <a href="{{ route('licencias') }}" wire:navigate class="kpi" data-tono="violeta">
                    <span class="kpi-icono"><flux:icon.clipboard-document-list class="size-6" /></span>
                    <span class="kpi-texto">
                        <span class="kpi-etiqueta">Licencias activas</span>
                        <span class="kpi-valor">{{ $estados['V'] + $estados['X'] }}</span>
                        <span class="kpi-nota">Vigentes y por vencer</span>
                    </span>
                </a>

                <a href="{{ route('licencias') }}" wire:navigate class="kpi" data-tono="ambar">
                    <span class="kpi-icono"><flux:icon.clock class="size-6" /></span>
                    <span class="kpi-texto">
                        <span class="kpi-etiqueta">Por vencer</span>
                        <span class="kpi-valor">{{ $porVencer }}</span>
                        <span class="kpi-nota">En 15 días o menos</span>
                    </span>
                </a>

                <a href="{{ route('licencias') }}" wire:navigate class="kpi" data-tono="rosa">
                    <span class="kpi-icono"><flux:icon.exclamation-triangle class="size-6" /></span>
                    <span class="kpi-texto">
                        <span class="kpi-etiqueta">Vencidas</span>
                        <span class="kpi-valor">{{ $estados['N'] }}</span>
                        <span class="kpi-nota">Sin acceso al CMMS</span>
                    </span>
                </a>

                <a href="{{ route('licencias') }}" wire:navigate class="kpi" data-tono="azul">
                    <span class="kpi-icono"><flux:icon.ellipsis-horizontal-circle class="size-6" /></span>
                    <span class="kpi-texto">
                        <span class="kpi-etiqueta">En proceso</span>
                        <span class="kpi-valor">{{ $estados['P'] }}</span>
                        <span class="kpi-nota">Ventas en negociación</span>
                    </span>
                </a>
            @endif

            @if ($verEmpresas)
                <a href="{{ route('empresas') }}" wire:navigate class="kpi" data-tono="turquesa">
                    <span class="kpi-icono"><flux:icon.building-office class="size-6" /></span>
                    <span class="kpi-texto">
                        <span class="kpi-etiqueta">Empresas activas</span>
                        <span class="kpi-valor">{{ $empresasActivas }}</span>
                        <span class="kpi-nota">{{ $demosEnCurso }} en demo</span>
                    </span>
                </a>
            @endif

            @if ($verMontos)
                <a href="{{ route('reportes') }}" wire:navigate class="kpi" data-tono="verde">
                    <span class="kpi-icono"><flux:icon.banknotes class="size-6" /></span>
                    <span class="kpi-texto">
                        <span class="kpi-etiqueta">Facturado este mes</span>
                        <span class="kpi-valor">${{ number_format($facturadoMes, 2) }}</span>
                        <span class="kpi-nota">Pagos del mes en curso</span>
                    </span>
                </a>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- ===== Facturación de los últimos 6 meses (barras) ===== --}}
            @if ($verMontos)
                <div class="tarjeta {{ $verLicencias ? 'lg:col-span-2' : 'lg:col-span-3' }}">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="sm">Facturación de los últimos 6 meses</flux:heading>
                        <a href="{{ route('reportes') }}" wire:navigate
                            class="text-xs font-semibold text-accent-content hover:underline">Ver reportes →</a>
                    </div>

                    @php
                        $cfgFacturacion = [
                            'tipo' => 'bar',
                            'titulo' => 'Facturado (USD)',
                            'moneda' => true,
                            'resaltarUltimo' => true,
                            'etiquetas' => collect($facturacionMeses)
                                ->pluck('etiqueta')
                                ->map(fn($e) => \Illuminate\Support\Str::ucfirst($e))
                                ->all(),
                            'valores' => collect($facturacionMeses)->pluck('total')->all(),
                            'colores' => '#3b82f6',
                        ];
                    @endphp

                    <div wire:key="grafico-facturacion-{{ md5(json_encode($cfgFacturacion)) }}" class="relative h-64">
                        <div wire:ignore class="h-full">
                            <canvas x-init="(function r() { window.dibujarGrafico ? window.dibujarGrafico($el, @js($cfgFacturacion)) : setTimeout(r, 50) })()"></canvas>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ===== Licencias por estado (dona) ===== --}}
            @if ($verLicencias)
                <div class="tarjeta {{ $verMontos ? '' : 'lg:col-span-3' }}">
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
                        <div wire:key="grafico-estados-{{ md5(json_encode($cfgEstados)) }}" class="relative h-64">
                            <div wire:ignore class="h-full">
                                <canvas x-init="(function r() { window.dibujarGrafico ? window.dibujarGrafico($el, @js($cfgEstados)) : setTimeout(r, 50) })()"></canvas>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ===== Próximos vencimientos de licencias (línea de tiempo) ===== --}}
            @if ($verLicencias)
                <div class="lg:col-span-3 tarjeta">
                    <div class="flex items-center justify-between mb-2">
                        <flux:heading size="sm">Próximos vencimientos de licencias</flux:heading>
                    </div>

                    @if (count($vencimientos) === 0)
                        <flux:text class="text-zinc-500 py-3">No hay licencias vigentes por vencer.</flux:text>
                    @else
                        <x-linea-tiempo :span="$spanVencimientos" :items="collect($vencimientos)
                            ->map(fn($v) => [
                                'dias' => $v['dias'],
                                'nombre' => $v['empresa'],
                                'texto' => $v['texto'],
                                'color' => $v['color'],
                                'detalle' => $v['codigo'] . ' · ' . $v['plan'] . ' · vence ' . $v['fecha'],
                            ])
                            ->all()" />
                        <flux:text class="text-xs text-zinc-500">
                            Cada punto es una licencia. Rojo: urgente (7 días o menos) · Amarillo: por vencer (hasta 15 días) ·
                            Verde: tranquila. Pasa el cursor para ver el detalle.
                        </flux:text>
                    @endif
                </div>
            @endif

            {{-- ===== Fin de prueba de las cuentas demo (línea de tiempo) ===== --}}
            @if ($verEmpresas)
                <div class="lg:col-span-3 tarjeta">
                    <div class="flex items-center justify-between mb-2">
                        <flux:heading size="sm">Fin de prueba de cuentas demo</flux:heading>
                    </div>

                    @if (count($demos) === 0)
                        <flux:text class="text-zinc-500 py-3 text-sm">No hay cuentas demo.</flux:text>
                    @else
                        <x-linea-tiempo :span="$spanDemos" :items="collect($demos)
                            ->map(fn($d) => [
                                'dias' => $d['dias'],
                                'nombre' => $d['nombre'],
                                'texto' => $d['texto'],
                                'color' => $d['color'],
                                'detalle' => $d['cod'] . ' · hasta ' . $d['fecha'],
                            ])
                            ->all()" />
                        <flux:text class="text-xs text-zinc-500">
                            Cada punto es una cuenta demo. Amarillo: le quedan 3 días o menos, hay que darle seguimiento ·
                            Rosa: prueba vencida · Azul: con tiempo.
                        </flux:text>
                    @endif
                </div>
            @endif

            {{-- ===== Top 5 empresas por ingresos ===== --}}
            @if ($verMontos)
                <div class="lg:col-span-3 tarjeta">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="sm">Top 5 empresas que más ingresos generan</flux:heading>
                        <a href="{{ route('reportes') }}" wire:navigate
                            class="text-xs font-semibold text-accent-content hover:underline">Ver reportes →</a>
                    </div>

                    <div class="space-y-3">
                        @forelse ($topEmpresas as $i => $e)
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid size-6 flex-none place-items-center rounded-full text-xs font-bold {{ $i === 0 ? 'bg-amber-500 text-white' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                    {{ $i + 1 }}
                                </span>
                                <span class="w-32 flex-none truncate text-sm sm:w-48">{{ $e['empresa'] }}</span>
                                <span class="h-3.5 min-w-0 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <span
                                        class="block h-full rounded-full {{ $i === 0 ? 'bg-[#1d3260] dark:bg-blue-300' : 'bg-blue-400/80 dark:bg-blue-400/60' }}"
                                        style="width: {{ $e['ancho'] }}%"></span>
                                </span>
                                <span class="w-28 flex-none text-right text-sm font-semibold tabular-nums sm:w-32">
                                    ${{ number_format($e['total'], 2) }}
                                    <span class="font-normal text-zinc-500">{{ $e['porcentaje'] }}%</span>
                                </span>
                            </div>
                        @empty
                            <flux:text class="text-zinc-500 text-sm">Todavía no hay pagos registrados.</flux:text>
                        @endforelse
                    </div>

                    <flux:text class="mt-3 text-xs text-zinc-500">
                        Suma de todos los pagos de la empresa; el porcentaje es su parte del total facturado.
                    </flux:text>
                </div>
            @endif
        </div>
    @endif
</div>