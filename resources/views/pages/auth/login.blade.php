<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col items-center gap-5">
        {{-- Título en píldora, como en el diseño de referencia --}}
        <span class="login-titulo">INICIAR SESIÓN</span>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        {{-- @chisel-passkeys --}}
        <x-passkey-verify />
        {{-- @end-chisel-passkeys --}}

        <form method="POST" action="{{ route('login.store') }}" class="flex w-full flex-col gap-4">
            @csrf

            {{-- Nombre de usuario (users.username). flux:field + flux:error son los que muestran el mensaje de error
                 aunque el campo no tenga etiqueta; el mensaje sale de FortifyServiceProvider::configureAuthentication() --}}
            <flux:field>
                <flux:input name="username" icon="user" :value="old('username')" type="text" required autofocus
                    autocomplete="username" placeholder="Nombre de usuario" aria-label="Nombre de usuario" />
                <flux:error name="username" />
            </flux:field>

            <!-- Password -->
            <flux:field>
                <flux:input name="password" icon="lock-closed" type="password" required autocomplete="current-password"
                    placeholder="Contraseña" aria-label="Contraseña" viewable />
                <flux:error name="password" />
            </flux:field>

            <!-- Recordarme (izquierda) y ¿Olvidaste tu contraseña? (derecha) -->
            <div class="flex items-center justify-between gap-3 text-sm">
                <flux:checkbox name="remember" label="Recordarme" :checked="old('remember')" />

                @if (Route::has('password.request'))
                    <flux:link class="text-sm" :href="route('password.request')" wire:navigate>
                        ¿Olvidaste tu contraseña?
                    </flux:link>
                @endif
            </div>

            <div class="flex justify-center">
                <flux:button variant="primary" type="submit" class="login-boton" data-test="login-button">
                    Ingresar
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
