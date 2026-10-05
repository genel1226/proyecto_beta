<?php

namespace App\Livewire\Roles;

use App\Models\Permission;
use App\Models\Rol;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla de Roles: se elige un rol y se editan sus permisos con switches.
 * Un cambio aquí le llega al instante a TODOS los usuarios que tengan ese rol.
 *
 * El Administrador es un rol del sistema: se muestra con todo encendido y
 * bloqueado, y ni sus permisos ni su nombre se pueden tocar.
 */
class Roles extends Component
{
    public ?int $rol_id = null;

    /**
     * permission_id => true/false para el rol elegido. Se llenan TODAS las llaves
     * (no solo las activas) porque wire:model necesita que la llave ya exista
     * para poder enlazarse a su switch.
     *
     * @var array<int, bool>
     */
    public array $activos = [];

    // ---- Formulario de crear / editar rol ----

    #[Locked]
    public ?int $form_id = null;

    public string $nombre = '';

    public string $descripcion = '';

    public function mount(): void
    {
        Gate::authorize('roles.index');

        $primero = Rol::internos()->orderBy('id')->value('id');
        $this->rol_id = $primero ? (int) $primero : null;

        $this->cargarActivos();
    }

    public function updatedRolId(): void
    {
        $this->cargarActivos();
    }

    private function rolActual(): ?Rol
    {
        return $this->rol_id ? Rol::internos()->find($this->rol_id) : null;
    }

    /**
     * Se puede editar si el usuario tiene roles.edit y el rol no es el del sistema.
     * Se vuelve a comprobar en cada acción: los switches se pueden manipular desde
     * el navegador aunque estén bloqueados en pantalla.
     */
    private function puedeEditar(?Rol $rol): bool
    {
        return $rol !== null && ! $rol->esDelSistema() && Gate::allows('roles.edit');
    }

    private function cargarActivos(): void
    {
        $ids = Permission::whereNotNull('parent_id')->where('active', 1)->pluck('id');
        $base = array_fill_keys($ids->all(), false);

        $rol = $this->rolActual();

        if ($rol) {
            $asignados = $rol->esDelSistema()
                ? $ids
                : DB::table('role_has_permissions')->where('role_id', $rol->id)->pluck('permission_id');

            foreach ($asignados as $id) {
                if (array_key_exists($id, $base)) {
                    $base[$id] = true;
                }
            }
        }

        $this->activos = $base;
    }

    /**
     * Se dispara solo cuando el cambio viene del navegador (un clic en un switch).
     * Los cambios que hace el propio PHP (marcarTodo, etc.) no pasan por aquí.
     */
    public function updatedActivos($value, $key): void
    {
        $rol = $this->rolActual();
        $permisoId = (int) $key;

        if ($key === null || ! $this->puedeEditar($rol) || ! $this->esPermisoValido($permisoId)) {
            $this->cargarActivos(); // deshace lo que el navegador haya intentado
            $this->avisarSinPermiso();

            return;
        }

        if ($value) {
            $this->otorgar($rol, [$permisoId]);
        } else {
            $this->revocar($rol, [$permisoId]);
        }
    }

    public function marcarTodo(int $moduloId): void
    {
        $rol = $this->rolActual();

        if (! $this->puedeEditar($rol)) {
            $this->avisarSinPermiso();

            return;
        }

        $ids = $this->idsDelModulo($moduloId);

        $this->otorgar($rol, $ids);

        foreach ($ids as $id) {
            $this->activos[$id] = true;
        }

        Flux::toast(text: 'Se activaron todos los permisos de este módulo.', variant: 'success');
    }

    public function quitarTodo(int $moduloId): void
    {
        $rol = $this->rolActual();

        if (! $this->puedeEditar($rol)) {
            $this->avisarSinPermiso();

            return;
        }

        $ids = $this->idsDelModulo($moduloId);

        $this->revocar($rol, $ids);

        foreach ($ids as $id) {
            $this->activos[$id] = false;
        }

        Flux::toast(text: 'Se quitaron todos los permisos de este módulo.', variant: 'warning');
    }

    /**
     * Solo permisos "hoja" y activos: un título de módulo no se asigna.
     */
    private function esPermisoValido(int $permisoId): bool
    {
        return Permission::whereKey($permisoId)->whereNotNull('parent_id')->where('active', 1)->exists();
    }

    /**
     * @return array<int, int>
     */
    private function idsDelModulo(int $moduloId): array
    {
        if (! Permission::modulos()->whereKey($moduloId)->exists()) {
            return [];
        }

        return Permission::where('parent_id', $moduloId)->where('active', 1)->pluck('id')->all();
    }

