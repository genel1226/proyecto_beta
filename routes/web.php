<?php

use App\Livewire\Licencias\Licencias;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::livewire('licencias', Licencias::class)->name('licencias');

require __DIR__.'/settings.php';
