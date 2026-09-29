<div>
    {{-- I have not failed. I've just found 10,000 ways that won't work. - Thomas Edison --}}

    <div class="flex justify-between py-6 bg-zinc-100 px-3 my-5 dark:bg-zinc-800">
        <div class="order-first flex text-4xl font-bold items-center gap-2 text-zinc-700 dark:text-zinc-200">
            <flux:icon.clipboard-document-list class="size-12" />
            Licencias
        </div>

        <div class="order-last">
            @can('licenses.create')
                <flux:modal.trigger name="nueva-licencia">
                    <flux:button icon="plus" variant="primary" wire:click="nuevo">Nueva licencia</flux:button>
                </flux:modal.trigger>
            @endcan

            <flux:modal name="nueva-licencia" class="max-w-[50vw]! lg:max-w-[960px]! w-full!">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">
                            <div class="flex">
                                <flux:icon.plus />
                                {{ $licencia_id ? 'Editar licencia' : 'Nueva licencia' }}
                            </div>
                        </flux:heading>
                    </div>

                    <flux:separator />

                    {{-- Si el usuario no puede ver nada del resumen, el formulario ocupa todo el ancho --}}
                    <div
                        class="grid grid-cols-1 {{ $verResumen ? 'lg:grid-cols-[1fr_320px]' : '' }} gap-6 items-start">
                        {{-- Columna izquierda: el formulario --}}
                        <div class="space-y-6">

                            {{-- Código de licencia: se genera solo, no es un input --}}
                            <div>
                                <flux:label>Código de Licencia</flux:label>
                                <div
                                    class="mt-1 w-full border border-zinc-300 dark:border-zinc-700 rounded-lg py-3 text-center bg-zinc-50 dark:bg-zinc-800/50">
                                    <span
                                        class="text-2xl font-bold tracking-wide text-blue-600 dark:text-blue-400 tabular-nums">
                                        {{ $licencia_id ? $codigo_actual : $this->proximoCodigo }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <flux:select wire:model="empresa_id" label="Empresa" :disabled="(bool) $licencia_id"
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
                                {{-- El mínimo de "hoy" solo aplica al crear; al editar la fecha de inicio ya puede ser pasada --}}
                                <flux:input label="Fecha de inicio" type="date" wire:model.live="start_date"
                                    :min="$licencia_id ? null : now()->format('Y-m-d')" />
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
                                                @if ($verPrecios)
                                                    <div class="text-xs text-zinc-500">
                                                        ${{ number_format($plan->monto, 2) }} base</div>
                                                @endif
                                            </div>
                                        </flux:radio>
                                    @endforeach
                                </flux:radio.group>
                            </flux:field>

                            {{-- Solo se elige al crear; en edición el estado se cambia con "Dar de baja" --}}
                            @if (!$licencia_id)
                                <flux:field>
                                    <flux:label>Estado inicial</flux:label>
                                    <flux:radio.group wire:model.live="estado_inicial" variant="segmented"
                                        class="w-full">
                                        <flux:radio value="V" class="flex-1">
                                            <div class="text-center">Vigente</div>
                                        </flux:radio>
                                        <flux:radio value="P" class="flex-1">
                                            <div class="text-center">En proceso</div>
                                        </flux:radio>
                                    </flux:radio.group>
                                    @if ($estado_inicial === 'P')
                                        <flux:text class="text-xs mt-1">Puedes dejar "Usuarios por tipo" en 0 mientras
                                            se termina de negociar.</flux:text>
                                    @endif
                                </flux:field>
                            @endif

                            {{-- Usuarios por tipo: stepper. El precio solo se muestra con permiso --}}
                            <flux:field>
                                <flux:label>Cantidad de Usuarios</flux:label>

                                <div
                                    class="border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach ($tipos as $tipo)
                                        <div class="flex items-center gap-4 px-4 py-3">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        class="text-[11px] font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-500 rounded px-1.5 py-0.5">
                                                        {{ $tipo['codigo'] }}
                                                    </span>
                                                    <span class="font-medium text-sm">{{ $tipo['nombre'] }}</span>
                                                </div>
                                                @if ($verPrecios)
                                                    <div class="text-xs text-zinc-500 mt-0.5">
                                                        ${{ number_format($tipo['precio'], 2) }} por usuario
                                                    </div>
                                                @endif
                                            </div>

                                            <div
                                                class="flex items-center border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                                                <flux:button icon="minus" size="sm" variant="ghost"
                                                    wire:click="decrementar({{ $tipo['id'] }})" />
                                                <span class="w-10 text-center font-semibold text-sm tabular-nums">
                                                    {{ $tipo['cantidad'] }}
                                                </span>
                                                <flux:button icon="plus" size="sm" variant="ghost"
                                                    wire:click="incrementar({{ $tipo['id'] }})" />
                                            </div>

                                            @if ($verPrecios)
                                                <div
                                                    class="w-20 text-right font-medium text-sm tabular-nums text-zinc-600 dark:text-zinc-400">
                                                    ${{ number_format($tipo['cantidad'] * $tipo['precio'], 2) }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @error('detalle')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>

                            {{-- Descuento: se ve con licenses.descuento.ver, se edita con licenses.descuento.aplicar --}}
                            @if ($verDescuento)
                                <flux:input label="Descuento (USD)" type="number" min="0" step="1"
                                    wire:model.live="descuento" :disabled="!$aplicarDescuento"
                                    :description="$aplicarDescuento
                                        ? 'Único monto que edita el vendedor directamente.'
                                        : 'Solo lectura: no tienes permiso para aplicar descuentos.'" />
                            @endif

                            @if ($verObservaciones)
                                <flux:textarea label="Observaciones" wire:model="observaciones"
                                    placeholder="Ej: descuento por pronto pago, condición especial acordada..."
                                    rows="3" />
                            @endif
                        </div>

                        {{-- Columna derecha: resumen. Cada bloque exige su propio permiso --}}
                        @if ($verResumen)
                            <div
                                class="bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-lg p-5 space-y-1 lg:sticky lg:top-4">
                                <flux:heading size="sm" class="mb-3">Resumen</flux:heading>

                                @if ($verPrecios)
                                    <div class="flex justify-between text-sm text-zinc-600 dark:text-zinc-400 py-1">
                                        <span>Plan {{ $calculo['plan_nombre'] ?? '—' }}</span>
                                        <span class="tabular-nums text-zinc-900 dark:text-zinc-100">
                                            ${{ number_format($calculo['plan_monto'], 2) }}
                                        </span>
                                    </div>

                                    @foreach ($tipos as $tipo)
                                        <div class="flex justify-between text-sm text-zinc-600 dark:text-zinc-400 py-1">
                                            <span>{{ $tipo['codigo'] }} × {{ $tipo['cantidad'] }}</span>
                                            <span class="tabular-nums text-zinc-900 dark:text-zinc-100">
                                                ${{ number_format($tipo['cantidad'] * $tipo['precio'], 2) }}
                                            </span>
                                        </div>
                                    @endforeach
                                @endif

                                @if ($verDescuento)
                                    <div class="flex justify-between text-sm text-zinc-600 dark:text-zinc-400 py-1">
                                        <span>Descuento</span>
                                        <span class="tabular-nums text-zinc-900 dark:text-zinc-100">
                                            −${{ number_format($calculo['descuento'], 2) }}
                                        </span>
                                    </div>
                                @endif

                                @if ($verMonto)
                                    @if ($verPrecios || $verDescuento)
                                        <flux:separator class="my-3" />
                                    @endif

                                    <div class="bg-emerald-50 dark:bg-emerald-950/40 rounded-lg p-4">
                                        <div
                                            class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 mb-1">
                                            <flux:icon.lock-closed class="size-3" />
                                            MONTO TOTAL — calculado automáticamente
                                        </div>
                                        <div
                                            class="text-3xl font-extrabold tabular-nums text-emerald-700 dark:text-emerald-400">
                                            ${{ number_format($calculo['total'], 2) }}
                                        </div>
                                        <div class="text-xs text-zinc-500 mt-1">plan + usuarios − descuento. No es
                                            editable.
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex">
                        <flux:spacer />
                        <flux:button type="button" wire:click="guardar" variant="primary">
                            {{ $licencia_id ? 'Guardar cambios' : 'Guardar licencia' }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    </div>

    {{-- ===== Dar de baja (reversible con "Reactivar") ===== --}}
    <flux:modal name="confirmar-baja" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">¿Dar de baja la licencia?</flux:heading>
                <flux:text class="mt-2">
                    Vas a dar de baja la licencia <strong>{{ $baja_codigo }}</strong>.<br>
                    Pasará a estado Cancelada y <strong>la empresa perderá el acceso al CMMS</strong>.<br>
                    Mientras no haya vencido, la podrás reactivar.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="darDeBaja">Sí, dar de baja</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===== Renovar: el pago llega antes de vencer ===== --}}
    <flux:modal name="confirmar-renovacion" class="min-w-[24rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">¿Renovar la licencia?</flux:heading>
                <flux:text class="mt-2">
                    Vas a registrar el pago de la licencia <strong>{{ $renovar_codigo }}</strong>.
                    El vencimiento actual pasa a ser la fecha de inicio del nuevo período.
                </flux:text>
            </div>

            @if ($renovacion)
                <div
                    class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4 text-sm space-y-2">
                    <div class="flex justify-between gap-4 text-zinc-600 dark:text-zinc-400">
                        <span>Período actual</span>
                        <span class="tabular-nums">
                            {{ $renovacion['actual_inicio']->format('d/m/Y') }} →
                            {{ $renovacion['actual_fin']->format('d/m/Y') }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4 font-semibold">
                        <span>Nuevo período</span>
                        <span class="tabular-nums">
                            {{ $renovacion['inicio']->format('d/m/Y') }} →
                            {{ $renovacion['fin']?->format('d/m/Y') ?? '—' }}
                        </span>
                    </div>
                </div>

                {{-- Personalizada: no se puede calcular el período, se elige la fecha --}}
                @if ($renovacion['periodicidad'] === 'P')
                    <flux:input type="date" label="Renovar hasta *" wire:model.live="renovar_hasta"
                        :min="$renovacion['actual_fin']->copy()->addDay()->format('Y-m-d')" />
                @endif
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="renovar">Sí, renovar</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===== Activar (En proceso) o Reactivar (Cancelada) ===== --}}
    <flux:modal name="confirmar-activacion" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $activar_reactivar ? '¿Reactivar la licencia?' : '¿Activar la licencia?' }}
                </flux:heading>
                <flux:text class="mt-2">
                    La licencia <strong>{{ $activar_codigo }}</strong> pasará a estado Vigente y
                    <strong>la empresa recuperará el acceso al CMMS</strong>.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="activar">
                    {{ $activar_reactivar ? 'Sí, reactivar' : 'Sí, activar' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:toast />

    <livewire:licencias.licencias-tabla />
</div>
