<div>
    <div class="flex justify-between py-6 bg-zinc-100 px-3 my-5 dark:bg-zinc-800">
        <div class="order-first flex text-4xl font-bold items-center gap-2 text-zinc-700 dark:text-zinc-200">
            <flux:icon.key class="size-12" />
            Permisos
        </div>
    </div>

    <div class="mx-3 mb-6 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
        <flux:select wire:model.live="usuario_id" label="Usuario a gestionar" placeholder="Selecciona un usuario...">
            @foreach ($usuarios as $usuario)
                <flux:select.option value="{{ $usuario->id }}">{{ $usuario->name }} ({{ $usuario->email }})
                </flux:select.option>
            @endforeach
        </flux:select>

        @if ($esUsuarioActual)
            <flux:text class="text-amber-600 dark:text-amber-400 text-xs mt-2">
                Estás editando tus propios permisos. Si te quitas "Usar la pantalla de switches", te vas a quedar sin
                poder volver a entrar aquí hasta que otro administrador te lo devuelva por base de datos.
            </flux:text>
        @endif
    </div>

    @if (!$usuario_id)
        <flux:text class="mx-3 text-zinc-500">Selecciona un usuario para ver y modificar sus permisos.</flux:text>
    @else
        <div class="mx-3 grid grid-cols-1 lg:grid-cols-2 gap-5">
            @foreach ($modulos as $modulo)
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <flux:heading size="sm">{{ $modulo->description }}</flux:heading>

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
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
                        @forelse ($modulo->children as $permiso)
                            <flux:switch wire:model.live="activos.{{ $permiso->id }}"
                                label="{{ $permiso->description }}" />
                        @empty
                            <flux:text class="text-zinc-500 text-sm">Este módulo no tiene permisos hijos todavía.
                            </flux:text>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:toast />
</div>
