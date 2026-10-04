<?php

namespace App\Livewire\Permisos;

use App\Models\Permission;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Permisos extends Component
{
    public ?int $usuario_id = null;

    /**
     * permission_id => true, solo para los que el usuario elegido SÍ
     * tiene. Se recarga completa cada vez que cambia el usuario elegido.
     *
     * @var array<int, bool>
     */
    public array $activos = [];

    public function mount(): void
    {
        Gate::authorize('permisos.admin');

        $this->cargarActivos();
    }

    public function updatedUsuarioId(): void
    {
        $this->cargarActivos();
    }

    /**
     * OJO: se llenan TODAS las llaves (en true o false), no solo las
     * activas. wire:model necesita que la llave del arreglo ya exista
     * para poder enlazarse bien al switch — si faltara, Livewire no
     * sabría a qué sincronizar ese control.
     */
    private function cargarActivos(): void
    {
        $todosLosIds = Permission::whereNotNull('parent_id')->pluck('id');
        $base = array_fill_keys($todosLosIds->all(), false);

        if ($this->usuario_id) {
            $asignados = DB::table('model_has_permissions')
                ->where('model_type', User::PERMISOS_MODEL_TYPE)
                ->where('model_id', $this->usuario_id)
                ->pluck('permission_id');

            foreach ($asignados as $id) {
                $base[$id] = true;
            }
        }

        $this->activos = $base;
    }

    /**
     * Se dispara solo cuando el CAMBIO VIENE DEL NAVEGADOR (el usuario
     * hace clic en un switch). Los cambios que hace el propio PHP (por
     * ejemplo, marcarTodo() más abajo) no vuelven a pasar por aquí, así
     * que no hay riesgo de guardar dos veces lo mismo.
     */
    public function updatedActivos($value, $key): void
    {
        if ($key === null || ! $this->usuario_id) {
            return;
        }

        $permisoId = (int) $key;

        if ($value) {
            $this->otorgar([$permisoId]);
        } else {
            $this->revocar([$permisoId]);
        }
    }

    public function marcarTodo(int $moduloId): void
    {
        if (! $this->usuario_id) {
            return;
        }

        $ids = Permission::where('parent_id', $moduloId)->pluck('id');

        $this->otorgar($ids->all());

        foreach ($ids as $id) {
            $this->activos[$id] = true;
        }

        Flux::toast(text: 'Se activaron todos los permisos de este módulo.', variant: 'success');
    }

    public function quitarTodo(int $moduloId): void
    {
        if (! $this->usuario_id) {
            return;
        }

        $ids = Permission::where('parent_id', $moduloId)->pluck('id');

        $this->revocar($ids->all());

        foreach ($ids as $id) {
            $this->activos[$id] = false;
        }

        Flux::toast(text: 'Se quitaron todos los permisos de este módulo.', variant: 'warning');
    }

    /**
     * @param  array<int, int>  $idsPermisos
     */
    private function otorgar(array $idsPermisos): void
    {
        if (empty($idsPermisos)) {
            return;
        }

        DB::table('model_has_permissions')->insertOrIgnore(
            array_map(fn (int $id) => [
                'permission_id' => $id,
                'model_type' => User::PERMISOS_MODEL_TYPE,
                'model_id' => $this->usuario_id,
            ], $idsPermisos)
        );
    }

    /**
     * @param  array<int, int>  $idsPermisos
     */
    private function revocar(array $idsPermisos): void
    {
        if (empty($idsPermisos)) {
            return;
        }

        DB::table('model_has_permissions')
            ->whereIn('permission_id', $idsPermisos)
            ->where('model_type', User::PERMISOS_MODEL_TYPE)
            ->where('model_id', $this->usuario_id)
            ->delete();
    }

    public function render()
    {
        return view('livewire.permisos.permisos', [
            // Ajusta el where si quieres limitarlo solo al equipo interno
            // de Software4tech en vez de listar todos los usuarios.
            'usuarios' => User::orderBy('name')->get(),
            'modulos' => Permission::modulos()->with('children')->get(),
            'usuarioSeleccionado' => $this->usuario_id ? User::find($this->usuario_id) : null,
            'esUsuarioActual' => $this->usuario_id === Auth::id(),
        ]);
    }
}