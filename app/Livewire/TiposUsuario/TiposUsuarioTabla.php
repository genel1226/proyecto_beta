<?php

namespace App\Livewire\TiposUsuario;

use App\Models\TipoUsuario\TipoUsuario;
use Illuminate\Database\Eloquent\Builder;
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

final class TiposUsuarioTabla extends PowerGridComponent
{
    public string $tableName = 'tiposUsuarioTablaTable';

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
     * Escucha el aviso que manda el componente TiposUsuario al guardar o al
     * activar/desactivar. No necesita hacer nada adentro: que Livewire
     * ejecute este método ya fuerza que PowerGrid vuelva a consultar.
     */
    #[On('tipo-usuario-guardado')]
    public function refrescar(): void
    {
        //
    }

    public function datasource(): Builder
    {
        // Sin este permiso no se consulta nada (responde 403).
        Gate::authorize('tipos_usuario.index');

        return TipoUsuario::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('codigo')
            ->add('nombre')
            ->add('precio_unitario')
            ->add('precio_formateado', fn(TipoUsuario $t) => '$' . number_format((float) $t->precio_unitario, 2))
            ->add('activo')

            // Cuántas licencias activas (Vigente, Por vencer o En proceso) lo
            // usan ahora. Es una consulta por fila, pero el catálogo tiene
            // pocas filas, así que no pesa.
            ->add('en_uso', fn(TipoUsuario $t) => DB::table('licencia_detalle_usuarios as d')
                ->join('licencias as l', 'l.id', '=', 'd.licencia_id')
                ->where('d.tipo_usuario_id', $t->id)
                ->where('d.cantidad', '>', 0)
                ->whereIn('l.estado', ['V', 'X', 'P'])
                ->count())

            ->add('estado_badge', function (TipoUsuario $t) {
                [$label, $color] = $t->activo ? ['Activo', 'green'] : ['Inactivo', 'zinc'];

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            });
    }

    public function columns(): array
    {
        return [
            Column::make('Código', 'codigo')
                ->sortable()
                ->searchable(),

            Column::make('Nombre', 'nombre')
                ->sortable()
                ->searchable(),

            Column::make('Precio unitario', 'precio_formateado', 'precio_unitario')
                ->sortable(),

            Column::make('Licencias activas', 'en_uso'),

            Column::make('Estado', 'estado_badge', 'activo')
                ->sortable(),

            // La columna de acciones SIEMPRE se declara: PowerGrid la exige
            // porque esta clase define actions(), aunque para este usuario
            // no haya botones. Lo que varía por permiso es qué botones hay.
            Column::action('Acciones'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('codigo')->operators(['contains']),
            Filter::inputText('nombre')->operators(['contains']),
            Filter::boolean('activo'),
        ];
    }

    /**
     * Los botones se deciden fila por fila y solo aparecen si el usuario
     * tiene el permiso. Disparan un evento que escucha el componente
     * TiposUsuario, que vuelve a validar el permiso en el servidor.
     */
    public function actions(TipoUsuario $row): array
    {
        $botones = [];

        if (Gate::allows('tipos_usuario.edit')) {
            $botones[] = Button::add('edit')
                ->slot(Blade::render('<span title="Editar"><flux:icon.pencil-square class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('editar-tipo-usuario', ['id' => $row->id]);
        }

        if (Gate::allows('tipos_usuario.desactivar')) {
            $botones[] = $row->activo
                ? Button::add('estado')
                ->slot(Blade::render('<span title="Desactivar"><flux:icon.no-symbol class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white text-red-600 border-red-300 hover:bg-red-50 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-950')
                ->dispatch('confirmar-estado-tipo-usuario', ['id' => $row->id])
                : Button::add('estado')
                ->slot(Blade::render('<span title="Reactivar"><flux:icon.check-circle class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white text-emerald-600 border-emerald-300 hover:bg-emerald-50 dark:text-emerald-400 dark:border-emerald-800 dark:hover:bg-emerald-950')
                ->dispatch('confirmar-estado-tipo-usuario', ['id' => $row->id]);
        }

        return $botones;
    }
}
