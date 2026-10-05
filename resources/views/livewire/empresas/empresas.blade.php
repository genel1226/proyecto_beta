<div>
    {{-- Very little is needed to make a happy life. - Marcus Aurelius --}}

    <x-pagina-titulo icono="building-office" titulo="Empresas">
        @can('empresas.create')
            <flux:modal.trigger name="nueva-empresa">
                <flux:button icon="plus" variant="primary" wire:click="nuevo">Nueva empresa</flux:button>
            </flux:modal.trigger>
        @endcan
    </x-pagina-titulo>

    <flux:modal name="nueva-empresa" class="max-w-[50vw]! lg:max-w-[760px]! w-full!">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    <div class="flex gap-2">
                        <flux:icon.building-office />
                        {{ $empresa_id ? 'Editar empresa' : 'Nueva empresa' }}
                    </div>
                </flux:heading>
            </div>

            <flux:separator />

            {{-- Código de empresa: se genera solo, no es un input --}}
            <div>
                <flux:label>Código de Empresa</flux:label>
                <div
                    class="mt-1 w-full border border-zinc-300 dark:border-zinc-700 rounded-lg py-3 text-center bg-zinc-50 dark:bg-zinc-800/50">
                    <span
                        class="text-2xl font-bold tracking-wide text-blue-600 dark:text-blue-400 tabular-nums">
                        {{ $empresa_id ? $codigo_actual : $this->proximoCodigo }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input label="Razón social *" wire:model.live.debounce.500ms="razon_social"
                    wire:blur="validarCampo('razon_social')" placeholder="Nombre legal de la empresa" />

                <flux:input label="Nombre comercial *" wire:model.live.debounce.500ms="nombre_comercial"
                    wire:blur="validarCampo('nombre_comercial')" placeholder="Nombre con el que se conoce" />

                <flux:input label="NIT *" wire:model.live.debounce.500ms="nit" wire:blur="validarCampo('nit')"
                    placeholder="Número de identificación" />

                {{-- País: lista completa con buscador. .live: el botón de guardar
                     depende de este campo, así que necesita avisar al instante --}}
                <flux:select wire:model.live="pais" wire:blur="validarCampo('pais')" label="País *"
                    placeholder="Selecciona un país...">
                    <x-slot name="search">
                        {{-- <flux:select.search class="px-4" placeholder="Buscar país..." /> --}}
                    </x-slot>

                    @foreach ($paises as $nombre)
                        <flux:select.option value="{{ $nombre }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input label="Correo electrónico *" type="email" wire:model.live.debounce.500ms="email"
                    wire:blur="validarCampo('email')" placeholder="contacto@empresa.com"
                    description="A este correo llegan las alertas de vencimiento." />

                <flux:input label="Teléfono" wire:model.live.debounce.500ms="telefono"
                    wire:blur="validarCampo('telefono')" placeholder="Opcional" />

                <div class="sm:col-span-2">
                    <flux:input label="Página web" wire:model.live.debounce.500ms="pagina_web"
                        wire:blur="validarCampo('pagina_web')" placeholder="www.empresa.com (opcional)" />
                </div>

                <div class="sm:col-span-2">
                    <flux:textarea label="Dirección" wire:model.live.debounce.500ms="direccion"
                        wire:blur="validarCampo('direccion')" rows="2" placeholder="Opcional" />
                </div>

                {{-- Cuenta demo: empresa de prueba, sin licencia --}}
                <div class="sm:col-span-2 space-y-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:switch wire:model.live="es_demo" label="Cuenta demo"
                        description="Empresa de prueba: todavía no tiene licencia. Al crearle su primera licencia Vigente deja de ser demo." />

                    @if ($es_demo)
                        <flux:input type="date" label="Fin de la prueba *" wire:model.live="demo_hasta"
                            wire:blur="validarCampo('demo_hasta')" />
                    @endif

                    @error('es_demo')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            </div>

            <div class="flex items-center">
                <flux:text class="text-xs">* Campos obligatorios</flux:text>
                <flux:spacer />
                <flux:button type="button" wire:click="guardar" variant="primary"
                    :disabled="! $this->puedeGuardar">
                    {{ $empresa_id ? 'Guardar cambios' : 'Guardar empresa' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirmar-estado-empresa" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $estado_activar ? '¿Reactivar la empresa?' : '¿Desactivar la empresa?' }}
                </flux:heading>
                <flux:text class="mt-2">
                    @if ($estado_activar)
                        La empresa <strong>{{ $estado_empresa }}</strong> volverá a estar activa y sus usuarios
                        podrán entrar de nuevo al CMMS.
                    @else
                        La empresa <strong>{{ $estado_empresa }}</strong> quedará inactiva:
                        <strong>sus usuarios no podrán entrar al CMMS</strong> hasta que la reactives.<br>
                        Su historial se conserva.
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

    <flux:toast />

    <div class="tarjeta">
        <livewire:empresas.empresas-tabla />
    </div>
</div>
