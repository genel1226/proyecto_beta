<?php

namespace App\Livewire\Pagos;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Pagos extends Component
{
    public function mount(): void
    {
        Gate::authorize('pagos.index');
    }

    public function render()
    {
        return view('livewire.pagos.pagos');
    }
}