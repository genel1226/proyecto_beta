<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario desactivado.
 *
 * Desactivar a alguien en la pantalla de Usuarios solo cambia users.active;
 * sin esto seguiría entrando con su contraseña (o su passkey). Va en el grupo
 * "web", así que cubre el login normal, las passkeys y cada petición de Livewire
 * de una sesión que ya estaba abierta.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && (int) $usuario->active !== 1) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Una petición de Livewire no puede seguir una redirección: con 419
            // Livewire pide recargar la página, y esa recarga ya cae en el login.
            if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
                abort(419);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta está desactivada. Comunícate con un administrador.',
            ]);
        }

        return $next($request);
    }
}
