<div>
    <div class="flex justify-between py-6 bg-zinc-100 px-3 my-5 dark:bg-zinc-800">
        <div class="order-first flex text-4xl font-bold items-center gap-2 text-zinc-700 dark:text-zinc-200">
            <flux:icon.chart-bar class="size-12" />
            Reportes
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mx-3 mb-6 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <flux:input type="date" label="Desde" wire:model.live="desde" />
            <flux:input type="date" label="Hasta" wire:model.live="hasta" />

            <flux:select wire:model.live="empresa_id" label="Empresa" placeholder="Todas las empresas">
                <flux:select.option value="">Todas las empresas</flux:select.option>
                @foreach ($empresas as $empresa)
                    <flux:select.option value="{{ $empresa->id }}">{{ $empresa->razon_social }}</flux:select.option>
                @endforeach
            </flux:select>

            @can('reportes.export')
                <div class="flex gap-2">
                    <flux:button wire:click="exportarExcel" icon="table-cells" variant="primary" class="w-full">
                        Excel
                    </flux:button>
                    <flux:button wire:click="exportarPdf" icon="document-arrow-down" variant="filled" class="w-full">
                        PDF
                    </flux:button>
                </div>
            @endcan
        </div>
    </div>

    {{-- KPIs --}}
    <div class="mx-3 mb-8 grid grid-cols-2 sm:grid-cols-5 gap-4">
        @can('reportes.montos')
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                    ${{ number_format($this->kpis['total_facturado'], 2) }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Facturado en el rango</div>
            </div>
        @endcan

        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold tabular-nums">{{ $this->kpis['cantidad_pagos'] }}</div>
            <div class="text-xs text-zinc-500 mt-1">Pagos en el rango</div>
        </div>

        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold tabular-nums text-blue-600 dark:text-blue-400">
                {{ $this->kpis['licencias_activas'] }}</div>
            <div class="text-xs text-zinc-500 mt-1">Licencias activas hoy</div>
        </div>

        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">
                {{ $this->kpis['por_vencer_15'] }}</div>
            <div class="text-xs text-zinc-500 mt-1">Por vencer en 15 días</div>
        </div>

        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold tabular-nums">{{ $this->kpis['empresas_activas'] }}</div>
            <div class="text-xs text-zinc-500 mt-1">Empresas activas</div>
        </div>
    </div>

    <div class="mx-3 grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Facturación por mes --}}
        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
            <flux:heading size="sm" class="mb-3">Facturación por mes</flux:heading>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->facturacionPorMes as $fila)
                    <div class="flex justify-between py-2 text-sm">
                        <span>{{ \Carbon\Carbon::createFromFormat('Y-m', $fila->mes)->translatedFormat('F Y') }}</span>
                        <span class="flex gap-3">
                            <span class="text-zinc-500">{{ $fila->cantidad }} pago(s)</span>
                            @can('reportes.montos')
                                <span class="font-semibold tabular-nums">${{ number_format($fila->total, 2) }}</span>
                            @endcan
                        </span>
                    </div>
                @empty
                    <flux:text class="text-zinc-500 py-2">Sin pagos en este rango.</flux:text>
                @endforelse
            </div>
        </div>

        {{-- Top empresas --}}
        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
            <flux:heading size="sm" class="mb-3">Top empresas por facturación</flux:heading>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->facturacionPorEmpresa as $fila)
                    <div class="flex justify-between py-2 text-sm">
                        <span>{{ $fila->nombre_comercial }}</span>
                        <span class="flex gap-3">
                            <span class="text-zinc-500">{{ $fila->cantidad }} pago(s)</span>
                            @can('reportes.montos')
                                <span class="font-semibold tabular-nums">${{ number_format($fila->total, 2) }}</span>
                            @endcan
                        </span>
                    </div>
                @empty
                    <flux:text class="text-zinc-500 py-2">Sin pagos en este rango.</flux:text>
                @endforelse
            </div>
        </div>

        {{-- Licencias por estado --}}
        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 lg:col-span-2">
            <flux:heading size="sm" class="mb-3">Licencias por estado (ahora mismo)</flux:heading>

            @php
                $etiquetas = [
                    'V' => 'Vigente',
                    'X' => 'Por vencer',
                    'N' => 'Vencida',
                    'P' => 'En proceso',
                    'C' => 'Cancelada',
                ];
                $colores = ['V' => 'green', 'X' => 'amber', 'N' => 'rose', 'P' => 'blue', 'C' => 'zinc'];
            @endphp

            <div class="flex flex-wrap gap-3">
                @forelse ($this->licenciasPorEstado as $estado => $cantidad)
                    <flux:badge :color="$colores[$estado] ?? 'zinc'" size="lg">
                        {{ $etiquetas[$estado] ?? $estado }}: {{ $cantidad }}
                    </flux:badge>
                @empty
                    <flux:text class="text-zinc-500">Todavía no hay licencias registradas.</flux:text>
                @endforelse
            </div>
        </div>
    </div>

    <flux:toast />
</div>
