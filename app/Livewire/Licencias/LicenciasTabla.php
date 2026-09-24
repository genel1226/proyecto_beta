<?php

namespace App\Livewire\Licencias;

use App\Models\Licencias\Licencias;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;

final class LicenciasTabla extends PowerGridComponent
{
    public string $tableName = 'licenciasTablaTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Licencias::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('empresa_id')
            ->add('plan_id')
            ->add('codigo_licencia')

           /** Example of custom column using a closure **/
            ->add('codigo_licencia_lower', fn (Licencias $model) => strtolower(e($model->codigo_licencia)))

            ->add('fecha_inicio_formatted', fn (Licencias $model) => Carbon::parse($model->fecha_inicio)->format('d/m/Y'))
            ->add('fecha_vencimiento_formatted', fn (Licencias $model) => Carbon::parse($model->fecha_vencimiento)->format('d/m/Y'))
            ->add('periodicidad')
            ->add('monto')
            ->add('descuento')
            ->add('moneda')
            ->add('estado')
            ->add('observaciones')
            ->add('created_at_formatted', fn (Licencias $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('Empresa id', 'empresa_id'),
            Column::make('Plan id', 'plan_id'),
            Column::make('Codigo licencia', 'codigo_licencia')
                ->sortable()
                ->searchable(),

            Column::make('Fecha inicio', 'fecha_inicio_formatted', 'fecha_inicio')
                ->sortable(),

            Column::make('Fecha vencimiento', 'fecha_vencimiento_formatted', 'fecha_vencimiento')
                ->sortable(),

            Column::make('Periodicidad', 'periodicidad')
                ->sortable()
                ->searchable(),

            Column::make('Monto', 'monto')
                ->sortable()
                ->searchable(),

            Column::make('Descuento', 'descuento')
                ->sortable()
                ->searchable(),

            Column::make('Moneda', 'moneda')
                ->sortable()
                ->searchable(),

            Column::make('Estado', 'estado')
                ->sortable()
                ->searchable(),

            Column::make('Observaciones', 'observaciones')
                ->sortable()
                ->searchable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::action('Action')
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('codigo_licencia')->operators(['contains']),
            Filter::datepicker('fecha_inicio'),
            Filter::datepicker('fecha_vencimiento'),
            Filter::inputText('periodicidad')->operators(['contains']),
            Filter::inputText('moneda')->operators(['contains']),
            Filter::inputText('estado')->operators(['contains']),
            Filter::inputText('observaciones')->operators(['contains']),
            Filter::datetimepicker('created_at'),
        ];
    }

    #[\Livewire\Attributes\On('edit')]
    public function edit($rowId): void
    {
        $this->js('alert('.$rowId.')');
    }

    public function actions(Licencias $row): array
    {
        return [
            Button::add('edit')
                ->slot('Edit: '.$row->id)
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('edit', ['rowId' => $row->id])
        ];
    }

    /*
    public function actionRules($row): array
    {
       return [
            // Hide button edit for ID 1
            Rule::button('edit')
                ->when(fn($row) => $row->id === 1)
                ->hide(),
        ];
    }
    */
}
