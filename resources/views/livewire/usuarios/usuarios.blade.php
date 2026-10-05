<div>
    <x-pagina-titulo icono="users" titulo="Usuarios">
        @can('usuarios.create')
            <flux:modal.trigger name="usuario-form">
                <flux:button icon="plus" variant="primary" wire:click="nuevo">Nuevo usuario</flux:button>
            </flux:modal.trigger>
        @endcan
    </x-pagina-titulo>

    {{-- ===== Crear / editar usuario ===== --}}
    <flux:modal name="usuario-form" class="max-w-[50vw]! lg:max-w-[620px]! w-full!">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    <div class="flex gap-2">
                        <flux:icon.users />
                        {{ $usuario_id ? 'Editar usuario' : 'Nuevo usuario' }}
                    </div>
                </flux:heading>
            </div>

            <flux:separator />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input label="Nombre *" wire:model.live.debounce.500ms="nombre" wire:blur="validarCampo('nombre')"
                    placeholder="Nombre y apellido" />

                <flux:input label="Correo electrónico *" type="email" wire:model.live.debounce.500ms="email"
                    wire:blur="validarCampo('email')" placeholder="usuario@software4tech.com"
                    description="Con este correo inicia sesión." />

                <flux:select wire:model.live="rol_id" wire:blur="validarCampo('rol_id')" label="Rol *"
                    placeholder="Selecciona un rol..." :disabled="$esPropio">
                    @foreach ($roles as $r)
                        <flux:select.option value="{{ $r->id }}">{{ $r->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input label="Cargo" wire:model.live.debounce.500ms="cargo" wire:blur="validarCampo('cargo')"
                    placeholder="Opcional" />

                <div class="sm:col-span-2">
                    <flux:input type="password" autocomplete="new-password"
                        :label="$usuario_id ? 'Nueva contraseña' : 'Contraseña *'"
                        wire:model.live.debounce.500ms="password" wire:blur="validarCampo('password')"
                        :description="$usuario_id
                            ?
                            'Déjala vacía para no cambiarla.' :
                            'La define el administrador; el usuario puede cambiarla después en Configuración.'" />
                </div>
            </div>

            @if ($esPropio)
                <flux:text class="text-xs text-zinc-500">
                    Estás editando tu propia cuenta: no puedes cambiarte el rol, para no quedarte sin acceso.
                </flux:text>
            @endif

            <div class="flex items-center">
                <flux:text class="text-xs">* Campos obligatorios</flux:text>
                <flux:spacer />
                <flux:button type="button" wire:click="guardar" variant="primary" :disabled="! $this->puedeGuardar">
                    {{ $usuario_id ? 'Guardar cambios' : 'Guardar usuario' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===== Activar / desactivar ===== --}}
    <flux:modal name="confirmar-estado-usuario" class="min-w-[22rem] max-w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $estado_activar ? '¿Reactivar al usuario?' : '¿Desactivar al usuario?' }}
                </flux:heading>
                <flux:text class="mt-2">
                    @if ($estado_activar)
                        <strong>{{ $estado_usuario }}</strong> podrá volver a iniciar sesión.
                    @else
                        <strong>{{ $estado_usuario }}</strong> ya no podrá iniciar sesión. Si tiene una sesión
                        abierta, se le cierra en su próxima acción.<br>
                        Su historial se conserva y lo puedes reactivar cuando quieras.
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

    {{-- ===== Permisos extra de un usuario ===== --}}
    <flux:modal name="permisos-usuario" class="max-w-[92vw]! lg:max-w-[980px]! w-full!">
        @if ($extrasUsuario)
            {{-- wire:key con el usuario: al abrir otro, los switches se dibujan de nuevo y no arrastran el estado del anterior --}}
            <div wire:key="extras-{{ $extrasUsuario->id }}" class="space-y-4">
                <div>
                    <flux:heading size="lg">Permisos de {{ $extrasUsuario->name }}</flux:heading>
                    <flux:text class="mt-1">
                        Rol: <strong>{{ $rolDelExtras?->name ?? 'sin rol' }}</strong>.
                        Los permisos que le da el rol salen encendidos y bloqueados ("del rol").
                        Los demás son extras: solo de este usuario.
                    </flux:text>
                </div>

                <div class="max-h-[62vh] overflow-y-auto pr-1">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @foreach ($modulos as $modulo)
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <flux:heading size="sm">{{ $modulo->description }}</flux:heading>

                                    <div class="flex gap-3 text-xs font-semibold">
                                        <button type="button" wire:click="marcarTodoExtras({{ $modulo->id }})"
                                            class="text-amber-600 dark:text-amber-400 hover:underline">
                                            MARCAR TODO
                                        </button>
                                        <button type="button" wire:click="quitarTodoExtras({{ $modulo->id }})"
                                            class="text-zinc-500 hover:underline">
                                            QUITAR TODO
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
                                    @forelse ($modulo->children as $permiso)
                                        <flux:switch wire:model.live="extras.{{ $permiso->id }}"
                                            :disabled="isset($idsDelRolExtras[$permiso->id])"
                                            label="{{ $permiso->description }}{{ isset($idsDelRolExtras[$permiso->id]) ? ' · del rol' : '' }}" />
                                    @empty
                                        <flux:text class="text-zinc-500 text-sm">Sin permisos en este módulo.
                                        </flux:text>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cerrar</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    <div class="tarjeta">
        <livewire:usuarios.usuarios-tabla />
    </div>
</div>
