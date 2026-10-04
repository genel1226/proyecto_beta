<?php

namespace App\Livewire\TiposUsuario;

use App\Models\TipoUsuario\TipoUsuario;
use Flux\Flux;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class TiposUsuario extends Component
{
    /**
     * Null = se está creando un tipo nuevo.
     * Con valor = se está editando el tipo con ese id.
     *
     * #[Locked]: sin esto, cualquiera podría cambiar este id desde el
     * navegador y hacer que "Guardar cambios" edite OTRO tipo.
     */
    #[Locked]
    public ?int $tipo_id = null;

    public string $codigo = '';

    public string $nombre = '';

    /**
     * Sin tipo a propósito: si el campo numérico queda vacío, el navegador
     * manda '' y una propiedad `float` estricta lanzaría error.
     */
    public $precio_unitario = '';

    // ---- Modal de activar / desactivar ----

    #[Locked]
    public ?int $estado_id = null;

    /** true = se va a reactivar; false = se va a desactivar. */
    #[Locked]
    public bool $estado_activar = false;

    public string $estado_tipo = '';

    /** Cuántas licencias activas tienen este tipo (solo informativo). */
    public int $estado_en_uso = 0;

    public function mount(): void
    {
        Gate::authorize('tipos_usuario.index');
    }

    protected function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('tipos_usuario', 'codigo')->ignore($this->tipo_id),
            ],
            'nombre' => ['required', 'string', 'max:60'],
            // La columna es decimal(10,2): hasta 99,999,999.99 con 2 decimales.
            'precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'codigo.max' => 'El código no puede superar los :max caracteres.',
            'codigo.regex' => 'El código solo puede tener letras, números y guiones (ej. GT-4).',
            'nombre.max' => 'El nombre no puede superar los :max caracteres.',
            'unique' => 'Ya existe un tipo de usuario con ese :attribute.',
            'precio_unitario.numeric' => 'Escribe un precio válido, por ejemplo: 10 o 7.50',
            'precio_unitario.min' => 'El precio no puede ser negativo.',
            'precio_unitario.max' => 'Ese precio es demasiado alto.',
            'precio_unitario.decimal' => 'El precio admite como máximo 2 decimales.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'precio_unitario' => 'precio unitario',
        ];
    }

    /**
     * Sugiere el próximo código libre (GT-4, GT-5...), con el número más
     * alto que exista, no con un conteo de filas. El usuario lo puede cambiar.
     */
    private function sugerirCodigo(): string
    {
        $siguiente = (int) TipoUsuario::query()
            ->whereRaw('codigo REGEXP ?', ['^GT-[0-9]+$'])
            ->max(DB::raw('CAST(SUBSTRING(codigo, 4) AS UNSIGNED)')) + 1;

        return 'GT-' . $siguiente;
    }

    /**
     * Botón "+ Nuevo tipo". Limpia el formulario por si el modal quedó con
     * datos de una edición anterior.
     */
    public function nuevo(): void
    {
        $this->reset(['tipo_id', 'codigo', 'nombre', 'precio_unitario']);
        $this->codigo = $this->sugerirCodigo();
        $this->resetErrorBag();
    }

    /**
     * Lo dispara el botón de lápiz de la tabla. Se puede invocar desde el
     * navegador aunque el botón esté oculto, así que el permiso se valida aquí.
     */
    #[On('editar-tipo-usuario')]
    public function abrirEdicion(int $id): void
    {
        if (! $this->autorizar('tipos_usuario.edit')) {
            return;
        }

        $tipo = TipoUsuario::findOrFail($id);

        $this->tipo_id = $tipo->id;
        $this->codigo = (string) $tipo->codigo;
        $this->nombre = (string) $tipo->nombre;
        $this->precio_unitario = number_format((float) $tipo->precio_unitario, 2, '.', '');

        $this->resetErrorBag();
        $this->modal('nuevo-tipo-usuario')->show();
    }

    public function guardar(): void
    {
        $editando = $this->tipo_id !== null;

        if (! $this->autorizar($editando ? 'tipos_usuario.edit' : 'tipos_usuario.create')) {
            return;
        }

        $this->codigo = Str::upper(trim($this->codigo));
        $this->nombre = trim($this->nombre);
        $this->precio_unitario = trim((string) $this->precio_unitario);

        $datos = $this->validate();
        $datos['precio_unitario'] = round((float) $datos['precio_unitario'], 2);

        if ($editando) {
            $tipo = TipoUsuario::findOrFail($this->tipo_id);
            $tipo->update($datos);

            $mensaje = "El tipo {$tipo->codigo} se actualizó correctamente.";
        } else {
            try {
                $tipo = TipoUsuario::create($datos + ['activo' => true]);
            } catch (UniqueConstraintViolationException) {
                // Otro usuario creó el mismo código justo antes que este.
                $this->addError('codigo', 'Ya existe un tipo de usuario con ese código.');

                return;
            }

            $mensaje = "El tipo {$tipo->codigo} se creó correctamente.";
        }

        $this->modal('nuevo-tipo-usuario')->close();
        $this->dispatch('tipo-usuario-guardado'); // refresca la tabla

        Flux::toast(heading: 'Listo', text: $mensaje, variant: 'success');

        $this->nuevo();
    }

    /**
     * Cuántas licencias activas (Vigente, Por vencer o En proceso) tienen
     * este tipo con al menos un usuario. Es una consulta directa, sin pasar
     * por los modelos de licencias, para no depender de sus relaciones.
     */
    private function licenciasActivasQueLoUsan(int $tipoId): int
    {
        return DB::table('licencia_detalle_usuarios as d')
            ->join('licencias as l', 'l.id', '=', 'd.licencia_id')
            ->where('d.tipo_usuario_id', $tipoId)
            ->where('d.cantidad', '>', 0)
            ->whereIn('l.estado', ['V', 'X', 'P'])
            ->count();
    }

    /**
     * Sin tipos activos, el formulario de licencias no tendría ningún
     * stepper y no se podría vender nada con usuarios.
     */
    private function esElUltimoActivo(TipoUsuario $tipo): bool
    {
        return $tipo->activo && TipoUsuario::where('activo', 1)->count() <= 1;
    }

    /**
     * Lo dispara el botón de activar/desactivar de la tabla. Abre el modal
     * de confirmación; el cambio real ocurre en cambiarEstado().
     */
    #[On('confirmar-estado-tipo-usuario')]
    public function confirmarEstado(int $id): void
    {
        if (! $this->autorizar('tipos_usuario.desactivar')) {
            return;
        }

        $tipo = TipoUsuario::findOrFail($id);

        if ($this->esElUltimoActivo($tipo)) {
            Flux::toast(
                heading: 'No se puede desactivar',
                text: 'Debe quedar al menos un tipo de usuario activo; sin tipos activos no se pueden armar licencias.',
                variant: 'warning',
            );

            return;
        }

        $this->estado_id = $tipo->id;
        $this->estado_activar = ! $tipo->activo;
        $this->estado_tipo = "{$tipo->codigo} · {$tipo->nombre}";
        $this->estado_en_uso = $this->licenciasActivasQueLoUsan($tipo->id);

        $this->modal('confirmar-estado-tipo-usuario')->show();
    }

    public function cambiarEstado(): void
    {
        if (! $this->estado_id) {
            return;
        }

        // Se valida otra vez aquí: el permiso pudo cambiar con el modal
        // abierto, o alguien pudo llamar este método directamente.
        if (! $this->autorizar('tipos_usuario.desactivar')) {
            $this->cerrarModalEstado();

            return;
        }

        $tipo = TipoUsuario::findOrFail($this->estado_id);
        $activar = $this->estado_activar;

        if (! $activar && $this->esElUltimoActivo($tipo)) {
            $this->cerrarModalEstado();

            Flux::toast(
                heading: 'No se puede desactivar',
                text: 'Debe quedar al menos un tipo de usuario activo.',
                variant: 'warning',
            );

            return;
        }

        $tipo->activo = $activar;
        $tipo->save();

        $this->cerrarModalEstado();
        $this->dispatch('tipo-usuario-guardado'); // refresca la tabla

        Flux::toast(
            heading: $activar ? 'Tipo reactivado' : 'Tipo desactivado',
            text: 'El tipo ' . $tipo->codigo . ($activar ? ' se reactivó' : ' se desactivó') . ' correctamente.',
            variant: 'success',
        );
    }

    private function cerrarModalEstado(): void
    {
        $this->modal('confirmar-estado-tipo-usuario')->close();
        $this->reset(['estado_id', 'estado_activar', 'estado_tipo', 'estado_en_uso']);
    }

    /**
     * true si el usuario tiene el permiso; si no, avisa con un toast y
     * devuelve false para que el método que llamó se detenga.
     */
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
        return view('livewire.tipos-usuario.tipos-usuario');
    }
}
