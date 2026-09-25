<?php

namespace App\Livewire\Licencias;

use App\Models\Empresa\Empresa;
use App\Models\Licencias\Licencias as ModelsLicencias;
use App\Models\Plan\Plan;
use App\Models\TipoUsuario\TipoUsuario;
use Livewire\Component;

class Licencias extends Component
{
    // I have not failed. I've just found 10,000 ways that won't work. - Thomas Edison

    public ?int $empresa_id = null;

    public ?int $plan_id = null;

    public string $estado_inicial = 'V';

    public string $start_date;

    public string $end_date = '';

    public string $periodicidad = 'M';

    public float $descuento = 0;

    public string $observaciones = '';

    /**
     * Una fila por cada tipo de usuario activo (GT-1, GT-2, GT-3).
     *
     * @var array<int, array{tipo_usuario_id:int, codigo:string, nombre:string, precio_unitario:float, cantidad:int}>
     */
    public array $detalle = [];

    public function mount(): void
    {
        $this->start_date = now()->format('Y-m-d');

        $this->detalle = TipoUsuario::where('activo', 1)
            ->orderBy('id')
            ->get()
            ->map(fn (TipoUsuario $tipo) => [
                'tipo_usuario_id' => $tipo->id,
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'precio_unitario' => (float) $tipo->precio_unitario,
                'cantidad' => 0,
            ])
            ->toArray();
    }

    public function incrementar(int $index): void
    {
        $this->detalle[$index]['cantidad']++;
    }

    public function decrementar(int $index): void
    {
        if ($this->detalle[$index]['cantidad'] > 0) {
            $this->detalle[$index]['cantidad']--;
        }
    }

    public function getMinEndDateProperty(): string
    {
        return $this->start_date ?: now()->format('Y-m-d');
    }

    public function getPlanSeleccionadoProperty(): ?Plan
    {
        return $this->plan_id ? Plan::find($this->plan_id) : null;
    }

    public function getSubtotalUsuariosProperty(): float
    {
        return collect($this->detalle)
            ->sum(fn (array $fila) => $fila['cantidad'] * $fila['precio_unitario']);
    }

    public function getCantidadTotalUsuariosProperty(): int
    {
        return collect($this->detalle)->sum('cantidad');
    }

    public function getTotalCalculadoProperty(): float
    {
        $base = $this->planSeleccionado->monto ?? 0;

        return max($base + $this->subtotalUsuarios - $this->descuento, 0);
    }

    /**
     * Vista previa de cuál sería el próximo código de licencia
     * (L0001, L0002, ...). Se basa en el id autoincremental más alto
     * que exista, NO en un conteo de filas — un conteo se desfasa si
     * alguna vez se elimina una licencia; el id nunca se repite.
     *
     * Es solo una vista previa: el código real se fija en guardar(),
     * con el id que MySQL le asigne en ese momento.
     */
    public function getProximoCodigoProperty(): string
    {
        $siguienteId = (ModelsLicencias::max('id') ?? 0) + 1;

        return 'L'.str_pad((string) $siguienteId, 4, '0', STR_PAD_LEFT);
    }

    public function guardar(): void
    {
        $this->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'estado_inicial' => ['required', 'in:V,P'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'periodicidad' => ['required', 'in:M,A,P'],
            'descuento' => ['numeric', 'min:0'],
        ]);

        // Solo se exige al menos un usuario si la licencia nace Vigente.
        // "En proceso" permite guardar el registro mientras todavía se
        // negocia la cantidad de usuarios.
        if ($this->estado_inicial === 'V' && $this->cantidadTotalUsuarios < 1) {
            $this->addError('detalle', 'Una licencia Vigente necesita al menos un usuario. Si todavía no está definido, selecciona "En proceso".');

            return;
        }

        $tieneActiva = ModelsLicencias::where('empresa_id', $this->empresa_id)
            ->whereIn('estado', ['V', 'X', 'P'])
            ->exists();

        if ($tieneActiva) {
            $this->addError('empresa_id', 'Esta empresa ya tiene una licencia activa. Debe darse de baja antes de crear una nueva.');

            return;
        }

        $licencia = ModelsLicencias::create([
            'empresa_id' => $this->empresa_id,
            'plan_id' => $this->plan_id,
            'fecha_inicio' => $this->start_date,
            'fecha_vencimiento' => $this->end_date,
            'periodicidad' => $this->periodicidad,
            'descuento' => $this->descuento,
            'moneda' => 'USD',
            'estado' => $this->estado_inicial,
            // 'vendedor_id' => auth()->id(),
            'observaciones' => $this->observaciones,
            'monto' => $this->totalCalculado,
        ]);

        // El código real se asigna DESPUÉS del insert, usando el id que
        // MySQL ya le dio a esta fila — nunca puede chocar con otro.
        $licencia->codigo_licencia = 'L'.str_pad((string) $licencia->id, 4, '0', STR_PAD_LEFT);
        $licencia->save();

        foreach ($this->detalle as $fila) {
            if ($fila['cantidad'] > 0) {
                $licencia->detalleUsuarios()->create([
                    'tipo_usuario_id' => $fila['tipo_usuario_id'],
                    'cantidad' => $fila['cantidad'],
                    'precio_unitario_aplicado' => $fila['precio_unitario'],
                ]);
            }
        }

        $this->modal('nueva-licencia')->close();
        $this->dispatch('licencia-creada');

        $this->reset(['empresa_id', 'plan_id', 'estado_inicial', 'end_date', 'descuento', 'observaciones']);
        $this->mount();
    }

    public function render()
    {
        return view('livewire.licencias.licencias', [
            'empresas' => Empresa::orderBy('razon_social')->get(),
            'planes' => Plan::primarios()->orderBy('monto')->get(),
        ]);
    }
}