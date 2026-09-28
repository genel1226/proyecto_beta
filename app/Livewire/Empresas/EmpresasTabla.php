<?php

namespace App\Livewire\Empresas;

use App\Models\Empresa\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EmpresasTabla extends PowerGridComponent
{
    public string $tableName = 'empresasTablaTable';

    public function setUp(): array
    {
        // $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    /**
     * Escucha el aviso que manda el componente Empresas al guardar o al
     * activar/desactivar. No necesita hacer nada adentro: que Livewire
     * ejecute este método ya fuerza que PowerGrid vuelva a consultar.
     */
    #[On('empresa-guardada')]
    public function refrescar(): void
    {
        //
    }

    public function datasource(): Builder
    {
        // Sin este permiso no se consulta nada (responde 403).
        Gate::authorize('empresas.index');

        return Empresa::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('cod')
            ->add('razon_social')
            ->add('nombre_comercial')
            ->add('nit')
            ->add('slug')
            ->add('email')
            ->add('telefono')
            ->add('active')
            ->add('pais')
            ->add('direccion')

            // Badge de estado: verde si está activa, gris si está inactiva
            ->add('estado_badge', function (Empresa $model) {
                [$label, $color] = $model->active ? ['Activa', 'green'] : ['Inactiva', 'zinc'];

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            })

            // Tipo de cuenta: demo (con o sin la prueba vencida), con licencia, o sin licencia
            ->add('licencia_badge', function (Empresa $model) {
                [$label, $color] = match (true) {
                    $model->trial_ends_at !== null && $model->trial_ends_at->isPast() => ['Demo vencida', 'rose'],
                    $model->trial_ends_at !== null => ['Demo hasta '.$model->trial_ends_at->format('d/m/Y'), 'violet'],
                    $model->con_licencia === '1' => ['Con licencia', 'green'],
                    default => ['Sin licencia', 'zinc'],
                };

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            })

            ->add('created_at_formatted', fn (Empresa $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        $hayAcciones = Gate::allows('empresas.edit') || Gate::allows('empresas.desactivar');

        return [
            // Column::make('Id', 'id'),

            Column::make('Código', 'cod')
                ->sortable()
                ->searchable(),

            Column::make('Razón social', 'razon_social')
                ->sortable()
                ->searchable(),

            Column::make('Nombre comercial', 'nombre_comercial')
                ->sortable()
                ->searchable(),

            Column::make('NIT', 'nit')
                ->sortable()
                ->searchable(),

            // Column::make('Slug', 'slug')
            //     ->sortable()
            //     ->searchable(),

            Column::make('Email', 'email')
                ->sortable()
                ->searchable(),

            Column::make('Teléfono', 'telefono')
                ->sortable()
                ->searchable(),

            Column::make('País', 'pais')
                ->sortable()
                ->searchable(),

            // Column::make('Dirección', 'direccion')
            //     ->sortable()
            //     ->searchable(),

            Column::make('Licencia', 'licencia_badge'),

            Column::make('Estado', 'estado_badge', 'active')
                ->sortable(),

            // Column::make('Created at', 'created_at_formatted', 'created_at')
            //     ->sortable(),

            ...($hayAcciones ? [Column::action('Acciones')] : []),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('cod')->operators(['contains']),
            Filter::inputText('razon_social')->operators(['contains']),
            Filter::inputText('nombre_comercial')->operators(['contains']),
            Filter::inputText('nit')->operators(['contains']),
            Filter::inputText('email')->operators(['contains']),
            Filter::inputText('telefono')->operators(['contains']),
            Filter::inputText('pais')->operators(['contains']),
            Filter::boolean('active'),
        ];
    }

    /**
     * Los botones se deciden fila por fila y solo aparecen si el usuario
     * tiene el permiso. Los dos disparan un evento que escucha el componente
     * Empresas (el del modal), que vuelve a validar el permiso en el servidor.
     */
    public function actions(Empresa $row): array
    {
        $botones = [];

        if (Gate::allows('empresas.edit')) {
            $botones[] = Button::add('edit')
                ->slot(Blade::render('<span title="Editar"><flux:icon.pencil-square class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('editar-empresa', ['id' => $row->id]);
        }

        if (Gate::allows('empresas.desactivar')) {
            $botones[] = $row->active
                ? Button::add('estado')
                    ->slot(Blade::render('<span title="Desactivar"><flux:icon.no-symbol class="size-4" /></span>'))
                    ->id()
                    ->class('pg-btn-white text-red-600 border-red-300 hover:bg-red-50 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-950')
                    ->dispatch('confirmar-estado-empresa', ['id' => $row->id])
                : Button::add('estado')
                    ->slot(Blade::render('<span title="Reactivar"><flux:icon.check-circle class="size-4" /></span>'))
                    ->id()
                    ->class('pg-btn-white text-emerald-600 border-emerald-300 hover:bg-emerald-50 dark:text-emerald-400 dark:border-emerald-800 dark:hover:bg-emerald-950')
                    ->dispatch('confirmar-estado-empresa', ['id' => $row->id]);
        }

        return $botones;
    }
}
