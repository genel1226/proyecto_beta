<div>
    <x-pagina-titulo icono="rectangle-stack" titulo="Planes">
        @can('planes.create')
            <flux:modal.trigger name="plan-form">
                <flux:button icon="plus" variant="primary" wire:click="nuevo">Nuevo plan</flux:button>
            </flux:modal.trigger>
        @endcan
    </x-pagina-titulo>

    <flux:modal name="plan-form" class="max-w-[50vw]! lg:max-w-[560px]! w-full!">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    <div class="flex gap-2">
                        <flux:icon.rectangle-stack />
                        {{ $plan_id ? 'Editar plan' : 'Nuevo plan' }}
                    </div>
                </flux:heading>
            </div>

            <flux:separator />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <flux:input label="Nombre *" wire:model.live="nombre" wire:blur="validarCampo('nombre')"
                        placeholder="Ej: Starter" />
                </div>

                <flux:input label="Sigla" wire:model.live="sigla" wire:blur="validarCampo('sigla')" placeholder="S"
                    description="Hasta 5 letras o números. Se guarda en mayúsculas." />

                <flux:input label="Monto base (USD) *" type="number" min="0" step="0.01"
                    wire:model.live="monto" wire:blur="validarCampo('monto')" placeholder="0.00"
                    description="Se suma a lo que cuestan los usuarios." />

                <flux:select label="Periodo base *" wire:model.live="lapso">
                    <flux:select.option value="M">Mensual</flux:select.option>
                    <flux:select.option value="A">Anual</flux:select.option>
                </flux:select>

                <flux:select label="Tipo *" wire:model.live="tipo">
                    <flux:select.option value="1">Primario (se ofrece al vender)</flux:select.option>
                    <flux:select.option value="2">Personal</flux:select.option>
                    <flux:select.option value="3">Otro (no se ofrece al vender)</flux:select.option>
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:input label="Mínimo de usuarios" type="number" min="1" step="1"
                        wire:model.live="cantidad_min" wire:blur="validarCampo('cantidad_min')" placeholder="Sin mínimo"
                        description="Solo para paquetes (ej. 21). Una licencia con este plan no se puede guardar Vigente con menos usuarios." />
                </div>
            </div>

            @if ($plan_id)
                <div
                    class="rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-3 text-sm text-amber-800 dark:text-amber-300">
                    Cambiar el monto <strong>no modifica lo ya vendido</strong>: las licencias existentes conservan el
                    monto con el que se vendieron. El nuevo monto aplica a ventas nuevas.
                    @if ($en_uso > 0)
                        <br>Este plan lo usan <strong>{{ $en_uso }}</strong> licencia(s).
                    @endif
                </div>
            @endif

            <div class="flex items-center">
                <flux:text class="text-xs">* Campos obligatorios</flux:text>
                <flux:spacer />
                <flux:button type="button" wire:click="guardar" variant="primary" :disabled="! $this->puedeGuardar">
                    {{ $plan_id ? 'Guardar cambios' : 'Guardar plan' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===== Eliminar plan (solo si ninguna licencia lo usa) ===== --}}
    <flux:modal name="confirmar-eliminar-plan" class="min-w-[22rem] max-w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">¿Eliminar el plan?</flux:heading>
                <flux:text class="mt-2">
                    Vas a eliminar el plan <strong>{{ $eliminar_nombre }}</strong>. Ninguna licencia lo usa, pero
                    esta acción no se puede deshacer.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="eliminar">Sí, eliminar</flux:button>
            </div>
        </div>
    </flux:modal>

    <div class="tarjeta">
        <livewire:planes.planes-tabla />
    </div>
</div>
