<div>
    {{-- I have not failed. I've just found 10,000 ways that won't work. - Thomas Edison --}}

    <div class="flex justify-between py-6 bg-zinc-100 px-3 my-5">
        <div class="order-first flex">
            <flux:icon.plus />
            Licencias view
        </div>

        <div class="order-last">
            <flux:modal.trigger name="nueva-licencia">
                <flux:button icon="plus" variant="primary">Nueva licencia</flux:button>
            </flux:modal.trigger>

            <flux:modal name="nueva-licencia" class="max-w-[50vw]! lg:max-w-[960px]! w-full!">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">
                            <div class="flex">
                                <flux:icon.plus />
                                Nueva licencia
                            </div>
                        </flux:heading>
                    </div>

                    <flux:separator />

                    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6 items-start">
                        {{-- Columna izquierda: el formulario --}}
                        <div class="space-y-6">

                            {{-- Código de licencia: se genera solo, no es un input --}}
                            <div>
                                <flux:label>Código de Licencia</flux:label>
                                <div
                                    class="mt-1 w-full border border-zinc-300 dark:border-zinc-700 rounded-lg py-3 text-center bg-zinc-50 dark:bg-zinc-800/50">
                                    <span
                                        class="text-2xl font-bold tracking-wide text-blue-600 dark:text-blue-400 tabular-nums">
                                        {{ $this->proximoCodigo }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <flux:select wire:model="empresa_id" label="Empresa"
                                    placeholder="Selecciona una empresa...">
                                    @foreach ($empresas as $empresa)
                                        <flux:select.option value="{{ $empresa->id }}">{{ $empresa->razon_social }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="periodicidad" label="Periodo">
                                    <flux:select.option value="M">Mensual</flux:select.option>
                                    <flux:select.option value="A">Anual</flux:select.option>
                                    <flux:select.option value="P">Personalizada</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <flux:input label="Fecha de inicio" type="date" wire:model.live="start_date"
                                    :min="now()->format('Y-m-d')" />
                                <flux:input label="Fecha de vencimiento" type="date" wire:model="end_date"
                                    :min="$this->minEndDate" />
                            </div>

                            {{-- Plan: control segmentado, no un dropdown --}}
                            <flux:field>
                                <flux:label>Plan</flux:label>
                                <flux:radio.group wire:model.live="plan_id" variant="segmented" class="w-full">
                                    @foreach ($planes as $plan)
                                        <flux:radio value="{{ $plan->id }}" class="flex-1">
                                            <div class="text-center">
                                                <div class="font-semibold">{{ $plan->nombre }}</div>
                                                <div class="text-xs text-zinc-500">${{ number_format($plan->monto, 2) }}
                                                    base</div>
                                            </div>
                                        </flux:radio>
                                    @endforeach
                                </flux:radio.group>
                            </flux:field>

                            {{-- Determina si "Usuarios por tipo" es obligatorio o no --}}
                            <flux:field>
                                <flux:label>Estado inicial</flux:label>
                                <flux:radio.group wire:model.live="estado_inicial" variant="segmented" class="w-full">
                                    <flux:radio value="V" class="flex-1">
                                        <div class="text-center">Vigente</div>
                                    </flux:radio>
                                    <flux:radio value="P" class="flex-1">
                                        <div class="text-center">En proceso</div>
                                    </flux:radio>
                                </flux:radio.group>
                                @if ($estado_inicial === 'P')
                                    <flux:text class="text-xs mt-1">Puedes dejar "Usuarios por tipo" en 0 mientras se
                                        termina de negociar.</flux:text>
                                @endif
                            </flux:field>

                            {{-- Usuarios por tipo: stepper con precio inline --}}
                            <flux:field>
                                <flux:label>Cantidad de Usuarios</flux:label>

                                <div
                                    class="border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach ($detalle as $index => $fila)
                                        <div class="flex items-center gap-4 px-4 py-3">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        class="text-[11px] font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-500 rounded px-1.5 py-0.5">
                                                        {{ $fila['codigo'] }}
                                                    </span>
                                                    <span class="font-medium text-sm">{{ $fila['nombre'] }}</span>
                                                </div>
                                                <div class="text-xs text-zinc-500 mt-0.5">
                                                    ${{ number_format($fila['precio_unitario'], 2) }} por usuario
                                                </div>
                                            </div>

                                            <div
                                                class="flex items-center border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                                                <flux:button icon="minus" size="sm" variant="ghost"
                                                    wire:click="decrementar({{ $index }})" />
                                                <span class="w-10 text-center font-semibold text-sm tabular-nums">
                                                    {{ $fila['cantidad'] }}
                                                </span>
                                                <flux:button icon="plus" size="sm" variant="ghost"
                                                    wire:click="incrementar({{ $index }})" />
                                            </div>

                                            <div
                                                class="w-20 text-right font-medium text-sm tabular-nums text-zinc-600 dark:text-zinc-400">
                                                ${{ number_format($fila['cantidad'] * $fila['precio_unitario'], 2) }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @error('detalle')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>

                            <flux:input label="Descuento (USD)" type="number" min="0" step="1"
                                wire:model.live="descuento"
                                description="Único monto que edita el vendedor directamente." />

                            <flux:textarea label="Observaciones" wire:model="observaciones"
                                placeholder="Ej: descuento por pronto pago, condición especial acordada..."
                                rows="3" />
                        </div>

                        {{-- Columna derecha: resumen, siempre visible --}}
                        <div
                            class="bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-lg p-5 space-y-1 lg:sticky lg:top-4">
                            <flux:heading size="sm" class="mb-3">Resumen</flux:heading>

                            <div class="flex justify-between text-sm text-zinc-600 dark:text-zinc-400 py-1">
                                <span>Plan {{ $this->planSeleccionado?->nombre ?? '—' }}</span>
                                <span class="tabular-nums text-zinc-900 dark:text-zinc-100">
                                    ${{ number_format($this->planSeleccionado->monto ?? 0, 2) }}
                                </span>
                            </div>

                            @foreach ($detalle as $fila)
                                <div class="flex justify-between text-sm text-zinc-600 dark:text-zinc-400 py-1">
                                    <span>{{ $fila['codigo'] }} × {{ $fila['cantidad'] }}</span>
                                    <span class="tabular-nums text-zinc-900 dark:text-zinc-100">
                                        ${{ number_format($fila['cantidad'] * $fila['precio_unitario'], 2) }}
                                    </span>
                                </div>
                            @endforeach

                            <div class="flex justify-between text-sm text-zinc-600 dark:text-zinc-400 py-1">
                                <span>Descuento</span>
                                <span class="tabular-nums text-zinc-900 dark:text-zinc-100">
                                    −${{ number_format($descuento, 2) }}
                                </span>
                            </div>

                            <flux:separator class="my-3" />

                            <div class="bg-emerald-50 dark:bg-emerald-950/40 rounded-lg p-4">
                                <div
                                    class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 mb-1">
                                    <flux:icon.lock-closed class="size-3" />
                                    MONTO TOTAL — calculado automáticamente
                                </div>
                                <div
                                    class="text-3xl font-extrabold tabular-nums text-emerald-700 dark:text-emerald-400">
                                    ${{ number_format($this->totalCalculado, 2) }}
                                </div>
                                <div class="text-xs text-zinc-500 mt-1">plan + usuarios − descuento. No es editable.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex">
                        <flux:spacer />
                        <flux:button type="button" wire:click="guardar" variant="primary">
                            Guardar licencia
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    </div>

    <livewire:licencias.licencias-tabla />
</div>
