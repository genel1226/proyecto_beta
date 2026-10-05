<?php

namespace App\Livewire\Usuarios;

use App\Models\User;
use App\Services\RolesUsuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class UsuariosTabla extends PowerGridComponent
{
    public string $tableName = 'usuariosTablaTable';

    public function setUp(): array
    {
        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    /**
     * Escucha el aviso del componente Usuarios al guardar o al activar/desactivar.
     * No necesita hacer nada: que Livewire lo ejecute ya fuerza que PowerGrid
     * vuelva a consultar.
     */
    #[On('usuario-guardado')]
    public function refrescar(): void
    {
        //
    }

    public function datasource(): Builder
    {
        // Sin este permiso no se consulta nada (responde 403).
        Gate::authorize('usuarios.index');

        // Solo el personal interno de Software4tech.
        return User::query()->internos();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('email')
            ->add('cargo')

            // El rol sale de model_has_roles. Una consulta por fila, pero la tabla es chica.
            ->add('rol_badge', function (User $u) {
                $rol = RolesUsuario::rolDe($u->id);

                [$label, $color] = $rol
                    ? [$rol->name, $rol->esDelSistema() ? 'violet' : 'blue']
                    : ['Sin rol', 'zinc'];

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            })

            // Cuántos permisos extra (además de los de su rol) tiene este usuario.
            ->add('extras', fn(User $u) => DB::table('model_has_permissions')
                ->where('model_type', User::PERMISOS_MODEL_TYPE)
                ->where('model_id', $u->id)
                ->count())

            ->add('estado_badge', function (User $u) {
                [$label, $color] = (int) $u->active === 1 ? ['Activo', 'green'] : ['Inactivo', 'zinc'];

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            });
    }

    public function columns(): array
    {
        return [
            Column::make('Nombre', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Correo', 'email')
                ->sortable()
                ->searchable(),

            Column::make('Cargo', 'cargo')
                ->sortable()
                ->searchable(),

            Column::make('Rol', 'rol_badge'),

            Column::make('Permisos extra', 'extras'),

            Column::make('Estado', 'estado_badge', 'active')
                ->sortable(),

            // La columna de acciones SIEMPRE se declara: PowerGrid la exige porque esta
            // clase define actions(), aunque para este usuario no haya botones.
            Column::action('Acciones'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')->operators(['contains']),
            Filter::inputText('email')->operators(['contains']),
            Filter::boolean('active'),
        ];
    }

    /**
     * Los botones se deciden fila por fila y según tus permisos. Disparan un
     * evento que escucha el componente Usuarios, que vuelve a validar el permiso.
     * Desactivarte a ti mismo no se ofrece.
     */
    public function actions(User $row): array
    {
        $botones = [];

        if (Gate::allows('usuarios.edit')) {
            $botones[] = Button::add('edit')
                ->slot(Blade::render('<span title="Editar"><flux:icon.pencil-square class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('editar-usuario', ['id' => $row->id]);
        }

        if (Gate::allows('usuarios.permisos')) {
            $botones[] = Button::add('permisos')
                ->slot(Blade::render('<span title="Permisos extra"><flux:icon.key class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white text-blue-600 border-blue-300 hover:bg-blue-50 dark:text-blue-400 dark:border-blue-800 dark:hover:bg-blue-950')
                ->dispatch('permisos-usuario', ['id' => $row->id]);
        }

        if (Gate::allows('usuarios.desactivar') && $row->id !== Auth::id()) {
            $botones[] = (int) $row->active === 1
                ? Button::add('estado')
                ->slot(Blade::render('<span title="Desactivar"><flux:icon.no-symbol class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white text-red-600 border-red-300 hover:bg-red-50 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-950')
                ->dispatch('confirmar-estado-usuario', ['id' => $row->id])
                : Button::add('estado')
                ->slot(Blade::render('<span title="Reactivar"><flux:icon.check-circle class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white text-emerald-600 border-emerald-300 hover:bg-emerald-50 dark:text-emerald-400 dark:border-emerald-800 dark:hover:bg-emerald-950')
                ->dispatch('confirmar-estado-usuario', ['id' => $row->id]);
        }

        return $botones;
    }
}
