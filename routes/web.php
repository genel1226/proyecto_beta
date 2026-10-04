<?php

use App\Livewire\Empresas\Empresas;
use App\Livewire\Licencias\Licencias;
use App\Livewire\Pagos\Pagos;
use App\Livewire\Permisos\Permisos;
use App\Livewire\Reportes\Reportes;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Rutas livewire

// Licencias
Route::livewire('licencias', Licencias::class)->name('licencias');

// Empresas
Route::livewire('empresas', Empresas::class)->name('empresas');

// Pagos
Route::livewire('pagos', Pagos::class)->name('pagos');

// Reportes
Route::livewire('reportes', Reportes::class)->name('reportes');

// Permisos
Route::livewire('permisos', Permisos::class)->name('permisos');

require __DIR__.'/settings.php';