    /**
     * @param  array<int, int>  $idsPermisos
     */
    private function otorgar(Rol $rol, array $idsPermisos): void
    {
        if (empty($idsPermisos)) {
            return;
        }

        DB::table('role_has_permissions')->insertOrIgnore(
            array_map(fn(int $id) => ['permission_id' => $id, 'role_id' => $rol->id], $idsPermisos)
        );
    }

    /**
     * @param  array<int, int>  $idsPermisos
     */
    private function revocar(Rol $rol, array $idsPermisos): void
    {
        if (empty($idsPermisos)) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('role_id', $rol->id)
            ->whereIn('permission_id', $idsPermisos)
            ->delete();
    }

    private function avisarSinPermiso(): void
    {
        Flux::toast(
            heading: 'No se puede modificar',
            text: 'No tienes permiso para editar este rol, o es un rol del sistema.',
            variant: 'danger',
        );
    }

    // =====================================================================
    // Crear y editar un rol (nombre y descripción)
    // =====================================================================

    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:60',
                Rule::unique('roles', 'name')
                    ->where('empresa_id', Rol::EMPRESA_INTERNA)
                    ->where('guard_name', Rol::GUARD)
                    ->ignore($this->form_id),
            ],
            'descripcion' => ['nullable', 'string', 'max:191'],
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los :max caracteres.',
            'descripcion.max' => 'La descripción no puede superar los :max caracteres.',
            'unique' => 'Ya existe un rol con ese nombre.',
        ];
    }

    protected function validationAttributes(): array
    {
        return ['nombre' => 'nombre', 'descripcion' => 'descripción'];
    }

    /**
     * Validación en tiempo real: cuando el valor cambia (updated) y cuando el
     * usuario sale del campo aunque no haya escrito nada (wire:blur).
     */
    public function updated(string $property): void
    {
        $this->validarCampo($property);
    }

    public function validarCampo(string $campo): void
    {
        if (array_key_exists($campo, $this->rules())) {
            $this->validateOnly($campo);
        }
    }

    public function getPuedeGuardarProperty(): bool
    {
        return trim($this->nombre) !== '' && $this->getErrorBag()->isEmpty();
    }

    public function nuevoRol(): void
    {
        $this->reset(['form_id', 'nombre', 'descripcion']);
        $this->resetErrorBag();
    }

    public function editarRol(): void
    {
        $rol = $this->rolActual();

        if (! $this->puedeEditar($rol)) {
            $this->avisarSinPermiso();

            return;
        }

        $this->form_id = $rol->id;
        $this->nombre = (string) $rol->name;
        $this->descripcion = (string) $rol->description;
        $this->resetErrorBag();
    }

    public function guardarRol(): void
    {
        $editando = $this->form_id !== null;

        if (! Gate::allows($editando ? 'roles.edit' : 'roles.create')) {
            $this->avisarSinPermiso();

            return;
        }

        $this->nombre = trim($this->nombre);
        $this->descripcion = trim($this->descripcion);

        $datos = $this->validate();

        if ($editando) {
            $rol = Rol::internos()->findOrFail($this->form_id);

            if ($rol->esDelSistema()) {
                $this->avisarSinPermiso();

                return;
            }

            $rol->update(['name' => $datos['nombre'], 'description' => $datos['descripcion'] ?? '']);
            $mensaje = "El rol {$rol->name} se actualizó correctamente.";
        } else {
            $rol = Rol::create([
                'name' => $datos['nombre'],
                'description' => $datos['descripcion'] ?? '',
                'guard_name' => Rol::GUARD,
                'empresa_id' => Rol::EMPRESA_INTERNA,
                'active' => true,
            ]);

            // El rol nuevo queda seleccionado, sin permisos, listo para armarlo.
            $this->rol_id = $rol->id;
            $this->cargarActivos();
            $mensaje = "El rol {$rol->name} se creó. Ahora activa sus permisos.";
        }

        $this->modal('rol-form')->close();
        $this->reset(['form_id', 'nombre', 'descripcion']);

        Flux::toast(heading: 'Listo', text: $mensaje, variant: 'success');
    }

    public function render()
    {
        $rolActual = $this->rolActual();

        $usuariosDelRol = $rolActual
            ? DB::table('model_has_roles')
            ->where('model_type', User::PERMISOS_MODEL_TYPE)
            ->where('role_id', $rolActual->id)
            ->count()
            : 0;

        return view('livewire.roles.roles', [
            'roles' => Rol::internos()->orderBy('id')->get(),
            'rolActual' => $rolActual,
            'usuariosDelRol' => $usuariosDelRol,
            'esSistema' => $rolActual?->esDelSistema() ?? false,
            'puedeEditar' => $this->puedeEditar($rolActual),
            'modulos' => Permission::modulos()
                ->with(['children' => fn($q) => $q->where('active', 1)])
                ->get(),
        ]);
    }
}
