<?php

use App\Livewire\Dashboard\Dashboard;
use App\Livewire\Empresas\Empresas;
use App\Livewire\Licencias\Licencias;
use App\Livewire\Pagos\Pagos;
use App\Livewire\Reportes\Reportes;
use App\Livewire\Roles\Roles;
use App\Livewire\TiposUsuario\TiposUsuario;
use App\Livewire\Usuarios\Usuarios;
use Illuminate\Support\Facades\Route;

// La raíz no muestra una página de bienvenida: manda al dashboard, y como el dashboard exige
// sesión, quien no la tiene termina en el login. Es una redirección simple (cacheable con route:cache).
Route::redirect('/', '/dashboard')->name('home');

// Todo el sistema es interno: sin sesión, cualquier ruta manda al login (antes, las pantallas
// de abajo respondían 403 a un visitante en vez de llevarlo al login).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('licencias', Licencias::class)->name('licencias');
    Route::livewire('empresas', Empresas::class)->name('empresas');
    Route::livewire('pagos', Pagos::class)->name('pagos');
    Route::livewire('reportes', Reportes::class)->name('reportes');
    Route::livewire('tipos-usuario', TiposUsuario::class)->name('tipos-usuario');
    Route::livewire('usuarios', Usuarios::class)->name('usuarios');
    Route::livewire('roles', Roles::class)->name('roles');
});

require __DIR__.'/settings.php';