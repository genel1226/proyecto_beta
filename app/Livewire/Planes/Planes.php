<?php

namespace App\Livewire\Planes;

use App\Models\Plan\Plan;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Planes extends Component
{
    /**
     * Null = se está creando un plan nuevo.
     * Con valor = se está editando el plan con ese id.
     *
     * #[Locked]: sin esto, cualquiera podría cambiar este id desde el
     * navegador y hacer que "Guardar cambios" edite OTRO plan.
     */
    #[Locked]
    public ?int $plan_id = null;

    public string $nombre = '';

    public string $sigla = '';

    /** Sin tipo a propósito: si el campo numérico queda vacío llega '' y una propiedad float fallaría. */
    public $monto = '';

    public string $lapso = 'M';

    public string $tipo = '1';

    /** Mínimo de usuarios que exige el plan (vacío = sin mínimo). */
    public $cantidad_min = '';

    /** Cuántas licencias usan el plan que se edita (solo informativo). */
    public int $en_uso = 0;

    // ---- Modal de eliminar ----

    #[Locked]
    public ?int $eliminar_id = null;

    public string $eliminar_nombre = '';

    public function mount(): void
    {
        Gate::authorize('planes.index');
    }

    public function updated(string $property): void
    {
        $this->validarCampo($property);
    }

    /** Valida UN solo campo (al cambiar o al salir del campo). Solo acepta nombres que estén en rules(). */
    public function validarCampo(string $campo): void
    {
        if (array_key_exists($campo, $this->rules())) {
            $this->validateOnly($campo);
        }
    }

    /** No reemplaza la validación de guardar(): solo decide si el botón se deja presionar. */
    public function getPuedeGuardarProperty(): bool
    {
        $completos = trim($this->nombre) !== ''
            && trim((string) $this->monto) !== '';

        return $completos && $this->getErrorBag()->isEmpty();
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:45', Rule::unique('plans', 'nombre')->ignore($this->plan_id)],
            'sigla' => ['nullable', 'string', 'max:5', 'alpha_num'],
            // La columna es decimal(14,4): se usa con 2 decimales, como en el resto del sistema.
            'monto' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'lapso' => ['required', 'in:M,A'],
            'tipo' => ['required', 'in:1,2,3'],
            'cantidad_min' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los :max caracteres.',
            'nombre.unique' => 'Ya existe un plan con ese nombre.',
            'sigla.max' => 'La sigla no puede superar los :max caracteres.',
            'sigla.alpha_num' => 'La sigla solo puede tener letras y números.',
            'monto.numeric' => 'Escribe un monto válido, por ejemplo: 30 o 29.90',
            'monto.min' => 'El monto no puede ser negativo.',
            'monto.max' => 'Ese monto es demasiado alto.',
            'monto.decimal' => 'El monto admite como máximo 2 decimales.',
            'lapso.in' => 'Elige un periodo base válido.',
            'tipo.in' => 'Elige un tipo de plan válido.',
            'cantidad_min.integer' => 'El mínimo de usuarios debe ser un número entero.',
            'cantidad_min.min' => 'El mínimo de usuarios debe ser 1 o más (o déjalo vacío).',
            'cantidad_min.max' => 'Ese mínimo de usuarios es demasiado alto.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'sigla' => 'sigla',
            'monto' => 'monto base',
            'lapso' => 'periodo base',
            'tipo' => 'tipo',
            'cantidad_min' => 'mínimo de usuarios',
        ];
    }

    /** Botón "+ Nuevo plan". Limpia el formulario por si quedó con datos de una edición anterior. */
    public function nuevo(): void
    {
        $this->reset(['plan_id', 'nombre', 'sigla', 'monto', 'lapso', 'tipo', 'cantidad_min', 'en_uso']);
        $this->resetErrorBag();
    }

    /** Lo dispara el botón de lápiz de la tabla. El permiso se valida aquí, en el servidor. */
    #[On('editar-plan')]
    public function abrirEdicion(int $id): void
    {
        if (! $this->autorizar('planes.edit')) {
            return;
        }

        $plan = Plan::withCount('licencias')->findOrFail($id);

        $this->plan_id = $plan->id;
        $this->nombre = (string) $plan->nombre;
        $this->sigla = (string) $plan->sigla;
        $this->monto = number_format((float) $plan->monto, 2, '.', '');
        $this->lapso = (string) $plan->lapso;
        $this->tipo = (string) $plan->tipo;
        $this->cantidad_min = $plan->cantidad_min !== null ? (string) $plan->cantidad_min : '';
        $this->en_uso = (int) $plan->licencias_count;

        $this->resetErrorBag();
        $this->modal('plan-form')->show();
    }

    public function guardar(): void
    {
        $editando = $this->plan_id !== null;

        if (! $this->autorizar($editando ? 'planes.edit' : 'planes.create')) {
            return;
        }

        $this->nombre = trim($this->nombre);
        $this->sigla = Str::upper(trim($this->sigla));
        $this->monto = trim((string) $this->monto);
        $this->cantidad_min = trim((string) $this->cantidad_min);

        $this->validate();

        $datos = [
            'nombre' => $this->nombre,
            'sigla' => $this->sigla !== '' ? $this->sigla : null,
            'monto' => round((float) $this->monto, 2),
            'lapso' => $this->lapso,
            'tipo' => $this->tipo,
            'cantidad_min' => $this->cantidad_min !== '' ? (int) $this->cantidad_min : null,
        ];

        if ($editando) {
            $plan = Plan::findOrFail($this->plan_id);
            $plan->update($datos);

            $mensaje = "El plan {$plan->nombre} se actualizó correctamente.";
        } else {
            $plan = Plan::create($datos);

            $mensaje = "El plan {$plan->nombre} se creó correctamente.";
        }

        $this->modal('plan-form')->close();
        $this->dispatch('plan-guardado'); // refresca la tabla

        Flux::toast(heading: 'Listo', text: $mensaje, variant: 'success');

        $this->nuevo();
    }

    // =====================================================================
    // ELIMINAR: solo un plan que ninguna licencia usa. Si ya se vendió alguna
    // licencia con ese plan, no se borra (se perdería el historial); para
    // dejar de ofrecerlo, cámbiale el tipo a "Otro".
    // =====================================================================

    #[On('confirmar-eliminar-plan')]
    public function confirmarEliminar(int $id): void
    {
        if (! $this->autorizar('planes.eliminar')) {
            return;
        }

        $plan = Plan::withCount('licencias')->findOrFail($id);

        if ($plan->licencias_count > 0) {
            Flux::toast(
                heading: 'No se puede eliminar',
                text: "El plan {$plan->nombre} tiene {$plan->licencias_count} licencia(s). Para dejar de ofrecerlo, cámbiale el tipo a «Otro».",
                variant: 'warning',
            );

            return;
        }

        $this->eliminar_id = $plan->id;
        $this->eliminar_nombre = (string) $plan->nombre;

        $this->modal('confirmar-eliminar-plan')->show();
    }

    public function eliminar(): void
    {
        if (! $this->eliminar_id) {
            return;
        }

        // Se vuelve a validar: el permiso pudo cambiar con el modal abierto.
        if (! $this->autorizar('planes.eliminar')) {
            $this->cerrarEliminar();

            return;
        }

        $plan = Plan::withCount('licencias')->findOrFail($this->eliminar_id);
        $nombre = $plan->nombre;

        if ($plan->licencias_count > 0) {
            $this->cerrarEliminar();
            Flux::toast(heading: 'No se puede eliminar', text: "El plan {$nombre} ya tiene licencias.", variant: 'warning');

            return;
        }

        try {
            $plan->delete();
        } catch (QueryException) {
            // Una clave foránea lo está usando en algún lugar que este módulo no conoce.
            $this->cerrarEliminar();
            Flux::toast(heading: 'No se puede eliminar', text: "El plan {$nombre} está en uso.", variant: 'warning');

            return;
        }

        $this->cerrarEliminar();
        $this->dispatch('plan-guardado'); // refresca la tabla

        Flux::toast(heading: 'Plan eliminado', text: "El plan {$nombre} se eliminó correctamente.", variant: 'success');
    }

    private function cerrarEliminar(): void
    {
        $this->modal('confirmar-eliminar-plan')->close();
        $this->reset(['eliminar_id', 'eliminar_nombre']);
    }

    /** true si el usuario tiene el permiso; si no, avisa con un toast y devuelve false. */
    private function autorizar(string $permiso): bool
    {
        if (Gate::allows($permiso)) {
            return true;
        }

        Flux::toast(
            heading: 'Sin permiso',
            text: 'No tienes permiso para realizar esta acción.',
            variant: 'danger',
        );

        return false;
    }

    public function render()
    {
        return view('livewire.planes.planes');
    }
}
