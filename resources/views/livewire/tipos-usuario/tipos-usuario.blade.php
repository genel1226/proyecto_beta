<div>
    <div class="flex justify-between py-6 bg-zinc-100 px-3 my-5 dark:bg-zinc-800">
        <div class="order-first flex text-4xl font-bold items-center gap-2 text-zinc-700 dark:text-zinc-200">
            <flux:icon.user-group class="size-12" />
            Tipos de usuario
        </div>

        <div class="order-last">
            @can('tipos_usuario.create')
                <flux:modal.trigger name="nuevo-tipo-usuario">
                    <flux:button icon="plus" variant="primary" wire:click="nuevo">Nuevo tipo</flux:button>
                </flux:modal.trigger>
            @endcan

            <flux:modal name="nuevo-tipo-usuario" class="max-w-[50vw]! lg:max-w-[560px]! w-full!">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">
                            <div class="flex gap-2">
                                <flux:icon.user-group />
                                {{ $tipo_id ? 'Editar tipo de usuario' : 'Nuevo tipo de usuario' }}
                            </div>
                        </flux:heading>
                    </div>

                    <flux:separator />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:input label="Código *" wire:model.live.debounce.500ms="codigo"
                            wire:blur="validarCampo('codigo')" placeholder="GT-4"
                            description="Se guarda en mayúsculas." />

                        <flux:input label="Precio unitario (USD) *" type="number" min="0" step="0.01"
                            wire:model.live.debounce.500ms="precio_unitario" wire:blur="validarCampo('precio_unitario')"
                            placeholder="0.00" description="Lo que cuesta cada usuario de este tipo." />

                        <div class="sm:col-span-2">
                            <flux:input label="Nombre *" wire:model.live.debounce.500ms="nombre"
                                wire:blur="validarCampo('nombre')" placeholder="Ej: Supervisor de planta" />
                        </div>
                    </div>

                    @if ($tipo_id)
                        <div
                            class="rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-3 text-sm text-amber-800 dark:text-amber-300">
                            Cambiar el precio <strong>no modifica lo ya vendido</strong>: las licencias que ya tienen
                            este tipo conservan el precio con el que se vendieron. El precio nuevo aplica a ventas
                            nuevas y a quien lo agregue después a una licencia.
                        </div>
                    @endif

                    <div class="flex items-center">
                        <flux:text class="text-xs">* Campos obligatorios</flux:text>
                        <flux:spacer />
                        <flux:button type="button" wire:click="guardar" variant="primary"
                            :disabled="! $this->puedeGuardar">
                            {{ $tipo_id ? 'Guardar cambios' : 'Guardar tipo' }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    </div>

    <flux:modal name="confirmar-estado-tipo-usuario" class="min-w-[22rem] max-w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $estado_activar ? '¿Reactivar el tipo de usuario?' : '¿Desactivar el tipo de usuario?' }}
                </flux:heading>
                <flux:text class="mt-2">
                    @if ($estado_activar)
                        El tipo <strong>{{ $estado_tipo }}</strong> volverá a ofrecerse al armar licencias nuevas.
                    @else
                        El tipo <strong>{{ $estado_tipo }}</strong> dejará de ofrecerse al armar licencias nuevas.<br>
                        @if ($estado_en_uso > 0)
                            Lo usan <strong>{{ $estado_en_uso }}</strong> licencia(s) activa(s): siguen funcionando
                            con el precio con el que se vendieron.
                        @else
                            Ninguna licencia activa lo usa ahora.
                        @endif
                        <br>El historial se conserva y lo puedes reactivar cuando quieras.
                    @endif
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button :variant="$estado_activar ? 'primary' : 'danger'" wire:click="cambiarEstado">
                    {{ $estado_activar ? 'Sí, reactivar' : 'Sí, desactivar' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <livewire:tipos-usuario.tipos-usuario-tabla />
</div>
