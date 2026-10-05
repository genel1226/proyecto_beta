<div>
    <x-pagina-titulo icono="chart-bar" titulo="Reportes" />

    {{-- Filtros --}}
    <div class="tarjeta mb-6">
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

    {{-- Indicadores: fondo suave e ícono en el color fuerte del mismo tono (el color significa algo: ámbar = por vencer, verde = dinero…) --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @can('reportes.montos')
            <div class="kpi" data-tono="verde">
                <span class="kpi-icono"><flux:icon.banknotes class="size-6" /></span>
                <span class="kpi-texto">
                    <span class="kpi-etiqueta">Facturado en el rango</span>
                    <span class="kpi-valor">${{ number_format($this->kpis['total_facturado'], 2) }}</span>
                </span>
            </div>
        @endcan

        <div class="kpi" data-tono="azul">
            <span class="kpi-icono"><flux:icon.credit-card class="size-6" /></span>
            <span class="kpi-texto">
                <span class="kpi-etiqueta">Pagos en el rango</span>
                <span class="kpi-valor">{{ $this->kpis['cantidad_pagos'] }}</span>
            </span>
        </div>

        <div class="kpi" data-tono="violeta">
            <span class="kpi-icono"><flux:icon.clipboard-document-list class="size-6" /></span>
            <span class="kpi-texto">
                <span class="kpi-etiqueta">Licencias activas hoy</span>
                <span class="kpi-valor">{{ $this->kpis['licencias_activas'] }}</span>
            </span>
        </div>

        <div class="kpi" data-tono="ambar">
            <span class="kpi-icono"><flux:icon.clock class="size-6" /></span>
            <span class="kpi-texto">
                <span class="kpi-etiqueta">Por vencer en 15 días</span>
                <span class="kpi-valor">{{ $this->kpis['por_vencer_15'] }}</span>
            </span>
        </div>

        <div class="kpi" data-tono="turquesa">
            <span class="kpi-icono"><flux:icon.building-office class="size-6" /></span>
            <span class="kpi-texto">
                <span class="kpi-etiqueta">Empresas activas</span>
                <span class="kpi-valor">{{ $this->kpis['empresas_activas'] }}</span>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Facturación por mes --}}
        <div class="tarjeta">
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
        <div class="tarjeta">
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

        {{-- Facturación por plan (producto) --}}
        <div class="tarjeta">
            <flux:heading size="sm" class="mb-3">Facturación por plan</flux:heading>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->facturacionPorPlan as $fila)
                    <div class="flex justify-between py-2 text-sm">
                        <span>{{ $fila->plan }}</span>
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
        <div class="tarjeta">
            <flux:heading size="sm" class="mb-3">Licencias por estado (ahora mismo)</flux:heading>

            @php
                $etiquetas = ['V' => 'Vigente', 'X' => 'Por vencer', 'N' => 'Vencida', 'P' => 'En proceso', 'C' => 'Cancelada'];
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
