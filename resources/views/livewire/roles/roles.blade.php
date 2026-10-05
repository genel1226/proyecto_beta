<div>
    <div class="flex flex-wrap justify-between gap-2 py-6 bg-zinc-100 px-3 my-5 dark:bg-zinc-800">
        <div class="order-first flex text-4xl font-bold items-center gap-2 text-zinc-700 dark:text-zinc-200">
            <flux:icon.shield-check class="size-12" />
            Roles
        </div>

        <div class="order-last">
            @can('roles.create')
                <flux:modal.trigger name="rol-form">
                    <flux:button icon="plus" variant="primary" wire:click="nuevoRol">Nuevo rol</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    {{-- ===== Crear / editar el nombre y la descripción de un rol ===== --}}
    <flux:modal name="rol-form" class="max-w-[50vw]! lg:max-w-[520px]! w-full!">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $form_id ? 'Editar rol' : 'Nuevo rol' }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $form_id ? 'Cambia el nombre o la descripción.' : 'Después de crearlo, activa sus permisos con los switches.' }}
                </flux:text>
            </div>

            <flux:separator />

            <div class="space-y-4">
                <flux:input label="Nombre *" wire:model.live.debounce.500ms="nombre" wire:blur="validarCampo('nombre')"
                    placeholder="Ej: Gestor sin descuentos" />

                <flux:textarea label="Descripción" wire:model.live.debounce.500ms="descripcion"
                    wire:blur="validarCampo('descripcion')" rows="2" placeholder="Opcional" />
            </div>

            <div class="flex items-center">
                <flux:text class="text-xs">* Campo obligatorio</flux:text>
                <flux:spacer />
                <flux:button type="button" wire:click="guardarRol" variant="primary" :disabled="! $this->puedeGuardar">
                    {{ $form_id ? 'Guardar cambios' : 'Crear rol' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===== Selector de rol ===== --}}
    <div class="mx-3 mb-6 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-3">
        <flux:select wire:model.live="rol_id" label="Rol a gestionar">
            @foreach ($roles as $r)
                <flux:select.option value="{{ $r->id }}">{{ $r->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($rolActual)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $rolActual->description ?: 'Sin descripción.' }}
                    · <strong>{{ $usuariosDelRol }}</strong> usuario(s) con este rol
                </div>

                @if ($puedeEditar)
                    <flux:modal.trigger name="rol-form">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editarRol">
                            Editar nombre y descripción
                        </flux:button>
                    </flux:modal.trigger>
                @endif
            </div>

            @if ($esSistema)
                <flux:text class="text-xs text-zinc-500">
                    Rol del sistema: siempre tiene todos los permisos, incluidos los que se agreguen más adelante.
                    No se puede editar.
                </flux:text>
            @elseif ($puedeEditar && $usuariosDelRol > 0)
                <flux:text class="text-xs text-amber-600 dark:text-amber-400">
                    Los cambios que hagas aquí afectan de inmediato a {{ $usuariosDelRol }} usuario(s).
                </flux:text>
            @elseif (!$puedeEditar)
                <flux:text class="text-xs text-zinc-500">Solo lectura: no tienes permiso para editar roles.</flux:text>
            @endif
        @endif
    </div>

    {{-- ===== Permisos del rol, por módulo ===== --}}
    @if ($rolActual)
        <div class="mx-3 grid grid-cols-1 lg:grid-cols-2 gap-5">
            @foreach ($modulos as $modulo)
                {{-- El wire:key lleva el rol: al cambiar de rol se dibujan switches nuevos, sin arrastrar el estado del anterior --}}
                <div wire:key="rol-{{ $rol_id }}-modulo-{{ $modulo->id }}"
                    class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <flux:heading size="sm">{{ $modulo->description }}</flux:heading>

                        @if ($puedeEditar)
                            <div class="flex gap-3 text-xs font-semibold">
                                <button type="button" wire:click="marcarTodo({{ $modulo->id }})"
                                    class="text-amber-600 dark:text-amber-400 hover:underline">
                                    MARCAR TODO
                                </button>
                                <button type="button" wire:click="quitarTodo({{ $modulo->id }})"
                                    class="text-zinc-500 hover:underline">
                                    QUITAR TODO
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
                        @forelse ($modulo->children as $permiso)
                            <flux:switch wire:model.live="activos.{{ $permiso->id }}"
                                label="{{ $permiso->description }}" :disabled="!$puedeEditar" />
                        @empty
                            <flux:text class="text-zinc-500 text-sm">Este módulo no tiene permisos todavía.</flux:text>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <flux:text class="mx-3 text-zinc-500">Todavía no hay roles. Corre el seeder de permisos para crear los básicos.
        </flux:text>
    @endif
</div>
